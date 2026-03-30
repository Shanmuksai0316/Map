<?php

namespace App\Filament\CampusManager\Resources\ImportJobResource\Pages;

use App\Filament\CampusManager\Resources\ImportJobResource;
use App\Services\Imports\RoomAllotmentImportService;
use App\Services\Imports\StudentImportService;
use App\Support\Imports\StudentImportColumns;
use Filament\Forms;
use Filament\Resources\Pages\Page as ResourcePage;
use Filament\Notifications\Notification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Spatie\SimpleExcel\SimpleExcelWriter;

class StartImport extends ResourcePage implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?string $navigationLabel = 'Upload CSV';

    protected static ?string $navigationGroup = 'Imports';

    protected static ?int $navigationSort = 10;

    protected static string $resource = ImportJobResource::class;

    protected static string $view = 'filament.campus-manager.pages.imports.start-import';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'kind' => 'students',
        ]);
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->makeForm()
                ->schema([
                    Forms\Components\Select::make('kind')
                        ->label('Import type')
                        ->options([
                            'students' => 'Students',
                            'room_allotments' => 'Room Allotments',
                        ])
                        ->required(),
                    Forms\Components\FileUpload::make('file')
                        ->label('Import file (XLSX or CSV)')
                        // Rely on server-side validation instead of strict MIME checks,
                        // since different browsers often send inconsistent content types.
                        ->helperText('Max 5 MB; upload the provided XLSX template or a CSV export.')
                        ->required()
                        ->disk('local')
                        ->directory('imports/uploads')
                        ->visibility('private'),
                ])
                ->statePath('data'),
        ];
    }

    public function submit(): void
    {
        $state = $this->form->getRawState();
        $kind = Arr::get($state, 'kind', 'students');

        $fileState = Arr::get($state, 'file', Arr::get($this->data, 'file'));
        $uploadedFile = $this->findUploadedFile($fileState)
            ?? $this->findUploadedFile(request()->allFiles());
        $temporaryPath = null;

        /** @var string|null $path */
        $path = null;

        if (is_string($fileState)) {
            $path = $fileState;
        } elseif (is_array($fileState)) {
            $first = Arr::first($fileState);

            if (is_string($first)) {
                $path = $first;
            }
        }

        $temporaryPath ??= $this->findTemporaryUploadPath($fileState);

        if (! $uploadedFile) {
            $temporaryPath = $temporaryPath ?: $this->findTemporaryUploadPath($fileState);
            $hasStoredPath = $path && Storage::disk('local')->exists($path);
            $hasTemporaryPath = is_string($temporaryPath) && $temporaryPath !== '';

            if (! $hasStoredPath && ! $hasTemporaryPath) {
                Notification::make()
                    ->title('Please wait for upload to finish and try again.')
                    ->body('The file was not available at submit time.')
                    ->danger()
                    ->send();

                return;
            }

            $fullPath = $hasStoredPath ? Storage::disk('local')->path($path) : $temporaryPath;

            $uploadedFile = new UploadedFile(
                $fullPath,
                basename($fullPath),
                @mime_content_type($fullPath) ?: 'application/octet-stream',
                null,
                true
            );
        }

        $service = match ($kind) {
            'room_allotments' => app(RoomAllotmentImportService::class),
            default => app(StudentImportService::class),
        };

        // First run the same validations as before (dry run)…
        $job = $service->dryRun(['file' => $uploadedFile]);
        $job->refresh();

        // If there are validation errors, redirect to the job detail so the Campus
        // Manager can see exactly which rows failed.
        if ($job->status === 'DryRunErrors') {
            Notification::make()
                ->title('Import has validation errors')
                ->danger()
                ->body('Please review the highlighted rows and fix the file before importing again.')
                ->send();

            $this->redirect(ImportJobResource::getUrl('view', ['record' => $job]));

            return;
        }

        // If validation passed, immediately queue the real import.
        $service->commit($job);

        Notification::make()
            ->title('Import started')
            ->success()
            ->body('Student import has been queued. Students will appear in Unassigned Students after processing.')
            ->send();

        $this->redirect(ImportJobResource::getUrl('view', ['record' => $job]));
    }

    public function downloadTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $path = 'imports/templates/student_import_template.xlsx';

        if (! Storage::disk('local')->exists($path)) {
            $this->generateTemplate($path);
        }

        return Storage::disk('local')->download($path, 'student_import_template.xlsx');
    }

    public function downloadGoogleFormTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $path = 'imports/templates/student_import_google_form_template.csv';

        if (! Storage::disk('local')->exists($path)) {
            $this->generateGoogleFormTemplate($path);
        }

        return Storage::disk('local')->download($path, 'student_import_google_form_template.csv');
    }

    protected function findTemporaryUploadPath(mixed $value): ?string
    {
        if (is_string($value)) {
            $candidate = str_replace('\/', '/', $value);

            if (preg_match('#/tmp/php[^\s"\\\\]+#', $candidate, $matches) === 1) {
                return $matches[0];
            }

            return null;
        }

        if (! is_array($value)) {
            return null;
        }

        foreach ($value as $nested) {
            $found = $this->findTemporaryUploadPath($nested);
            if ($found) {
                return $found;
            }
        }

        return null;
    }

    protected function findUploadedFile(mixed $value): ?UploadedFile
    {
        if ($value instanceof UploadedFile) {
            return $value;
        }

        if (! is_array($value)) {
            return null;
        }

        foreach ($value as $nested) {
            $found = $this->findUploadedFile($nested);
            if ($found) {
                return $found;
            }
        }

        return null;
    }

    protected function generateTemplate(string $relativePath): void
    {
        $absolutePath = Storage::disk('local')->path($relativePath);

        // Ensure directory exists
        Storage::disk('local')->makeDirectory(dirname($relativePath));

        $headers = [
            'student_uid',
            'full_name',
            'email_address',
            'mobile_number',
            'gender',
            'date_of_birth',
            'map_id',
            'erp_number',
            'department',
            'year_of_study',
            'father_name',
            'father_mobile_number',
            'mother_name',
            'mother_mobile_number',
            'local_guardian_name',
            'local_guardian_contact',
            'local_relationship',
            'local_address',
            'blood_group',
            'medical_information',
        ];

        $sampleRow = [
            'STD-2025-0001',               // student_uid (optional)
            'Alex Johnson',                // full_name
            'alex.johnson@example.com',    // email_address
            '+919876543210',               // mobile_number
            'male',                        // gender
            '2005-03-14',                  // date_of_birth (YYYY-MM-DD)
            'MAP-001',                     // map_id (optional)
            'ERP-001',                     // erp_number (optional)
            'Computer Science',            // department (optional)
            '1',                           // year_of_study (1-5, optional)
            'John Johnson',                // father_name (optional)
            '+919812345678',               // father_mobile_number (optional)
            'Jane Johnson',                // mother_name (optional)
            '+919823456789',               // mother_mobile_number (optional)
            'Sam Guardian',                // local_guardian_name (optional)
            '+919834567890',               // local_guardian_contact (optional)
            'Uncle',                       // local_relationship (optional)
            '123, MG Road, City',          // local_address (optional)
            'A+',                          // blood_group (optional)
            'Peanut allergy',              // medical_information (optional)
        ];

        SimpleExcelWriter::create($absolutePath)
            ->addHeader($headers)
            ->addRow($sampleRow)
            ->close();
    }

    protected function generateGoogleFormTemplate(string $relativePath): void
    {
        $absolutePath = Storage::disk('local')->path($relativePath);

        Storage::disk('local')->makeDirectory(dirname($relativePath));

        $headers = StudentImportColumns::googleFormResponseHeaders();

        $sampleRow = [
            now()->format('Y-m-d H:i:s'),       // Timestamp (Google Forms adds this automatically)
            'Alex Johnson',                     // Full Name
            'alex.johnson@example.com',         // Email Address
            '+919876543210',                    // Mobile Number
            'male',                             // Gender
            '2005-03-14',                       // Date of Birth
            'STD-2025-0001',                    // Student UID (optional)
            'MAP-001',                          // MAP ID (optional)
            'ERP-001',                          // ERP Number (optional)
            'Computer Science',                 // Department (optional)
            '1',                                // Year of Study (1-5, optional)
            'John Johnson',                     // Father Name (optional)
            '+919812345678',                    // Father Mobile Number (optional)
            'Jane Johnson',                     // Mother Name (optional)
            '+919823456789',                    // Mother Mobile Number (optional)
            'Sam Guardian',                     // Local Guardian Name (optional)
            '+919834567890',                    // Local Guardian Contact (optional)
            'Uncle',                            // Local Relationship (optional)
            '123, MG Road, City',               // Local Address (optional)
            'A+',                               // Blood Group (optional)
            'Peanut allergy',                   // Medical Information (optional)
        ];

        SimpleExcelWriter::create($absolutePath)
            ->addHeader($headers)
            ->addRow($sampleRow)
            ->close();
    }
}
