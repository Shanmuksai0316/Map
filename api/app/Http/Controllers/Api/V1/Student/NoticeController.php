<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class NoticeController extends Controller
{
    /**
     * Get public notices visible to students
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->student) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/not_a_student',
                'title' => 'Not a Student',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only students can access this endpoint.',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $student = $user->student;
            $category = $request->input('category'); // all, urgent, events, general
            $limit = (int) ($request->input('limit') ?? 50);
            
            $query = Notice::query();
            
            // Filter by status if column exists
            if (Schema::hasColumn('notices', 'status')) {
                $query->where('status', 'published');
            } else {
                // Fallback: use is_published if status doesn't exist
                if (Schema::hasColumn('notices', 'is_published')) {
                    $query->where('is_published', true);
                }
            }
            
            // Filter by tenant if column exists
            if (Schema::hasColumn('notices', 'tenant_id') && $user->tenant_id) {
                $query->where('tenant_id', $user->tenant_id);
            }
            
            // Filter by audience/hostel
            $query->where(function ($q) use ($student) {
                // Notices for all students
                if (Schema::hasColumn('notices', 'audience')) {
                    $q->where('audience', 'all')
                      ->orWhere('audience', 'all_students');
                }
                
                // Or for specific campus (if column exists)
                if (Schema::hasColumn('notices', 'campus_id') && $student->campus_id) {
                    $q->orWhere('campus_id', $student->campus_id);
                }
                
                // Or for specific hostel
                $hostelColumn = Schema::hasColumn('notices', 'hostel_id') ? 'hostel_id' : 'target_hostel_id';
                if ($student->hostel_id) {
                    $q->orWhere($hostelColumn, $student->hostel_id);
                }
            });
            
            // Only active notices (not expired, already published)
            $query->where(function ($q) {
                $q->whereNull('publish_at')
                  ->orWhere('publish_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>=', now());
            });
            
            // Filter by category if provided and column exists
            if ($category && $category !== 'all' && Schema::hasColumn('notices', 'category')) {
                $query->where('category', $category);
            }
            
            $notices = $query->latest('publish_at')
                ->limit($limit)
                ->get()
                ->map(function ($notice) {
                    return [
                        'id' => (string) $notice->id,
                        'title' => $notice->title,
                        'description' => $notice->body ?? $notice->content ?? '',
                        'category' => $notice->category ?? 'general',
                        'priority' => $notice->priority ?? 'normal',
                        'published_at' => $notice->publish_at?->toIso8601String(),
                        'expires_at' => $notice->expires_at?->toIso8601String(),
                        'created_at' => $notice->created_at->toIso8601String(),
                        'images' => $notice->images ?? [],
                    ];
                });

            return response()->json([
                'data' => $notices,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to fetch notices for student', [
                'error' => $e->getMessage(),
                'student_id' => $user->student->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/fetch_failed',
                'title' => 'Fetch Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to retrieve notices. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get single notice details
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->student) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/not_a_student',
                'title' => 'Not a Student',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only students can access this endpoint.',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $student = $user->student;
            
            $query = Notice::where('id', $id);
            
            // Filter by status if column exists
            if (Schema::hasColumn('notices', 'status')) {
                $query->where('status', 'published');
            } else {
                if (Schema::hasColumn('notices', 'is_published')) {
                    $query->where('is_published', true);
                }
            }
            
            // Filter by tenant if column exists
            if (Schema::hasColumn('notices', 'tenant_id') && $user->tenant_id) {
                $query->where('tenant_id', $user->tenant_id);
            }
            
            // Filter by audience/hostel
            $query->where(function ($q) use ($student) {
                if (Schema::hasColumn('notices', 'audience')) {
                    $q->where('audience', 'all')
                      ->orWhere('audience', 'all_students');
                }
                
                if (Schema::hasColumn('notices', 'campus_id') && $student->campus_id) {
                    $q->orWhere('campus_id', $student->campus_id);
                }
                
                $hostelColumn = Schema::hasColumn('notices', 'hostel_id') ? 'hostel_id' : 'target_hostel_id';
                if ($student->hostel_id) {
                    $q->orWhere($hostelColumn, $student->hostel_id);
                }
            });
            
            $notice = $query->with('attachments')->firstOrFail();

            return response()->json([
                'data' => [
                    'id' => (string) $notice->id,
                    'title' => $notice->title,
                    'body' => $notice->body ?? $notice->content ?? '',
                    'category' => $notice->category ?? 'general',
                    'priority' => $notice->priority ?? 'normal',
                    'published_at' => $notice->publish_at?->toIso8601String(),
                    'expires_at' => $notice->expires_at?->toIso8601String(),
                    'images' => $notice->images ?? [],
                    'attachments' => $notice->attachments->map(fn($att) => [
                        'id' => (string) $att->id,
                        'filename' => $att->filename,
                        'url' => $att->url,
                    ]),
                ],
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/not_found',
                'title' => 'Not Found',
                'status' => Response::HTTP_NOT_FOUND,
                'detail' => 'Notice not found.',
            ], Response::HTTP_NOT_FOUND);
        }
    }
}

