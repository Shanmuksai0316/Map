<?php

namespace App\Jobs;

use App\Domain\Checklists\Models\ChecklistInstance;
use App\Models\User;
use App\Services\Notifications\SmsService;
use App\Services\Notify\PushNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendChecklistReminderNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $checklistInstanceId,
        public string $window = 'morning'
    ) {}

    public function handle(SmsService $smsService, PushNotifier $pushNotifier): void
    {
        $instance = ChecklistInstance::with('assignee')->find($this->checklistInstanceId);

        if (! $instance || ! $instance->assignee instanceof User) {
            return;
        }

        $assignee = $instance->assignee;
        $title = $this->window === 'overdue'
            ? 'Checklist overdue'
            : 'Checklist reminder';

        $body = match ($this->window) {
            'afternoon' => 'Friendly reminder: please finish and submit your assigned checklist.',
            'overdue' => 'Checklist is overdue. Complete it immediately or escalate to your manager.',
            default => 'New day, new checklist. Please start and complete your assignment.',
        };

        if ($assignee->phone) {
            $smsService->send(
                $assignee->phone,
                $body,
                (string) $instance->tenant_id,
                'checklist_'.$this->window,
                [
                    'related_type' => ChecklistInstance::class,
                    'related_id' => $instance->id,
                ]
            );
        }

        $pushMeta = [
            'type' => $this->window === 'overdue' ? 'checklist_overdue' : 'checklist_reminder',
            'window' => $this->window,
            'checklist_instance_id' => $instance->id,
        ];

        $templateKey = $this->resolveChecklistTemplateKey($assignee, $this->window === 'overdue');

        $pushNotifier->toUserTemplate(
            $assignee->id,
            $templateKey,
            [],
            $pushMeta
        );

        if ($this->window === 'overdue' && $instance->manager_user_id) {
            $pushNotifier->toUserTemplate(
                $instance->manager_user_id,
                'staff_all.checklist_overdue',
                [],
                [
                    'type' => 'checklist_overdue_manager',
                    'checklist_instance_id' => $instance->id,
                ]
            );
        }
    }

    private function resolveChecklistTemplateKey(User $assignee, bool $isOverdue): string
    {
        if ($assignee->hasRole('Laundry Manager')) {
            return $isOverdue
                ? 'laundry_manager.checklist_overdue'
                : 'laundry_manager.checklist_assigned';
        }

        return $isOverdue
            ? 'staff_all.checklist_overdue'
            : 'staff_all.checklist_assigned';
    }
}
