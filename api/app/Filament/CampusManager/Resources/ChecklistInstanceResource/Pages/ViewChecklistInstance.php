<?php

namespace App\Filament\CampusManager\Resources\ChecklistInstanceResource\Pages;

use App\Domain\Checklists\Models\ChecklistInstance;
use App\Filament\CampusManager\Resources\ChecklistInstanceResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Support\Facades\Http;

class ViewChecklistInstance extends ViewRecord
{
    protected static string $resource = ChecklistInstanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(function (ChecklistInstance $record): bool {
                    if ($record->status !== 'Submitted') {
                        return false;
                    }

                    if (! in_array($record->review_status, ['Pending', 'SentBack'], true)) {
                        return false;
                    }

                    $user = auth()->user();

                    return $user && $user->can('approve', $record);
                })
                ->requiresConfirmation()
                ->modalHeading('Approve Checklist')
                ->modalDescription('Are you sure you want to approve this checklist?')
                ->action(function (ChecklistInstance $record): void {
                    $response = Http::withToken(auth()->user()->createToken('filament')->plainTextToken)
                        ->post(url("/api/v1/checklists/{$record->id}/approve"));

                    if ($response->successful()) {
                        $record->refresh();
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Checklist Approved')
                            ->send();

                        $this->refreshFormData(['review_status', 'reviewed_at', 'manager_user_id']);
                    } else {
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('Failed to approve')
                            ->body($response->json('message', 'An error occurred'))
                            ->send();
                    }
                }),
            Actions\Action::make('send_back')
                ->label('Send Back')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(function (ChecklistInstance $record): bool {
                    if ($record->status !== 'Submitted') {
                        return false;
                    }

                    if (! in_array($record->review_status, ['Pending', 'SentBack'], true)) {
                        return false;
                    }

                    $user = auth()->user();

                    return $user && $user->can('sendBack', $record);
                })
                ->form([
                    Forms\Components\Textarea::make('note')
                        ->label('Reason (optional)')
                        ->maxLength(500)
                        ->rows(3),
                ])
                ->action(function (ChecklistInstance $record, array $data): void {
                    $response = Http::withToken(auth()->user()->createToken('filament')->plainTextToken)
                        ->post(url("/api/v1/checklists/{$record->id}/send-back"), [
                            'note' => $data['note'] ?? null,
                        ]);

                    if ($response->successful()) {
                        $record->refresh();
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Checklist Sent Back')
                            ->send();

                        $this->refreshFormData(['review_status', 'reviewed_at', 'manager_user_id', 'manager_note']);
                    } else {
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('Failed to send back')
                            ->body($response->json('message', 'An error occurred'))
                            ->send();
                    }
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Checklist Details')
                    ->schema([
                        Infolists\Components\Group::make([
                            Infolists\Components\TextEntry::make('status')
                                ->badge()
                                ->color(fn (string $state): string => match ($state) {
                                    'Pending' => 'gray',
                                    'Submitted' => 'info',
                                    default => 'gray',
                                }),
                            Infolists\Components\TextEntry::make('review_status')
                                ->label('Review Status')
                                ->badge()
                                ->color(fn (string $state): string => match ($state) {
                                    'Pending' => 'warning',
                                    'SentBack' => 'danger',
                                    'Approved' => 'success',
                                    default => 'gray',
                                }),
                        ])->columns(2),
                        Infolists\Components\Group::make([
                            Infolists\Components\TextEntry::make('date')
                                ->date(),
                            Infolists\Components\TextEntry::make('role'),
                            Infolists\Components\TextEntry::make('assignee.name')
                                ->label('Assignee'),
                        ])->columns(3),
                        Infolists\Components\Group::make([
                            Infolists\Components\TextEntry::make('completed_tasks')
                                ->label('Progress')
                                ->formatStateUsing(fn (ChecklistInstance $record): string =>
                                    "{$record->completed_tasks}/{$record->total_tasks} tasks completed"
                                ),
                            Infolists\Components\TextEntry::make('submitted_at')
                                ->dateTime(),
                        ])->columns(2),
                    ]),
                Infolists\Components\Section::make('Review Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('manager.name')
                            ->label('Reviewed By')
                            ->default('—'),
                        Infolists\Components\TextEntry::make('reviewed_at')
                            ->dateTime()
                            ->default('—'),
                        Infolists\Components\TextEntry::make('manager_note')
                            ->label('Manager Note')
                            ->default('—')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (ChecklistInstance $record): bool =>
                        $record->review_status !== 'Pending' || $record->manager_note !== null
                    ),
                Infolists\Components\Section::make('Checklist Items')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('items')
                            ->schema([
                                Infolists\Components\TextEntry::make('label')
                                    ->weight(FontWeight::SemiBold),
                                Infolists\Components\TextEntry::make('state')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'Done' => 'success',
                                        'NA' => 'gray',
                                        'Pending' => 'warning',
                                        default => 'gray',
                                    }),
                                Infolists\Components\TextEntry::make('comment')
                                    ->default('—'),
                            ])
                            ->columns(3)
                            ->contained(false),
                    ]),
            ]);
    }
}
