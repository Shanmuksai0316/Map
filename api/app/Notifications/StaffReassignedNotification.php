<?php

namespace App\Notifications;

use App\Models\Hostel;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffReassignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Tenant $tenant,
        public ?Hostel $hostel,
        public string $role
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->email) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Staff Assignment Update')
            ->greeting("Hello {$notifiable->name},")
            ->line("You have been assigned a new role in MAP HMS.");

        $message->line("**Role:** {$this->role}");
        $message->line("**Tenant:** {$this->tenant->name}");

        if ($this->hostel) {
            $message->line("**Hostel:** {$this->hostel->name}");
        }

        $message->line('Please log in to access your new dashboard.');
        $message->action('Go to Dashboard', url('/'));

        return $message;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'staff_reassignment',
            'title' => 'Staff Assignment Update',
            'message' => "You have been assigned as {$this->role}" .
                ($this->hostel ? " at {$this->hostel->name}" : "") .
                " for {$this->tenant->name}.",
            'tenant_id' => $this->tenant->id,
            'tenant_name' => $this->tenant->name,
            'hostel_id' => $this->hostel?->id,
            'hostel_name' => $this->hostel?->name,
            'role' => $this->role,
        ];
    }
}
