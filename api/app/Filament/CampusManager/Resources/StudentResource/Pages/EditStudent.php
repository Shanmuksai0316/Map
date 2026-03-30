<?php

namespace App\Filament\CampusManager\Resources\StudentResource\Pages;

use App\Filament\CampusManager\Resources\StudentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStudent extends EditRecord
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load user data into form
        if (isset($data['user_id'])) {
            $user = \App\Models\User::find($data['user_id']);
            if ($user) {
                $data['user'] = [
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'gender' => $user->gender,
                    'dob' => $user->dob?->format('Y-m-d'),
                ];
            }
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Update user data
        if (isset($data['user']) && isset($data['user_id'])) {
            $user = \App\Models\User::find($data['user_id']);
            if ($user) {
                $user->update([
                    'name' => $data['user']['name'] ?? $user->name,
                    'email' => $data['user']['email'] ?? $user->email,
                    'phone' => $data['user']['phone'] ?? $user->phone,
                    'gender' => $data['user']['gender'] ?? $user->gender,
                    'dob' => $data['user']['dob'] ?? $user->dob,
                ]);
            }
        }

        // Remove user data from student data
        unset($data['user']);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

