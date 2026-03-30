<?php

namespace App\Filament\CampusManager\Resources;

use App\Domain\Visitors\Models\GuestVisit;
use App\Filament\CampusManager\Resources\GuestVisitResource\Pages;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GuestVisitResource extends Resource
{
    protected static ?string $model = GuestVisit::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Security';

    protected static ?string $navigationLabel = 'Guest Visits';

    protected static ?int $navigationSort = 21;

    // Hidden - replaced by GuestEntryRequests page
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            // No create/edit in this slice - read only
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('visit_date')
                    ->label('Visit Date')
                    ->date('Y-m-d')
                    ->sortable(),
                Tables\Columns\TextColumn::make('student.user.name')
                    ->label('Student')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Guest Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('whom_to_meet')
                    ->label('Whom to Meet')
                    ->limit(30),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pre_registered',
                        'success' => 'allowed',
                        'danger' => 'denied',
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered At')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pre_registered' => 'Pre-Registered',
                        'allowed' => 'Allowed',
                        'denied' => 'Denied',
                    ]),
                Tables\Filters\Filter::make('visit_date')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('date')
                            ->label('Visit Date')
                            ->default(today()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['date'],
                            fn (Builder $query, $date): Builder => $query->whereDate('visit_date', $date),
                        );
                    }),
            ])
            ->defaultSort('visit_date', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGuestVisits::route('/'),
            'view' => Pages\ViewGuestVisit::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        return parent::getEloquentQuery()
            ->where('tenant_id', $user->tenant_id)
            ->whereDate('visit_date', '>=', today()->subDays(7)); // Show last 7 days
    }

    public static function canCreate(): bool
    {
        return false; // Read-only in this slice
    }
}

