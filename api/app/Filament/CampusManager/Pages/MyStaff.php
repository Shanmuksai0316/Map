<?php

namespace App\Filament\CampusManager\Pages;

use App\Models\Hostel;
use App\Models\User;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Stancl\Tenancy\Database\Models\Domain;

class MyStaff extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'My Staff';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.campus-manager.pages.my-staff';

    public function getHeading(): string
    {
        return 'My Staff';
    }

    public function getSubheading(): ?string
    {
        return 'Warden, supervisors, guard, laundry and sports staff assigned to Tulip Boys Hostel';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $user = auth()->user();
                $tenantId = null;

                // Prefer tenant from subdomain context; fallback to user tenant.
                if (function_exists('tenant') && tenant()) {
                    $tenantId = tenant()->id;
                }

                if (!$tenantId) {
                    $tenantId = $user?->tenant_id;
                }

                if (!$tenantId) {
                    $host = request()->getHost();
                    $tenantId = Domain::query()
                        ->where('domain', $host)
                        ->value('tenant_id');
                }

                if (!$tenantId) {
                    return User::query()->whereRaw('1 = 0');
                }

                $allowedRoles = [
                    'Warden',
                    'Guard',
                    'HK Supervisor',
                    'RM Supervisor',
                    'Laundry Manager',
                    'Sports Manager',
                ];
                $tulipHostelIds = Hostel::query()
                    ->where('tenant_id', $tenantId)
                    ->where(function (Builder $query) {
                        $query->whereRaw('LOWER(TRIM(name)) = ?', ['tulip boys hostel'])
                            ->orWhereRaw('LOWER(name) LIKE ?', ['%tulip boys hostel%']);
                    })
                    ->pluck('id');

                \Log::info('MyStaff query context', [
                    'user_id' => $user?->id,
                    'host' => request()->getHost(),
                    'tenant_id' => $tenantId,
                    'tulip_hostel_count' => $tulipHostelIds->count(),
                ]);

                if ($tulipHostelIds->isEmpty()) {
                    return User::query()->whereRaw('1 = 0');
                }

                return User::query()
                    ->where('tenant_id', $tenantId)
                    ->where('kind', '!=', 'student')
                    ->where('archived', false)
                    ->whereHas('roles', function (Builder $query) use ($allowedRoles) {
                        $query->whereIn('name', $allowedRoles);
                    })
                    ->whereHas('staffHostels', function (Builder $query) use ($tulipHostelIds) {
                        $query->whereIn('hostels.id', $tulipHostelIds);
                    })
                    ->whereDoesntHave('roles', function (Builder $query) {
                        $query->where('name', 'Super Admin');
                    });
            })
            ->columns([
                Tables\Columns\TextColumn::make('employee_id')
                    ->label('Employee ID')
                    ->searchable()
                    ->sortable()
                    ->default('—'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('department')
                    ->label('Department')
                    ->getStateUsing(function (User $record) {
                        // Get role name as department
                        $role = $record->roles->first();
                        if (!$role) return '—';
                        
                        return match($role->name) {
                            'Warden' => 'Warden',
                            'Guard' => 'Security Guard',
                            'HK Supervisor' => 'Housekeeping',
                            'RM Supervisor' => 'Maintenance',
                            'Laundry Manager' => 'Laundry',
                            'Sports Manager' => 'Sports',
                            default => $role->name,
                        };
                    })
                    ->badge(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Contact Number')
                    ->searchable(),
            ])
            ->filters([])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('name')
            ->emptyStateHeading('No staff assigned')
            ->emptyStateDescription('There are no staff members assigned to hostels you manage.');
    }
}
