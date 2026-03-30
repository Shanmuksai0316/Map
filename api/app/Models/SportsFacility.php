<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SportsFacility extends Model
{
    use HasFactory;

    protected $fillable = [
        // 'tenant_id', // REMOVED - automatic isolation
        'hostel_id',
        'name',
        'type',
        'open_time',
        'close_time',
        'capacity',
        'is_active',
        'rules',
        'description',
    ];

    protected $casts = [
        'open_time' => 'datetime:H:i',
        'close_time' => 'datetime:H:i',
        'is_active' => 'boolean',
        'capacity' => 'integer',
        'rules' => 'array',
    ];

    /**
     * Tenant relationship.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(FacilityBooking::class, 'facility_id');
    }

    public function activeBookings(): HasMany
    {
        return $this->hasMany(FacilityBooking::class, 'facility_id')
            ->where('status', 'active');
    }

    public function upcomingBookings(): HasMany
    {
        return $this->hasMany(FacilityBooking::class, 'facility_id')
            ->where('start_at', '>', now())
            ->where('status', 'active')
            ->orderBy('start_at');
    }

    public function blockouts(): HasMany
    {
        return $this->hasMany(SportsBlockout::class, 'facility_id');
    }

    public function activeBlockouts(): HasMany
    {
        $now = now();
        return $this->hasMany(SportsBlockout::class, 'facility_id')
            ->where('start_at', '<=', $now)
            ->where('end_at', '>=', $now);
    }

    /**
     * Check if the facility is available for a given time slot
     * Considers both active bookings and blockouts
     */
    public function isAvailable($startTime, $endTime): bool
    {
        // Check for overlapping bookings
        $hasOverlappingBooking = $this->bookings()
            ->where('status', 'active')
            ->where(function ($query) use ($startTime, $endTime) {
                $query->whereBetween('start_at', [$startTime, $endTime])
                      ->orWhereBetween('end_at', [$startTime, $endTime])
                      ->orWhere(function ($q) use ($startTime, $endTime) {
                          $q->where('start_at', '<=', $startTime)
                            ->where('end_at', '>=', $endTime);
                      });
            })
            ->exists();

        if ($hasOverlappingBooking) {
            return false;
        }

        // Check for overlapping blockouts
        $hasOverlappingBlockout = $this->blockouts()
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where('start_at', '<', $endTime)
                      ->where('end_at', '>', $startTime);
            })
            ->exists();

        return !$hasOverlappingBlockout;
    }

    /**
     * Check if facility has a blockout for a given time slot
     */
    public function hasBlockout($startTime, $endTime): bool
    {
        return $this->blockouts()
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where('start_at', '<', $endTime)
                      ->where('end_at', '>', $startTime);
            })
            ->exists();
    }

    /**
     * Get available time slots for a given date
     */
    public function getAvailableSlots($date, int $durationMinutes = 60): array
    {
        $facilityOpen = $date->setTime($this->open_time->hour, $this->open_time->minute);
        $facilityClose = $date->setTime($this->close_time->hour, $this->close_time->minute);

        $slots = [];
        $current = clone $facilityOpen;

        while ($current->addMinutes($durationMinutes) <= $facilityClose) {
            $slotStart = clone $current;
            $slotEnd = clone $current;

            if ($this->isAvailable($slotStart, $slotEnd)) {
                $slots[] = [
                    'start' => $slotStart->format('H:i'),
                    'end' => $slotEnd->format('H:i'),
                ];
            }
        }

        return $slots;
    }
}
