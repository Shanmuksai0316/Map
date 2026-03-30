<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Api\V1\NoticeController as WebNoticeController;
use App\Http\Controllers\Controller;
use App\Models\Notice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class NoticeController extends Controller
{
    /**
     * Create a new notice from mobile app
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Check if user has permission to create notices
        if (!$user->hasAnyRole(['Campus Manager', 'Warden', 'Rector'])) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'You do not have permission to create notices.',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'content' => 'required|string|max:5000',
                'images' => 'nullable|array|max:4',
                'images.*' => 'required|string', // Base64 encoded images or URLs
                'hostel_id' => 'nullable|integer|exists:hostels,id',
                'audience' => 'nullable|string|in:all,all_students,specific_hostel',
            ]);

            // Get user's tenant and determine hostel
            $tenantId = $user->tenant_id;
            $hostelId = $validated['hostel_id'] ?? null;
            
            // If user is Warden, use their assigned hostel if no hostel_id provided
            if (!$hostelId && $user->hasRole('Warden')) {
                $staffHostel = $user->staffHostels->first();
                if ($staffHostel) {
                    $hostelId = $staffHostel->id;
                }
            }

            // Process images - upload to S3 if base64, otherwise use provided URLs
            $imageUrls = [];
            if (!empty($validated['images'])) {
                foreach ($validated['images'] as $imageData) {
                    // Check if it's a base64 string or already a URL
                    if (str_starts_with($imageData, 'data:image')) {
                        // Base64 image - upload to S3
                        $imageUrl = $this->uploadBase64Image($imageData, $tenantId);
                        if ($imageUrl) {
                            $imageUrls[] = $imageUrl;
                        }
                    } elseif (filter_var($imageData, FILTER_VALIDATE_URL)) {
                        // Already a URL - use as is
                        $imageUrls[] = $imageData;
                    }
                }
            }

            // Determine audience
            $audience = $validated['audience'] ?? 'all_students';
            if ($hostelId && $audience === 'specific_hostel') {
                $audience = 'specific_hostel';
            }

            // Create notice
            $notice = Notice::create([
                'tenant_id' => $tenantId,
                'hostel_id' => $hostelId,
                'title' => $validated['title'],
                'body' => $validated['content'],
                'status' => 'published', // Auto-publish from mobile
                'audience' => $audience,
                'created_by_user_id' => $user->id,
                'publish_at' => now(),
                'images' => !empty($imageUrls) ? $imageUrls : null,
            ]);

            // Keep mobile notice delivery behavior in sync with web notice publish flow.
            // Do not fail notice creation if push dispatch fails.
            try {
                app(WebNoticeController::class)->sendPushNotifications($notice);
            } catch (\Throwable $e) {
                Log::warning('Mobile notice push dispatch failed', [
                    'notice_id' => $notice->id,
                    'error' => $e->getMessage(),
                ]);
            }

            Log::info('Notice created from mobile', [
                'notice_id' => $notice->id,
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'hostel_id' => $hostelId,
            ]);

            return response()->json([
                'data' => [
                    'id' => (string) $notice->id,
                    'title' => $notice->title,
                    'content' => $notice->body,
                    'images' => $notice->images ?? [],
                    'created_at' => $notice->created_at->toIso8601String(),
                ],
            ], Response::HTTP_CREATED);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/validation_failed',
                'title' => 'Validation Failed',
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'detail' => 'The request data is invalid.',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (\Exception $e) {
            Log::error('Failed to create notice from mobile', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/server_error',
                'title' => 'Server Error',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to create notice. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Upload base64 image to S3
     */
    private function uploadBase64Image(string $base64Data, string $tenantId): ?string
    {
        try {
            // Extract image data
            if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $matches)) {
                $imageType = $matches[1];
                $imageData = base64_decode(substr($base64Data, strpos($base64Data, ',') + 1));
                
                // Validate image size (max 5MB)
                if (strlen($imageData) > 5 * 1024 * 1024) {
                    Log::warning('Image too large', ['size' => strlen($imageData)]);
                    return null;
                }

                // Generate unique filename
                $filename = uniqid('notice_', true) . '.' . $imageType;
                $path = "notices/{$tenantId}/" . now()->format('Y/m/d/') . $filename;

                // Store on local server (public disk)
                Storage::disk('public')->put($path, $imageData);
                return Storage::disk('public')->url($path);
            }
        } catch (\Exception $e) {
            Log::error('Failed to upload image', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
            ]);
        }

        return null;
    }
}
