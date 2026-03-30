<?php

namespace App\Filament\Rector\Resources\OutPassResource\Pages;

use App\Enums\OutPassStatus;
use App\Filament\Rector\Resources\OutPassResource;
use Filament\Actions;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class ViewOutPass extends ViewRecord
{
    protected static string $resource = OutPassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Approve Out-Pass')
                ->form([
                    Textarea::make('note')
                        ->label('Approval Note')
                        ->maxLength(500)
                        ->required(),
                ])
                ->visible(fn () => $this->record->status === OutPassStatus::PENDING)
                ->action(function (array $data): void {
                    $user = Auth::user();
                    $record = $this->record;

                    // Check for 24-hour expiry
                    $expiryTime = $record->requested_at->copy()->addHours(24);
                    if (now()->isAfter($expiryTime)) {
                        $previous = $record->status;
                        $record->forceFill([
                            'status' => OutPassStatus::EXPIRED,
                            'decided_at' => now(),
                            'note' => 'Automatically expired after 24 hours',
                            'decision_by' => null,
                        ])->save();
                        $record->recordHistory($previous, OutPassStatus::EXPIRED, 'Automatically expired');
                        
                        Notification::make()
                            ->warning()
                            ->title('Out-Pass Expired')
                            ->body('This out-pass has expired and cannot be approved.')
                            ->send();
                        return;
                    }

                    $previous = $record->status;
                    $record->forceFill([
                        'status' => OutPassStatus::APPROVED,
                        'decided_at' => now(),
                        'note' => $data['note'] ?? 'Approved by Rector',
                        'decision_by' => $user->id,
                    ])->save();
                    $record->recordHistory($previous, OutPassStatus::APPROVED, $data['note'] ?? 'Approved by Rector', actorId: $user->id);
                    
                    Notification::make()
                        ->success()
                        ->title('Out-Pass Approved')
                        ->body("Out-pass for {$record->student->user->name} has been approved.")
                        ->send();
                    
                    $this->redirect(static::getResource()::getUrl('index'));
                }),
            Actions\Action::make('decline')
                ->label('Decline')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Decline Out-Pass')
                ->form([
                    Textarea::make('note')
                        ->label('Decline Reason')
                        ->maxLength(500)
                        ->required(),
                ])
                ->visible(fn () => $this->record->status === OutPassStatus::PENDING)
                ->action(function (array $data): void {
                    $user = Auth::user();
                    $record = $this->record;
                    $previous = $record->status;
                    $record->forceFill([
                        'status' => OutPassStatus::DECLINED,
                        'decided_at' => now(),
                        'note' => $data['note'],
                        'decision_by' => $user->id,
                    ])->save();
                    $record->recordHistory($previous, OutPassStatus::DECLINED, $data['note'], actorId: $user->id);
                    
                    Notification::make()
                        ->success()
                        ->title('Out-Pass Declined')
                        ->body("Out-pass for {$record->student->user->name} has been declined.")
                        ->send();
                    
                    $this->redirect(static::getResource()::getUrl('index'));
                }),
        ];
    }
}
