<?php

namespace App\Filament\CampusManager\Resources\AttendanceSessionResource\Pages;

use App\Filament\CampusManager\Resources\AttendanceSessionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceSessions extends ListRecords
{
    protected static string $resource = AttendanceSessionResource::class;

    protected static ?string $title = 'Attendance Sessions';

    protected function getHeaderActions(): array
    {
        return [
            // No create action - sessions are created by jobs
        ];
    }

    public function getHeading(): string
    {
        return 'Attendance Sessions';
    }
}
