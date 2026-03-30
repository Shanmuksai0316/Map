<?php

namespace App\Domain\GuestEntries\Models;

use App\Models\Hostel;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Notifications\NotificationRecipients;
use App\Services\Notify\PushNotifier;
use Database\Factories\Domain\GuestEntries\GuestEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class GuestEntry extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return GuestEntryFactory::new();
    }

    protected $fillable = [
        'tenant_id',
        'student_id',
        'hostel_id',
        'unique_id',
        'title',
        'description',
        'guests',
        'primary_contact_mobile',
        'visit_date',
        'check_in_time',
        'check_out_time',
        'purpose_to_visit',
        'status',
        'rejection_reason',
        'approved_by',
        'approved_at',
        'submitted_at',
        'idempotency_key',
    ];

    protected $casts = [
        'guests' => 'array',
        'visit_date' => 'date',
        'check_in_time' => 'string', // Stored as time string (HH:mm)
        'check_out_time' => 'string', // Stored as time string (HH:mm)
        'approved_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($guestEntry) {
            if (empty($guestEntry->unique_id)) {
                $guestEntry->unique_id = 'GST-' . strtoupper(Str::random(8));
            }
            if (empty($guestEntry->title)) {
                $guestEntry->title = 'Parents Visit';
            }
            if (empty($guestEntry->submitted_at)) {
                $guestEntry->submitted_at = now();
            }
        });

        static::updated(function (GuestEntry $entry) {
            // When status changes to approved, notify the Warden with student + guests details
            if (!$entry->wasChanged('status')) {
                return;
            }

            $newStatus = strtolower((string) $entry->status);
            $originalStatus = strtolower((string) $entry->getOriginal('status'));

            if ($newStatus !== 'approved' || $originalStatus === 'approved') {
                return;
            }

            try {
                if (!$entry->tenant_id || !$entry->hostel_id || !$entry->student || !$entry->student->user) {
                    return;
                }

                $tenantId = (string) $entry->tenant_id;
                $hostelId = (int) $entry->hostel_id;

                /** @var NotificationRecipients $recipients */
                $recipients = app(NotificationRecipients::class);
                /** @var PushNotifier $push */
                $push = app(PushNotifier::class);

                $warden = $recipients->wardenForHostel($tenantId, $hostelId);
                if (!$warden) {
                    return;
                }

                $studentName = $entry->student->user->name ?? $entry->student->full_name ?? 'Unknown';
                $visitDate = $entry->visit_date?->format('d M Y') ?? '';

                $guests = is_array($entry->guests) ? $entry->guests : [];
                $guestSummary = '';
                if (!empty($guests)) {
                    $names = collect($guests)->pluck('name')->filter()->take(3)->all();
                    $guestSummary = implode(', ', $names);
                    if (count($guests) > 3) {
                        $guestSummary .= ' +' . (count($guests) - 3) . ' more';
                    }
                }

                $push->toUserTemplate(
                    $warden->id,
                    'warden.guest_entry_approved',
                    [
                        'student_name' => $studentName,
                        'visit_date'   => $visitDate,
                        'guests'       => $guestSummary ?: 'Guests',
                    ],
                    [
                        'type'          => 'guest_entry_approved',
                        'guestentry_id' => (string) $entry->id,
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('GuestEntry approved warden notification failed', [
                    'guest_entry_id' => $entry->id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class);
    }
}

