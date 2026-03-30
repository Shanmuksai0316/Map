<?php

namespace App\Filament\Resources\Admin\TenantResource\RelationManagers;

use App\Models\Campus;
use App\Models\Hostel;
use App\Models\Room;
use App\Models\RoomBed;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HostelsRelationManager extends RelationManager
{
    protected static string $relationship = 'hostels';

    protected static ?string $title = 'Hostels';

    protected static ?string $icon = 'heroicon-o-home';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Hostel Details')
                    ->schema([
                        Grid::make(3)->schema([
                            Forms\Components\TextInput::make('code')
                                ->label('Hostel Code')
                                ->required()
                                ->maxLength(20)
                                ->regex('/^[A-Z0-9-]{2,20}$/')
                                ->placeholder('H1')
                                ->helperText('Uppercase letters, numbers, hyphens only'),
                            Forms\Components\TextInput::make('name')
                                ->label('Hostel Name')
                                ->required()
                                ->maxLength(120)
                                ->placeholder("Boys' Hostel 1"),
                            Forms\Components\Select::make('gender_mode')
                                ->label('Gender Mode')
                                ->required()
                                ->options([
                                    'boys' => 'Boys Hostel',
                                    'girls' => 'Girls Hostel',
                                    'co-ed' => 'Co-Ed Hostel',
                                ]),
                        ]),
                    ]),
                Section::make('Rules & Timings')
                    ->schema([
                        Grid::make(3)->schema([
                            Forms\Components\TimePicker::make('curfew_start')
                                ->label('Curfew Start')
                                ->seconds(false)
                                ->default('22:00'),
                            Forms\Components\TimePicker::make('curfew_end')
                                ->label('Curfew End')
                                ->seconds(false)
                                ->default('06:00'),
                            Forms\Components\Toggle::make('overnight_enabled')
                                ->label('Overnight Out-Pass')
                                ->default(false),
                        ]),
                        Grid::make(2)->schema([
                            Forms\Components\TimePicker::make('visiting_start')
                                ->label('Visiting Hours Start')
                                ->seconds(false),
                            Forms\Components\TimePicker::make('visiting_end')
                                ->label('Visiting Hours End')
                                ->seconds(false),
                        ]),
                    ])
                    ->collapsed(),
                Section::make('Address')
                    ->schema([
                        Forms\Components\TextInput::make('address.line1')
                            ->label('Street Address')
                            ->maxLength(255),
                        Grid::make(3)->schema([
                            Forms\Components\TextInput::make('address.city')
                                ->label('City')
                                ->maxLength(100),
                            Forms\Components\TextInput::make('address.state')
                                ->label('State')
                                ->maxLength(100),
                            Forms\Components\TextInput::make('address.postal_code')
                                ->label('Pincode')
                                ->maxLength(6)
                                ->numeric(),
                        ]),
                    ])
                    ->collapsed(),
                Section::make('Room Configuration')
                    ->description('Configure floors and rooms for this hostel. Rooms and beds will be auto-generated.')
                    ->schema([
                        Repeater::make('floor_config')
                            ->label('Floors')
                            ->schema([
                                Grid::make(3)->schema([
                                    Forms\Components\TextInput::make('floor_number')
                                        ->label('Floor #')
                                        ->required()
                                        ->numeric()
                                        ->minValue(1)
                                        ->maxValue(50)
                                        ->default(1),
                                    Forms\Components\Select::make('room_capacity')
                                        ->label('Beds per Room')
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
                                    Forms\Components\TextInput::make('room_count')
                                        ->label('Number of Rooms')
                                        ->required()
                                        ->numeric()
                                        ->minValue(1)
                                        ->maxValue(100)
                                        ->default(10),
                                ]),
                            ])
                            ->defaultItems(1)
                            ->minItems(1)
                            ->addActionLabel('Add Floor')
                            ->reorderable(false),
                    ])
                    ->visible(fn ($record) => $record === null), // Only show for new hostels
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->color('primary'),
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('gender_mode')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'boys' => 'info',
                        'girls' => 'danger',
                        'co-ed' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                TextColumn::make('rooms_count')
                    ->label('Rooms')
                    ->counts('rooms')
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('students_count')
                    ->label('Students')
                    ->counts('students')
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('occupancy')
                    ->label('Occupancy')
                    ->getStateUsing(function (Hostel $record): string {
                        $totalBeds = RoomBed::where('hostel_id', $record->id)->count();
                        $occupiedBeds = RoomBed::where('hostel_id', $record->id)
                            ->where('status', 'occupied')
                            ->count();
                        if ($totalBeds === 0) {
                            return '0%';
                        }
                        return round(($occupiedBeds / $totalBeds) * 100) . '%';
                    })
                    ->alignCenter(),
                TextColumn::make('created_at')
                    ->label('Added')
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('addHostel')
                    ->label('Add Hostel')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->visible(fn () => auth()->user()?->hasRole('Super Admin'))
                    ->modalHeading('Add New Hostel')
                    ->modalWidth('4xl')
                    ->form([
                        Section::make('Hostel Details')
                            ->schema([
                                Grid::make(3)->schema([
                                    Forms\Components\TextInput::make('code')
                                        ->label('Hostel Code')
                                        ->required()
                                        ->maxLength(20)
                                        ->placeholder('H1'),
                                    Forms\Components\TextInput::make('name')
                                        ->label('Hostel Name')
                                        ->required()
                                        ->maxLength(120)
                                        ->placeholder("Boys' Hostel 1"),
                                    Forms\Components\Select::make('gender_mode')
                                        ->label('Gender Mode')
                                        ->required()
                                        ->options([
                                            'boys' => 'Boys Hostel',
                                            'girls' => 'Girls Hostel',
                                            'co-ed' => 'Co-Ed Hostel',
                                        ]),
                                ]),
                            ]),
                        Section::make('Room Configuration')
                            ->description('Configure floors and rooms for this hostel.')
                            ->schema([
                                Repeater::make('floor_config')
                                    ->label('Floors')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            Forms\Components\TextInput::make('floor_number')
                                                ->label('Floor #')
                                                ->required()
                                                ->numeric()
                                                ->default(1),
                                            Forms\Components\Select::make('room_capacity')
                                                ->label('Beds per Room')
                                                ->required()
                                                ->options([
                                                    1 => 'Single (1 bed)',
                                                    2 => 'Double (2 beds)',
                                                    3 => 'Triple (3 beds)',
                                                    4 => 'Quad (4 beds)',
                                                ])
                                                ->default(2),
                                            Forms\Components\TextInput::make('room_count')
                                                ->label('Number of Rooms')
                                                ->required()
                                                ->numeric()
                                                ->default(10),
                                        ]),
                                    ])
                                    ->defaultItems(1)
                                    ->minItems(1)
                                    ->addActionLabel('Add Floor')
                                    ->reorderable(false),
                            ]),
                    ])
                    ->action(function (array $data): void {
                        $tenant = $this->getOwnerRecord();
                        $campus = Campus::where('tenant_id', $tenant->id)->first();

                        $data['tenant_id'] = $tenant->id;
                        $data['campus_id'] = $campus?->id;

                        $floorConfig = $data['floor_config'] ?? [];
                        unset($data['floor_config']);

                        DB::transaction(function () use ($data, $floorConfig): void {
                            $hostel = Hostel::create($data);

                            // Generate rooms and beds from floor configuration
                            $this->generateRoomsForHostel($hostel, $floorConfig);

                            Log::info('Hostel added post-activation', [
                                'tenant_id' => $hostel->tenant_id,
                                'hostel_id' => $hostel->id,
                                'hostel_code' => $hostel->code,
                                'added_by' => Auth::id(),
                            ]);
                        });

                        Notification::make()
                            ->title('Hostel added')
                            ->body('The new hostel has been created with rooms and beds.')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('viewRooms')
                    ->label('Rooms')
                    ->icon('heroicon-o-view-columns')
                    ->color('info')
                    ->url(fn (Hostel $record): string => route('filament.admin.resources.hostels.index', [
                        'tableFilters[tenant][value]' => $record->tenant_id,
                    ])),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->where('tenant_id', $this->getOwnerRecord()->id)
            );
    }

    /**
     * Generate rooms and beds for a newly added hostel.
     */
    protected function generateRoomsForHostel(Hostel $hostel, array $floorConfigs): void
    {
        $bedLabels = ['A', 'B', 'C', 'D', 'E', 'F'];

        foreach ($floorConfigs as $config) {
            $floorNumber = max(1, (int) ($config['floor_number'] ?? 1));
            $roomCapacity = max(1, (int) ($config['room_capacity'] ?? 4));
            $roomCount = max(0, (int) ($config['room_count'] ?? 0));

            if ($roomCount <= 0) {
                continue;
            }

            for ($i = 1; $i <= $roomCount; $i++) {
                $roomNumber = str_pad((string) $floorNumber, 2, '0', STR_PAD_LEFT)
                    . '-'
                    . str_pad((string) $i, 3, '0', STR_PAD_LEFT);

                $roomTypeName = match ($roomCapacity) {
                    1 => 'Single',
                    2 => 'Double',
                    3 => 'Triple',
                    4 => 'Quad',
                    5 => 'Quint',
                    6 => 'Six',
                    default => 'Quad',
                };

                $room = Room::create([
                    'tenant_id' => $hostel->tenant_id,
                    'campus_id' => $hostel->campus_id,
                    'hostel_id' => $hostel->id,
                    'block_code' => 'A',
                    'floor_code' => (string) $floorNumber,
                    'number' => $roomNumber,
                    'capacity' => $roomCapacity,
                    'room_type' => $roomTypeName,
                    'is_active' => true,
                ]);

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
    }
}
