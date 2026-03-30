<?php

namespace App\Services\Notifications;

use App\Domain\Checklists\Models\ChecklistInstance;
use App\Models\User;
use App\Services\Notify\PushNotifier;
use App\Services\Notifications\SmsService;
use Illuminate\Support\Facades\Log;

class ChecklistNotifier
{
    public function __construct(
        private readonly SmsService $sms,
        private readonly PushNotifier $push
    ) {}

    public function reminder(ChecklistInstance $instance, string $phase): void
    {
        $assignee = $instance->assignee;
        if (! $assignee instanceof User) {
            Log::warning('checklist.reminder.no_assignee', ['instance_id' => $instance->id]);
            return;
        }

        $title = match ($phase) {
            'T-60' => 'Checklist reminder',
            'T-15' => 'Checklist due soon',
            default => 'Checklist reminder',
        };

        // Map phases to specific DLT templates and SMS bodies
        $template = match ($phase) {
            // Morning reminder → checklist_morning
            'T-60' => 'checklist_morning',
            // Afternoon reminder → checklist_afternoon
            'T-15' => 'checklist_afternoon',
            // Fallback generic reminder template
            default => 'checklist_reminder',
        };

        $body = match ($phase) {
            'T-60' => 'OMAPMS : New day, new checklist. Please start and complete your assignment.',
            'T-15' => 'OMAPMS : Friendly reminder: please finish and submit your assigned checklist',
            default => 'OMAPMS: Reminder to complete your assigned checklist.',
        };

        $this->sendSmsAndPush($assignee, $title, $body, $template, [
            'type' => 'checklist_reminder',
            'phase' => $phase,
            'checklist_instance_id' => $instance->id,
        ]);
    }

    public function escalation(ChecklistInstance $instance): void
    {
        $assignee = $instance->assignee;
        if ($assignee instanceof User) {
            $this->sendSmsAndPush(
                $assignee,
                'Checklist overdue',
                'OMAPMS : Checklist is overdue. Complete it immediately or escalate to your manager',
                'checklist_overdue',
                [
                    'type' => 'checklist_overdue',
                    'checklist_instance_id' => $instance->id,
                ]
            );
        }

        // Notify the reviewing manager if one is assigned
        if ($instance->manager instanceof User) {
            $this->push->toUser(
                $instance->manager->id,
                'Checklist overdue',
                sprintf('Checklist for %s is overdue.', $assignee?->name ?? 'assignee'),
                [
                    'type' => 'checklist_overdue_manager',
                    'checklist_instance_id' => $instance->id,
                ]
            );
        }

        // Also notify all Campus Managers in the tenant about overdue checklists
        $this->notifyCampusManagers($instance, $assignee);

        Log::warning('checklist.escalated', [
            'instance_id' => $instance->id,
        ]);
    }

    /**
     * Notify all Campus Managers in the tenant about an overdue checklist.
     */
    protected function notifyCampusManagers(ChecklistInstance $instance, ?User $assignee): void
    {
        // Find all Campus Managers in the same tenant
        $campusManagers = User::query()
            ->where('tenant_id', $instance->tenant_id)
            ->whereHas('roles', fn ($q) => $q->where('name', 'Campus Manager'))
            ->get();

        if ($campusManagers->isEmpty()) {
            Log::info('checklist.no_campus_managers', [
                'instance_id' => $instance->id,
                'tenant_id' => $instance->tenant_id,
            ]);
            return;
        }

        $staffName = $assignee?->name ?? 'Staff member';
        $role = $instance->role ?? 'Staff';
        $date = $instance->date?->format('d M Y') ?? 'today';

        foreach ($campusManagers as $manager) {
            // Skip if this manager is already the reviewing manager (already notified above)
            if ($instance->manager_user_id === $manager->id) {
                continue;
            }

            $this->push->toUser(
                $manager->id,
                'Staff Checklist Overdue',
                sprintf('%s (%s) has not submitted their checklist for %s.', $staffName, $role, $date),
                [
                    'type' => 'checklist_overdue_campus_manager',
                    'checklist_instance_id' => $instance->id,
                    'assignee_user_id' => $assignee?->id,
                    'role' => $role,
                ]
            );
        }

        Log::info('checklist.campus_managers_notified', [
            'instance_id' => $instance->id,
            'manager_count' => $campusManagers->count(),
        ]);
    }

    public function sentBack(ChecklistInstance $instance, ?string $note): void
    {
        $assignee = $instance->assignee;
        if (! $assignee instanceof User) {
            return;
        }

        $body = $note
            ? "Checklist sent back: $note"
            : 'Checklist sent back. Please review and resubmit.';

        $this->sendSmsAndPush(
            $assignee,
            'Checklist sent back',
            $body,
            // No dedicated DLT template yet; use generic checklist reminder bucket
            'checklist_reminder',
            [
                'type' => 'checklist_sent_back',
                'checklist_instance_id' => $instance->id,
            ]
        );

        Log::info('checklist.sent_back', [
            'instance_id' => $instance->id,
            'note' => $note,
        ]);
    }

    private function sendSmsAndPush(User $user, string $title, string $body, string $template, array $meta = []): void
    {
        if ($user->phone) {
            $this->sms->send(
                $user->phone,
                $body,
                (string) $user->tenant_id,
                $template,
                $meta + ['related_type' => ChecklistInstance::class]
            );
        }

        $type = $meta['type'] ?? null;
        $templateKey = $this->resolveChecklistTemplateKey($user, $type);

        $this->push->toUserTemplate(
            $user->id,
            $templateKey,
            [], // current templates don't require placeholders
            $meta
        );
    }

    private function resolveChecklistTemplateKey(User $user, ?string $type): string
    {
        $isOverdue = in_array($type, [
            'checklist_overdue',
            'checklist_overdue_manager',
            'checklist_overdue_campus_manager',
        ], true);

        if ($user->hasRole('Laundry Manager')) {
            return $isOverdue
                ? 'laundry_manager.checklist_overdue'
                : 'laundry_manager.checklist_assigned';
        }

        return $isOverdue
            ? 'staff_all.checklist_overdue'
            : 'staff_all.checklist_assigned';
    }
}
