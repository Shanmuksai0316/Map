<?php

namespace App\Filament\CampusManager\Resources\SportsEventResource\Pages;

use App\Filament\CampusManager\Resources\SportsEventResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewSportsEvent extends ViewRecord
{
    protected static string $resource = SportsEventResource::class;

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
                Infolists\Components\Section::make('Event Details')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label('Event Name')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight('bold'),
                        Infolists\Components\TextEntry::make('sport')
                            ->label('Sport')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state))),
                        Infolists\Components\TextEntry::make('description')
                            ->label('Description')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('venue')
                            ->label('Venue'),
                        Infolists\Components\TextEntry::make('hostel.name')
                            ->label('Hostel')
                            ->default('All Hostels'),
                    ]),

                Infolists\Components\Section::make('Schedule')
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('scheduled_at')
                            ->label('Start Time')
                            ->dateTime('d M Y H:i'),
                        Infolists\Components\TextEntry::make('end_time')
                            ->label('End Time')
                            ->dateTime('d M Y H:i'),
                        Infolists\Components\TextEntry::make('registration_deadline')
                            ->label('Registration Deadline')
                            ->dateTime('d M Y H:i'),
                    ]),

                Infolists\Components\Section::make('Registration')
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'upcoming' => 'warning',
                                'ongoing' => 'primary',
                                'completed' => 'success',
                                'cancelled' => 'danger',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('enrollments_count')
                            ->label('Enrolled')
                            ->suffix(fn ($record) => " / {$record->capacity}"),
                        Infolists\Components\TextEntry::make('capacity')
                            ->label('Maximum Capacity'),
                        Infolists\Components\TextEntry::make('requirements')
                            ->label('Requirements')
                            ->columnSpanFull()
                            ->default('No special requirements'),
                    ]),

                Infolists\Components\Section::make('System Information')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime('d M Y H:i'),
                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime('d M Y H:i'),
                    ]),
            ]);
    }
}

