<?php

namespace App\Filament\CampusManager\Resources;

use App\Filament\CampusManager\Resources\LaundryRequestResource\Pages;
use App\Models\LaundryRequest;
use App\Enums\LaundryRequestStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class LaundryRequestResource extends Resource
{
    protected static ?string $model = LaundryRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'Add-ons';

    protected static ?string $navigationLabel = 'Laundry Requests';

    protected static ?int $navigationSort = 12;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Request Details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('student_id')
                            ->label('Student')
                            ->relationship('student.user', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn ($record) => $record !== null),
                        Forms\Components\Select::make('hostel_id')
                            ->label('Hostel')
                            ->relationship('hostel', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn ($record) => $record !== null),
                        Forms\Components\Select::make('service_type')
                            ->label('Service Type')
                            ->options([
                                'wash_iron' => 'Wash & Iron',
                                'dry_clean' => 'Dry Clean',
                                'iron_only' => 'Iron Only',
                            ])
                            ->required()
                            ->native(false),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'requested' => 'Requested',
                                'processing' => 'Processing',
                                'ready' => 'Ready',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('requested')
                            ->required()
                            ->native(false),
                    ]),

                Forms\Components\Section::make('Item Details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('bag_count')
                            ->label('Number of Bags')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(10)
                            ->default(1)
                            ->required(),
                        Forms\Components\TextInput::make('weight_kg')
                            ->label('Weight (kg)')
                            ->numeric()
                            ->minValue(0.1)
                            ->maxValue(50)
                            ->step(0.1)
                            ->suffix('kg'),
                        Forms\Components\Textarea::make('special_instructions')
                            ->label('Special Instructions')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Processing Information')
                    ->columns(2)
                    ->schema([
                        Forms\Components\DateTimePicker::make('requested_at')
                            ->label('Requested At')
                            ->default(now())
                            ->native(false)
                            ->seconds(false)
                            ->timezone('Asia/Kolkata')
                            ->disabled(fn ($record) => $record !== null),
                        Forms\Components\DateTimePicker::make('estimated_completion_at')
                            ->label('Estimated Completion')
                            ->native(false)
                            ->seconds(false)
                            ->timezone('Asia/Kolkata')
                            ->helperText('Estimated time when laundry will be ready'),
                        Forms\Components\Textarea::make('collection_notes')
                            ->label('Collection Notes')
                            ->rows(2),
                        Forms\Components\Textarea::make('delivery_notes')
                            ->label('Delivery Notes')
                            ->rows(2),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Payment')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('payment_status')
                            ->label('Payment Status')
                            ->options([
                                'pending' => 'Pending',
                                'paid' => 'Paid',
                                'failed' => 'Failed',
                            ])
                            ->default('pending')
                            ->native(false),
                        Forms\Components\TextInput::make('payment_amount')
                            ->label('Amount')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('₹')
                            ->step(0.01),
                        Forms\Components\TextInput::make('payment_method')
                            ->label('Payment Method')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('payment_reference')
                            ->label('Payment Reference')
                            ->maxLength(100),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('student.user.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('hostel.name')
                    ->label('Hostel')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('service_type')
                    ->label('Service')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'wash_iron' => 'Wash & Iron',
                        'dry_clean' => 'Dry Clean',
                        'iron_only' => 'Iron Only',
                        default => ucwords(str_replace('_', ' ', $state)),
                    }),
                Tables\Columns\TextColumn::make('bag_count')
                    ->label('Bags')
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\TextColumn::make('weight_kg')
                    ->label('Weight')
                    ->suffix(' kg')
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'warning' => 'requested',
                        'primary' => 'processing',
                        'info' => 'ready',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'paid',
                        'danger' => 'failed',
                    ])
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('payment_amount')
                    ->label('Amount')
                    ->money('INR')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('requested_at')
                    ->label('Requested')
                    ->dateTime('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('estimated_completion_at')
                    ->label('Est. Completion')
                    ->dateTime('d M H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'requested' => 'Requested',
                        'processing' => 'Processing',
                        'ready' => 'Ready',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('service_type')
                    ->label('Service Type')
                    ->options([
                        'wash_iron' => 'Wash & Iron',
                        'dry_clean' => 'Dry Clean',
                        'iron_only' => 'Iron Only',
                    ]),
                Tables\Filters\SelectFilter::make('payment_status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'failed' => 'Failed',
                    ]),
                Tables\Filters\SelectFilter::make('hostel_id')
                    ->label('Hostel')
                    ->relationship('hostel', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('active')
                    ->label('Active Requests')
                    ->query(fn (Builder $query): Builder => $query->whereIn('status', ['requested', 'processing', 'ready'])),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('markReady')
                    ->label('Mark Ready')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (LaundryRequest $record) => $record->update([
                        'status' => 'ready',
                        'ready_at' => now(),
                    ]))
                    ->visible(fn (LaundryRequest $record) => $record->status === 'processing'),
                Tables\Actions\Action::make('markCompleted')
                    ->label('Complete')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (LaundryRequest $record) => $record->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                        'actual_completion_at' => now(),
                    ]))
                    ->visible(fn (LaundryRequest $record) => $record->status === 'ready'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('requested_at', 'desc');
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
            'index' => Pages\ListLaundryRequests::route('/'),
            'create' => Pages\CreateLaundryRequest::route('/create'),
            'view' => Pages\ViewLaundryRequest::route('/{record}'),
            'edit' => Pages\EditLaundryRequest::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['student.user', 'hostel']);
    }

    public static function canAccess(): bool
    {
        // Only visible if Laundry add-on is enabled
        if (!config('features.laundry_addon', false)) {
            return false;
        }

        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        return Auth::check() && $user && $user->hasAnyRole(['Campus Manager', 'Laundry Manager', 'Super Admin']);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::canAccess();
    }
}

