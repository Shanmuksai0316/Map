<?php

namespace App\Providers;

use App\Domain\Attendance\Models\AttendanceSession;
use App\Domain\Checklists\Models\ChecklistInstance;
use App\Models\Campus;
use App\Models\Domain\OutPass\OutPass;
use App\Models\Domain\OutPass\OutPassExport;
use App\Models\GateEntry;
use App\Domain\Gate\Models\GateEntry as DomainGateEntry;
use App\Domain\Visitors\Models\GuestVisit;
use App\Models\Hostel;
use App\Models\ImportJob;
use App\Models\LaundryCycle;
use App\Models\LaundryRequest;
use App\Models\Notice;
use App\Models\RoomAllocation;
use App\Models\SportsEvent;
use App\Models\SportsEnrollment;
use App\Models\User;
use App\Domain\RoomChanges\Models\RoomChange;
use App\Domain\Tickets\Models\Ticket;
use App\Policies\AttendancePolicy;
use App\Policies\AttendanceSessionPolicy;
use App\Policies\Auth\LoginPolicy;
use App\Policies\CampusPolicy;
use App\Policies\Filament\AccessPolicy as FilamentAccessPolicy;
use App\Policies\GateEntryPolicy;
use App\Policies\GatePolicy;
use App\Policies\GuestVisitPolicy;
use App\Policies\HostelPolicy;
use App\Policies\ImportJobPolicy;
use App\Policies\LaundryCyclePolicy;
use App\Policies\LaundryRequestPolicy;
use App\Policies\NoticePolicy;
use App\Policies\OutPass\OutPassPolicy;
use App\Policies\OutPassExportPolicy;
use App\Policies\RoomAllocationPolicy;
use App\Policies\ChecklistPolicy;
use App\Policies\RoomChangePolicy;
use App\Policies\TicketPolicy;
use App\Policies\SportsEventPolicy;
use App\Policies\SportsEnrollmentPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        OutPass::class => OutPassPolicy::class,
        OutPassExport::class => OutPassExportPolicy::class,
        Hostel::class => HostelPolicy::class,
        RoomAllocation::class => RoomAllocationPolicy::class,
        Campus::class => CampusPolicy::class,
        ImportJob::class => ImportJobPolicy::class,
        GateEntry::class => GateEntryPolicy::class,
        DomainGateEntry::class => GatePolicy::class,
        GuestVisit::class => GuestVisitPolicy::class,
        AttendanceSession::class => AttendancePolicy::class,
        LaundryRequest::class => LaundryRequestPolicy::class,
        LaundryCycle::class => LaundryCyclePolicy::class,
        Notice::class => NoticePolicy::class,
        SportsEvent::class => SportsEventPolicy::class,
        SportsEnrollment::class => SportsEnrollmentPolicy::class,
        Ticket::class => TicketPolicy::class,
            User::class => FilamentAccessPolicy::class,
            ChecklistInstance::class => ChecklistPolicy::class,
        RoomChange::class => RoomChangePolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::define('viewFilament', [FilamentAccessPolicy::class, 'access']);
        Gate::define('viewCampusManagerPanel', [FilamentAccessPolicy::class, 'campusManager']);
        Gate::define('auth.login', [LoginPolicy::class, 'attempt']);
        
        // Debug gate - only available when DEBUG_403=true
        if (config('app.debug_403', false)) {
            Gate::define('viewDiag', function ($user) {
                return $user && $user->hasRole('Super Admin');
            });
        }

        Route::bind('outpass', function ($value) {
            return OutPass::query()
                ->whereKey($value)
                ->with(['student', 'hostel'])
                ->firstOrFail();
        });

        Route::bind('roomChange', function ($value) {
            return RoomChange::query()
                ->whereKey($value)
                ->with(['student.user', 'hostel'])
                ->firstOrFail();
        });

        Route::bind('roomAllocation', function ($value) {
            return RoomAllocation::query()
                ->whereKey($value)
                ->with(['student.user', 'roomBed.room', 'checkoutChecklist', 'checkoutHistories'])
                ->firstOrFail();
        });
    }
}
