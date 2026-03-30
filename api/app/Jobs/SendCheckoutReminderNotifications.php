<?php

namespace App\Jobs;

use App\Models\RoomAllocation;
use App\Services\Notifications\SmsService;
use App\Services\Notify\PushNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class SendCheckoutReminderNotifications implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(SmsService $smsService, PushNotifier $pushNotifier): void
    {
        $thresholds = [
            '7_days' => now()->addDays(7),
            '3_days' => now()->addDays(3),
            '1_day' => now()->addDay(),
        ];

        foreach ($thresholds as $label => $date) {
            $allocations = RoomAllocation::query()
                ->with('student.user', 'hostel')
                ->where('is_active', true)
                ->where('checkout_status', 'pending')
                ->whereDate('expected_checkout_at', $date->toDateString())
                ->get();

            foreach ($allocations as $allocation) {
                $studentUser = $allocation->student?->user;
                if (! $studentUser) {
                    continue;
                }

                $daysText = match ($label) {
                    '7_days' => '7 days',
                    '3_days' => '3 days',
                    '1_day' => '1 day',
                    default => 'soon',
                };
                
                // Build message with template variables
                $message = "Reminder: checkout for {#student#} is due in {#days#} (Hostel: {#hostel#}). Please complete the checkout checklist.";
                
                // Replace template variables
                $message = str_replace('{#student#}', $studentUser->name, $message);
                $message = str_replace('{#days#}', $daysText, $message);
                $message = str_replace('{#hostel#}', $allocation->hostel?->name ?? 'hostel', $message);

                $smsService->send(
                    $studentUser->phone,
                    $message,
                    $allocation->tenant_id,
                    'checkout_reminder',
                    [
                        'related_type' => RoomAllocation::class,
                        'related_id' => $allocation->id,
                    ]
                );

                $pushNotifier->toUserTemplate(
                    $studentUser->id,
                    'student.checkout_reminder',
                    [
                        'student_name'   => $studentUser->name,
                        'days_remaining' => $daysText,
                        'hostel_name'    => $allocation->hostel?->name ?? 'hostel',
                    ],
                    [
                        'type'               => 'checkout_reminder',
                        'room_allocation_id' => $allocation->id,
                    ]
                );
            }
        }

        // Overdue reminders
        $overdueAllocations = RoomAllocation::query()
            ->with('student.user', 'hostel')
            ->where('is_active', true)
            ->where('checkout_status', 'pending')
            ->where('expected_checkout_at', '<', Carbon::today())
            ->get();

        foreach ($overdueAllocations as $allocation) {
            $studentUser = $allocation->student?->user;
            if (! $studentUser) {
                continue;
            }

            $dateText = Carbon::parse($allocation->expected_checkout_at)->format('d M');
            
            // Build message with template variables
            $message = "Checkout for {#student#} is overdue since {#date#}. Please complete checkout immediately.";
            
            // Replace template variables
            $message = str_replace('{#student#}', $studentUser->name, $message);
            $message = str_replace('{#date#}', $dateText, $message);

            $smsService->send(
                $studentUser->phone,
                $message,
                $allocation->tenant_id,
                'checkout_overdue',
                [
                    'related_type' => RoomAllocation::class,
                    'related_id' => $allocation->id,
                ]
            );

            $pushNotifier->toUserTemplate(
                $studentUser->id,
                'student.checkout_overdue',
                [
                    'student_name' => $studentUser->name,
                    'since_date'   => $dateText,
                ],
                [
                    'type'               => 'checkout_overdue',
                    'room_allocation_id' => $allocation->id,
                ]
            );
        }
    }
}

