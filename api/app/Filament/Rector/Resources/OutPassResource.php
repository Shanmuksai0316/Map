<?php

namespace App\Filament\Rector\Resources;

use App\Enums\OutPassStatus;
use App\Filament\Rector\Resources\OutPassResource\Pages;
use App\Filament\Rector\Resources\OutPassResource\RelationManagers\HistoriesRelationManager;
use App\Models\Domain\OutPass\OutPass;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action as RowAction;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;

class OutPassResource extends Resource
{
    protected static ?string $model = OutPass::class;

    protected static ?string $navigationIcon = null;

    protected static ?string $navigationLabel = 'Out-Pass Approvals';

    protected static ?string $navigationGroup = 'Approvals';

    protected static ?int $navigationSort = 1;

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
                            ->required()
                            ->disabled(fn (OutPass $record) => $record->status !== OutPassStatus::PENDING),
                        Textarea::make('note')
                            ->label('Decision note')
                            ->maxLength(500)
                            ->required(fn (OutPass $record) => $record->status === OutPassStatus::PENDING),
                    ])->description('Approve or decline the request'),
            ]);
    }

    public static function table(Table $table): Table
    {
        $user = Auth::user();
        $tenantId = $user?->tenant_id;

        // Get rector's assigned hostel IDs from staff_assignments
        $assignedHostelIds = DB::table('staff_assignments')
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->whereNull('revoked_at')
            ->whereNotNull('hostel_id')
            ->pluck('hostel_id')
            ->toArray();

        return $table
            ->modifyQueryUsing(function (Builder $query) use ($tenantId, $assignedHostelIds) {
                $query->with(['student.user', 'hostel'])
                    ->where('tenant_id', $tenantId);
                
                // If rector has assigned hostels, filter by them
                // If no assigned hostels, show all tenant out passes (rector oversees all)
                if (!empty($assignedHostelIds)) {
                    $query->whereIn('hostel_id', $assignedHostelIds);
                }
                // If empty, don't filter by hostel - show all tenant out passes
                
                return $query->orderBy('requested_at', 'asc'); // Oldest first
            })
            ->columns([
                TextColumn::make('unique_id')
                    ->label('Request ID')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('student.user.name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('room_number')
                    ->label('Room Number')
                    ->getStateUsing(function (OutPass $record) {
                        $allocation = $record->student->allocation;
                        if (!$allocation || !$allocation->bed) {
                            return '--';
                        }
                        $room = $allocation->bed->room;
                        return $room->block_code . '-' . $room->floor_code . $room->room_no;
                    })
                    ->searchable(),
                BadgeColumn::make('reason')
                    ->label('Purpose')
                    ->colors([
                        'primary' => 'normal',
                        'warning' => 'leave',
                        'danger' => 'sick',
                    ])
                    ->formatStateUsing(function ($state) {
                        if ($state instanceof \App\Enums\OutPassType) {
                            return ucfirst($state->value);
                        }
                        return ucfirst((string) $state);
                    }),
                TextColumn::make('requested_for')
                    ->label('Going Out Date')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('requested_at')
                    ->label('Submitted At')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sla_status')
                    ->label('SLA Status')
                    ->getStateUsing(function (OutPass $record) {
                        if ($record->status !== OutPassStatus::PENDING) return null;

                        $hours = now()->diffInHours($record->requested_at);
                        $remaining = 2 - $hours; // 2-hour SLA for Out-Pass

                        if ($remaining <= 0) {
                            $breachHours = abs($remaining);
                            return "Overdue: +{$breachHours}h";
                        } elseif ($remaining <= 0.5) { // 30 minutes remaining
                            return "Due: {$remaining}h";
                        }
                        return "{$remaining}h left";
                    })
                    ->badge()
                    ->color(function ($record) {
                        if ($record->status !== OutPassStatus::PENDING) return 'gray';
                        $hours = now()->diffInHours($record->requested_at);
                        $remaining = 2 - $hours;
                        if ($remaining <= 0) return 'danger';
                        if ($remaining <= 0.5) return 'warning';
                        return 'success';
                    }),
            ])
            ->defaultSort('requested_at', 'asc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'declined' => 'Rejected',
                        'expired' => 'Expired',
                        'cancelled' => 'Cancelled',
                        'checked_out' => 'Out',
                        'checked_in' => 'Returned',
                    ])
                    ->placeholder('All statuses'),
                SelectFilter::make('hostel')
                    ->relationship('hostel', 'name')
                    ->label('Hostel'),
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('requested_for', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('requested_for', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                ViewAction::make(),
                RowAction::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Out-Pass')
                    ->modalDescription('Approve this out-pass request.')
                    ->form([
                        Textarea::make('note')
                            ->label('Approval Note')
                            ->maxLength(500)
                            ->required(),
                    ])
                    ->visible(fn (OutPass $record) => $record->status === OutPassStatus::PENDING)
                    ->action(function (OutPass $record, array $data): void {
                        $user = Auth::user();

                        // Check for 24-hour expiry
                        $expiryTime = $record->requested_at->copy()->addHours(24);
                        if (now()->isAfter($expiryTime)) {
                            $previous = $record->status;
                            $record->forceFill([
                                'status' => OutPassStatus::EXPIRED,
                                'decided_at' => now(),
                                'note' => 'Automatically expired after 24 hours',
                                'decision_by' => null,
                            ])->save();
                            $record->recordHistory($previous, OutPassStatus::EXPIRED, 'Automatically expired');
                            
                            Notification::make()
                                ->warning()
                                ->title('Out-Pass Expired')
                                ->body('This out-pass has expired and cannot be approved.')
                                ->send();
                            return;
                        }

                        $previous = $record->status;
                        $record->forceFill([
                            'status' => OutPassStatus::APPROVED,
                            'decided_at' => now(),
                            'note' => $data['note'] ?? 'Approved by Rector',
                            'decision_by' => $user->id,
                        ])->save();
                        $record->recordHistory($previous, OutPassStatus::APPROVED, $data['note'] ?? 'Approved by Rector', actorId: $user->id);
                        
                        // Dispatch notification job
                        dispatch(new \App\Jobs\SendApprovalNotification(
                            approvalType: 'outpass',
                            recordId: $record->id,
                            decision: 'approved',
                            note: $data['note'] ?? null,
                            studentId: $record->student->user->id,
                            rectorId: $user->id,
                            tenantId: $record->tenant_id
                        ));
                        
                        Notification::make()
                            ->success()
                            ->title('Out-Pass Approved')
                            ->body("Out-pass for {$record->student->user->name} has been approved. Notifications sent.")
                            ->send();
                    }),
                RowAction::make('decline')
                    ->label('Decline')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Decline Out-Pass')
                    ->form([
                        Textarea::make('note')
                            ->label('Decline Reason')
                            ->maxLength(500)
                            ->required(),
                    ])
                    ->visible(fn (OutPass $record) => $record->status === OutPassStatus::PENDING)
                    ->action(function (OutPass $record, array $data): void {
                        $user = Auth::user();
                        $previous = $record->status;
                        $record->forceFill([
                            'status' => OutPassStatus::DECLINED,
                            'decided_at' => now(),
                            'note' => $data['note'],
                            'decision_by' => $user->id,
                        ])->save();
                        $record->recordHistory($previous, OutPassStatus::DECLINED, $data['note'], actorId: $user->id);
                        
                        // Dispatch notification job
                        dispatch(new \App\Jobs\SendApprovalNotification(
                            approvalType: 'outpass',
                            recordId: $record->id,
                            decision: 'rejected',
                            note: $data['note'],
                            studentId: $record->student->user->id,
                            rectorId: $user->id,
                            tenantId: $record->tenant_id
                        ));
                        
                        Notification::make()
                            ->success()
                            ->title('Out-Pass Declined')
                            ->body("Out-pass for {$record->student->user->name} has been declined. Notifications sent.")
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    BulkAction::make('approve')
                        ->label('Approve Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Bulk Approve Out-Passes')
                        ->modalDescription('Approve all selected out-pass requests.')
                        ->form([
                            Textarea::make('note')
                                ->label('Approval Note')
                                ->maxLength(500)
                                ->default('Bulk approved by Rector'),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $user = Auth::user();

                            $approved = 0;
                            $failed = 0;
                            $errors = [];

                            foreach ($records as $record) {
                                if ($record->status !== OutPassStatus::PENDING) {
                                    $failed++;
                                    $errors[] = "Out-pass #{$record->id} is not pending";
                                    continue;
                                }

                                // Check for 24-hour expiry
                                $expiryTime = $record->requested_at->copy()->addHours(24);
                                if (now()->isAfter($expiryTime)) {
                                    $previous = $record->status;
                                    $record->forceFill([
                                        'status' => OutPassStatus::EXPIRED,
                                        'decided_at' => now(),
                                        'note' => 'Automatically expired after 24 hours',
                                        'decision_by' => null,
                                    ])->save();
                                    $record->recordHistory($previous, OutPassStatus::EXPIRED, 'Automatically expired');
                                    $failed++;
                                    $errors[] = "Out-pass #{$record->id} has expired";
                                    continue;
                                }

                                try {
                                    $previous = $record->status;
                                    $record->forceFill([
                                        'status' => OutPassStatus::APPROVED,
                                        'decided_at' => now(),
                                        'note' => $data['note'] ?? 'Bulk approved by Rector',
                                        'decision_by' => $user->id,
                                    ])->save();
                                    $record->recordHistory($previous, OutPassStatus::APPROVED, $data['note'] ?? 'Bulk approved by Rector', actorId: $user->id);
                                    
                                    // Dispatch notification job
                                    dispatch(new \App\Jobs\SendApprovalNotification(
                                        approvalType: 'outpass',
                                        recordId: $record->id,
                                        decision: 'approved',
                                        note: $data['note'] ?? null,
                                        studentId: $record->student->user->id,
                                        rectorId: $user->id,
                                        tenantId: $record->tenant_id
                                    ));
                                    
                                    $approved++;
                                } catch (\Exception $e) {
                                    $failed++;
                                    $errors[] = "Out-pass #{$record->id}: {$e->getMessage()}";
                                }
                            }

                            if ($approved > 0) {
                                Notification::make()
                                    ->success()
                                    ->title('Bulk Approval Complete')
                                    ->body("Approved {$approved} out-pass(es)" . ($failed > 0 ? ", {$failed} failed" : ''))
                                    ->send();
                            } else {
                                Notification::make()
                                    ->warning()
                                    ->title('Bulk Approval Failed')
                                    ->body('No out-passes were approved. ' . implode(', ', $errors))
                                    ->send();
                            }
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
            'view' => Pages\ViewOutPass::route('/{record}'),
        ];
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user && $user->hasRole('Rector');
    }
}
