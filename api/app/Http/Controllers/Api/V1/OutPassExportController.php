<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ExportOutPassesJob;
use App\Models\Domain\OutPass\OutPassExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

class OutPassExportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', OutPassExport::class);

        $filters = $request->validate([
            'status' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $export = OutPassExport::create([
            'tenant_id' => Auth::user()->tenant_id,
            'requested_by' => Auth::id(),
            'status' => OutPassExport::STATUS_PENDING,
            'filters' => array_filter($filters),
        ]);

        Bus::dispatch(new ExportOutPassesJob($export->id));

        return response()->json([
            'data' => [
                'export_id' => $export->id,
                'status' => $export->status,
            ],
        ], 202);
    }

    public function show(OutPassExport $export): JsonResponse
    {
        $this->authorize('view', $export);

        $downloadUrl = null;

        if ($export->status === OutPassExport::STATUS_COMPLETE && $export->file_path) {
            $downloadUrl = Storage::disk('exports')->temporaryUrl(
                $export->file_path,
                now()->addMinutes(15)
            );
        }

        return response()->json([
            'data' => [
                'status' => $export->status,
                'download_url' => $downloadUrl,
                'error' => $export->error,
            ],
        ]);
    }
}
