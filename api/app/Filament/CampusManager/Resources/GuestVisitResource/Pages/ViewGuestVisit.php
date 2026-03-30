<?php

namespace App\Filament\CampusManager\Resources\GuestVisitResource\Pages;

use App\Filament\CampusManager\Resources\GuestVisitResource;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewGuestVisit extends ViewRecord
{
    protected static string $resource = GuestVisitResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Visitor Details')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')->label('Guest Name'),
                        TextEntry::make('phone')->label('Phone'),
                        TextEntry::make('whom_to_meet')->label('Whom to Meet'),
                        TextEntry::make('visit_date')->label('Visit Date')->date('Y-m-d'),
                    ]),
                Section::make('Student Information')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('student.user.name')->label('Student Name'),
                        TextEntry::make('hostel.name')->label('Hostel'),
                    ]),
                Section::make('Status Information')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('status')->badge()
                            ->colors([
                                'warning' => 'pre_registered',
                                'success' => 'allowed',
                                'danger' => 'denied',
                            ]),
                        TextEntry::make('created_at')->label('Created At')->dateTime(),
                        TextEntry::make('allowedBy.name')->label('Allowed By')->visible(fn ($record) => $record->status === 'allowed'),
                        TextEntry::make('allowed_at')->label('Allowed At')->dateTime()->visible(fn ($record) => $record->status === 'allowed'),
                        TextEntry::make('deniedBy.name')->label('Denied By')->visible(fn ($record) => $record->status === 'denied'),
                        TextEntry::make('denied_at')->label('Denied At')->dateTime()->visible(fn ($record) => $record->status === 'denied'),
                    ]),
            ]);
    }
}

