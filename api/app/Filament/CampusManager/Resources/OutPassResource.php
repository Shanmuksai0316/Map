<?php

namespace App\Filament\CampusManager\Resources;

use App\Enums\OutPassStatus;
use App\Filament\CampusManager\Resources\OutPassResource\Pages;
use App\Filament\CampusManager\Resources\OutPassResource\RelationManagers\HistoriesRelationManager;
use App\Models\Domain\OutPass\OutPass;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action as RowAction;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class OutPassResource extends Resource
{
    protected static ?string $model = OutPass::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $tenantOwnershipRelationshipName = 'tenant';

    protected static ?string $navigationLabel = 'Out-Pass Requests';

    // Hidden - replaced by OutpassRequests page
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Decision')
                    ->schema([
                        Select::make('status')
                            ->options([
                                OutPassStatus::APPROVED->value => OutPassStatus::APPROVED->label(),
                                OutPassStatus::DECLINED->value => OutPassStatus::DECLINED->label(),
                            ])
                            ->required(),
                        Textarea::make('note')
                            ->label('Decision note')
                            ->maxLength(500),
                    ])->description('Approve or decline the request'),
            ]);
    }

    public static function table(Table $table): Table
    {
        $tenantId = Auth::user()?->tenant_id;

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['student.user', 'hostel'])
                ->where('tenant_id', $tenantId))
            ->columns([
                TextColumn::make('student.user.name')->label('Student')->searchable()->sortable(),
                TextColumn::make('hostel.name')->label('Hostel')->sortable(),
                BadgeColumn::make('reason')->label('Reason')->colors([
                    'primary' => 'normal',
                    'warning' => 'leave',
                    'danger' => 'sick',
                ]),
                IconColumn::make('overnight')->label('Overnight')->boolean(),
                BadgeColumn::make('status')->label('Status')->colors([
                    OutPassStatus::PENDING->color() => OutPassStatus::PENDING->value,
                    OutPassStatus::APPROVED->color() => OutPassStatus::APPROVED->value,
                    OutPassStatus::DECLINED->color() => OutPassStatus::DECLINED->value,
                    OutPassStatus::CANCELLED->color() => OutPassStatus::CANCELLED->value,
                    OutPassStatus::EXPIRED->color() => OutPassStatus::EXPIRED->value,
                ])->sortable(),
                TextColumn::make('requested_at')->label('Requested')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('valid_until')->label('Valid Until')->dateTime('d M Y H:i'),
            ])
            ->defaultSort('requested_at', 'desc')
            ->filters([
                SelectFilter::make('hostel')->relationship('hostel', 'name')->label('Hostel'),
                SelectFilter::make('status')->options([
                    OutPassStatus::PENDING->value => OutPassStatus::PENDING->label(),
                    OutPassStatus::APPROVED->value => OutPassStatus::APPROVED->label(),
                    OutPassStatus::DECLINED->value => OutPassStatus::DECLINED->label(),
                    OutPassStatus::CANCELLED->value => OutPassStatus::CANCELLED->label(),
                ]),
                TernaryFilter::make('overnight')->label('Overnight'),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (OutPass $record) => $record->status === OutPassStatus::PENDING),
                RowAction::make('cancel')
                    ->label('Cancel')
                    ->requiresConfirmation()
                    ->color('danger')
                    ->visible(fn (OutPass $record) => in_array($record->status, [OutPassStatus::PENDING, OutPassStatus::APPROVED], true))
                    ->action(function (OutPass $record): void {
                        $previous = $record->status;
                        $record->forceFill([
                            'status' => OutPassStatus::CANCELLED,
                            'decided_at' => now(),
                            'decision_by' => Auth::id(),
                        ])->save();
                        $record->recordHistory($previous, OutPassStatus::CANCELLED, 'Cancelled via panel');
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    BulkAction::make('approve')
                        ->label('Approve selected')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $records->each(function (OutPass $record): void {
                                if ($record->status !== OutPassStatus::PENDING) {
                                    return;
                                }

                                $previous = $record->status;
                                $record->forceFill([
                                    'status' => OutPassStatus::APPROVED,
                                    'decided_at' => now(),
                                    'decision_by' => Auth::id(),
                                ])->save();
                                $record->recordHistory($previous, OutPassStatus::APPROVED, 'Bulk approval');
                            });
                        }),
                    BulkAction::make('decline')
                        ->label('Decline selected')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $records->each(function (OutPass $record): void {
                                if ($record->status !== OutPassStatus::PENDING) {
                                    return;
                                }

                                $previous = $record->status;
                                $record->forceFill([
                                    'status' => OutPassStatus::DECLINED,
                                    'decided_at' => now(),
                                    'decision_by' => Auth::id(),
                                ])->save();
                                $record->recordHistory($previous, OutPassStatus::DECLINED, 'Bulk decline');
                            });
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            HistoriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOutPasses::route('/'),
            'edit' => Pages\EditOutPass::route('/{record}/edit'),
        ];
    }
}
