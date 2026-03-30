<?php

namespace App\Filament\CampusManager\Resources;

use App\Filament\CampusManager\Resources\SportsEventResource\Pages;
use App\Models\SportsEvent;
use App\Enums\SportsEventStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SportsEventResource extends Resource
{
    protected static ?string $model = SportsEvent::class;

    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationGroup = 'Add-ons';

    protected static ?string $navigationLabel = 'Sports Events';

    protected static ?int $navigationSort = 11;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Event Details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Event Name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('sport')
                            ->label('Sport')
                            ->options([
                                'cricket' => 'Cricket',
                                'football' => 'Football',
                                'basketball' => 'Basketball',
                                'volleyball' => 'Volleyball',
                                'badminton' => 'Badminton',
                                'table_tennis' => 'Table Tennis',
                                'tennis' => 'Tennis',
                                'athletics' => 'Athletics',
                                'swimming' => 'Swimming',
                                'other' => 'Other',
                            ])
                            ->required()
                            ->searchable()
                            ->native(false),
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('venue')
                            ->label('Venue')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('hostel_id')
                            ->label('Hostel')
                            ->relationship('hostel', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Leave empty for all hostels'),
                    ]),

                Forms\Components\Section::make('Schedule')
                    ->columns(2)
                    ->schema([
                        Forms\Components\DateTimePicker::make('scheduled_at')
                            ->label('Start Time')
                            ->required()
                            ->native(false)
                            ->seconds(false)
                            ->timezone('Asia/Kolkata')
                            ->minDate(now()),
                        Forms\Components\DateTimePicker::make('end_time')
                            ->label('End Time')
                            ->required()
                            ->native(false)
                            ->seconds(false)
                            ->timezone('Asia/Kolkata')
                            ->after('scheduled_at'),
                        Forms\Components\DateTimePicker::make('registration_deadline')
                            ->label('Registration Deadline')
                            ->required()
                            ->native(false)
                            ->seconds(false)
                            ->timezone('Asia/Kolkata')
                            ->before('scheduled_at'),
                    ]),

                Forms\Components\Section::make('Registration')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('capacity')
                            ->label('Maximum Participants')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1000)
                            ->default(50)
                            ->helperText('Maximum number of students who can register'),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'upcoming' => 'Upcoming',
                                'ongoing' => 'Ongoing',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('upcoming')
                            ->required()
                            ->native(false),
                        Forms\Components\Textarea::make('requirements')
                            ->label('Requirements')
                            ->rows(2)
                            ->helperText('Any special requirements or rules')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Event Name')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('sport')
                    ->label('Sport')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state))),
                Tables\Columns\TextColumn::make('venue')
                    ->label('Venue')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('Scheduled')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('enrollments_count')
                    ->label('Enrolled')
                    ->counts('enrollments')
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\TextColumn::make('capacity')
                    ->label('Capacity')
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'warning' => 'upcoming',
                        'primary' => 'ongoing',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('hostel.name')
                    ->label('Hostel')
                    ->default('All Hostels')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('sport')
                    ->options([
                        'cricket' => 'Cricket',
                        'football' => 'Football',
                        'basketball' => 'Basketball',
                        'volleyball' => 'Volleyball',
                        'badminton' => 'Badminton',
                        'table_tennis' => 'Table Tennis',
                        'tennis' => 'Tennis',
                        'athletics' => 'Athletics',
                        'swimming' => 'Swimming',
                        'other' => 'Other',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'upcoming' => 'Upcoming',
                        'ongoing' => 'Ongoing',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('hostel_id')
                    ->label('Hostel')
                    ->relationship('hostel', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('upcoming')
                    ->label('Upcoming Events')
                    ->query(fn (Builder $query): Builder => $query->where('status', 'upcoming')
                        ->where('scheduled_at', '>', now())),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('viewEnrollments')
                    ->label('Enrollments')
                    ->icon('heroicon-o-users')
                    ->color('info')
                    ->url(fn (SportsEvent $record): string => route('filament.campus-manager.resources.sports-events.enrollments', ['record' => $record->id]))
                    ->openUrlInNewTab(false),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('scheduled_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSportsEvents::route('/'),
            'create' => Pages\CreateSportsEvent::route('/create'),
            'view' => Pages\ViewSportsEvent::route('/{record}'),
            'edit' => Pages\EditSportsEvent::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['hostel', 'campus'])
            ->withCount('enrollments');
    }

    public static function canAccess(): bool
    {
        // Only visible if Sports add-on is enabled
        if (!config('features.sports_addon', false)) {
            return false;
        }

        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        return Auth::check() && $user && $user->hasAnyRole(['Campus Manager', 'Sports Manager', 'Super Admin']);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::canAccess();
    }
}

