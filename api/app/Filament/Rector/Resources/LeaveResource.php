<?php

namespace App\Filament\Rector\Resources;

use App\Domain\Leaves\Models\Leave;
use App\Domain\SickLeaves\Models\SickLeave;
use App\Models\CombinedLeaveRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action as RowAction;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveResource extends Resource
{
    protected static ?string $model = CombinedLeaveRequest::class;

    protected static ?string $navigationIcon = null;

    protected static ?string $navigationLabel = 'Leave Approvals';

    protected static ?string $navigationGroup = 'Approvals';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Decision')
                    ->schema([
                        Forms\Components\Textarea::make('note')
                            ->label('Approval Note')
                            ->placeholder('You can type or select a template...')
                            ->maxLength(500)
                            ->required(),
                        Forms\Components\Select::make('template')
                            ->label('Quick Templates')
                            ->options([
                                'Approved as requested' => 'Approved as requested',
                                'Approved with conditions' => 'Approved with conditions',
                                'Emergency approved' => 'Emergency approved',
                            ])
                            ->reactive()
                            ->afterStateUpdated(fn ($state, callable $set) => $set('note', $state)),
                    ])->description('Approve or reject the leave request'),
            ]);
    }

    public static function table(Table $table): Table
    {
        $user = Auth::user();
        $tenantId = $user?->tenant_id;

        // Get rector's assigned hostel IDs from staff_assignments
        // Rectors are campus-wide, so if they have no assignments, show all leaves
        $assignedHostelIds = DB::table('staff_assignments')
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->whereNull('revoked_at')
            ->whereNotNull('hostel_id')
            ->pluck('hostel_id')
            ->toArray();

        return $table
            ->query(function () use ($tenantId, $assignedHostelIds) {
                // CombinedLeaveRequest UNION does NOT filter by tenant_id,
                // so we must apply it here to avoid cross-tenant data leakage.
                $query = CombinedLeaveRequest::query()
                    ->where('tenant_id', $tenantId);

                return $query->orderBy('submitted_at', 'asc');
            })
            ->paginated(false)
            ->columns([
                TextColumn::make('unique_id')
                    ->label('Request ID')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('student_name')
                    ->label('Name')
                    ->getStateUsing(function ($record) {
                        $student = $record->type === 'leave'
                            ? $record->student
                            : $record->student;
                        return $student?->user?->name ?? 'Unknown';
                    })
                    ->searchable(),
                TextColumn::make('room_number')
                    ->label('Room Number')
                    ->getStateUsing(function ($record) {
                        $student = $record->type === 'leave'
                            ? \App\Domain\Leaves\Models\Leave::find($record->id)?->student
                            : \App\Domain\SickLeaves\Models\SickLeave::find($record->id)?->student;
                        
                        if (!$student) return '--';
                        
                        // Get active room allocation
                        $allocation = $student->roomAllocations()
                            ->where('is_active', true)
                            ->with(['roomBed.room'])
                            ->first();
                        
                        if (!$allocation || !$allocation->roomBed || !$allocation->roomBed->room) {
                            return '--';
                        }
                        
                        $room = $allocation->roomBed->room;
                        return ($room->block_code ?? '') . '-' . ($room->floor_code ?? '') . ($room->room_no ?? $room->number ?? '--');
                    }),
                TextColumn::make('reason')
                    ->label('Purpose')
                    ->limit(30),
                TextColumn::make('duration')
                    ->label('From Date - To Date')
                    ->getStateUsing(function ($record) {
                        if ($record->type === 'leave' && $record->from_date && $record->to_date) {
                            return \Carbon\Carbon::parse($record->from_date)->format('d M Y') . ' - ' . 
                                   \Carbon\Carbon::parse($record->to_date)->format('d M Y');
                        }
                        return 'N/A';
                    }),
                BadgeColumn::make('type')
                    ->label('Type')
                    ->colors([
                        'primary' => 'leave',
                        'warning' => 'sick_leave',
                    ])
                    ->formatStateUsing(fn ($state) => match($state) {
                        'leave' => 'Leave',
                        'sick_leave' => 'Sick Leave',
                        default => $state,
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('submitted_at')
                    ->label('Submitted At')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('submitted_at', 'asc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->placeholder('All statuses'),
                SelectFilter::make('type')
                    ->options([
                        'leave' => 'Leave',
                        'sick_leave' => 'Sick Leave',
                    ])
                    ->label('Request Type'),
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Until Date'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['from'],
                                fn ($query, $date) => $query->whereDate('submitted_at', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn ($query, $date) => $query->whereDate('submitted_at', '<=', $date),
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
                    ->modalHeading('Approve Leave Request')
                    ->modalDescription('Approve this leave request.')
                    ->form([
                        Forms\Components\Textarea::make('note')
                            ->label('Approval Note')
                            ->placeholder('You can type or select a template...')
                            ->maxLength(500)
                            ->required(),
                        Forms\Components\Select::make('template')
                            ->label('Quick Templates')
                            ->options([
                                'Approved as requested' => 'Approved as requested',
                                'Approved with conditions' => 'Approved with conditions',
                                'Emergency approved' => 'Emergency approved',
                            ])
                            ->reactive()
                            ->afterStateUpdated(fn ($state, callable $set) => $set('note', $state)),
                    ])
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->action(function ($record, array $data) {
                        $user = Auth::user();

                        // Get the actual model instance
                        $model = $record->type === 'leave'
                            ? Leave::find($record->id)
                            : SickLeave::find($record->id);

                        if (!$model) return;

                        $model->update([
                            'status' => 'approved',
                            'approved_by' => $user->id,
                            'approved_at' => now(),
                        ]);

                        // Get student ID
                        $studentUserId = $model->student?->user?->id;
                        
                        if ($studentUserId) {
                            // Send notifications
                            dispatch(new \App\Jobs\SendApprovalNotification(
                                approvalType: $record->type,
                                recordId: $model->id,
                                decision: 'approved',
                                note: $data['note'] ?? null,
                                studentId: $studentUserId,
                                rectorId: $user->id,
                                tenantId: $model->tenant_id
                            ));
                        }

                        Notification::make()
                            ->success()
                            ->title('Leave Approved')
                            ->body(($record->type === 'leave' ? 'Leave' : 'Sick leave') . ' request has been approved. Notifications sent.')
                            ->send();
                    }),
                RowAction::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Reject Leave Request')
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->maxLength(500),
                    ])
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->action(function ($record, array $data) {
                        $user = Auth::user();

                        // Get the actual model instance
                        $model = $record->type === 'leave'
                            ? Leave::find($record->id)
                            : SickLeave::find($record->id);

                        if (!$model) return;

                        $model->update([
                            'status' => 'rejected',
                            'rejection_reason' => $data['rejection_reason'],
                            'approved_by' => $user->id,
                            'approved_at' => now(),
                        ]);

                        // Get student ID
                        $studentUserId = $model->student?->user?->id;
                        
                        if ($studentUserId) {
                            // Send notifications
                            dispatch(new \App\Jobs\SendApprovalNotification(
                                approvalType: $record->type,
                                recordId: $model->id,
                                decision: 'rejected',
                                note: $data['rejection_reason'],
                                studentId: $studentUserId,
                                rectorId: $user->id,
                                tenantId: $model->tenant_id
                            ));
                        }

                        Notification::make()
                            ->success()
                            ->title('Leave Rejected')
                            ->body(($record->type === 'leave' ? 'Leave' : 'Sick leave') . ' request has been rejected. Notifications sent.')
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
                        ->modalHeading('Bulk Approve Leave Requests')
                        ->modalDescription('Approve all selected leave requests.')
                        ->form([
                            Forms\Components\Textarea::make('note')
                                ->label('Approval Note for All')
                                ->maxLength(500)
                                ->default('Bulk approved by Rector'),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $user = Auth::user();

                            $approved = 0;
                            $failed = 0;

                            foreach ($records as $record) {
                                // Get the actual model instance
                                $model = $record->type === 'leave'
                                    ? Leave::find($record->id)
                                    : SickLeave::find($record->id);

                                if (!$model || $model->status !== 'pending') {
                                    $failed++;
                                    continue;
                                }

                                try {
                                    $model->update([
                                        'status' => 'approved',
                                        'approved_by' => $user->id,
                                        'approved_at' => now(),
                                    ]);

                                    // Get student ID and send notification
                                    $studentUserId = $model->student?->user?->id;
                                    if ($studentUserId) {
                                        dispatch(new \App\Jobs\SendApprovalNotification(
                                            approvalType: $record->type,
                                            recordId: $model->id,
                                            decision: 'approved',
                                            note: $data['note'] ?? 'Bulk approved by Rector',
                                            studentId: $studentUserId,
                                            rectorId: $user->id,
                                            tenantId: $model->tenant_id
                                        ));
                                    }
                                    
                                    $approved++;
                                } catch (\Exception $e) {
                                    $failed++;
                                }
                            }

                            if ($approved > 0) {
                                Notification::make()
                                    ->success()
                                    ->title('Bulk Approval Complete')
                                    ->body("Approved {$approved} leave request(s)" . ($failed > 0 ? ", {$failed} failed" : ''))
                                    ->send();
                            } else {
                                Notification::make()
                                    ->warning()
                                    ->title('Bulk Approval Failed')
                                    ->body('No leave requests were approved.')
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Rector\Resources\LeaveResource\RelationManagers\HistoriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Rector\Resources\LeaveResource\Pages\ListLeaves::route('/'),
            'view' => \App\Filament\Rector\Resources\LeaveResource\Pages\ViewLeave::route('/{record}'),
        ];
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user && $user->hasRole('Rector');
    }
}
