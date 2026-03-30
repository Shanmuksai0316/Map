<?php

namespace App\Filament\CampusManager\Resources\StudentResource\Pages;

use App\Filament\CampusManager\Resources\StudentResource;
use App\Models\Student;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use App\Http\Middleware\SetPostgresSessionTenant;

class ListStudents extends ListRecords
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getTenantId(): ?string
    {
        // Prioritize tenant context from subdomain
        try {
            if (function_exists('tenant') && tenant()) {
                return tenant()->id;
            }
        } catch (\Exception $e) {
            // tenant() might not be available
        }

        // Fallback to user's tenant_id
        if (Auth::check() && Auth::user()?->tenant_id) {
            return Auth::user()->tenant_id;
        }

        return null;
    }

    public function getTabs(): array
    {
        $tenantId = $this->getTenantId();
        
        if ($tenantId) {
            SetPostgresSessionTenant::setTenantSessionVariable($tenantId);
        }

        $baseQuery = Student::query();
        if ($tenantId) {
            $baseQuery->where('tenant_id', $tenantId);
        }

        return [
            'all' => Tab::make('All Students')
                ->modifyQueryUsing(function (Builder $query) use ($tenantId) {
                    if ($tenantId) {
                        SetPostgresSessionTenant::setTenantSessionVariable($tenantId);
                        $query->where('tenant_id', $tenantId);
                    }
                    return $query;
                })
                ->badge(fn () => (clone $baseQuery)->count()),

            'assigned' => Tab::make('Assigned')
                ->modifyQueryUsing(function (Builder $query) use ($tenantId) {
                    if ($tenantId) {
                        SetPostgresSessionTenant::setTenantSessionVariable($tenantId);
                        $query->where('tenant_id', $tenantId);
                    }
                    return $query->assigned();
                })
                ->badge(fn () => (clone $baseQuery)->assigned()->count()),

            'unassigned' => Tab::make('Unassigned')
                ->modifyQueryUsing(function (Builder $query) use ($tenantId) {
                    if ($tenantId) {
                        SetPostgresSessionTenant::setTenantSessionVariable($tenantId);
                        $query->where('tenant_id', $tenantId);
                    }
                    return $query->unassigned();
                })
                ->badge(fn () => (clone $baseQuery)->unassigned()->count()),

            'archived' => Tab::make('Archived')
                ->modifyQueryUsing(function (Builder $query) use ($tenantId) {
                    if ($tenantId) {
                        SetPostgresSessionTenant::setTenantSessionVariable($tenantId);
                        $query->where('tenant_id', $tenantId);
                    }
                    return $query->archived();
                })
                ->badge(fn () => (clone $baseQuery)->archived()->count()),
        ];
    }

}
