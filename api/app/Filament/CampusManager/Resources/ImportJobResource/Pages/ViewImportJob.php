<?php

namespace App\Filament\CampusManager\Resources\ImportJobResource\Pages;

use App\Filament\CampusManager\Resources\ImportJobResource;
use App\Services\Imports\RoomAllotmentImportService;
use App\Services\Imports\StudentImportService;
use App\Support\Feature;
use Filament\Actions;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewImportJob extends ViewRecord
{
    protected static string $resource = ImportJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('commit')
                ->label('Commit Import')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status === 'DryRunOK')
                ->action(fn () => $this->commitImport()),
            // Student activation removed from import page.
            // All students must be activated from Student Management > Unassigned Students.
            Actions\Action::make('download')
                ->label('Download CSV (Coming Soon)')
                ->disabled()
                ->visible(fn (): bool => Feature::isEnabled('imports')),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Grid::make(3)
                ->schema([
                    Section::make('Summary')
                        ->columnSpan(3)
                        ->schema([
                            TextEntry::make('kind')
                                ->badge()
                                ->label('Type')
                                ->formatStateUsing(fn (string $state): string => match ($state) {
                                    'students' => 'Students',
                                    'room_allotments' => 'Room Allotments',
                                    default => ucfirst(str_replace('_', ' ', $state)),
                                }),
                            TextEntry::make('status')
                                ->badge()
                                ->label('Status')
                                ->formatStateUsing(fn (string $state): string => match ($state) {
                                    'DryRun' => 'Dry Run',
                                    'DryRunErrors' => 'Dry Run Errors',
                                    'DryRunOK' => 'Dry Run OK',
                                    'Queued' => 'Queued',
                                    'Processing' => 'Processing',
                                    'Completed' => 'Completed',
                                    'Failed' => 'Failed',
                                    default => $state,
                                })
                                ->color(fn (string $state): string => match ($state) {
                                    'DryRun' => 'gray',
                                    'DryRunErrors' => 'warning',
                                    'Queued', 'Processing' => 'info',
                                    'DryRunOK', 'Completed' => 'success',
                                    'Failed' => 'danger',
                                    default => 'gray',
                                }),
                            TextEntry::make('created_at')->label('Created At')->dateTime('d M Y H:i'),
                            TextEntry::make('committed_at')->label('Committed At')->dateTime('d M Y H:i')->placeholder('-'),
                            TextEntry::make('meta.original_name')->label('Original Filename')->default('-'),
                        ]),
                    Section::make('Counts')
                        ->columnSpan(3)
                        ->columns(3)
                        ->schema([
                            TextEntry::make('total_rows')->label('Total Rows'),
                            TextEntry::make('processed_rows')->label('Processed Rows'),
                            TextEntry::make('error_rows')->label('Error Rows'),
                            TextEntry::make('inserted_rows')->label('Inserted Rows'),
                            TextEntry::make('updated_rows')->label('Updated Rows'),
                        ]),
                    Section::make('Imported Students')
                        ->columnSpan(3)
                        ->visible(fn (): bool => $this->record->kind === 'students')
                        ->schema([
                            TextEntry::make('meta.imported_students_preview_note')
                                ->label('Details')
                                ->default(fn (): string => $this->resolveImportedStudentDetailsMessage()),
                            RepeatableEntry::make('meta.imported_students')
                                ->label('Student Preview')
                                ->visible(fn (): bool => is_array($this->record->meta['imported_students'] ?? null) && count($this->record->meta['imported_students']) > 0)
                                ->schema([
                                    TextEntry::make('student_name')->label('Name'),
                                    TextEntry::make('student_uid')->label('Student UID')->placeholder('-'),
                                    TextEntry::make('map_student_id')->label('MAP ID')->placeholder('-'),
                                    TextEntry::make('erp_number')->label('ERP Number')->placeholder('-'),
                                    TextEntry::make('email')->label('Email')->placeholder('-'),
                                ])
                                ->columns(2),
                        ]),
                ]),
        ]);
    }

    protected function commitImport(): void
    {
        $service = match ($this->record->kind) {
            'room_allotments' => app(RoomAllotmentImportService::class),
            default => app(StudentImportService::class),
        };

        $service->commit($this->record);
        $this->record->refresh();

        Notification::make()
            ->title('Import queued')
            ->success()
            ->body('Import job has been queued for processing.')
            ->send();
    }

    protected function resolveImportedStudentDetailsMessage(): string
    {
        $meta = $this->record->meta ?? [];

        if (! empty($meta['imported_students_preview_note']) && is_string($meta['imported_students_preview_note'])) {
            return $meta['imported_students_preview_note'];
        }

        if ($this->record->status === 'Completed') {
            if (($this->record->inserted_rows ?? 0) > 0) {
                return 'No student-level preview is available for this import. Re-run import to generate detailed student list.';
            }

            return 'No students were imported in this job.';
        }

        return 'Import is still processing. Student details will appear after completion.';
    }
}
