<?php

namespace App\Services;

use App\Models\OfflineAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Offline Queue Service
 * 
 * Handles synchronization of offline actions from mobile apps.
 * Supports Guard gate operations and Warden attendance marking.
 */
class OfflineQueueService
{
    /**
     * Process a batch of offline actions
     * 
     * @param array $actions Array of offline actions from mobile
     * @param User $user The authenticated user
     * @return array Results with success/failure counts
     */
    public function processBatch(array $actions, User $user): array
    {
        $results = [
            'processed' => 0,
            'succeeded' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        DB::beginTransaction();

        try {
            foreach ($actions as $index => $actionData) {
                $results['processed']++;

                try {
                    $this->processAction($actionData, $user);
                    $results['succeeded']++;
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'index' => $index,
                        'action_type' => $actionData['action_type'] ?? 'unknown',
                        'error' => $e->getMessage(),
                        'client_timestamp' => $actionData['client_timestamp'] ?? null,
                    ];

                    Log::warning('Offline action failed', [
                        'user_id' => $user->id,
                        'action' => $actionData,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            DB::commit();

            Log::info('Offline batch processed', [
                'user_id' => $user->id,
                'results' => $results,
            ]);

            return $results;

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Offline batch processing failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Process a single offline action
     * 
     * @param array $actionData The action data
     * @param User $user The authenticated user
     * @return void
     * @throws \Exception
     */
    protected function processAction(array $actionData, User $user): void
    {
        $actionType = $actionData['action_type'] ?? null;

        if (!$actionType) {
            throw new \Exception('Action type is required');
        }

        // Validate timestamp is not too old (max 7 days)
        $clientTimestamp = $actionData['client_timestamp'] ?? null;
        if ($clientTimestamp) {
            $actionTime = Carbon::parse($clientTimestamp);
            if ($actionTime->lt(now()->subDays(7))) {
                throw new \Exception('Action is too old to process (> 7 days)');
            }
        }

        // Store in offline_actions table for audit
        $offlineAction = OfflineAction::create([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'action_type' => $actionType,
            'payload' => $actionData,
            'client_timestamp' => $clientTimestamp,
            'processed_at' => now(),
            'status' => 'pending',
        ]);

        // Dispatch to appropriate handler
        switch ($actionType) {
            case 'gate_out':
                $this->processGateOut($actionData, $user, $offlineAction);
                break;

            case 'gate_in':
                $this->processGateIn($actionData, $user, $offlineAction);
                break;

            case 'attendance_mark':
                $this->processAttendanceMark($actionData, $user, $offlineAction);
                break;

            case 'emergency_exit':
                $this->processEmergencyExit($actionData, $user, $offlineAction);
                break;

            default:
                $offlineAction->update(['status' => 'failed']);
                throw new \Exception("Unknown action type: {$actionType}");
        }

        $offlineAction->update(['status' => 'succeeded']);
    }

    /**
     * Process gate OUT action
     */
    protected function processGateOut(array $data, User $user, OfflineAction $offlineAction): void
    {
        $payload = $data['payload'] ?? [];

        // Validate required fields
        if (!isset($payload['student_id']) || !isset($payload['outpass_id'])) {
            throw new \Exception('student_id and outpass_id are required for gate_out');
        }

        // Check for duplicates (same student, same day, already OUT)
        // Extract client_timestamp from data if available
        $clientTimestamp = $data['client_timestamp'] ?? now();
        $existingEntry = DB::table('gate_entries')
            ->where('student_id', $payload['student_id'])
            ->where('direction', 'out')
            ->whereDate('created_at', $clientTimestamp)
            ->whereNull('in_time')
            ->first();

        if ($existingEntry) {
            Log::info('Duplicate gate OUT detected, skipping', [
                'student_id' => $payload['student_id'],
                'existing_entry_id' => $existingEntry->id,
            ]);
            return; // Skip duplicate, but don't fail
        }

        // Create gate entry
        DB::table('gate_entries')->insert([
            'tenant_id' => $user->tenant_id,
            'student_id' => $payload['student_id'],
            'outpass_id' => $payload['outpass_id'],
            'hostel_id' => $payload['hostel_id'] ?? null,
            'direction' => 'out',
            'out_time' => $payload['out_time'] ?? ($data['client_timestamp'] ?? now()),
            'recorded_by_user_id' => $user->id,
            'offline_synced' => true,
            'created_at' => $data['client_timestamp'] ?? now(),
            'updated_at' => now(),
        ]);

        Log::info('Gate OUT processed from offline queue', [
            'student_id' => $payload['student_id'],
            'user_id' => $user->id,
        ]);
    }

    /**
     * Process gate IN action
     */
    protected function processGateIn(array $data, User $user, OfflineAction $offlineAction): void
    {
        $payload = $data['payload'] ?? [];

        if (!isset($payload['student_id'])) {
            throw new \Exception('student_id is required for gate_in');
        }

        // Find the corresponding OUT entry
        $outEntry = DB::table('gate_entries')
            ->where('student_id', $payload['student_id'])
            ->where('direction', 'out')
            ->whereNull('in_time')
            ->orderBy('out_time', 'desc')
            ->first();

        if ($outEntry) {
            // Update existing OUT entry with IN time
            DB::table('gate_entries')
                ->where('id', $outEntry->id)
                ->update([
                    'in_time' => $payload['in_time'] ?? ($data['client_timestamp'] ?? now()),
                    'updated_at' => now(),
                ]);
        } else {
            // Create standalone IN entry (student returned without OUT record)
            DB::table('gate_entries')->insert([
                'tenant_id' => $user->tenant_id,
                'student_id' => $payload['student_id'],
                'hostel_id' => $payload['hostel_id'] ?? null,
                'direction' => 'in',
                'in_time' => $payload['in_time'] ?? ($data['client_timestamp'] ?? now()),
                'recorded_by_user_id' => $user->id,
                'offline_synced' => true,
                'created_at' => $data['client_timestamp'] ?? now(),
                'updated_at' => now(),
            ]);
        }

        Log::info('Gate IN processed from offline queue', [
            'student_id' => $payload['student_id'],
            'user_id' => $user->id,
        ]);
    }

    /**
     * Process attendance marking action
     */
    protected function processAttendanceMark(array $data, User $user, OfflineAction $offlineAction): void
    {
        $payload = $data['payload'] ?? [];

        if (!isset($payload['student_id']) || !isset($payload['status'])) {
            throw new \Exception('student_id and status are required for attendance_mark');
        }

        $markedDate = isset($payload['marked_at']) ? Carbon::parse($payload['marked_at']) : now();
        $sessionId = $payload['session_id'] ?? null;
        $roomId = $payload['room_id'] ?? null;

        // Prefer session-scoped uniqueness when session_id is provided (V2 behavior)
        $query = DB::table('attendance_logs')
            ->where('student_id', $payload['student_id']);

        if ($sessionId) {
            $query->where('session_id', $sessionId);
        } else {
            // fallback to date-based uniqueness for legacy payloads
            $query->whereDate('marked_at', $markedDate->toDateString());
        }

        $existing = $query->first();

        if ($existing) {
            // Update existing (last-write-wins)
            DB::table('attendance_logs')
                ->where('id', $existing->id)
                ->update([
                    'status' => $payload['status'],
                    'note' => $payload['note'] ?? $existing->note,
                    'marked_by' => $user->id,
                    'session_id' => $sessionId ?? $existing->session_id,
                    'attendance_session_id' => $sessionId ?? $existing->attendance_session_id,
                    'updated_at' => now(),
                    'offline_synced' => true,
                ]);

            Log::info('Attendance updated from offline queue (conflict resolution)', [
                'student_id' => $payload['student_id'],
                'date' => $markedDate->toDateString(),
                'status' => $payload['status'],
                'user_id' => $user->id,
            ]);
        } else {
            // Create new attendance record
            DB::table('attendance_logs')->insert([
                'tenant_id' => $user->tenant_id,
                'student_id' => $payload['student_id'],
                'session_id' => $sessionId,
                'attendance_session_id' => $sessionId,
                'hostel_id' => $payload['hostel_id'] ?? null,
                'status' => $payload['status'],
                'marked_at' => $markedDate,
                'marked_by' => $user->id,
                'note' => $payload['note'] ?? null,
                'metadata' => [
                    'room_id' => $roomId,
                    'offline' => true,
                ],
                'offline_synced' => true,
                'created_at' => $data['client_timestamp'] ?? now(),
                'updated_at' => now(),
            ]);

            Log::info('Attendance created from offline queue', [
                'student_id' => $payload['student_id'],
                'date' => $markedDate->toDateString(),
                'status' => $payload['status'],
                'user_id' => $user->id,
            ]);
        }
    }

    /**
     * Process emergency exit action
     */
    protected function processEmergencyExit(array $data, User $user, OfflineAction $offlineAction): void
    {
        $payload = $data['payload'] ?? [];

        if (!isset($payload['student_id']) || !isset($payload['note'])) {
            throw new \Exception('student_id and note are required for emergency_exit');
        }

        // Create OutPass with emergency_exit status
        $outpassId = DB::table('out_passes')->insertGetId([
            'tenant_id' => $user->tenant_id,
            'student_id' => $payload['student_id'],
            'hostel_id' => $payload['hostel_id'] ?? null,
            'reason' => 'Emergency Exit: ' . $payload['note'],
            'overnight' => false,
            'status' => 'emergency_exit',
            'requested_at' => $data['client_timestamp'] ?? now(),
            'valid_until' => isset($data['client_timestamp']) 
                ? Carbon::parse($data['client_timestamp'])->addHours(24) 
                : now()->addHours(24),
            'created_by_user_id' => $user->id,
            'offline_synced' => true,
            'created_at' => $data['client_timestamp'] ?? now(),
            'updated_at' => now(),
        ]);

        // Create gate entry for the emergency exit
        DB::table('gate_entries')->insert([
            'tenant_id' => $user->tenant_id,
            'student_id' => $payload['student_id'],
            'outpass_id' => $outpassId,
            'hostel_id' => $payload['hostel_id'] ?? null,
            'direction' => 'out',
            'out_time' => $data['client_timestamp'] ?? now(),
            'recorded_by_user_id' => $user->id,
            'offline_synced' => true,
            'created_at' => $data['client_timestamp'] ?? now(),
            'updated_at' => now(),
        ]);

        Log::info('Emergency exit processed from offline queue', [
            'student_id' => $payload['student_id'],
            'outpass_id' => $outpassId,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Get offline actions for a user (for debugging/audit)
     * 
     * @param User $user
     * @param int $limit
     * @return \Illuminate\Support\Collection
     */
    public function getUserOfflineActions(User $user, int $limit = 50)
    {
        return OfflineAction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Clean up old offline actions (older than 30 days)
     * 
     * @return int Number of deleted records
     */
    public function cleanupOldActions(): int
    {
        return OfflineAction::where('created_at', '<', now()->subDays(30))
            ->delete();
    }
}

