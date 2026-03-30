<?php

namespace App\Filament\CampusManager\Resources\StudentResource\Pages;

use App\Filament\CampusManager\Resources\StudentResource;
use App\Models\Student;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Creates a new student record along with an associated User record.
 *
 * Key behaviors:
 * - Validates phone and email uniqueness within the current tenant (not globally).
 * - Auto-generates student_uid and map_student_id if not provided by the form.
 * - Default password for new student accounts is "Student@123".
 * - Maps form field names (full_name, mobile_number, email_address) to User model columns.
 * - Filters out any form fields that don't exist in the students table before insert.
 */
class CreateStudent extends CreateRecord
{
    protected static string $resource = StudentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Get tenant_id from authenticated user
        $tenantId = auth()->user()?->tenant_id ?? (tenancy()->tenant?->id ?? null);

        if (!$tenantId) {
            throw new \Exception('Tenant ID is required to create a student.');
        }

        // Map form field names to user fields
        $name = $data['full_name'] ?? '';
        $phone = $data['mobile_number'] ?? null;
        $email = $data['email_address'] ?? null;
        $gender = $data['gender'] ?? null;
        $dob = $data['date_of_birth'] ?? null;

        // Validate required fields
        if (empty($name)) {
            throw ValidationException::withMessages([
                'full_name' => 'Full Name is required.',
            ]);
        }

        if (empty($phone)) {
            throw ValidationException::withMessages([
                'mobile_number' => 'Mobile Number is required.',
            ]);
        }

        // Check uniqueness
        $this->assertUniqueUserFields([
            'phone' => $phone,
            'email' => $email,
        ]);

        // Create user - email is optional
        $userData = [
            'name' => $name,
            'phone' => $phone,
            'gender' => $gender,
            'dob' => $dob,
            'password' => Hash::make('Student@123'),
            'kind' => 'student',
            'is_active' => false,
            'tenant_id' => $tenantId,
        ];

        // Only add email if provided
        if (!empty($email)) {
            $userData['email'] = $email;
        }

        $user = User::create($userData);

        // Assign student role
        $user->assignRole('Student');

        // Clean up form data - remove user-related fields
        unset($data['full_name'], $data['mobile_number'], $data['email_address'], $data['gender'], $data['date_of_birth']);

        // Set student record fields
        $data['user_id'] = $user->id;
        $data['tenant_id'] = $tenantId;

        // Generate student_uid if not provided
        if (blank($data['student_uid'] ?? null)) {
            $data['student_uid'] = Student::generateStudentUid($tenantId);
        } else {
            // Check uniqueness with tenant_id filtering
            $uidQuery = Student::query()->where('student_uid', $data['student_uid']);
            if ($tenantId) {
                $uidQuery->where('tenant_id', $tenantId);
            }
            if ($uidQuery->exists()) {
                throw ValidationException::withMessages([
                    'student_uid' => 'This Student UID is already registered.',
                ]);
            }
        }

        // Generate map_student_id if not provided
        if (blank($data['map_student_id'] ?? null)) {
            if (!empty($data['map_id'])) {
                // Check if map_id is already used with tenant_id filtering
                $mapIdQuery = Student::query()->where('map_student_id', $data['map_id']);
                if ($tenantId) {
                    $mapIdQuery->where('tenant_id', $tenantId);
                }
                if ($mapIdQuery->exists()) {
                    throw ValidationException::withMessages([
                        'map_id' => 'This MAP ID is already registered.',
                    ]);
                }
                $data['map_student_id'] = $data['map_id'];
            } else {
                $data['map_student_id'] = Student::generateMapStudentId($tenantId);
            }
        } else {
            // Check uniqueness with tenant_id filtering
            $mapIdQuery = Student::query()->where('map_student_id', $data['map_student_id']);
            if ($tenantId) {
                $mapIdQuery->where('tenant_id', $tenantId);
            }
            if ($mapIdQuery->exists()) {
                throw ValidationException::withMessages([
                    'map_student_id' => 'This MAP Student ID is already registered.',
                ]);
            }
        }
        unset($data['map_id']);

        // Map hostel_id to assigned_hostel if needed
        if (isset($data['hostel_id']) && !isset($data['assigned_hostel'])) {
            // Keep hostel_id for relationship
        }

        // Filter out fields that don't exist in the students table
        $allowedFields = [
            'user_id',
            'tenant_id',
            'hostel_id',
            'map_student_id',
            'student_uid',
            'roll_no',
            'program',
            'year_of_study',
            'admission_year',
            'hostel_fee_paid',
            'payment_mode',
            'payment_amount',
            'payment_date',
            'payment_reference',
            'payment_notes',
            'guardian',
            'medical_notes',
            'correspondence_address',
        ];

        $filteredData = array_intersect_key($data, array_flip($allowedFields));

        // Handle guardian, medical_notes, correspondence_address
        $filteredData['guardian'] = $this->cleanNullableArray($data['guardian'] ?? null);
        $filteredData['medical_notes'] = $this->cleanNullableArray($data['medical_notes'] ?? null);
        $filteredData['correspondence_address'] = $this->cleanNullableArray($data['correspondence_address'] ?? null);

        // Map 'erp_number' from form to 'roll_no'
        if (!empty($data['erp_number']) && empty($filteredData['roll_no'])) {
            $filteredData['roll_no'] = $data['erp_number'];
        }

        // Map 'department' from form to 'program'
        if (!empty($data['department']) && empty($filteredData['program'])) {
            $filteredData['program'] = $data['department'];
        }

        return $filteredData;
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (\Throwable $e) {
            \Log::error('CreateStudent: Error creating student record', [
                'error' => $e->getMessage(),
                'data_keys' => array_keys($data),
            ]);

            if ($e instanceof ValidationException) {
                throw $e;
            }

            throw ValidationException::withMessages([
                'form' => 'Failed to create student: ' . $e->getMessage(),
            ]);
        }
    }

    protected function afterCreate(): void
    {
        \Filament\Notifications\Notification::make()
            ->title('Student created successfully')
            ->success()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function assertUniqueUserFields(array $userData): void
    {
        $messages = [];
        $tenantId = auth()->user()?->tenant_id ?? (tenancy()->tenant?->id ?? null);

        if (filled($userData['phone'] ?? null)) {
            $phoneQuery = User::query()->where('phone', $userData['phone']);
            if ($tenantId) {
                $phoneQuery->where('tenant_id', $tenantId);
            }
            if ($phoneQuery->exists()) {
                $messages['user.phone'] = 'This phone number is already registered.';
            }
        }

        if (filled($userData['email'] ?? null)) {
            $emailQuery = User::query()->where('email', $userData['email']);
            if ($tenantId) {
                $emailQuery->where('tenant_id', $tenantId);
            }
            if ($emailQuery->exists()) {
                $messages['user.email'] = 'This email address is already registered.';
            }
        }

        if ($messages) {
            throw ValidationException::withMessages($messages);
        }
    }

    protected function cleanNullableArray(mixed $values): ?array
    {
        if (! is_array($values)) {
            return null;
        }

        $cleaned = collect($values)
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->filter(fn ($value) => ! (is_null($value) || $value === ''))
            ->toArray();

        return empty($cleaned) ? null : $cleaned;
    }
}
