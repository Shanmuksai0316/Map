<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateExportJob;
use App\Models\ExportJob;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ExportController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {}

    /**
     * List user's export jobs
     */
    public function index(Request $request): JsonResponse
    {
        $exports = ExportJob::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $exports->map(fn (ExportJob $export) => [
                'id' => $export->id,
                'type' => $export->type,
                'status' => $export->status,
                'file_url' => $export->file_url,
                'expires_at' => $export->expires_at?->toIso8601String(),
                'created_at' => $export->created_at->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $exports->currentPage(),
                'per_page' => $exports->perPage(),
                'total' => $exports->total(),
            ],
        ]);
    }

    /**
     * Create a new export job
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:students,out_passes,attendance,payments,tickets,laundry,sports,visitors',
            'filters' => 'sometimes|array',
            'filters.hostel_id' => 'sometimes|exists:hostels,id',
            'filters.campus_id' => 'sometimes|exists:campuses,id',
            'filters.from_date' => 'sometimes|date',
            'filters.to_date' => 'sometimes|date|after_or_equal:filters.from_date',
            'filters.status' => 'sometimes|string',
        ]);

        // Authorization: Check if user can export this type
        $this->authorizeExport($request->user(), $validated['type']);

        $exportJob = DB::transaction(function () use ($request, $validated) {
            $exportJob = ExportJob::create([
                'tenant_id' => $request->user()->tenant_id,
                'user_id' => $request->user()->id,
                'type' => $validated['type'],
                'filters' => $validated['filters'] ?? [],
                'status' => 'queued',
                'expires_at' => now()->addDays(7), // 7-day expiry
            ]);

            // Dispatch async job
            GenerateExportJob::dispatch($exportJob);

            // Log the action
            $this->auditLogger->logEvent(
                'export.create',
                ['export_id' => $exportJob->id, 'type' => $validated['type']]
            );

            return $exportJob;
        });

        return response()->json([
            'message' => 'Export job queued successfully. You will receive a notification when ready.',
            'data' => [
                'id' => $exportJob->id,
                'type' => $exportJob->type,
                'status' => $exportJob->status,
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Get export job details
     */
    public function show(ExportJob $exportJob): JsonResponse
    {
        // Authorization: Own export only
        if ($exportJob->user_id !== auth()->id()) {
            abort(Response::HTTP_FORBIDDEN, 'You can only view your own exports');
        }

        return response()->json([
            'data' => [
                'id' => $exportJob->id,
                'type' => $exportJob->type,
                'status' => $exportJob->status,
                'file_url' => $exportJob->file_url,
                'expires_at' => $exportJob->expires_at?->toIso8601String(),
                'created_at' => $exportJob->created_at->toIso8601String(),
                'updated_at' => $exportJob->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Download export file (redirects to presigned S3 URL)
     */
    public function download(ExportJob $exportJob): JsonResponse
    {
        // Authorization: Own export only
        if ($exportJob->user_id !== auth()->id()) {
            abort(Response::HTTP_FORBIDDEN, 'You can only download your own exports');
        }

        if ($exportJob->status !== 'ready') {
            return response()->json([
                'error' => 'Export is not ready yet',
                'status' => $exportJob->status,
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!$exportJob->file_url) {
            return response()->json([
                'error' => 'Export file not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Check expiry
        if ($exportJob->expires_at && $exportJob->expires_at->isPast()) {
            return response()->json([
                'error' => 'Export has expired',
            ], Response::HTTP_GONE);
        }

        // Generate presigned URL (valid for 15 minutes)
        $presignedUrl = \Storage::disk('s3')->temporaryUrl(
            $exportJob->file_url,
            now()->addMinutes(15)
        );

        // Log download
        $this->auditLogger->logEvent(
            'export.download',
            ['export_id' => $exportJob->id, 'type' => $exportJob->type]
        );

        return response()->json([
            'download_url' => $presignedUrl,
            'expires_in' => 900, // 15 minutes in seconds
        ]);
    }

    /**
     * Cancel a pending export job
     */
    public function cancel(ExportJob $exportJob): JsonResponse
    {
        // Authorization: Own export only
        if ($exportJob->user_id !== auth()->id()) {
            abort(Response::HTTP_FORBIDDEN, 'You can only cancel your own exports');
        }

        if (!in_array($exportJob->status, ['queued', 'running'])) {
            return response()->json([
                'error' => 'Can only cancel queued or running exports',
            ], Response::HTTP_BAD_REQUEST);
        }

        $exportJob->update(['status' => 'failed']);

        return response()->json([
            'message' => 'Export cancelled',
        ]);
    }

    /**
     * Authorize export based on type
     */
    private function authorizeExport($user, string $type): void
    {
        $typePermissions = [
            'students' => ['Campus Manager', 'Warden', 'Rector', 'Super Admin'],
            'out_passes' => ['Campus Manager', 'Warden', 'Rector', 'Super Admin'],
            'attendance' => ['Campus Manager', 'Warden', 'Rector', 'Super Admin'],
            'payments' => ['Campus Manager', 'Rector', 'Super Admin'],
            'tickets' => ['Campus Manager', 'Warden', 'Super Admin'],
            'laundry' => ['Campus Manager', 'Warden', 'Super Admin'],
            'sports' => ['Campus Manager', 'Super Admin'],
            'visitors' => ['Campus Manager', 'Warden', 'Guard', 'Super Admin'],
        ];

        $allowedRoles = $typePermissions[$type] ?? [];

        if (!$user->hasAnyRole($allowedRoles)) {
            abort(Response::HTTP_FORBIDDEN, "You do not have permission to export {$type}");
        }
    }
}

