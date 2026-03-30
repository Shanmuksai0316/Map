<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Filament\Panel;
use Filament\Models\Contracts\FilamentUser;

/**
 * User Model (Works in Both Central and Tenant Contexts)
 *
 * This model works in both central and tenant database contexts.
 * - In central context: Users are queried from central database with tenant_id
 * - In tenant context: Users are queried from tenant database with tenant_id
 *
 * The Laravel tenancy package automatically switches database connections based on context.
 * tenant_id is required in both contexts for cross-tenant queries, policies, and ready checks.
 */
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use Notifiable;
    // NOTE: NO TenantScoped trait needed - isolation is automatic by database

    /**
     * Default guard for Spatie roles/permissions.
     * Super Admin flows in tests expect the web guard.
     */
    protected $guard_name = 'web';

    /**
     * The database connection that should be used by the model.
     * 
     * When null, Laravel will use the current default connection.
     * With single shared database architecture, all data is in central database.
     * 
     * tenant_id is required for tenant isolation and queries.
     */
    protected $connection = null; // Uses central database connection (single shared database)

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'phone',
        'username',
        'name',
        'email',
        'password',
        'kind',
        'profile_photo_path',
        'gender',
        'dob',
        'id_type',
        'id_number',
        'address',
        'status',
        'date_of_joining',
        'emergency_contact_name',
        'emergency_contact_phone',
        'archived',
        'archived_at',
        'archived_reason',
        'is_active',
        'is_map_staff',
        'mfa_secret',
        'mfa_enabled',
    ];

    protected $casts = [
        'tenant_id' => 'string',
        'address' => 'array',
        'dob' => 'date',
        'date_of_joining' => 'date',
        'archived' => 'boolean',
        'archived_at' => 'datetime',
        'is_active' => 'boolean',
        'is_map_staff' => 'boolean',
        'mfa_enabled' => 'boolean',
        'password' => 'hashed',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Alias for tenant() for Filament resources compatibility.
     */
    public function tenantRelation(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function roomAllocation(): HasMany
    {
        return $this->hasMany(RoomAllocation::class, 'student_id');
    }

    public function staffHostels()
    {
        return $this->belongsToMany(\App\Models\Hostel::class, 'staff_assignments')
            ->withPivot(['assigned_at','revoked_at'])
            ->wherePivotNull('revoked_at');
    }

    /**
     * Alias for staffHostels() for backward compatibility.
     * Returns the same relation - staff assignments are stored via staffHostels() many-to-many.
     */
    public function staffAssignments()
    {
        return $this->staffHostels();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Normalize kinds/roles for safety
        $kind = strtolower((string) $this->kind);
        $isSuper = $this->hasRole('Super Admin');

        return match ($panel->getId()) {
            'admin' => $isSuper, // only Super Admin
            'campus-manager' => $isSuper || $kind === 'campus_manager' || $this->hasRole('Campus Manager'),
            'rector' => $isSuper || $this->hasRole('Rector'),
            'college-mgmt' => $isSuper || $this->hasRole('College Management') || $this->hasRole('College Mgmt'),
            default => false,
        };
    }

    /**
     * Get tenants that this user can access (for Filament tenancy)
     * Since users belong to a single tenant, return that tenant
     */
    public function getTenants(): \Illuminate\Database\Eloquent\Collection
    {
        if ($this->tenant_id) {
            return \App\Models\Tenant::where('id', $this->tenant_id)->get();
        }

        // For Super Admin, return all tenants
        if ($this->hasRole('Super Admin')) {
            return \App\Models\Tenant::all();
        }

        return collect();
    }

    /**
     * Check if user is MAP staff (assigned by Super Admin, requires hostel assignment)
     */
    public function isMapStaff(): bool
    {
        return $this->is_map_staff === true;
    }

    /**
     * Check if user is college representative (Rector or College Management)
     * These are created during onboarding and do NOT require hostel assignment
     */
    public function isCollegeRepresentative(): bool
    {
        return $this->hasAnyRole(['Rector', 'College Management']);
    }

    /**
     * Get MAP staff roles (assigned by Super Admin, require hostel assignment)
     */
    public static function mapStaffRoles(): array
    {
        return [
            'Campus Manager',
            'Warden',
            'Guard',
            'HK Supervisor',
            'RM Supervisor',
            'Laundry Manager',
            'Sports Manager',
        ];
    }

    /**
     * Get college representative roles (created during onboarding, no hostel assignment)
     */
    public static function collegeRepresentativeRoles(): array
    {
        return [
            'Rector',
            'College Management',
        ];
    }

    /**
     * Scope to get only staff users (non-students).
     */
    public function scopeStaff($query)
    {
        return $query->where('kind', '!=', 'student');
    }

    /**
     * Scope to get assigned staff (has tenant_id).
     */
    public function scopeAssigned($query)
    {
        return $query->staff()->whereNotNull('tenant_id')->where('archived', false);
    }

    /**
     * Scope to get unassigned staff (no tenant_id).
     */
    public function scopeUnassigned($query)
    {
        return $query->staff()->whereNull('tenant_id')->where('archived', false);
    }

    /**
     * Scope to get archived staff.
     */
    public function scopeArchivedStaff($query)
    {
        return $query->staff()->where('archived', true);
    }

    /**
     * Check if user is assigned to a tenant.
     */
    public function isAssigned(): bool
    {
        return !is_null($this->tenant_id);
    }

    /**
     * Generate a unique employee ID.
     */
    public static function generateEmployeeId(): string
    {
        $driver = static::query()->getConnection()->getDriverName();
        $orderExpr = match ($driver) {
            'sqlite' => 'CAST(SUBSTR(employee_id, 5) AS INTEGER) DESC',
            default => 'CAST(SUBSTRING(employee_id FROM 5) AS INTEGER) DESC',
        };

        $lastId = static::whereNotNull('employee_id')
            ->orderByRaw($orderExpr)
            ->value('employee_id');

        if ($lastId) {
            $number = (int) substr($lastId, 4) + 1;
        } else {
            $number = 1;
        }

        return 'EMP-' . str_pad($number, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Boot method to auto-generate employee_id for staff.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if ($user->kind !== 'student' && empty($user->employee_id)) {
                $user->employee_id = static::generateEmployeeId();
            }
        });
    }
}
