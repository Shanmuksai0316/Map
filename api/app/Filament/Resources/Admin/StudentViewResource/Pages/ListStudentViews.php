<?php

namespace App\Filament\Resources\Admin\StudentViewResource\Pages;

use App\Filament\Resources\Admin\StudentViewResource;
use Filament\Resources\Pages\ListRecords;

class ListStudentViews extends ListRecords
{
    protected static string $resource = StudentViewResource::class;
    
    protected function getHeaderActions(): array
    {
        return [
            // No create action - read-only
        ];
    }
}

