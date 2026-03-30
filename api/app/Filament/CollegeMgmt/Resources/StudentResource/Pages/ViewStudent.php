<?php

namespace App\Filament\CollegeMgmt\Resources\StudentResource\Pages;

use App\Filament\CollegeMgmt\Resources\StudentResource;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewStudent extends ViewRecord
{
    protected static string $resource = StudentResource::class;

    private function activeAllocation($record)
    {
        return $record->roomAllocations()->with('roomBed.room.hostel')->where('is_active', true)->first()
            ?? $record->roomAllocations()->with('roomBed.room.hostel')->latest('id')->first();
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // A. Basic Information
                Section::make('Basic Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('user.name')
                                    ->label('Full Name'),
                                TextEntry::make('user.email')
                                    ->label('Email ID')
                                    ->default('N/A'),
                                TextEntry::make('user.phone')
                                    ->label('Mobile Number'),
                                TextEntry::make('user.gender')
                                    ->label('Gender')
                                    ->default('N/A'),
                                TextEntry::make('user.date_of_birth')
                                    ->label('Date of Birth')
                                    ->date('d M Y')
                                    ->default('N/A'),
                            ]),
                    ])
                    ->collapsible(),

                // B. Academic Details
                Section::make('Academic Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('map_student_id')
                                    ->label('MAP ID'),
                                TextEntry::make('erp_number')
                                    ->label('ERP Number')
                                    ->default('N/A'),
                                TextEntry::make('program')
                                    ->label('Department')
                                    ->default('N/A'),
                                TextEntry::make('year_of_study')
                                    ->label('Year')
                                    ->default('N/A'),
                            ]),
                    ])
                    ->collapsible(),

                // C. Hostel Allocation
                Section::make('Hostel Allocation')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('assigned_hostel')
                                    ->label('Assigned Hostel')
                                    ->state(function ($record) {
                                        $allocation = $this->activeAllocation($record);
                                        return $allocation?->roomBed?->room?->hostel?->name ?? 'Not Assigned';
                                    })
                                    ->default('Not Assigned'),
                                TextEntry::make('room_info')
                                    ->label('Room Number')
                                    ->state(function ($record) {
                                        $allocation = $this->activeAllocation($record);
                                        if (!$allocation || !$allocation->roomBed || !$allocation->roomBed->room) {
                                            return 'Not Assigned';
                                        }
                                        $room = $allocation->roomBed->room;
                                        return $room->block_code . '-' . $room->floor_code . $room->room_no . ' (Bed ' . $allocation->roomBed->bed_code . ')';
                                    }),
                                TextEntry::make('room_capacity')
                                    ->label('Room Capacity')
                                    ->state(function ($record) {
                                        $allocation = $this->activeAllocation($record);
                                        if (!$allocation || !$allocation->roomBed || !$allocation->roomBed->room) {
                                            return 'N/A';
                                        }
                                        $bedCount = \DB::table('room_beds')
                                            ->where('room_id', $allocation->roomBed->room_id)
                                            ->count();
                                        return $bedCount . ' beds';
                                    }),
                                TextEntry::make('allocation_status')
                                    ->label('Current Status')
                                    ->state(function ($record) {
                                        $allocation = $this->activeAllocation($record);
                                        if (!$allocation) {
                                            return 'Unassigned';
                                        }
                                        if ($allocation->effective_to && $allocation->effective_to < now()) {
                                            return 'Inactive';
                                        }
                                        return 'Active';
                                    })
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'Active' => 'success',
                                        'Inactive' => 'danger',
                                        'Unassigned' => 'warning',
                                    }),
                            ]),
                    ])
                    ->collapsible(),

                // D. Emergency Contacts & Parent Info
                Section::make('Parent/Guardian Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('guardian.father_name')
                                    ->label('Father Name')
                                    ->default('N/A')
                                    ->state(fn ($record) => $record->guardian['father_name'] ?? $record->father_name ?? 'N/A'),
                                TextEntry::make('guardian.father_phone')
                                    ->label('Father Number')
                                    ->default('N/A')
                                    ->state(fn ($record) => $record->guardian['father_phone'] ?? $record->father_mobile_number ?? 'N/A'),
                                TextEntry::make('guardian.mother_name')
                                    ->label('Mother Name')
                                    ->default('N/A')
                                    ->state(fn ($record) => $record->guardian['mother_name'] ?? $record->mother_name ?? 'N/A'),
                                TextEntry::make('guardian.mother_phone')
                                    ->label('Mother Number')
                                    ->default('N/A')
                                    ->state(fn ($record) => $record->guardian['mother_phone'] ?? $record->mother_mobile_number ?? 'N/A'),
                            ]),
                    ])
                    ->collapsible(),

                Section::make('Local Guardian Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('guardian.local_guardian_name')
                                    ->label('Guardian Name')
                                    ->default('N/A')
                                    ->state(fn ($record) => $record->guardian['local_guardian_name'] ?? $record->local_guardian_name ?? 'N/A'),
                                TextEntry::make('guardian.local_guardian_phone')
                                    ->label('Contact Number')
                                    ->default('N/A')
                                    ->state(fn ($record) => $record->guardian['local_guardian_phone'] ?? $record->local_guardian_contact ?? 'N/A'),
                                TextEntry::make('guardian.local_guardian_relationship')
                                    ->label('Relationship')
                                    ->default('N/A')
                                    ->state(fn ($record) => $record->guardian['local_guardian_relationship'] ?? $record->local_relationship ?? 'N/A'),
                                TextEntry::make('guardian.local_guardian_address')
                                    ->label('Local Address')
                                    ->default('N/A')
                                    ->state(fn ($record) => $record->guardian['local_guardian_address'] ?? $record->local_address ?? 'N/A')
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->collapsible(),

                // E. Medical Information
                Section::make('Medical & Health Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('medical_notes.blood_group')
                                    ->label('Blood Group')
                                    ->default('N/A')
                                    ->state(fn ($record) => $record->medical_notes['blood_group'] ?? $record->blood_group ?? 'N/A'),
                                TextEntry::make('medical_notes.allergies')
                                    ->label('Medical Conditions / Allergies')
                                    ->default('None')
                                    ->state(fn ($record) => $record->medical_notes['allergies'] ?? $record->medical_information ?? 'None')
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->collapsible(),
            ]);
    }
}
