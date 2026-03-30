<?php

namespace App\Filament\Resources\Admin\StaffManagementResource\Pages;

use App\Filament\Resources\Admin\StaffManagementResource;
use App\Services\StaffAssignmentService;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Support\Facades\DB;
use App\Models\Hostel;
use App\Models\Tenant;

class ViewStaffManagement extends ViewRecord
{
    protected static string $resource = StaffManagementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        $service = app(StaffAssignmentService::class);
        $currentAssignment = $service->getActiveAssignment($this->record);
        
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Staff Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label('Full Name'),
                        Infolists\Components\TextEntry::make('email')
                            ->label('Email'),
                        Infolists\Components\TextEntry::make('phone')
                            ->label('Phone'),
                        Infolists\Components\TextEntry::make('tenant.name')
                            ->label('Tenant')
                            ->badge()
                            ->color('info'),
                        Infolists\Components\TextEntry::make('roles.name')
                            ->label('Current Role')
                            ->badge()
                            ->color('primary'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                    ])
                    ->columns(2),
                
                Infolists\Components\Section::make('Current Assignment')
                    ->schema([
                        Infolists\Components\TextEntry::make('assignment_hostel')
                            ->label('Hostel')
                            ->state(function () use ($currentAssignment) {
                                if (!$currentAssignment) {
                                    return 'Not assigned';
                                }
                                $hostel = Hostel::find($currentAssignment->hostel_id);
                                return $hostel?->name ?? 'Unknown';
                            })
                            ->badge()
                            ->color(fn ($state) => $state === 'Not assigned' ? 'warning' : 'success'),
                        
                        Infolists\Components\TextEntry::make('assignment_tenant')
                            ->label('Assignment Tenant')
                            ->state(function () use ($currentAssignment) {
                                if (!$currentAssignment) {
                                    return '-';
                                }
                                $tenant = Tenant::find($currentAssignment->tenant_id);
                                return $tenant?->name ?? 'Unknown';
                            })
                            ->visible(fn () => $currentAssignment !== null),
                        
                        Infolists\Components\TextEntry::make('assigned_at')
                            ->label('Assigned Since')
                            ->state(fn () => $currentAssignment?->assigned_at 
                                ? \Carbon\Carbon::parse($currentAssignment->assigned_at)->format('d M Y, h:i A')
                                : '-'
                            )
                            ->visible(fn () => $currentAssignment !== null),
                        
                        Infolists\Components\TextEntry::make('assignment_notes')
                            ->label('Assignment Notes')
                            ->state(fn () => $currentAssignment?->assignment_notes ?? 'No notes')
                            ->visible(fn () => $currentAssignment !== null),
                    ])
                    ->columns(2)
                    ->collapsible(),
                
                Infolists\Components\Section::make('Assignment History')
                    ->schema([
                        Infolists\Components\ViewEntry::make('history')
                            ->view('filament.infolists.staff-assignment-history-entry')
                            ->state(function () use ($service) {
                                return $service->getAssignmentHistory($this->record);
                            }),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}


