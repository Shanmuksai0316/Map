<?php

namespace App\Filament\CollegeMgmt\Resources\AttendanceSessionResource\Pages;

use App\Filament\CollegeMgmt\Resources\AttendanceSessionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceSessions extends ListRecords
{
    protected static string $resource = AttendanceSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action - read-only
        ];
    }
}
