<?php

namespace App\Filament\Pages\Admin;


use App\Models\Hostel;
use App\Models\Campus;
use App\Models\Room;
use App\Models\RoomBed;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Amenity;
use App\Services\Onboarding\PreflightService;
use App\Services\StaffAssignmentService;
use App\Services\IdempotencyService;
use App\Events\TenantActivated;
use Filament\Forms;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\Access\AuthorizationException;

class TenantOnboardingWizard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-rocket-launch';
    protected static string $view = 'filament.pages.admin.tenant-onboarding-wizard';
    protected static ?string $navigationGroup = 'Tenant Management';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'New Tenant Onboarding';

    public ?Tenant $tenant = null;
    public array $data = [];

    protected ?PreflightService $preflightService = null;
    protected ?StaffAssignmentService $staffAssignmentService = null;

    protected function getPreflightService(): ?PreflightService
    {
        if ($this->preflightService instanceof PreflightService) {
            return $this->preflightService;
        }

        try {
            $this->preflightService = app(PreflightService::class);
        } catch (\Throwable $e) {
            Log::warning('TenantOnboardingWizard: Failed to resolve PreflightService from container', [
                'error' => $e->getMessage(),
            ]);
            $this->preflightService = null;
        }

        return $this->preflightService;
    }

    protected function authorizeSuperAdmin(): void
    {
        if (! Auth::user()?->hasRole('Super Admin')) {
            throw new AuthorizationException();
        }
    }

    /**
     * Create initial users (Rector and College Management) when tenant is activated
     */
    protected function createInitialUsers(): void
    {
        if (!$this->tenant) {
            return;
        }

        $tenantInfo = $this->data['tenant_info'] ?? [];
        $createdUsers = [];

        // Create Rector
        if (!empty($tenantInfo['rector_phone'])) {
            // Normalize phone number to ensure it can be used for login
            // Remove all non-digits, then format as +91XXXXXXXXXX for Indian numbers
            $phone = preg_replace('/\D+/', '', $tenantInfo['rector_phone']);
            if (strlen($phone) === 10 && preg_match('/^[6-9]\d{9}$/', $phone)) {
                // 10-digit Indian mobile number - add +91 prefix
                $normalizedPhone = '+91' . $phone;
            } elseif (strlen($phone) === 12 && str_starts_with($phone, '91')) {
                // Already has country code, add + prefix
                $normalizedPhone = '+' . $phone;
            } elseif (str_starts_with($phone, '+')) {
                // Already has + prefix, use as-is
                $normalizedPhone = $phone;
            } else {
                // Use as-is if it doesn't match expected formats
                $normalizedPhone = $tenantInfo['rector_phone'];
            }
            
            // Find existing user or create new one
            $rector = User::firstOrCreate(
                ['phone' => $normalizedPhone],
                [
                    'tenant_id' => $this->tenant->id,
                    'name' => $tenantInfo['rector_name'] ?? 'Rector',
                    'email' => $tenantInfo['rector_email'] ?? null,
                    'kind' => 'Rector',
                    'is_active' => true,
                    'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(32)),
                ]
            );
            
            // If user already existed, update tenant_id and other fields
            $wasExisting = $rector->wasRecentlyCreated === false;
            if ($wasExisting) {
                $rector->update([
                    'phone' => $normalizedPhone, // Update phone to normalized format
                    'tenant_id' => $this->tenant->id,
                    'name' => $tenantInfo['rector_name'] ?? $rector->name,
                    'email' => $tenantInfo['rector_email'] ?? $rector->email,
                    'kind' => 'Rector',
                    'is_active' => true,
                ]);
            }
            
            // Ensure Rector role is assigned (remove other conflicting roles if needed)
            if (!$rector->hasRole('Rector')) {
                $rector->assignRole('Rector');
            }
            
            $createdUsers[] = [
                'name' => $rector->name,
                'phone' => $rector->phone,
                'role' => 'Rector',
            ];

            Log::info('Rector user created/assigned', [
                'tenant_id' => $this->tenant->id,
                'user_id' => $rector->id,
                'phone' => $rector->phone,
                'was_existing' => $wasExisting,
            ]);
        }

        // Create College Management
        if (!empty($tenantInfo['college_mgmt_phone'])) {
            // Normalize phone number to ensure it can be used for login
            // Remove all non-digits, then format as +91XXXXXXXXXX for Indian numbers
            $phone = preg_replace('/\D+/', '', $tenantInfo['college_mgmt_phone']);
            if (strlen($phone) === 10 && preg_match('/^[6-9]\d{9}$/', $phone)) {
                // 10-digit Indian mobile number - add +91 prefix
                $normalizedPhone = '+91' . $phone;
            } elseif (strlen($phone) === 12 && str_starts_with($phone, '91')) {
                // Already has country code, add + prefix
                $normalizedPhone = '+' . $phone;
            } elseif (str_starts_with($phone, '+')) {
                // Already has + prefix, use as-is
                $normalizedPhone = $phone;
            } else {
                // Use as-is if it doesn't match expected formats
                $normalizedPhone = $tenantInfo['college_mgmt_phone'];
            }
            
            // Find existing user or create new one
            $collegeMgmt = User::firstOrCreate(
                ['phone' => $normalizedPhone],
                [
                    'tenant_id' => $this->tenant->id,
                    'name' => $tenantInfo['college_mgmt_name'] ?? 'College Management',
                    'email' => $tenantInfo['college_mgmt_email'] ?? null,
                    'kind' => 'CollegeMgmt',
                    'is_active' => true,
                    'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(32)),
                ]
            );
            
            // If user already existed, update tenant_id and other fields
            $wasExisting = $collegeMgmt->wasRecentlyCreated === false;
            if ($wasExisting) {
                $collegeMgmt->update([
                    'phone' => $normalizedPhone, // Update phone to normalized format
                    'tenant_id' => $this->tenant->id,
                    'name' => $tenantInfo['college_mgmt_name'] ?? $collegeMgmt->name,
                    'email' => $tenantInfo['college_mgmt_email'] ?? $collegeMgmt->email,
                    'kind' => 'CollegeMgmt',
                    'is_active' => true,
                ]);
            }
            
            // Ensure College Management role is assigned
            if (!$collegeMgmt->hasRole('College Management')) {
                $collegeMgmt->assignRole('College Management');
            }
            
            $createdUsers[] = [
                'name' => $collegeMgmt->name,
                'phone' => $collegeMgmt->phone,
                'role' => 'College Mgmt',
            ];

            Log::info('College Management user created/assigned', [
                'tenant_id' => $this->tenant->id,
                'user_id' => $collegeMgmt->id,
                'phone' => $collegeMgmt->phone,
                'was_existing' => $wasExisting,
            ]);
        }

        // Store created users in tenant data for display in success notification
        if (!empty($createdUsers)) {
            $currentData = $this->getTenantDataArray($this->tenant);
            $currentData['created_users'] = $createdUsers;
            
            DB::table('tenants')
                ->where('id', $this->tenant->id)
                ->update(['data' => json_encode($currentData)]);
        }
    }

    public function boot(): void
    {
        $this->authorizeSuperAdmin();
    }

    public function hydrate(): void
    {
        $this->authorizeSuperAdmin();
    }

    public static function canView(): bool
    {
        if (! Auth::user()?->hasRole('Super Admin')) {
            throw new AuthorizationException();
        }

        return true;
    }

    public function mount(PreflightService $preflightService, StaffAssignmentService $staffAssignmentService): void
    {
        $this->authorizeSuperAdmin();

        $this->preflightService = $preflightService;
        $this->staffAssignmentService = $staffAssignmentService;
        $this->data = $this->getDefaultWizardData();

        $tenantId = request()->query('tenant_id') ?? request()->query('tenant');
        if ($tenantId) {
            $this->loadDraftTenant((string) $tenantId);
        }
    }

    protected function getDefaultWizardData(): array
    {
        return [
            'tenant_info' => [
                'name' => '',
                'code' => '',
                'logo' => null,
                'campus_name' => '',
                'subdomain' => '',
                'rector_name' => '',
                'rector_phone' => '',
                'rector_email' => '',
                'college_mgmt_name' => '',
                'college_mgmt_phone' => '',
                'college_mgmt_email' => '',
            ],
            'hostels' => [],
            'staff' => [
                // Explicitly initialize nested keys so Livewire entangle can bind Select inputs reliably.
                'campus_manager_id' => null,
                'hostel_assignments' => [],
            ],
            'room_config' => [],
            'amenities' => [
                'selected' => [],
            ],
            'activation' => [
                'confirmed' => false,
                'idempotency_key' => null,
            ],
            'contacts' => [],
        ];
    }

    protected function loadDraftTenant(string $tenantId): void
    {
        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant) {
            return;
        }

        $raw = $this->getTenantDataArray($tenant);
        $wizard = is_array($raw['wizard'] ?? null) ? $raw['wizard'] : [];

        if (! empty($wizard)) {
            $this->data = array_replace_recursive($this->data, $wizard);
        }

        $this->tenant = $tenant;
    }

    protected function getForms(): array
    {
        $this->authorizeSuperAdmin();

        return [
            'wizardForm' => $this->makeWizardForm(),
        ];
    }

    public function getProgressPercent(): int
    {
        $steps = $this->getStepCompletion();
        $total = count($steps);
        $completed = collect($steps)->where('done', true)->count();

        return $total > 0 ? (int) round(($completed / $total) * 100) : 0;
    }

    public function getValidationSummary(): array
    {
        $summary = [];

        if (empty($this->data['tenant_info']['name'] ?? null)) {
            $summary[] = 'Tenant name is required in Step 1.';
        }

        if (empty($this->data['hostels'] ?? [])) {
            $summary[] = 'At least one hostel is required in Step 2.';
        }

        if (empty($this->data['staff']['campus_manager_id'] ?? null)) {
            $summary[] = 'Assign a Campus Manager in Step 3.';
        }

        if (empty($this->data['room_config'] ?? [])) {
            $summary[] = 'Configure rooms in Step 4.';
        }

        return $summary;
    }

    public function saveAndExit(): void
    {
        $this->saveDraft();
        $this->redirect(route('filament.admin.resources.tenants.index'));
    }

    protected function getStepCompletion(): array
    {
        return [
            ['label' => 'Tenant Info', 'done' => filled($this->data['tenant_info']['name'] ?? null)],
            ['label' => 'Hostels', 'done' => !empty($this->data['hostels'] ?? [])],
            ['label' => 'Staff', 'done' => !empty($this->data['staff']['campus_manager_id'] ?? null)],
            ['label' => 'Rooms', 'done' => !empty($this->data['room_config'] ?? [])],
            ['label' => 'Amenities', 'done' => !empty($this->data['amenities']['selected'] ?? [])],
            ['label' => 'Confirmation', 'done' => (bool) ($this->data['activation']['confirmed'] ?? false)],
        ];
    }

    protected function makeWizardForm(): Form
    {
        return Form::make($this)
            ->schema([
                Wizard::make([
                    // Step 1: Tenant Information
                    Wizard\Step::make('tenant_info')
                        ->label('Tenant Information')
                        ->description('Create the tenant and its single campus')
                        ->icon('heroicon-o-building-office')
                        ->schema([
                            Section::make('Tenant Details')
                                ->schema([
                                    TextInput::make('data.tenant_info.name')
                                        ->label('Tenant Name')
                                        ->required()
                                        ->maxLength(120)
                                        ->placeholder('St. Xavier\'s College')
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn ($state, $set) => $set('data.tenant_info.campus_name', $state)),
                                    TextInput::make('data.tenant_info.code')
                                        ->label('Tenant Code')
                                        ->required()
                                        ->regex('/^MAP-[A-Z0-9-]{2,20}$/')
                                        ->placeholder('MAP-STXAV')
                                        ->helperText('Must begin with "MAP". Uppercase letters, numbers, and hyphens only.')
                                        ->rule(fn () => Rule::unique('tenants', 'code')->ignore($this->tenant?->id)),
                                    FileUpload::make('data.tenant_info.logo')
                                        ->label('Logo (Optional)')
                                        ->image()
                                        ->maxSize(1024)
                                        ->helperText('PNG/JPG, ≤ 1MB. Compress the image if over 1MB. If upload still fails, try a smaller file or skip and add logo later.')
                                        ->disk('public')
                                        ->directory('logos')
                                        ->visibility('public')
                                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/jpg'])
                                        ->downloadable()
                                        ->previewable()
                                        ->reorderable(false),
                                ]),
                            Section::make('Campus Details')
                                ->schema([
                                    TextInput::make('data.tenant_info.campus_name')
                                        ->label('Campus Name')
                                        ->required()
                                        ->maxLength(255)
                                        ->default(fn ($get) => $get('data.tenant_info.name'))
                                        ->placeholder('St. Xavier\'s Campus'),
                                    TextInput::make('data.tenant_info.subdomain')
                                        ->label('Subdomain')
                                        ->required()
                                        ->maxLength(50)
                                        ->regex('/^[a-z0-9-]+$/')
                                        ->placeholder('stxaviers')
                                        ->helperText('Will be used as: yoursubdomain.' . config('app.domain', 'mapservices.in'))
                                        ->suffix('.' . config('app.domain', 'mapservices.in')),
                                ]),
                            Section::make('Rector Information')
                                ->description('Primary contact for the college')
                                ->schema([
                                    Forms\Components\Grid::make(3)->schema([
                                        TextInput::make('data.tenant_info.rector_name')
                                            ->label('Rector Name')
                                            ->required()
                                            ->maxLength(100),
                                        TextInput::make('data.tenant_info.rector_phone')
                                            ->label('Rector Phone')
                                            ->required()
                                            ->tel()
                                            ->regex('/^[6-9]\d{9}$/')
                                            ->placeholder('9876543210')
                                            ->helperText('10-digit mobile number for OTP login'),
                                        TextInput::make('data.tenant_info.rector_email')
                                            ->label('Rector Email')
                                            ->email()
                                            ->maxLength(100),
                                    ]),
                                ]),
                            Section::make('College Management')
                                ->description('Administrative contact')
                                ->schema([
                                    Forms\Components\Grid::make(3)->schema([
                                        TextInput::make('data.tenant_info.college_mgmt_name')
                                            ->label('Name')
                                            ->required()
                                            ->maxLength(100),
                                        TextInput::make('data.tenant_info.college_mgmt_phone')
                                            ->label('Phone')
                                            ->required()
                                            ->tel()
                                            ->regex('/^[6-9]\d{9}$/')
                                            ->placeholder('9876543210')
                                            ->helperText('10-digit mobile number for OTP login'),
                                        TextInput::make('data.tenant_info.college_mgmt_email')
                                            ->label('Email')
                                            ->email()
                                            ->maxLength(100),
                                    ]),
                                ]),
                        ])
                        ->afterValidation(function () {
                            // Create tenant draft after Step 1 validation
                            if (!$this->tenant && filled($this->data['tenant_info']['code'])) {
                                $this->createTenantDraft();
                            }
                        }),

                    // Step 2: Hostel Details
                    Wizard\Step::make('hostels')
                        ->label('Hostel Details')
                        ->description('Add hostel information and address')
                        ->icon('heroicon-o-home')
                        ->schema([
                            Repeater::make('data.hostels')
                                ->label('Hostels')
                                ->schema([
                                    Forms\Components\Grid::make(2)->schema([
                                        TextInput::make('name')
                                            ->label('Hostel Name')
                                            ->required()
                                            ->placeholder('Boys\' Hostel 1'),
                                        Select::make('gender')
                                            ->label('Hostel Type')
                                            ->required()
                                            ->options([
                                                'boys' => 'Boys Hostel',
                                                'girls' => 'Girls Hostel',
                                                'co-ed' => 'Co-Ed Hostel',
                                            ]),
                                    ]),
                                    TextInput::make('code')
                                        ->label('Hostel Code')
                                        ->required()
                                        ->regex('/^[A-Z0-9-]{2,20}$/')
                                        ->placeholder('H1')
                                        ->rule(fn (callable $get) => Rule::unique('hostels', 'code')->ignore($get('id'))),
                                    Section::make('Hostel Address')
                                        ->schema([
                                            TextInput::make('address_street')
                                                ->label('Street Address')
                                                ->maxLength(255),
                                            Forms\Components\Grid::make(3)->schema([
                                                TextInput::make('address_city')
                                                    ->label('City')
                                                    ->maxLength(100),
                                                TextInput::make('address_state')
                                                    ->label('State')
                                                    ->maxLength(100),
                                                TextInput::make('address_pincode')
                                                    ->label('Pincode')
                                                    ->maxLength(6)
                                                    ->numeric(),
                                            ]),
                                        ])
                                        ->collapsed(),
                                    TimePicker::make('curfew_time')
                                        ->label('Daily Curfew Time')
                                        ->required()
                                        ->seconds(false)
                                        ->helperText('Campus Manager can adjust later'),
                                ])
                                ->defaultItems(1)
                                ->minItems(1)
                                ->addActionLabel('Add Another Hostel')
                                ->reorderable(false)
                                ->afterStateUpdated(function ($state) {
                                    if ($this->tenant) {
                                        $this->saveHostels($state);
                                    }
                                }),
                        ]),

                    // Step 3: Staff Assignment (per-hostel)
                    Wizard\Step::make('staff')
                        ->label('Staff Assignment')
                        ->description('Assign Campus Manager (tenant-level) and hostel staff per hostel')
                        ->icon('heroicon-o-users')
                        ->schema([
                            Placeholder::make('staff_info')
                                ->content(new \Illuminate\Support\HtmlString(
                                    '<div class="text-sm text-gray-600 mb-4">
                                        <a href="' . route('filament.admin.resources.unassigned-staff.index') . '" target="_blank" class="text-primary-600 hover:underline font-medium">
                                            + Create New Staff Member
                                        </a>
                                        <span class="mx-2">|</span>
                                        <button type="button" onclick="window.location.reload()" class="text-primary-600 hover:underline">
                                            Refresh Staff List
                                        </button>
                                    </div>'
                                )),

                            // Tenant-level: Campus Manager
                            Section::make('Campus Manager (Tenant-Level)')
                                ->description('Campus Manager manages all hostels under this tenant.')
                                ->schema([
                                    Select::make('data.staff.campus_manager_id')
                                        ->label('Campus Manager')
                                        ->required()
                                        ->searchable()
                                        ->preload()
                                        ->live()
                                        ->options(function (callable $get): array {
                                            $selectedId = $get('data.staff.campus_manager_id');

                                            return $this->getAssignableStaffOptions(
                                                is_numeric($selectedId) ? (int) $selectedId : null
                                            );
                                        })
                                        ->afterStateUpdated(function ($state) {
                                            $this->assignCampusManager($state ? (int) $state : null);
                                        })
                                        ->helperText('Select from unassigned staff pool'),
                                ]),

                            // Per-hostel staff assignment
                            Repeater::make('data.staff.hostel_assignments')
                                ->label('Hostel Staff Assignments')
                                ->schema([
                                    Select::make('hostel_id')
                                        ->label('Hostel')
                                        ->required()
                                        ->options(function () {
                                            if (! $this->tenant) {
                                                return [];
                                            }
                                            return Hostel::where('tenant_id', $this->tenant->id)
                                                ->pluck('name', 'id');
                                        })
                                        ->searchable()
                                        ->preload()
                                        ->live()
                                        ->distinct()
                                        ->disableOptionsWhenSelectedInSiblingRepeaterItems(),

                                    Forms\Components\Grid::make(2)->schema([
                                        Select::make('warden_id')
                                            ->label('Warden')
                                            ->required()
                                            ->searchable()
                                            ->options(fn (): array => $this->getAssignableStaffOptions())
                                            ->helperText('Required'),
                                        Select::make('guard_id')
                                            ->label('Guard')
                                            ->searchable()
                                            ->options(fn (): array => $this->getAssignableStaffOptions())
                                            ->helperText('Optional'),
                                    ]),
                                    Forms\Components\Grid::make(2)->schema([
                                        Select::make('hk_supervisor_id')
                                            ->label('HK Supervisor')
                                            ->required()
                                            ->searchable()
                                            ->options(fn (): array => $this->getAssignableStaffOptions())
                                            ->helperText('Required'),
                                        Select::make('rm_supervisor_id')
                                            ->label('RM Supervisor')
                                            ->required()
                                            ->searchable()
                                            ->options(fn (): array => $this->getAssignableStaffOptions())
                                            ->helperText('Required'),
                                    ]),
                                    Forms\Components\Grid::make(2)->schema([
                                        Select::make('laundry_manager_id')
                                            ->label('Laundry Manager')
                                            ->searchable()
                                            ->options(fn (): array => $this->getAssignableStaffOptions())
                                            ->helperText('Optional (add-on module)'),
                                        Select::make('sports_manager_id')
                                            ->label('Sports Manager')
                                            ->searchable()
                                            ->options(fn (): array => $this->getAssignableStaffOptions())
                                            ->helperText('Optional (add-on module)'),
                                    ]),
                                ])
                                ->defaultItems(0)
                                ->addActionLabel('Assign Staff for Hostel')
                                ->afterStateUpdated(fn ($state) => $this->saveStaffAssignments($state)),
                        ])
                        ->afterValidation(function () {
                            $this->validateStaffAssignments();
                        }),

                    // Step 4: Hostel Configuration (Rooms/Beds)
                    Wizard\Step::make('room_config')
                        ->label('Hostel Configuration')
                        ->description('Configure floors, rooms, and beds for each hostel')
                        ->icon('heroicon-o-view-columns')
                        ->schema([
                            Placeholder::make('config_info')
                                ->content(new \Illuminate\Support\HtmlString(
                                    '<div class="p-4 bg-info-50 dark:bg-info-900/20 rounded-lg mb-4">
                                        <p class="text-sm text-info-700 dark:text-info-300">
                                            Configure the room layout for each hostel. Add floors, specify room capacity (number of beds), 
                                            and the quantity of rooms for each type. Room numbers will be auto-generated.
                                        </p>
                                    </div>'
                                )),
                            Repeater::make('data.room_config')
                                ->label('Hostel Configurations')
                                ->schema([
                                    Select::make('hostel_id')
                                        ->label('Select Hostel')
                                        ->required()
                                        ->options(function (callable $get) {
                                            $options = [];

                                            // Primary source: persisted hostels for this tenant (stable numeric IDs).
                                            if ($this->tenant) {
                                                $dbHostels = Hostel::query()
                                                    ->where('tenant_id', $this->tenant->id)
                                                    ->orderBy('name')
                                                    ->get()
                                                    ->mapWithKeys(fn (Hostel $hostel) => [$hostel->id => $hostel->name])
                                                    ->toArray();
                                                $options = array_merge($options, $dbHostels);
                                            }

                                            // Fallback (before Step 2 persistence): temporary keys resolved later.
                                            $formHostels = $get('../../data.hostels') ?? [];
                                            foreach ($formHostels as $index => $hostelData) {
                                                $name = $hostelData['name'] ?? null;
                                                if (!is_string($name) || trim($name) === '') {
                                                    continue;
                                                }

                                                if (!empty($hostelData['id']) && is_numeric($hostelData['id'])) {
                                                    $options[(int) $hostelData['id']] = $name;
                                                    continue;
                                                }

                                                if (!empty($hostelData['code']) && is_string($hostelData['code'])) {
                                                    $options['code:' . $hostelData['code']] = $name;
                                                    continue;
                                                }

                                                $options['index:' . $index] = $name;
                                            }

                                            return $options;
                                        })
                                        ->searchable()
                                        ->preload()
                                        ->live()
                                        ->afterStateUpdated(function ($state, callable $set) {
                                            if (! $this->tenant) {
                                                return;
                                            }

                                            // Already resolved to a real hostel ID.
                                            if (is_numeric($state) && (int) $state > 0) {
                                                return;
                                            }

                                            if (! is_string($state) || $state === '') {
                                                return;
                                            }

                                            // Resolve temporary code key to DB hostel ID.
                                            if (str_starts_with($state, 'code:')) {
                                                $code = str_replace('code:', '', $state);
                                                $hostel = Hostel::query()
                                                    ->where('tenant_id', $this->tenant->id)
                                                    ->where('code', $code)
                                                    ->first();
                                                if ($hostel) {
                                                    $set('hostel_id', $hostel->id);
                                                }
                                                return;
                                            }

                                            // Resolve temporary index key (pre-save path) to DB hostel ID.
                                            if (str_starts_with($state, 'index:')) {
                                                $index = (int) str_replace('index:', '', $state);
                                                $formHostels = $this->data['hostels'] ?? [];
                                                $candidate = $formHostels[$index] ?? null;
                                                $candidateCode = $candidate['code'] ?? null;

                                                if (is_string($candidateCode) && $candidateCode !== '') {
                                                    $hostel = Hostel::query()
                                                        ->where('tenant_id', $this->tenant->id)
                                                        ->where('code', $candidateCode)
                                                        ->first();
                                                    if ($hostel) {
                                                        $set('hostel_id', $hostel->id);
                                                    }
                                                }
                                            }
                                        }),
                                    Repeater::make('floors')
                                        ->label('Floors')
                                        ->schema([
                                            Forms\Components\Grid::make(4)->schema([
                                                TextInput::make('floor_number')
                                                    ->label('Floor #')
                                                    ->required()
                                                    ->numeric()
                                                    ->minValue(1)
                                                    ->maxValue(50)
                                                    ->default(1),
                                                Select::make('room_capacity')
                                                    ->label('Room Capacity')
                                                    ->required()
                                                    ->options([
                                                        1 => 'Single (1 bed)',
                                                        2 => 'Double (2 beds)',
                                                        3 => 'Triple (3 beds)',
                                                        4 => 'Quad (4 beds)',
                                                        5 => 'Quint (5 beds)',
                                                        6 => 'Six (6 beds)',
                                                    ])
                                                    ->default(2),
                                                TextInput::make('room_count')
                                                    ->label('Number of Rooms')
                                                    ->required()
                                                    ->numeric()
                                                    ->minValue(1)
                                                    ->maxValue(100)
                                                    ->default(10),
                                                Select::make('numbering')
                                                    ->label('Room Numbering')
                                                    ->options([
                                                        'auto' => 'Auto-generate',
                                                        'manual' => 'Manual',
                                                    ])
                                                    ->default('auto'),
                                            ]),
                                        ])
                                        ->defaultItems(1)
                                        ->minItems(1)
                                        ->addActionLabel('Add Floor'),
                                ])
                                ->defaultItems(0)
                                ->addActionLabel('Configure Hostel'),
                        ]),

                    // Step 5: Amenities Selection
                    Wizard\Step::make('amenities')
                        ->label('Amenities')
                        ->description('Select available amenities for the hostel(s)')
                        ->icon('heroicon-o-sparkles')
                        ->schema([
                            Section::make('Select Amenities')
                                ->description('Choose the amenities available at your hostel(s)')
                                ->schema([
                                    Forms\Components\CheckboxList::make('data.amenities.selected')
                                        ->label('Available Amenities')
                                        ->options(fn () => \App\Models\Amenity::pluck('label', 'key'))
                                        ->columns(3)
                                        ->gridDirection('row')
                                        ->bulkToggleable(),
                                ]),
                        ]),

                    // Step 6: Confirmation & Activation
                    Wizard\Step::make('confirmation')
                        ->label('Confirmation')
                        ->description('Review all details and activate tenant')
                        ->icon('heroicon-o-check-circle')
                        ->schema([
                            Section::make('Pre-flight Checklist')
                                ->schema([
                                    Placeholder::make('preflight_checks')
                                        ->label('Checks')
                                        ->content(fn () => $this->renderPreflightChecks()),
                                ]),
                            Checkbox::make('data.activation.confirmed')
                                ->label('I understand that structural hostel settings are locked and no new hostels can be added after activation.')
                                ->required(),
                        ]),
                ]),
            ]);
    }

    /**
     * Form footer actions (buttons).
     *
     * This adds an explicit \"Activate Tenant\" button on the Confirmation step.
     */
    protected function getFormActions(): array
    {
        return [
            FormAction::make('saveDraft')
                ->label('Save Draft')
                ->action('saveDraft')
                ->color('secondary'),

            FormAction::make('saveAndExit')
                ->label('Save & Exit')
                ->action('saveAndExit')
                ->color('gray'),

            FormAction::make('activate')
                ->label('Activate Tenant')
                ->action('activate')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Activate Tenant')
                ->modalDescription('This will activate the tenant and lock structural hostel settings.')
                ->visible(fn () => (bool) ($this->data['activation']['confirmed'] ?? false)),
        ];
    }

    protected function createTenantDraft(): void
    {
        try {
            $tenantInfo = $this->data['tenant_info'];
            $normalizedCode = $this->normalizeTenantCode((string) ($tenantInfo['code'] ?? ''));
            $tenantInfo['code'] = $normalizedCode;
            $this->data['tenant_info']['code'] = $normalizedCode;
            
            $this->tenant = DB::transaction(function () use ($tenantInfo) {
                $tenant = Tenant::create([
                    'code' => $tenantInfo['code'],
                    'name' => $tenantInfo['name'],
                    'status' => \App\Enums\TenantStatus::PROVISIONING,
                    'data' => [
                        'logo' => $tenantInfo['logo'] ?? null,
                        'campus_name' => $tenantInfo['campus_name'] ?? $tenantInfo['name'],
                        'campus_address' => [
                            'line1' => $tenantInfo['address_line1'] ?? null,
                            'line2' => $tenantInfo['address_line2'] ?? null,
                            'city' => $tenantInfo['city'] ?? null,
                            'state' => $tenantInfo['state'] ?? null,
                            'postal_code' => $tenantInfo['postal_code'] ?? null,
                        ],
                        'contact_email' => $tenantInfo['contact_email'] ?? null,
                        'contact_phone' => $tenantInfo['contact_phone'] ?? null,
                    ],
                ]);

                // Create domain for tenant subdomain access
                // This is CRITICAL for subdomain routing to work
                $subdomain = $tenantInfo['subdomain'] ?? Str::slug(strtolower($tenant->code));
                $domainSuffix = config('app.domain', 'mapservices.in');
                
                $domain = env('APP_ENV') === 'local' 
                    ? $subdomain . '.localhost'
                    : $subdomain . '.' . $domainSuffix;
                
                $tenant->domains()->create([
                    'domain' => $domain,
                ]);

                Log::info('Tenant domain created', [
                    'tenant_id' => $tenant->id,
                    'tenant_code' => $tenant->code,
                    'domain' => $domain,
                ]);

                // Create campus (1 tenant = 1 campus)
                Campus::create([
                    'tenant_id' => $tenant->id,
                    'code' => $tenant->code . '-CAMPUS',
                    'name' => $tenantInfo['campus_name'] ?? $tenantInfo['name'],
                    'address' => [
                        'line1' => $tenantInfo['address_line1'] ?? null,
                        'line2' => $tenantInfo['address_line2'] ?? null,
                        'city' => $tenantInfo['city'] ?? null,
                        'state' => $tenantInfo['state'] ?? null,
                        'postal_code' => $tenantInfo['postal_code'] ?? null,
                    ],
                ]);

                return $tenant;
            });

            Notification::make()
                ->title('Tenant draft created')
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Failed to create tenant')
                ->body($e->getMessage())
                ->danger()
                ->send();
            
            Log::error('Failed to create tenant draft', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    protected function normalizeTenantCode(string $code): string
    {
        $normalized = strtoupper(trim($code));
        if (! str_starts_with($normalized, 'MAP-')) {
            $normalized = 'MAP-' . ltrim($normalized, '-');
        }

        $normalized = preg_replace('/[^A-Z0-9-]/', '', $normalized) ?? 'MAP-';

        if (strlen($normalized) > 24) {
            $normalized = substr($normalized, 0, 24);
        }

        return $normalized;
    }

    public function submit(): void
    {
        $this->activate();
    }

    public function cancel(): void
    {
        $this->saveDraft();
    }

    protected function saveHostels(array $hostels): void
    {
        if (!$this->tenant) {
            return;
        }

        try {
            DB::transaction(function () use ($hostels) {
                $campus = Campus::where('tenant_id', $this->tenant->id)->first();
                if (! $campus) {
                    $tenantInfo = $this->data['tenant_info'] ?? [];
                    $campus = Campus::create([
                        'tenant_id' => $this->tenant->id,
                        'code' => $this->tenant->code . '-CAMPUS',
                        'name' => $tenantInfo['campus_name'] ?? ($tenantInfo['name'] ?? $this->tenant->name),
                        'address' => $this->normalizeCampusAddress($tenantInfo),
                    ]);
                }

                $processed = collect($hostels)
                    ->filter(fn ($hostelData) => !empty($hostelData['code']))
                    ->mapWithKeys(function ($hostelData) use ($campus) {
                        $floors = max(1, (int) ($hostelData['floors'] ?? 1));

                        $payload = [
                            'campus_id' => $campus->id,
                            'name' => $hostelData['name'] ?? null,
                            'gender_mode' => $hostelData['gender'] ?? null,
                            'curfew_time' => $hostelData['curfew_time'] ?? null,
                            'address' => $this->normalizeHostelAddress($hostelData),
                            'settings' => [
                                'floors' => $floors,
                                'room_types' => array_values($hostelData['room_types'] ?? []),
                            ],
                        ];

                        $hostel = Hostel::updateOrCreate(
                            [
                                'tenant_id' => $this->tenant->id,
                                'code' => $hostelData['code'],
                            ],
                            $payload
                        );

                        $this->syncRooms($hostel, array_merge($hostelData, ['floors' => $floors]));

                        return [
                            $hostel->code => array_merge($hostelData, [
                                'id' => $hostel->id,
                                'floors' => (string) $floors,
                                'room_types' => collect($hostelData['room_types'] ?? [])
                                    ->map(function ($roomType) {
                                        return [
                                            'type' => $roomType['type'] ?? null,
                                            'quantity' => (string) max(0, (int) ($roomType['quantity'] ?? 0)),
                                        ];
                                    })
                                    ->values()
                                    ->all(),
                            ]),
                        ];
                    })
                    ->filter();

                $submittedCodes = $processed->keys()->all();

                if (! empty($submittedCodes)) {
                    Hostel::where('tenant_id', $this->tenant->id)
                        ->whereNotIn('code', $submittedCodes)
                        ->get()
                        ->each(function (Hostel $hostel) {
                            $hostel->rooms()->each(function (Room $room) {
                                $room->beds()->delete();
                                $room->delete();
                            });

                            $hostel->delete();
                        });
                }

                $this->data['hostels'] = collect($hostels)
                    ->map(function ($hostelData) use ($processed) {
                        $code = $hostelData['code'] ?? null;

                        if ($code && $processed->has($code)) {
                            return $processed->get($code);
                        }

                        return $hostelData;
                    })
                    ->values()
                    ->all();
            });
        } catch (\Exception $e) {
            Log::error('Failed to save hostels', [
                'tenant_id' => $this->tenant->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function syncRooms(Hostel $hostel, array $hostelData): void
    {
        // Reset rooms/beds before regenerating so the RoomBed counts stay in sync
        $hostel->rooms()->each(function (Room $room) {
            $room->beds()->delete();
            $room->delete();
        });

        $this->generateRooms($hostel, $hostelData);
    }

    protected function generateRooms(Hostel $hostel, array $hostelData): void
    {
        $floors = max(1, (int)($hostelData['floors'] ?? 1));
        $roomTypes = $hostelData['room_types'] ?? [];

        foreach ($roomTypes as $roomTypeData) {
            $type = $roomTypeData['type'];
            $quantity = max(0, (int)($roomTypeData['quantity'] ?? 0));
            $capacity = match($type) {
                'single' => 1,
                'twin' => 2,
                'triple' => 3,
                default => 1,
            };

            for ($i = 1; $i <= $quantity; $i++) {
                $floorNum = (($i - 1) % $floors) + 1;
                $roomNumber = str_pad((string)$floorNum, 2, '0', STR_PAD_LEFT) . '-' . str_pad((string)$i, 3, '0', STR_PAD_LEFT);

                $room = Room::create([
                    'tenant_id' => $hostel->tenant_id,
                    'campus_id' => $hostel->campus_id,
                    'hostel_id' => $hostel->id,
                    'block_code' => 'A',
                    'floor_code' => (string) $floorNum,
                    'number' => $roomNumber,
                    'capacity' => $capacity,
                    'room_type' => ucfirst($type),
                    'is_active' => true,
                ]);

                // Create beds
                for ($bed = 1; $bed <= $capacity; $bed++) {
                    $bedCode = match($bed) {
                        1 => 'A',
                        2 => 'B',
                        3 => 'C',
                        4 => 'D',
                        default => 'A',
                    };

                    RoomBed::firstOrCreate(
                        [
                            'tenant_id' => $hostel->tenant_id,
                            'room_id' => $room->id,
                            'code' => $bedCode,
                        ],
                        [
                            'hostel_id' => $hostel->id,
                            'status' => 'available',
                        ]
                    );
                }
            }
        }
    }

    /**
     * Process room configurations from Step 4 and generate rooms
     */
    protected function processRoomConfigurations(): void
    {
        if (!$this->tenant) {
            return;
        }

        $roomConfigs = $this->data['room_config'] ?? [];
        
        if (empty($roomConfigs)) {
            Log::warning('No room configurations found during tenant activation', [
                'tenant_id' => $this->tenant->id,
            ]);
            return;
        }

        try {
            DB::transaction(function () use ($roomConfigs) {
                foreach ($roomConfigs as $config) {
                    $hostelId = $config['hostel_id'] ?? null;
                    if (!$hostelId) {
                        continue;
                    }

                    // Handle temporary identifiers from form (code:ABC or index:0)
                    $hostel = null;
                    if (is_numeric($hostelId)) {
                        $numericHostelId = (int) $hostelId;

                        // Direct ID lookup for valid IDs.
                        if ($numericHostelId > 0) {
                            $hostel = Hostel::query()
                                ->where('tenant_id', $this->tenant->id)
                                ->where('id', $numericHostelId)
                                ->first();
                        }

                        // Legacy fallback: some older wizard states persisted `0` for first entry.
                        if (!$hostel && $numericHostelId === 0) {
                            $firstConfiguredHostel = collect($this->data['hostels'] ?? [])->first();
                            $firstHostelId = data_get($firstConfiguredHostel, 'id');
                            $firstHostelCode = data_get($firstConfiguredHostel, 'code');

                            if (is_numeric($firstHostelId) && (int) $firstHostelId > 0) {
                                $hostel = Hostel::query()
                                    ->where('tenant_id', $this->tenant->id)
                                    ->where('id', (int) $firstHostelId)
                                    ->first();
                            } elseif (is_string($firstHostelCode) && $firstHostelCode !== '') {
                                $hostel = Hostel::query()
                                    ->where('tenant_id', $this->tenant->id)
                                    ->where('code', $firstHostelCode)
                                    ->first();
                            }
                        }
                    } elseif (str_starts_with($hostelId, 'code:')) {
                        // Lookup by code
                        $code = str_replace('code:', '', $hostelId);
                        $hostel = Hostel::query()
                            ->where('tenant_id', $this->tenant->id)
                            ->where('code', $code)
                            ->first();
                    } elseif (str_starts_with($hostelId, 'index:')) {
                        // Lookup by index in form data
                        $index = (int) str_replace('index:', '', $hostelId);
                        $formHostels = $this->data['hostels'] ?? [];
                        if (isset($formHostels[$index]) && !empty($formHostels[$index]['code'])) {
                            $code = $formHostels[$index]['code'];
                            $hostel = Hostel::query()
                                ->where('tenant_id', $this->tenant->id)
                                ->where('code', $code)
                                ->first();
                        }
                    }

                    if (!$hostel) {
                        Log::warning('Hostel not found for room configuration', [
                            'tenant_id' => $this->tenant->id,
                            'hostel_id' => $hostelId,
                            'form_hostels' => array_map(fn($h) => ['code' => $h['code'] ?? null, 'name' => $h['name'] ?? null], $this->data['hostels'] ?? []),
                        ]);
                        continue;
                    }

                    $floors = $config['floors'] ?? [];
                    
                    // Reset existing rooms for this hostel
                    $hostel->rooms()->each(function (Room $room) {
                        $room->beds()->delete();
                        $room->delete();
                    });

                    // Generate rooms from floor configurations
                    foreach ($floors as $floorConfig) {
                        $floorNumber = max(1, (int)($floorConfig['floor_number'] ?? 1));
                        $roomCapacity = max(1, (int)($floorConfig['room_capacity'] ?? 4));
                        $roomCount = max(0, (int)($floorConfig['room_count'] ?? 0));
                        $numberingMode = $floorConfig['numbering'] ?? 'auto';

                        if ($roomCount <= 0) {
                            continue;
                        }

                        // Generate rooms for this floor
                        for ($i = 1; $i <= $roomCount; $i++) {
                            // Generate room number based on numbering mode
                            if ($numberingMode === 'auto') {
                                $roomNumber = str_pad((string)$floorNumber, 2, '0', STR_PAD_LEFT) . '-' . str_pad((string)$i, 3, '0', STR_PAD_LEFT);
                            } else {
                                // Manual numbering - use sequential number
                                $roomNumber = (string)$i;
                            }

                            $room = Room::create([
                                'tenant_id' => $hostel->tenant_id,
                                'campus_id' => $hostel->campus_id,
                                'hostel_id' => $hostel->id,
                                'block_code' => 'A',
                                'floor_code' => (string)$floorNumber,
                                'number' => $roomNumber,
                                'capacity' => $roomCapacity,
                                'room_type' => $this->getRoomTypeName($roomCapacity),
                                'is_active' => true,
                            ]);

                            // Create beds for this room
                            $bedLabels = ['A', 'B', 'C', 'D', 'E', 'F'];
                            for ($bedIndex = 0; $bedIndex < $roomCapacity; $bedIndex++) {
                                RoomBed::create([
                                    'tenant_id' => $hostel->tenant_id,
                                    'hostel_id' => $hostel->id,
                                    'room_id' => $room->id,
                                    'code' => $bedLabels[$bedIndex] ?? 'A',
                                    'status' => 'available',
                                ]);
                            }
                        }
                    }

                    Log::info('Rooms generated for hostel', [
                        'tenant_id' => $this->tenant->id,
                        'hostel_id' => $hostel->id,
                        'hostel_name' => $hostel->name,
                    ]);
                }
            });
        } catch (\Exception $e) {
            Log::error('Failed to process room configurations', [
                'tenant_id' => $this->tenant->id,
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 500),
            ]);
            throw $e; // Re-throw to rollback transaction
        }
    }

    /**
     * Get room type name based on capacity
     */
    protected function getRoomTypeName(int $capacity): string
    {
        return match($capacity) {
            1 => 'Single',
            2 => 'Double',
            3 => 'Triple',
            4 => 'Quad',
            5 => 'Quint',
            6 => 'Six',
            default => 'Quad',
        };
    }

    protected function normalizeHostelAddress(array $hostelData): ?array
    {
        $address = Arr::get($hostelData, 'address');

        if (is_array($address)) {
            return [
                'line1' => $address['line1'] ?? $address['street'] ?? null,
                'line2' => $address['line2'] ?? null,
                'city' => $address['city'] ?? null,
                'state' => $address['state'] ?? null,
                'postal_code' => $address['postal_code'] ?? null,
            ];
        }

        if (filled($address)) {
            return [
                'line1' => $address,
            ];
        }

        return null;
    }

    protected function normalizeCampusAddress(array $tenantInfo): ?array
    {
        if (empty($tenantInfo)) {
            return null;
        }

        return [
            'line1' => $tenantInfo['address_line1'] ?? null,
            'line2' => $tenantInfo['address_line2'] ?? null,
            'city' => $tenantInfo['city'] ?? null,
            'state' => $tenantInfo['state'] ?? null,
            'postal_code' => $tenantInfo['postal_code'] ?? null,
        ];
    }

    protected function getRoomsStatus($record): string
    {
        if (empty($record['code'])) {
            return 'Save hostel first';
        }

        $hostel = Hostel::where('code', $record['code'])
            ->where('tenant_id', $this->tenant->id)
            ->first();
        if (!$hostel) {
            return 'Hostel not saved yet';
        }

        $roomCount = $hostel->rooms()->count();
        $bedCount = $hostel->rooms()->withCount('beds')->get()->sum('beds_count');

        return "Generated: {$roomCount} rooms / {$bedCount} beds";
    }

    protected function assignCampusManager(?int $userId): void
    {
        if (!$this->tenant || !$userId) {
            return;
        }

        try {
            $user = User::find($userId);
            if (!$user) {
                return;
            }

            // Assign Campus Manager at tenant scope (no hostel_id)
            // This will be handled by StaffAssignmentService with tenant scope
            $this->data['staff']['campus_manager_id'] = $userId;
        } catch (\Exception $e) {
            Log::error('Failed to assign Campus Manager', [
                'tenant_id' => $this->tenant->id,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function saveStaffAssignments(array $assignments): void
    {
        if (!$this->tenant) {
            return;
        }

        // Store assignments in data for later processing during activation
        $this->data['staff']['hostel_assignments'] = $assignments;
    }

    protected function getAssignableStaffOptions(?int $selectedId = null): array
    {
        return User::query()
            ->where(function ($query) use ($selectedId) {
                $query->whereNull('tenant_id');

                if ($selectedId) {
                    $query->orWhere('id', $selectedId);
                }
            })
            ->where('kind', '!=', 'student')
            ->where('archived', false)
            ->where(function ($query) {
                $query->whereNull('is_active')
                    ->orWhere('is_active', true);
            })
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected function validateStaffAssignments(): void
    {
        $errors = [];
        $staffData = is_array($this->data['staff'] ?? null) ? $this->data['staff'] : [];
        $campusManagerId = $staffData['campus_manager_id'] ?? null;

        if (empty($campusManagerId)) {
            $errors['data.staff.campus_manager_id'] = 'Campus Manager is required.';
        }

        $assignments = collect($staffData['hostel_assignments'] ?? [])
            ->filter(fn ($assignment) => is_array($assignment))
            ->values();

        if ($assignments->isEmpty()) {
            $errors['data.staff.hostel_assignments'] = 'Add at least one hostel staff assignment before proceeding.';
        }

        $requiredRoles = [
            'warden_id' => 'Warden',
            'hk_supervisor_id' => 'HK Supervisor',
            'rm_supervisor_id' => 'RM Supervisor',
        ];

        $optionalRoles = [
            'guard_id' => 'Guard',
            'laundry_manager_id' => 'Laundry Manager',
            'sports_manager_id' => 'Sports Manager',
        ];

        $selectedUserIds = [];

        if (is_numeric($campusManagerId)) {
            $selectedUserIds[] = (int) $campusManagerId;
        }

        foreach ($assignments as $index => $assignment) {
            $hostelId = $assignment['hostel_id'] ?? null;
            if (empty($hostelId)) {
                $errors["data.staff.hostel_assignments.{$index}.hostel_id"] = 'Hostel is required.';
            }

            foreach ($requiredRoles as $field => $label) {
                $userId = $assignment[$field] ?? null;
                if (empty($userId)) {
                    $errors["data.staff.hostel_assignments.{$index}.{$field}"] = "{$label} is required.";
                    continue;
                }

                if (is_numeric($userId)) {
                    $selectedUserIds[] = (int) $userId;
                }
            }

            foreach ($optionalRoles as $field => $label) {
                $userId = $assignment[$field] ?? null;

                if (empty($userId)) {
                    continue;
                }

                if (is_numeric($userId)) {
                    $selectedUserIds[] = (int) $userId;
                } else {
                    $errors["data.staff.hostel_assignments.{$index}.{$field}"] = "{$label} selection is invalid.";
                }
            }
        }

        if ($this->tenant) {
            $tenantHostelIds = Hostel::query()
                ->where('tenant_id', $this->tenant->id)
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->all();

            $assignedHostelIds = $assignments
                ->pluck('hostel_id')
                ->filter()
                ->map(fn ($id) => (string) $id)
                ->unique()
                ->all();

            if (!empty(array_diff($tenantHostelIds, $assignedHostelIds))) {
                $errors['data.staff.hostel_assignments'] = 'Assign required staff roles for every hostel before proceeding.';
            }
        }

        $selectedUserIds = array_values(array_unique(array_filter($selectedUserIds)));

        if (!empty($selectedUserIds)) {
            $enabledUserIds = User::query()
                ->whereIn('id', $selectedUserIds)
                ->where('archived', false)
                ->where(function ($query) {
                    $query->whereNull('is_active')
                        ->orWhere('is_active', true);
                })
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $enabledLookup = array_flip($enabledUserIds);

            if (is_numeric($campusManagerId) && !isset($enabledLookup[(int) $campusManagerId])) {
                $errors['data.staff.campus_manager_id'] = 'Selected Campus Manager is disabled.';
            }

            foreach ($assignments as $index => $assignment) {
                foreach ($requiredRoles as $field => $label) {
                    $userId = $assignment[$field] ?? null;
                    if (!is_numeric($userId)) {
                        continue;
                    }

                    if (!isset($enabledLookup[(int) $userId])) {
                        $errors["data.staff.hostel_assignments.{$index}.{$field}"] = "Selected {$label} is disabled.";
                    }
                }
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    protected function resolveTenant(): ?Tenant
    {
        if ($this->tenant instanceof Tenant) {
            return $this->tenant;
        }

        if (is_array($this->tenant) && isset($this->tenant['id'])) {
            return $this->tenant = Tenant::find($this->tenant['id']);
        }

        if (is_string($this->tenant) || is_int($this->tenant)) {
            return $this->tenant = Tenant::find($this->tenant);
        }

        return null;
    }

    protected function getTenantDataArray(Tenant $tenant): array
    {
        $raw = DB::table('tenants')
            ->where('id', $tenant->id)
            ->value('data');

        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && $raw !== '') {
            return json_decode($raw, true) ?? [];
        }

        return [];
    }

    protected function renderPreflightChecks(): string
    {
        if (!$this->tenant) {
            return '<p class="text-gray-500">Complete Step 1 first.</p>';
        }

        $preflightService = $this->getPreflightService();
        if (!$preflightService) {
            // If preflight service is not available, treat as pass but indicate limited checks.
            return '<p class="text-gray-500">Preflight service temporarily unavailable. Proceed with manual review.</p>';
        }

        $wizardData = is_array($this->data) ? $this->data : [];

        $preflight = $preflightService->evaluate($this->tenant, $wizardData);

        $html = '<ul class="space-y-2">';
        if (empty($preflight['errors'])) {
            $html .= '<li class="text-success-600">✅ All checks passed</li>';
        } else {
            foreach ($preflight['errors'] as $error) {
                $hostelCode = $error['hostel_code'] ?? '';
                $field = $error['field'] ?? '';
                $message = $error['message'] ?? '';
                $html .= '<li class="text-danger-600">❌ ' . ($hostelCode ? "[{$hostelCode}] " : '') . $message . '</li>';
            }
        }
        $html .= '</ul>';

        return $html;
    }

    public function saveDraft(): void
    {
        $tenant = $this->resolveTenant();

        if (!$tenant) {
            // Allow Save Draft directly from Step 1 by creating the draft tenant first.
            $tenantInfo = $this->data['tenant_info'] ?? [];
            $normalizedCode = $this->normalizeTenantCode((string) ($tenantInfo['code'] ?? ''));
            $canCreateDraft = filled($tenantInfo['name'] ?? null)
                && preg_match('/^MAP-[A-Z0-9-]{2,20}$/', $normalizedCode) === 1
                && filled($tenantInfo['campus_name'] ?? null)
                && filled($tenantInfo['subdomain'] ?? null)
                && filled($tenantInfo['rector_name'] ?? null)
                && filled($tenantInfo['rector_phone'] ?? null)
                && filled($tenantInfo['college_mgmt_name'] ?? null)
                && filled($tenantInfo['college_mgmt_phone'] ?? null);

            if ($canCreateDraft) {
                $this->data['tenant_info']['code'] = $normalizedCode;
                $this->createTenantDraft();
                $tenant = $this->resolveTenant();
            }

            if (! $tenant) {
                Notification::make()
                    ->title('Error')
                    ->body('Please complete Step 1 first')
                    ->danger()
                    ->send();
                return;
            }
        }

        $currentData = $this->getTenantDataArray($tenant);
        $currentData['wizard'] = $this->data;

        DB::table('tenants')
            ->where('id', $tenant->id)
            ->update(['data' => json_encode($currentData)]);

        $tenant->refresh();
        $this->tenant = $tenant;

        Notification::make()
            ->title('Draft saved')
            ->body('Your progress has been saved. You can resume later.')
            ->success()
            ->send();
    }

    public function activate(): void
    {
        if (!$this->tenant) {
            Notification::make()
                ->title('Error')
                ->body('Please complete Step 1 first')
                ->danger()
                ->send();
            return;
        }

        if (data_get($this->data, 'activation.confirmed') !== true) {
            Notification::make()
                ->title('Confirm activation first')
                ->body('Please acknowledge the structural lock notice before activating.')
                ->warning()
                ->send();
            return;
        }

        $wizardData = is_array($this->data) ? $this->data : [];

        // Run preflight checks if service is available; otherwise log and continue.
        $preflightService = $this->getPreflightService();
        if ($preflightService) {
            $preflight = $preflightService->evaluate($this->tenant, $wizardData);

            if (!$preflight['passed']) {
                $errors = collect($preflight['errors'])->pluck('message')->join(', ');
                Notification::make()
                    ->title('Pre-flight checks failed')
                    ->body($errors)
                    ->danger()
                    ->send();
                return;
            }
        } else {
            Log::warning('TenantOnboardingWizard: Preflight service missing during activation', [
                'tenant_id' => $this->tenant->id ?? null,
            ]);
        }

        $user = auth()->user();
        $idempotencyService = app(IdempotencyService::class);

        // Idempotency enforcement (24h dedupe) - key auto-generated when null
        $idemKey = data_get($this->data, 'activation.idempotency_key');
        try {
            $idemKey = $idempotencyService->assertUnique(
                action: 'tenant_activation',
                key: $idemKey,
                userId: $user?->id,
                tenantId: (string) $this->tenant->id,
                fingerprint: ['tenant_id' => $this->tenant->id]
            );
        } catch (\RuntimeException $e) {
            Notification::make()
                ->title('Duplicate request blocked')
                ->body($e->getMessage())
                ->warning()
                ->send();
            return;
        }

        try {
            DB::transaction(function () use ($wizardData, $idempotencyService, $idemKey) {
                // Process room configuration from Step 4
                $this->processRoomConfigurations();

                // Persist selected amenities to all tenant hostels.
                $this->syncAmenitiesForTenant();

                // Create initial users (Rector, College Mgmt) BEFORE assigning staff
                $this->createInitialUsers();

                // Assign staff
                $this->assignAllStaff($wizardData);

                // Activate tenant
                $this->tenant->update([
                    'status' => \App\Enums\TenantStatus::ACTIVE,
                ]);

                // Dispatch TenantActivated event
                event(new TenantActivated($this->tenant, $wizardData));

                Log::info('Tenant activated', [
                    'tenant_id' => $this->tenant->id,
                    'code' => $this->tenant->code,
                ]);

                $idempotencyService->storeResponse('tenant_activation', $idemKey, [
                    'tenant_id' => $this->tenant->id,
                    'status' => \App\Enums\TenantStatus::ACTIVE->value ?? 'active',
                ]);
            });

            // Get created users from tenant data (supports both array-cast and JSON-string data columns).
            $tenantData = $this->getTenantDataArray($this->tenant);
            $createdUsers = $tenantData['created_users'] ?? [];
            
            $notificationBody = 'Tenant is now active. Structural changes are locked.';
            
            if (!empty($createdUsers)) {
                $notificationBody .= "\n\n**Created Users (Login via OTP):**\n";
                foreach ($createdUsers as $user) {
                    $notificationBody .= "• {$user['role']}: {$user['phone']}\n";
                }
                $baseDomain = config('app.base_domain', 'mapservices.in');
                $subdomain = data_get($this->data, 'tenant_info.subdomain');
                if (! is_string($subdomain) || $subdomain === '') {
                    $subdomain = optional($this->tenant->domains()->first())->domain;
                    if (is_string($subdomain) && str_contains($subdomain, '.')) {
                        $subdomain = explode('.', $subdomain)[0];
                    }
                }
                $notificationBody .= "\n**Subdomain:** https://" . ($subdomain ?: strtolower((string) $this->tenant->code)) . ".{$baseDomain}/campus-manager";
            }

            Notification::make()
                ->title('Tenant activated')
                ->body($notificationBody)
                ->success()
                ->duration(15000) // Show for 15 seconds
                ->send();

            $this->redirect(route('filament.admin.resources.tenants.index'));

        } catch (\Exception $e) {
            Log::error('Failed to activate tenant', [
                'tenant_id' => $this->tenant->id,
                'error' => $e->getMessage(),
            ]);

            Notification::make()
                ->title('Activation failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function syncAmenitiesForTenant(): void
    {
        if (! $this->tenant) {
            return;
        }

        $selectedKeys = collect(data_get($this->data, 'amenities.selected', []))
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->values();

        if ($selectedKeys->isEmpty()) {
            return;
        }

        $amenityIds = Amenity::query()
            ->whereIn('key', $selectedKeys->all())
            ->pluck('id')
            ->all();

        if (empty($amenityIds)) {
            return;
        }

        $hostels = Hostel::query()
            ->where('tenant_id', $this->tenant->id)
            ->get();

        foreach ($hostels as $hostel) {
            $hostel->amenities()->sync($amenityIds);
        }
    }

    protected function assignAllStaff(array $wizardData): void
    {
        // Assign Campus Manager (tenant-scope — all hostels)
        if (!empty($wizardData['staff']['campus_manager_id'])) {
            $cmUser = User::find($wizardData['staff']['campus_manager_id']);
            if ($cmUser) {
                $cmUser->update([
                    'tenant_id' => $this->tenant->id,
                    'kind' => 'CampusManager',
                    'is_active' => true,
                ]);
                if (!$cmUser->hasRole('Campus Manager')) {
                    $cmUser->syncRoles(['Campus Manager']);
                }

                Log::info('Campus Manager assigned during onboarding', [
                    'tenant_id' => $this->tenant->id,
                    'user_id' => $cmUser->id,
                ]);
            }
        }

        // Assign hostel-scoped staff (per hostel)
        $assignments = $wizardData['staff']['hostel_assignments'] ?? [];
        foreach ($assignments as $assignment) {
            $hostelId = $assignment['hostel_id'] ?? null;
            if (!$hostelId) {
                continue;
            }

            // Only hostel-level MAP staff roles (Rector/College Mgmt assigned in Step 1)
            $roles = [
                'warden_id' => 'Warden',
                'guard_id' => 'Guard',
                'hk_supervisor_id' => 'HK Supervisor',
                'rm_supervisor_id' => 'RM Supervisor',
                'laundry_manager_id' => 'Laundry Manager',
                'sports_manager_id' => 'Sports Manager',
            ];

            foreach ($roles as $field => $roleName) {
                if (empty($assignment[$field])) {
                    continue;
                }

                $user = User::find($assignment[$field]);
                if ($user) {
                    // Update user's tenant_id and assign role
                    $user->update([
                        'tenant_id' => $this->tenant->id,
                        'is_active' => true,
                        'is_map_staff' => true,
                    ]);

                    if (!$user->hasRole($roleName)) {
                        $user->syncRoles([$roleName]);
                    }

                    // Create staff assignment linking user to hostel
                    DB::table('staff_assignments')->insert([
                        'tenant_id' => $this->tenant->id,
                        'user_id' => $user->id,
                        'hostel_id' => $hostelId,
                        'assigned_at' => now(),
                        'assigned_by' => Auth::id(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    Log::info('Staff assigned during onboarding', [
                        'tenant_id' => $this->tenant->id,
                        'user_id' => $user->id,
                        'hostel_id' => $hostelId,
                        'role' => $roleName,
                    ]);
                }
            }
        }
    }
}
