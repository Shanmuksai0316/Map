<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Uploads\PresignedUploadService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UploadController extends Controller
{
    public function __construct(
        private readonly PresignedUploadService $uploadService,
    ) {}

    public function presigned(Request $request): JsonResponse
    {
        $request->validate([
            'directory' => 'required|string|max:100',
            'filename' => 'required|string|max:140',
            'mime' => 'required|string|max:80',
            'max_size' => 'nullable|integer|max:52428800', // 50MB
        ]);

        $result = $this->uploadService->generatePresignedUrl(
            $request->directory,
            $request->filename,
            $request->mime,
            $request->max_size ?? 5242880
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }

        return response()->json($result);
    }
}
