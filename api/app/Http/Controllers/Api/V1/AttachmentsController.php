<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAttachment;
use App\Models\Attachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AttachmentsController extends Controller
{
    public function presign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filename' => 'required|string|max:255',
            'mime_type' => 'required|string|max:100',
            'size' => 'required|integer|max:10485760', // 10MB max
        ]);

        // Validate MIME type
        $allowedMimes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
            'text/plain',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        if (!in_array($validated['mime_type'], $allowedMimes)) {
            return response()->json([
                'error' => 'File type not allowed'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // All uploads stored on local server (public disk) - use POST /attachments with multipart file
        return response()->json([
            'error' => 'Use POST /attachments with multipart form field "file" to upload directly',
        ], Response::HTTP_BAD_REQUEST);
    }

    /**
     * Direct upload: store file on local server (public disk), create attachment, queue processing.
     */
    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|max:10240', // 10MB
            'file.*' => 'nullable',
        ], ['file.required' => 'No file provided. Use multipart form field "file".']);

        $file = $request->file('file');
        $allowedMimes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf', 'text/plain',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            return response()->json(['error' => 'File type not allowed'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = auth()->user();
        $filename = Str::uuid() . '_' . $file->getClientOriginalName();
        $key = $file->storeAs('attachments/' . $user->tenant_id, $filename, 'public');

        $attachment = Attachment::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'key' => $key,
            'status' => 'uploaded',
            'metadata' => ['upload_completed_at' => now()->toISOString()],
        ]);

        ProcessAttachment::dispatch($attachment);

        return response()->json([
            'data' => [
                'attachment_id' => $attachment->id,
                'key' => $key,
                'download_url' => Storage::disk('public')->url($key),
                'status' => 'uploaded',
                'message' => 'File uploaded. Processing...',
            ]
        ], 201);
    }

    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'attachment_id' => 'required|exists:attachments,id',
            'key' => 'required|string',
        ]);

        $user = auth()->user();
        $attachment = Attachment::where('id', $validated['attachment_id'])
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($attachment->status !== 'pending') {
            return response()->json([
                'error' => 'Attachment already processed'
            ], Response::HTTP_CONFLICT);
        }

        // Verify file exists on local disk
        if (!Storage::disk('public')->exists($validated['key'])) {
            return response()->json([
                'error' => 'File not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Update attachment status and queue for processing
        $attachment->update([
            'status' => 'uploaded',
            'metadata' => array_merge($attachment->metadata ?? [], [
                'upload_completed_at' => now()->toISOString(),
            ]),
        ]);

        // Queue for AV scan and EXIF stripping
        ProcessAttachment::dispatch($attachment);

        return response()->json([
            'data' => [
                'attachment_id' => $attachment->id,
                'status' => 'uploaded',
                'message' => 'File uploaded successfully. Processing...',
            ]
        ]);
    }

    public function show(Attachment $attachment): JsonResponse
    {
        $this->authorize('view', $attachment);

        return response()->json([
            'data' => [
                'id' => $attachment->id,
                'filename' => $attachment->filename,
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
                'status' => $attachment->status,
                'download_url' => $attachment->status === 'clean' && Storage::disk('public')->exists($attachment->key)
                    ? Storage::disk('public')->url($attachment->key)
                    : null,
                'created_at' => $attachment->created_at,
            ]
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        $attachments = Attachment::where('tenant_id', $user->tenant_id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return response()->json([
            'data' => $attachments->map(function (Attachment $attachment) {
                return [
                    'id' => $attachment->id,
                    'filename' => $attachment->filename,
                    'mime_type' => $attachment->mime_type,
                    'size' => $attachment->size,
                    'status' => $attachment->status,
                    'created_at' => $attachment->created_at,
                ];
            })
        ]);
    }
}



