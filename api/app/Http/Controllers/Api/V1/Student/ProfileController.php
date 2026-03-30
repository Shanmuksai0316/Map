<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ProfileController extends Controller
{
    /**
     * Get student profile
     * 
     * This is an alias to /auth/me for easier mobile integration
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/unauthenticated',
                'title' => 'Unauthenticated',
                'status' => Response::HTTP_UNAUTHORIZED,
                'detail' => 'You must be logged in to access this endpoint.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            // Eager load student relationships to avoid N+1 queries
            $user->load([
                'student.hostel',
                'student.roomAllocations' => function ($query) {
                    $query->where('is_active', true)
                        ->with(['roomBed.room.hostel', 'roomBed.room.campus'])
                        ->latest('effective_from');
                },
            ]);
            
            // Mobile/tenant routes may not have tenancy()->tenant set; resolve from user's tenant_id.
            $tenant = tenancy()->tenant ?? ($user->tenant_id ? \App\Models\Tenant::find($user->tenant_id) : null);
            $logoUrl = null;
            
            // Mirror web panel: when tenant has branding.logo_path, always build the same storage URL (no exists check).
            $appUrl = rtrim(config('app.url'), '/');
            $centralPublic = Storage::disk('public_central');

            if ($tenant && $tenant->settings) {
                $logoPath = data_get($tenant->settings, 'branding.logo_path');
                if ($logoPath !== null) {
                    $pathString = is_array($logoPath)
                        ? ($logoPath[0] ?? $logoPath['path'] ?? null)
                        : $logoPath;
                    if (is_string($pathString) && $pathString !== '') {
                        $publicPath = ltrim($pathString, '/');
                        $storagePath = preg_replace('#^storage/#', '', $publicPath);
                        $logoUrl = $centralPublic->url($storagePath);
                    }
                }
            }
            // Fallback: default MAP logo so the app always shows a logo (tenant may not have uploaded one)
            if ($logoUrl === null || $logoUrl === '') {
                $logoUrl = $appUrl . '/images/map-logo.png';
            }

            $response = [
                'id' => (string) $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'kind' => $user->kind,
                'tenant_id' => (string) $user->tenant_id,
                'tenant_logo_url' => $logoUrl,
            ];

            // Add student-specific data
            if ($user->student) {
                $student = $user->student;
                
                $response['student'] = [
                    'id' => (string) $student->id,
                    'student_uid' => $student->student_uid,
                    'map_student_id' => $student->map_student_id,
                    'roll_no' => $student->roll_no,
                    'hostel_id' => $student->hostel_id ? (string) $student->hostel_id : null,
                    'year_of_study' => $student->year_of_study,
                    'program' => $student->program,
                    'admission_year' => $student->admission_year,
                ];
                
                // Add hostel name if hostel_id exists
                if ($student->hostel_id && $student->hostel) {
                    $response['student']['hostel_name'] = $student->hostel->name;
                }
                
                // Add room allocation details if student has active allocation
                $activeAllocation = $student->roomAllocations->first();
                
                if ($activeAllocation && $activeAllocation->roomBed) {
                    $roomBed = $activeAllocation->roomBed;
                    $room = $roomBed->room;
                    
                    $response['student']['room_allocation'] = [
                        'allocation_id' => (string) $activeAllocation->id,
                        'room_id' => $room ? (string) $room->id : null,
                        'room_number' => $room?->number,
                        'bed_id' => (string) $roomBed->id,
                        'bed_code' => $roomBed->code,
                        'block_code' => $room?->block_code,
                        'floor_code' => $room?->floor_code,
                        'hostel_id' => $room && $room->hostel ? (string) $room->hostel->id : null,
                        'hostel_name' => $room?->hostel?->name,
                        'campus_id' => $room && $room->campus ? (string) $room->campus->id : null,
                        'campus_name' => $room?->campus?->name,
                        'effective_from' => $activeAllocation->effective_from?->toIso8601String(),
                        'effective_to' => $activeAllocation->effective_to?->toIso8601String(),
                    ];
                }
            }

            return response()->json([
                'data' => $response,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to fetch student profile', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/fetch_failed',
                'title' => 'Fetch Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to retrieve profile. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
