<?php

namespace App\Filament\CampusManager\Widgets\CampusManager;

use App\Domain\RoomChanges\Models\RoomChange;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

class RoomChangeQueueWidget extends Widget
{
    protected static string $view = 'filament.campus-manager.widgets.room-change-queue';

    protected int|string|array $columnSpan = [
        'md' => 1,
    ];

    protected function getViewData(): array
    {
        try {
            // Get tenant ID - prioritize tenant context from subdomain
            $tenantId = null;
            try {
                if (function_exists('tenant') && tenant()) {
                    $tenantId = tenant()->id;
                }
            } catch (\Exception $e) {
                // tenant() might not be available
            }
            
            if (!$tenantId) {
                $user = auth()->user();
                $tenantId = $user?->tenant_id;
            }
            
            // Explicitly scope by tenant_id to ensure we get data for current tenant
            // Even if TenantScope bypasses for Super Admin, we want tenant-specific data
            $pending = RoomChange::query()->where('status', 'pending');
            if ($tenantId) {
                $pending->where('tenant_id', $tenantId);
            }

            $pendingCount = (clone $pending)->count();
            $oldest = (clone $pending)->orderBy('submitted_at')->first();
            $oldestAge = $oldest?->submitted_at
                ? Carbon::parse($oldest->submitted_at)->diffForHumans(null, true)
                : null;

            $urgent = (clone $pending)->where('submitted_at', '<=', now()->subDays(3))->count();

            return [
                'pending' => $pendingCount,
                'urgent' => $urgent,
                'oldestAge' => $oldestAge,
                'oldestStudent' => $oldest?->student?->user?->name ?? null,
            ];
        } catch (\Exception $e) {
            \Log::error('RoomChangeQueueWidget error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'pending' => 0,
                'urgent' => 0,
                'oldestAge' => null,
                'oldestStudent' => null,
            ];
        }
    }
}

