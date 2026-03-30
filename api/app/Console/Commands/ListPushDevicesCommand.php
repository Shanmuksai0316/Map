<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ListPushDevicesCommand extends Command
{
    protected $signature = 'push:list-devices
                            {--tenant= : Tenant ID to scope (optional)}';

    protected $description = 'List registered push device tokens (user_id, name, platform). Use this to find your user_id for push:send-test.';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $query = \DB::table('push_device_tokens')
            ->select('user_id', 'tenant_id', 'platform', 'updated_at')
            ->orderByDesc('updated_at');

        if ($tenantId !== null && $tenantId !== '') {
            $query->where('tenant_id', $tenantId);
        }

        $rows = $query->get();
        if ($rows->isEmpty()) {
            $this->warn('No device tokens registered. Log in from the mobile app first.');
            return 0;
        }

        $this->info('Registered devices (use user_id with: php artisan push:send-test <user_id>):');
        $this->newLine();

        foreach ($rows as $row) {
            $name = null;
            try {
                $user = User::find($row->user_id);
                $name = $user ? $user->name : null;
            } catch (\Throwable $e) {
                // Ignore if User not in same DB / tenant context
            }
            $namePart = $name ? " ({$name})" : '';
            $this->line(sprintf(
                '  user_id %s%s  platform: %s  updated: %s',
                $row->user_id,
                $namePart,
                $row->platform,
                $row->updated_at
            ));
        }

        $this->newLine();
        $this->line('Example: php artisan push:send-test ' . $rows->first()->user_id);
        return 0;
    }
}
