<?php

namespace App\Filament\CampusManager\Resources\StudentResource\Pages;

use App\Filament\CampusManager\Resources\StudentResource;
use App\Models\RoomAllocation;
use App\Models\RoomBed;
use App\Models\Room;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewStudent extends ViewRecord
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // A. Basic Information
                Infolists\Components\Section::make('Basic Information')
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('full_name')
                            ->label('Full Name')
                            ->default(fn ($record) => $record->full_name ?? optional(\App\Models\User::on('pgsql')->find($record->user_id))->name ?? '—'),
                        Infolists\Components\TextEntry::make('email_address')
                            ->label('Email ID')
                            ->default(fn ($record) => $record->email_address ?? optional(\App\Models\User::on('pgsql')->find($record->user_id))->email ?? '—'),
                        Infolists\Components\TextEntry::make('mobile_number')
                            ->label('Mobile Number')
                            ->default(fn ($record) => $record->mobile_number ?? optional(\App\Models\User::on('pgsql')->find($record->user_id))->phone ?? '—'),
                        Infolists\Components\TextEntry::make('user_gender')
                            ->label('Gender')
                            ->badge()
                            ->getStateUsing(fn ($record) => optional(\App\Models\User::on('pgsql')->find($record->user_id))->gender ?? '—')
                            ->formatStateUsing(fn ($state) => ucfirst($state ?? '—')),
                        Infolists\Components\TextEntry::make('user_dob')
                            ->label('Date of Birth')
                            ->getStateUsing(fn ($record) => optional(\App\Models\User::on('pgsql')->find($record->user_id))->dob)
                            ->date('d M Y'),
                    ]),

                // B. Academic Details
                Infolists\Components\Section::make('Academic Details')
                    ->columns(4)
                    ->schema([
                        Infolists\Components\TextEntry::make('map_id')
                            ->label('MAP ID')
                            ->default('—'),
                        Infolists\Components\TextEntry::make('erp_number')
                            ->label('ERP Number')
                            ->default('—'),
                        Infolists\Components\TextEntry::make('department')
                            ->label('Department')
                            ->default('—'),
                        Infolists\Components\TextEntry::make('year_of_study')
                            ->label('Year')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state ? "Year {$state}" : '—'),
                    ]),

                // C. Hostel Allocation
                Infolists\Components\Section::make('Hostel Allocation')
                    ->columns(4)
                    ->schema([
                        Infolists\Components\TextEntry::make('hostel_name')
                            ->label('Assigned Hostel')
                            ->getStateUsing(function ($record) {
                                $allocation = $record->roomAllocations?->firstWhere('is_active', true);
                                if (!$allocation) return '—';
                                return $allocation->hostel?->name ?? '—';
                            }),
                        Infolists\Components\TextEntry::make('room_number')
                            ->label('Room Number')
                            ->getStateUsing(function ($record) {
                                $allocation = $record->roomAllocations?->firstWhere('is_active', true);
                                if (!$allocation || !$allocation->room_bed_id) return '—';
                                $bed = RoomBed::withoutGlobalScopes()->find($allocation->room_bed_id);
                                if (!$bed) return '—';
                                $room = Room::withoutGlobalScopes()->find($bed->room_id);
                                return $room?->number ?? '—';
                            }),
                        Infolists\Components\TextEntry::make('room_capacity')
                            ->label('Room Capacity')
                            ->getStateUsing(function ($record) {
                                $allocation = $record->roomAllocations?->firstWhere('is_active', true);
                                if (!$allocation || !$allocation->room_bed_id) return '—';
                                $bed = RoomBed::withoutGlobalScopes()->find($allocation->room_bed_id);
                                if (!$bed) return '—';
                                $room = Room::withoutGlobalScopes()->find($bed->room_id);
                                return $room?->capacity ?? '—';
                            }),
                        Infolists\Components\TextEntry::make('allocation_status')
                            ->label('Current Status')
                            ->badge()
                            ->getStateUsing(fn ($record) => $record->roomAllocations?->isNotEmpty() ? 'Assigned' : 'Unassigned')
                            ->color(fn ($state) => $state === 'Assigned' ? 'success' : 'warning'),
                    ]),

                // D. Emergency Contacts & Parent Info
                Infolists\Components\Section::make('Emergency Contacts & Parent Info')
                    ->schema([
                        Infolists\Components\Fieldset::make('Parent Information')
                            ->columns(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('father_name')
                                    ->label('Father Name')
                                    ->default('—'),
                                Infolists\Components\TextEntry::make('father_mobile_number')
                                    ->label('Father Number')
                                    ->default('—'),
                                Infolists\Components\TextEntry::make('mother_name')
                                    ->label('Mother Name')
                                    ->default('—'),
                                Infolists\Components\TextEntry::make('mother_mobile_number')
                                    ->label('Mother Number')
                                    ->default('—'),
                            ]),
                        Infolists\Components\Fieldset::make('Local Guardian')
                            ->columns(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('local_guardian_name')
                                    ->label('Guardian Name')
                                    ->default('—'),
                                Infolists\Components\TextEntry::make('local_guardian_contact')
                                    ->label('Contact Number')
                                    ->default('—'),
                                Infolists\Components\TextEntry::make('local_relationship')
                                    ->label('Relationship')
                                    ->default('—'),
                                Infolists\Components\TextEntry::make('local_address')
                                    ->label('Local Address')
                                    ->default('—')
                                    ->columnSpan(4),
                            ]),
                    ]),

                // E. Medical Information
                Infolists\Components\Section::make('Medical Information')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('blood_group')
                            ->label('Blood Group')
                            ->badge()
                            ->default('—'),
                        Infolists\Components\TextEntry::make('medical_information')
                            ->label('Medical Conditions / Allergies')
                            ->default('—')
                            ->columnSpan(1),
                    ]),
            ]);
    }
}
