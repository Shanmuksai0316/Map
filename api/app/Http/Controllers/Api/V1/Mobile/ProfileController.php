<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mobile Profile (Student + Staff)
 *
 * Single endpoint for both app variants:
 * GET /api/v1/mobile/profile
 */
class ProfileController extends Controller
{
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
            // Roles are used by staff apps and sometimes for UI decisions
            $user->loadMissing(['roles']);

            // Student relationships (safe even for staff; just stays null)
            $user->loadMissing([
                'student.hostel',
                'student.roomAllocations' => function ($query) {
                    $query->where('is_active', true)
                        ->with(['roomBed.room.hostel', 'roomBed.room.campus'])
                        ->latest('effective_from');
                },
            ]);

            // Mobile routes may not have tenancy()->tenant set (middleware only sets DB session).
            // Resolve tenant from user's tenant_id so logo and tenant data are available.
            $tenant = tenancy()->tenant ?? ($user->tenant_id ? \App\Models\Tenant::find($user->tenant_id) : null);
            $logoUrl = null;
            $logoPath = null;

            // Mirror web panel (CampusManagerPanelProvider / CollegeMgmtPanelProvider): when tenant has
            // branding.logo_path, always build the same storage URL they use (no exists check).
            // Use public_central so the URL points at the app’s canonical storage (same as web).
            $appUrl = rtrim(config('app.url'), '/');
            $centralPublic = Storage::disk('public_central');

            if ($tenant && $tenant->settings) {
                $logoPath = data_get($tenant->settings, 'branding.logo_path');
                // Filament/Livewire may store logo_path as array; normalize to string
                if ($logoPath !== null) {
                    $pathString = is_array($logoPath)
                        ? ($logoPath[0] ?? $logoPath['path'] ?? null)
                        : $logoPath;
                    if (is_string($pathString) && $pathString !== '') {
                        $publicPath = ltrim($pathString, '/');
                        $storagePath = preg_replace('#^storage/#', '', $publicPath);
                        // Same as web: $disk->url($logoPath) whenever path is set
                        $logoUrl = $centralPublic->url($storagePath);
                    }
                }
            }
            // Fallback: default MAP logo so the app always shows a logo (tenant may not have uploaded one)
            if ($logoUrl === null || $logoUrl === '') {
                $logoUrl = $appUrl . '/images/map-logo.png';
            }

            // Prefer first role name, but always provide a predictable value
            $primaryRole = $user->roles?->first()?->name;
            $role = $primaryRole ?: ($user->student ? 'Student' : null);

            $response = [
                'id' => (string) $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'kind' => $user->kind,
                'role' => $role,
                'roles' => $user->roles?->pluck('name')->values()->all() ?? [],
                'tenant_id' => (string) $user->tenant_id,
                'tenant_logo_url' => $logoUrl,
                // Common staff identifiers (if present on the model)
                'staff_id' => $user->staff_id ?? null,
                'employee_id' => $user->employee_id ?? null,
            ];

            if ($user->student) {
                $student = $user->student;

                // Convenience top-level field (mobile UI sometimes expects it)
                $response['student_uid'] = $student->student_uid;

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

                if ($student->hostel_id && $student->hostel) {
                    $response['student']['hostel_name'] = $student->hostel->name;
                }

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

            return response()->json(['data' => $response], Response::HTTP_OK);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch mobile profile', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString(),
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
