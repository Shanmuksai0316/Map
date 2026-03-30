<?php

namespace App\Filament\Resources\Admin\TenantResource\Pages;

use App\Filament\Resources\Admin\TenantResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Merge existing settings with form data so nested keys (branding.logo_path, contact, address)
     * are never lost when saving. Form values take precedence.
     * Normalize logo_path so it is always a string (Filament/Livewire may send array).
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['settings']) && $this->record) {
            $existing = $this->record->settings ?? [];
            $data['settings'] = array_replace_recursive($existing, $data['settings']);

            // Ensure branding.logo_path is a string (Filament FileUpload may dehydrate as array in some cases)
            $logoPath = data_get($data['settings'], 'branding.logo_path');
            if ($logoPath !== null) {
                $pathString = is_array($logoPath) ? ($logoPath[0] ?? $logoPath['path'] ?? null) : $logoPath;
                if (is_string($pathString) && $pathString !== '') {
                    data_set($data['settings'], 'branding.logo_path', $pathString);
                }
            }
        }

        return $data;
    }
}
