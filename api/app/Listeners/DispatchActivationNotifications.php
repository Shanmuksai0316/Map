<?php

namespace App\Listeners;

use App\Events\TenantActivated;
use App\Jobs\SendActivationNotification;
use Illuminate\Support\Facades\Log;

class DispatchActivationNotifications
{
    /**
     * Handle the event.
     */
    public function handle(TenantActivated $event): void
    {
        $tenant = $event->tenant;
        $wizardData = $event->wizardData;

        // Get all assigned staff
        $assignments = $wizardData['staff']['hostel_assignments'] ?? [];
        $campusManagerId = $wizardData['staff']['campus_manager_id'] ?? null;

        // Send notification to Campus Manager
        if ($campusManagerId) {
            SendActivationNotification::dispatch($tenant->id, $campusManagerId, 'campus_manager', 'tenant');
        }

        // Send notifications to hostel-scoped staff
        foreach ($assignments as $assignment) {
            $hostelId = $assignment['hostel_id'] ?? null;
            if (!$hostelId) {
                continue;
            }

            $roles = [
                'rector_id' => 'rector',
                'warden_id' => 'warden',
                'guard_id' => 'guard',
                'hk_supervisor_id' => 'hk_supervisor',
                'rm_supervisor_id' => 'rm_supervisor',
                'laundry_manager_id' => 'laundry_manager',
                'sports_manager_id' => 'sports_manager',
            ];

            foreach ($roles as $field => $role) {
                if (empty($assignment[$field])) {
                    continue;
                }

                // Skip if marked N/A
                if (($field === 'laundry_manager_id' && ($assignment['laundry_na'] ?? false)) ||
                    ($field === 'sports_manager_id' && ($assignment['sports_na'] ?? false))) {
                    continue;
                }

                SendActivationNotification::dispatch($tenant->id, $assignment[$field], $role, 'hostel', $hostelId);
            }
        }

        // Send notification to Rector (contact)
        if (!empty($wizardData['contacts']['rector_phone'])) {
            SendActivationNotification::dispatch(
                $tenant->id,
                null,
                'rector_contact',
                'contact',
                null,
                $wizardData['contacts']['rector_phone'],
                $wizardData['contacts']['rector_email'] ?? null
            );
        }

        // Send notification to College Management
        if (!empty($wizardData['contacts']['college_mgmt_user_id'])) {
            SendActivationNotification::dispatch(
                $tenant->id,
                $wizardData['contacts']['college_mgmt_user_id'],
                'college_mgmt',
                'contact',
                null,
                $wizardData['contacts']['college_mgmt_phone'] ?? null,
                $wizardData['contacts']['college_mgmt_email'] ?? null
            );
        }

        Log::info('Activation notifications dispatched', [
            'tenant_id' => $tenant->id,
            'assignments_count' => count($assignments),
        ]);
    }
}

