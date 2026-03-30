<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use App\Models\Tenant;

/**
 * Student Model (Single Shared Database)
 * 
 * Stored in central database with tenant_id scoping.
 * Tenant isolation is enforced via TenantScope global scope and PostgreSQL RLS.
 */
class Student extends Model
{
    use HasFactory;
    use Traits\TenantScoped;

    protected $fillable = [
        // A. Identity & Academic
        'full_name',
        'gender',
        'date_of_birth',
        'map_student_id',
        'erp_number',
        'department',
        'year_of_study',

        // B. Contact
        'mobile_number',
        'email_address',

        // C. Parent Information
        'father_name',
        'father_mobile_number',
        'mother_name',
        'mother_mobile_number',

        // D. Local Guardian
        'local_guardian_name',
        'local_guardian_contact',
        'local_address',
        'local_relationship',

        // E. Medical
        'blood_group',
        'medical_information',

        // F. Hostel Allocation
        'assigned_hostel',
        'hostel_id',
        'check_in_date',
        'check_out_date',
    ];

    protected $casts = [
        'guardian' => 'encrypted:array', // PII - encrypted at rest
        'medical_notes' => 'encrypted:array', // PHI - encrypted at rest
        'correspondence_address' => 'array',
        'hostel_fee_paid' => 'boolean',
        'payment_amount' => 'decimal:2',
        'payment_date' => 'date',
        'archived_at' => 'datetime',
    ];

    /**
     * Get the tenant this student belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function user(): BelongsTo
    {
        // All data is in single shared database now
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class);
    }

    public function preferredHostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class, 'preferred_hostel_id');
    }

    public function roomAllocations(): HasMany
    {
        return $this->hasMany(RoomAllocation::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Scope: students with an active room allocation.
     */
    public function scopeAssigned(Builder $query): Builder
    {
        return $query->whereHas('roomAllocations', function (Builder $allocation): void {
            $allocation->where('is_active', true);
        });
    }

    /**
     * Scope: students without an active room allocation.
     */
    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereDoesntHave('roomAllocations', function (Builder $allocation): void {
            $allocation->where('is_active', true);
        });
    }

    /**
     * Scope: archived students.
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    /**
     * Generate a unique student UID for the given tenant.
     */
    public static function generateStudentUid(int|string|null $tenantId = null): string
    {
        $year = now()->format('Y');
        $tenantPrefix = 'STD';

        if ($tenantId && ($tenant = Tenant::find($tenantId))) {
            $candidate = strtoupper(Str::slug($tenant->code ?? $tenant->name ?? 'STD', ''));
            $tenantPrefix = substr($candidate, 0, 4) ?: 'STD';
        }

        do {
            $suffix = Str::upper(Str::random(4));
            $uid = sprintf('%s-%s-%s', $tenantPrefix, $year, $suffix);
        } while (self::query()->where('student_uid', $uid)->exists());

        return $uid;
    }

    /**
     * Generate a global MAP student identifier required by legacy systems.
     */
    public static function generateMapStudentId(int|string|null $tenantId = null): string
    {
        $tenantCode = 'MAP';

        if ($tenantId && ($tenant = Tenant::find($tenantId))) {
            $candidate = strtoupper(Str::slug($tenant->code ?? $tenant->name ?? 'MAP', ''));
            $tenantCode = substr($candidate, 0, 6) ?: 'MAP';
        }

        do {
            $suffix = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $mapId = sprintf('STD-%s-%s', $tenantCode, $suffix);
        } while (self::query()->where('map_student_id', $mapId)->exists());

        return $mapId;
    }
}
