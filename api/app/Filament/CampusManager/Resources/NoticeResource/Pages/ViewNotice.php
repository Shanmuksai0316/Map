<?php

namespace App\Filament\CampusManager\Resources\NoticeResource\Pages;

use App\Filament\CampusManager\Resources\NoticeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewNotice extends ViewRecord
{
    protected static string $resource = NoticeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('publish')
                ->label('Publish Now')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->publish();
                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Notice Published')
                        ->body('The notice has been published and notifications are being sent.')
                        ->send();
                    
                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                })
                ->visible(fn () => $this->record->status !== 'published'),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Notice Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('title')
                            ->label('Title')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight('bold'),
                        Infolists\Components\TextEntry::make('body')
                            ->label('Content')
                            ->html()
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Targeting & Delivery')
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('audience')
                            ->label('Audience')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'students' => 'primary',
                                'staff' => 'warning',
                                'both' => 'success',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                        Infolists\Components\TextEntry::make('hostel.name')
                            ->label('Hostel')
                            ->default('All Hostels'),
                        Infolists\Components\TextEntry::make('campus.name')
                            ->label('Campus')
                            ->default('All Campuses'),
                        Infolists\Components\IconEntry::make('push_enabled')
                            ->label('Push Notification')
                            ->boolean()
                            ->getStateUsing(fn ($record) => in_array('push', $record->channels ?? [])),
                        Infolists\Components\IconEntry::make('email_enabled')
                            ->label('Email Notification')
                            ->boolean()
                            ->getStateUsing(fn ($record) => in_array('email', $record->channels ?? [])),
                    ]),

                Infolists\Components\Section::make('Schedule & Status')
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'draft' => 'gray',
                                'scheduled' => 'warning',
                                'published' => 'success',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('publish_at')
                            ->label('Publish At')
                            ->dateTime('d M Y H:i')
                            ->default('Immediate'),
                        Infolists\Components\TextEntry::make('expires_at')
                            ->label('Expires At')
                            ->dateTime('d M Y H:i')
                            ->default('Never'),
                        Infolists\Components\TextEntry::make('published_at')
                            ->label('Published At')
                            ->dateTime('d M Y H:i')
                            ->visible(fn ($record) => $record->status === 'published'),
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

