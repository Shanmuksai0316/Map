<?php

namespace App\Filament\CampusManager\Resources\NoticeResource\Pages;

use App\Filament\CampusManager\Resources\NoticeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNotice extends CreateRecord
{
    protected static string $resource = NoticeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set tenant_id from authenticated user or tenancy context
        if (!isset($data['tenant_id'])) {
            $data['tenant_id'] = auth()->user()?->tenant_id ?? tenancy()?->tenant?->id;
        }

        // Set default status if not provided
        if (!isset($data['status'])) {
            $data['status'] = 'draft';
        }

        // If publish_at is set and status is draft, change to scheduled
        if (isset($data['publish_at']) && $data['status'] === 'draft') {
            $data['status'] = 'scheduled';
        }

        // If no publish_at and status is published, set it to now
        if ($data['status'] === 'published' && !isset($data['publish_at'])) {
            $data['publish_at'] = now();
            $data['published_at'] = now();
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Notice created successfully';
    }
}

