<?php

namespace App\Console\Commands;

use App\Models\LaundryRequest;
use App\Models\Tenant;
use App\Domain\Tickets\Models\Ticket;
use App\Services\Notifications\DelayedRequestNotifier;
use Illuminate\Console\Command;

class CheckDelayedRequests extends Command
{
    protected $signature = 'requests:check-delayed {--tenant=* : Limit to specific tenant IDs}';

    protected $description = 'Find requests that exceeded 72h SLA, notify campus managers (in-app only), and mark as notified.';

    public function handle(DelayedRequestNotifier $notifier): int
    {
        $tenantIds = array_filter((array) $this->option('tenant'));
        $tenants = $tenantIds
            ? Tenant::whereIn('id', $tenantIds)->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->info('No tenants found.');
            return self::SUCCESS;
        }

        $ticketsNotified = 0;
        $laundryNotified = 0;

        foreach ($tenants as $tenant) {
            // Tickets (Housekeeping, Repair & Maintenance) – central DB with tenant_id
            $tickets = Ticket::query()
                ->where('tenant_id', $tenant->id)
                ->delayedUnnotified()
                ->get();

            foreach ($tickets as $ticket) {
                $notifier->notifyCampusManagersForDelayed($ticket);
                $ticket->update(['delayed_notified_at' => now()]);
                $ticketsNotified++;
            }

            // Laundry requests – central DB with tenant_id, or tenant DB (initialize tenancy)
            $laundryRequests = collect();
            if (\Schema::hasColumn((new LaundryRequest)->getTable(), 'tenant_id')) {
                $laundryRequests = LaundryRequest::query()
                    ->where('tenant_id', $tenant->id)
                    ->delayedUnnotified()
                    ->get();
            } elseif (function_exists('tenancy')) {
                tenancy()->initialize($tenant);
                $laundryRequests = LaundryRequest::query()->delayedUnnotified()->get();
            }

            foreach ($laundryRequests as $laundry) {
                $notifier->notifyCampusManagersForDelayed($laundry);
                $laundry->update(['delayed_notified_at' => now()]);
                $laundryNotified++;
            }
        }

        if ($ticketsNotified > 0 || $laundryNotified > 0) {
            $this->info(sprintf(
                'Notified campus managers: %d delayed ticket(s), %d delayed laundry request(s).',
                $ticketsNotified,
                $laundryNotified
            ));
        } else {
            $this->info('No delayed unnotified requests found.');
        }

        return self::SUCCESS;
    }
}
