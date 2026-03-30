<?php

namespace App\Filament\CollegeMgmt\Resources\AttendanceSessionResource\Pages;

use App\Filament\CollegeMgmt\Resources\AttendanceSessionResource;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewAttendanceSession extends ViewRecord
{
    protected static string $resource = AttendanceSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Back to Sessions')
                ->icon('heroicon-o-arrow-left')
                ->url(fn (): string => static::getResource()::getUrl('index')),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Session Details')
                    ->schema([
                        TextEntry::make('hostel.name')
                            ->label('Hostel'),
                        TextEntry::make('kind')
                            ->label('Type')
                            ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state))),
                        TextEntry::make('scheduled_at')
                            ->label('Scheduled At')
                            ->dateTime(),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'open' => 'success',
                                'scheduled' => 'warning',
                                'closed' => 'gray',
                                default => 'gray',
                            }),
                        TextEntry::make('progress_summary')
                            ->label('Progress Summary')
                            ->state(function ($record): string {
                                $present = $record->metadata['present_count'] ?? 0;
                                $absent = $record->metadata['absent_count'] ?? 0;
                                $leave = $record->metadata['leave_count'] ?? 0;
                                $unmarked = $record->metadata['unmarked_count'] ?? 0;
                                $total = $present + $absent + $leave + $unmarked;
                                
                                return "Total: {$total} | Present: {$present} | Absent: {$absent} | Leave: {$leave} | Unmarked: {$unmarked}";
                            }),
                    ])
                    ->columns(2),
            ]);
    }
}
