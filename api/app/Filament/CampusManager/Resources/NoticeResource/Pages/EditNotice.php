<?php

namespace App\Filament\CampusManager\Resources\NoticeResource\Pages;

use App\Filament\CampusManager\Resources\NoticeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNotice extends EditRecord
{
    protected static string $resource = NoticeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
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
                })
                ->visible(fn () => $this->record->status !== 'published'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // If publish_at is set and status is draft, change to scheduled
        if (isset($data['publish_at']) && $data['status'] === 'draft') {
            $data['status'] = 'scheduled';
        }

        // If no publish_at and status is published, set it to now
        if ($data['status'] === 'published' && !isset($data['publish_at'])) {
            $data['publish_at'] = now();
            if (!isset($data['published_at'])) {
                $data['published_at'] = now();
            }
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Notice updated successfully';
    }
}

