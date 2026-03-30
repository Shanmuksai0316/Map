<?php

namespace App\Providers;

use App\Events\StaffAssignmentChanged;
use App\Events\StudentActivated;
use App\Events\TenantActivated;
use App\Events\UserRoleChanged;
use App\Listeners\CreateTenantDomain;
use App\Listeners\DispatchActivationNotifications;
use App\Listeners\RevokeUserTokens;
use App\Listeners\SendStudentWelcomeNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        UserRoleChanged::class => [
            RevokeUserTokens::class,
        ],
        StaffAssignmentChanged::class => [
            RevokeUserTokens::class,
        ],
        StudentActivated::class => [
            SendStudentWelcomeNotification::class,
        ],
        TenantActivated::class => [
            // Ensure domain is created before we send any activation notifications.
            CreateTenantDomain::class,
            DispatchActivationNotifications::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        // Register model observers
        \App\Models\Domain\OutPass\OutPass::observe(\App\Observers\OutPassObserver::class);
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
