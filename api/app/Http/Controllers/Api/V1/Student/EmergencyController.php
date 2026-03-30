<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Services\Notifications\NotificationRecipients;
use App\Services\Notifications\SmsService;
use App\Services\Notify\PushNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EmergencyController extends Controller
{
    /**
     * Report a medical emergency from the student app.
     *
     * Frontend only needs to send:
     * { "type": "need_assistance" | "contact_doctor" }
     * Student + room details are derived from the authenticated user.
     */
    public function reportMedical(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/unauthenticated',
                'title' => 'Unauthenticated',
                'status' => Response::HTTP_UNAUTHORIZED,
                'detail' => 'Authentication required. Please log in again.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user->student) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/not_a_student',
                'title' => 'Not a Student',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only students can report emergencies from the mobile app.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'type' => 'required|string|in:need_assistance,contact_doctor',
        ]);

        $student = $user->student;
        $hostelId = $student->hostel_id;

        // Derive current room number (active allocation if available, fallback to student->room_no)
        $roomNumber = null;
        $activeAlloc = $student->roomAllocations()
            ->where('is_active', true)
            ->with('roomBed.room')
            ->first();

        if ($activeAlloc && $activeAlloc->roomBed && $activeAlloc->roomBed->room) {
            $roomNumber = $activeAlloc->roomBed->room->number;
        } else {
            $roomNumber = $student->room_no ?? null;
        }

        $studentUser = $student->user;
        $studentName = $studentUser?->name ?? 'N/A';
        $studentUid = $student->student_uid ?? 'N/A';
        $studentPhone = $studentUser?->phone ?? 'N/A';

        $recommendedAction = $validated['type'] === 'need_assistance'
            ? 'Student requires immediate assistance. Please send support staff to the room.'
            : 'Student requires medical attention. Please arrange for doctor consultation.';

        $noteLines = [
            'Medical emergency reported from student mobile app.',
            '',
            "Student Name: {$studentName}",
            "Student UID: {$studentUid}",
            'Room: ' . ($roomNumber ?? 'N/A'),
            "Contact: {$studentPhone}",
            '',
            "Requested Action: {$recommendedAction}",
        ];

        $incident = Incident::create([
            'hostel_id' => $hostelId,
            'type' => Incident::TYPE_MEDICAL,
            'student_id' => $student->id,
            'note' => implode("\n", $noteLines),
            'status' => 'Open',
            'opened_by' => $user->id,
            'opened_at' => now(),
            'metadata' => [
                'source' => 'student_app',
                'emergency_type' => $validated['type'],
                'room_number' => $roomNumber,
            ],
        ]);

        $this->notifyEmergencyContacts(
            $user,
            $incident,
            'Medical emergency reported',
            "Medical emergency reported by {$studentName} (Room " . ($roomNumber ?? 'N/A') . ").",
            $studentPhone
        );

        return response()->json([
            'message' => 'Medical emergency reported successfully.',
            'data' => [
                'id' => $incident->id,
                'status' => $incident->status,
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Report a security/safety incident from the student app.
     *
     * Frontend only sends:
     * { "description": "..." }
     * Student + room details are derived from the authenticated user.
     */
    public function reportIncident(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/unauthenticated',
                'title' => 'Unauthenticated',
                'status' => Response::HTTP_UNAUTHORIZED,
                'detail' => 'Authentication required. Please log in again.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user->student) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/not_a_student',
                'title' => 'Not a Student',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only students can report incidents from the mobile app.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'description' => 'required|string|max:1000',
        ]);

        $student = $user->student;
        $hostelId = $student->hostel_id;

        // Derive current room number (active allocation if available, fallback to student->room_no)
        $roomNumber = null;
        $activeAlloc = $student->roomAllocations()
            ->where('is_active', true)
            ->with('roomBed.room')
            ->first();

        if ($activeAlloc && $activeAlloc->roomBed && $activeAlloc->roomBed->room) {
            $roomNumber = $activeAlloc->roomBed->room->number;
        } else {
            $roomNumber = $student->room_no ?? null;
        }

        $studentUser = $student->user;
        $studentName = $studentUser?->name ?? 'N/A';
        $studentUid = $student->student_uid ?? 'N/A';
        $studentPhone = $studentUser?->phone ?? 'N/A';

        $noteLines = [
            'Student incident reported from mobile app.',
            '',
            "Student Name: {$studentName}",
            "Student UID: {$studentUid}",
            'Room: ' . ($roomNumber ?? 'N/A'),
            "Contact: {$studentPhone}",
            '',
            'Incident Description:',
            trim($validated['description']),
        ];

        $incident = Incident::create([
            'hostel_id' => $hostelId,
            'type' => Incident::TYPE_SECURITY,
            'student_id' => $student->id,
            'note' => implode("\n", $noteLines),
            'status' => 'Open',
            'opened_by' => $user->id,
            'opened_at' => now(),
            'metadata' => [
                'source' => 'student_app',
                'room_number' => $roomNumber,
            ],
        ]);

        $this->notifyEmergencyContacts(
            $user,
            $incident,
            'Incident reported by student',
            "Security incident reported by {$studentName} (Room " . ($roomNumber ?? 'N/A') . ").",
            $studentPhone
        );

        return response()->json([
            'message' => 'Incident reported successfully.',
            'data' => [
                'id' => $incident->id,
                'status' => $incident->status,
            ],
        ], Response::HTTP_CREATED);
    }

    private function notifyEmergencyContacts($user, Incident $incident, string $title, string $body, string $contactPhone): void
    {
        try {
            $tenantId = (string) ($user->tenant_id ?? '');
            $hostelId = (int) ($user->student?->hostel_id ?? 0);

            if ($tenantId === '' || $hostelId <= 0) {
                return;
            }

            $recipients = app(NotificationRecipients::class);
            $push = app(PushNotifier::class);
            $sms = app(SmsService::class);

            $data = [
                'type' => 'student_emergency_reported',
                'incident_id' => (string) $incident->id,
                'incident_type' => (string) $incident->type,
            ];

            $recipientUsers = [];

            $rector = $recipients->rectorForHostel($tenantId, $hostelId);
            if ($rector) {
                $recipientUsers[$rector->id] = $rector;
            }

            $warden = $recipients->wardenForHostel($tenantId, $hostelId);
            if ($warden) {
                $recipientUsers[$warden->id] = $warden;
            }

            $campusManager = $recipients->campusManagerForTenant($tenantId);
            if ($campusManager) {
                $recipientUsers[$campusManager->id] = $campusManager;
            }

            foreach ($recipients->guardsForHostel($tenantId, $hostelId) as $guard) {
                $recipientUsers[$guard->id] = $guard;
            }

            $smsMessage = "Emergency Alert: {$body} Contact: {$contactPhone}.Team OMAP Services";

            foreach ($recipientUsers as $recipientId => $recipientUser) {
                // Push notification
                $push->toUser((int) $recipientId, $title, $body, $data);

                // SMS notification (if phone number is available)
                if (!empty($recipientUser->phone)) {
                    $sms->send(
                        $recipientUser->phone,
                        $smsMessage,
                        $tenantId,
                        'emergency_alert',
                        [
                            'related_type' => 'incident',
                            'related_id' => (string) $incident->id,
                        ]
                    );
                }
            }
        } catch (\Throwable $e) {
            Log::warning('emergency.student_notify_failed', [
                'incident_id' => $incident->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
