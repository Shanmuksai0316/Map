<?php

namespace App\Filament\Resources\Admin\StudentViewResource\Pages;

use App\Filament\Resources\Admin\StudentViewResource;
use Filament\Resources\Pages\ViewRecord;

class ViewStudentView extends ViewRecord
{
    protected static string $resource = StudentViewResource::class;
    
    protected function getHeaderActions(): array
    {
        return [
            // No edit/delete actions - read-only
        ];
    }
}

