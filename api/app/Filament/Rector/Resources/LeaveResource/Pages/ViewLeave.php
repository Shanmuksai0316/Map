<?php

namespace App\Filament\Rector\Resources\LeaveResource\Pages;

use App\Filament\Rector\Resources\LeaveResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\IconEntry;

class ViewLeave extends ViewRecord
{
    protected static string $resource = LeaveResource::class;
    
    protected static ?string $title = 'View Leave Request';
    
    /**
     * Only show histories relation manager for Leave records, not SickLeave
     */
    public function getRelations(): array
    {
        // Only show histories for Leave records (SickLeave doesn't have histories)
        if ($this->record instanceof \App\Domain\Leaves\Models\Leave) {
            return [
                \App\Filament\Rector\Resources\LeaveResource\RelationManagers\HistoriesRelationManager::class,
            ];
        }
        return [];
    }

    /**
     * Resolve the record from the route parameter
     */
    protected function resolveRecord(string|int $key): \Illuminate\Database\Eloquent\Model
    {
        try {
            $user = auth()->user();
            $tenantId = $user?->tenant_id;
            
            if (!$tenantId) {
                \Log::error('ViewLeave::resolveRecord - No tenant_id for user', [
                    'user_id' => $user->id ?? null,
                ]);
                abort(500, 'Unable to determine tenant context.');
            }
            
            // Try to find as Leave first (with relationships loaded)
            $leave = \App\Domain\Leaves\Models\Leave::with(['student.user', 'hostel'])
                ->where('tenant_id', $tenantId)
                ->find($key);

            if ($leave) {
                return $leave;
            }

            // Try SickLeave (with relationships loaded)
            $sickLeave = \App\Domain\SickLeaves\Models\SickLeave::with(['student.user', 'hostel'])
                ->where('tenant_id', $tenantId)
                ->find($key);

            if ($sickLeave) {
                return $sickLeave;
            }
            
            abort(404, 'Leave request not found');
        } catch (\Exception $e) {
            \Log::error('ViewLeave::resolveRecord - Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'record_id' => $key,
                'user_id' => $user->id ?? null,
                'tenant_id' => $tenantId ?? null,
            ]);
            abort(500, 'Failed to load leave request: ' . $e->getMessage());
        }
    }

    public function infolist(Infolist $infolist): Infolist
    {
        $isSickLeave = $this->record instanceof \App\Domain\SickLeaves\Models\SickLeave;
        
        return $infolist
            ->schema([
                Section::make('Request Information')
                    ->schema([
                        TextEntry::make('unique_id')
                            ->label('Request ID')
                            ->badge()
                            ->color('primary'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'pending' => 'warning',
                                'approved' => 'success',
                                'rejected' => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                        TextEntry::make('submitted_at')
                            ->label('Submitted At')
                            ->dateTime('d M Y, h:i A'),
                        TextEntry::make('sla_due_at')
                            ->label('SLA Due At')
                            ->dateTime('d M Y, h:i A')
                            ->visible(fn () => $this->record->sla_due_at !== null),
                        TextEntry::make('sla_breached_at')
                            ->label('SLA Breached At')
                            ->dateTime('d M Y, h:i A')
                            ->badge()
                            ->color('danger')
                            ->visible(fn () => $this->record->sla_breached_at !== null),
                    ])
                    ->columns(2),

                Section::make('Student Information')
                    ->schema([
                        TextEntry::make('student.user.name')
                            ->label('Student Name')
                            ->default('Unknown'),
                        TextEntry::make('student.user.phone')
                            ->label('Phone Number')
                            ->default('N/A'),
                        TextEntry::make('hostel.name')
                            ->label('Hostel')
                            ->default('N/A'),
                        TextEntry::make('room_number')
                            ->label('Room Number')
                            ->getStateUsing(function () {
                                $student = $this->record->student;
                                if (!$student) return 'N/A';
                                
                                $allocation = $student->roomAllocations()
                                    ->where('is_active', true)
                                    ->with(['roomBed.room'])
                                    ->first();
                                
                                if (!$allocation || !$allocation->roomBed || !$allocation->roomBed->room) {
                                    return 'N/A';
                                }
                                
                                $room = $allocation->roomBed->room;
                                return ($room->block_code ?? '') . '-' . ($room->floor_code ?? '') . ($room->room_no ?? $room->number ?? 'N/A');
                            }),
                    ])
                    ->columns(2),

                // Leave-specific fields
                Section::make('Leave Details')
                    ->schema([
                        TextEntry::make('title')
                            ->label('Title'),
                        TextEntry::make('description')
                            ->label('Description')
                            ->columnSpanFull(),
                        TextEntry::make('reason_for_leave')
                            ->label('Reason for Leave')
                            ->columnSpanFull()
                            ->visible(fn () => !$isSickLeave),
                        TextEntry::make('from_date')
                            ->label('From Date')
                            ->date('d M Y')
                            ->visible(fn () => !$isSickLeave),
                        TextEntry::make('to_date')
                            ->label('To Date')
                            ->date('d M Y')
                            ->visible(fn () => !$isSickLeave),
                        TextEntry::make('emergency_contact')
                            ->label('Emergency Contact')
                            ->default('N/A')
                            ->visible(fn () => !$isSickLeave),
                    ])
                    ->columns(2)
                    ->visible(fn () => !$isSickLeave),

                // Sick Leave-specific fields
                Section::make('Sick Leave Details')
                    ->schema([
                        TextEntry::make('title')
                            ->label('Title'),
                        TextEntry::make('description')
                            ->label('Description')
                            ->columnSpanFull(),
                        TextEntry::make('illness')
                            ->label('Illness Type')
                            ->columnSpanFull(),
                        TextEntry::make('illness_details')
                            ->label('Illness Details')
                            ->columnSpanFull(),
                        IconEntry::make('need_medical_attention')
                            ->label('Needs Medical Attention')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('danger')
                            ->falseColor('gray'),
                        IconEntry::make('contact_parents')
                            ->label('Contact Parents')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('warning')
                            ->falseColor('gray'),
                    ])
                    ->columns(2)
                    ->visible(fn () => $isSickLeave),

                Section::make('Approval Information')
                    ->schema([
                        TextEntry::make('approved_by')
                            ->label('Approved By')
                            ->getStateUsing(function () {
                                if (!$this->record->approved_by) return 'N/A';
                                $approver = \App\Models\User::find($this->record->approved_by);
                                return $approver?->name ?? 'Unknown';
                            })
                            ->visible(fn () => $this->record->approved_by !== null),
                        TextEntry::make('approved_at')
                            ->label('Approved At')
                            ->dateTime('d M Y, h:i A')
                            ->visible(fn () => $this->record->approved_at !== null),
                        TextEntry::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->columnSpanFull()
                            ->visible(fn () => $this->record->status === 'rejected' && $this->record->rejection_reason),
                    ])
                    ->columns(2)
                    ->visible(fn () => $this->record->status !== 'pending'),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')
                ->label('Approve Leave')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Approve Leave Request')
                ->modalDescription('Approve this leave request.')
                ->form([
                    \Filament\Forms\Components\Textarea::make('note')
                        ->label('Approval Note')
                        ->placeholder('You can type or select a template...')
                        ->maxLength(500)
                        ->required(),
                    \Filament\Forms\Components\Select::make('template')
                        ->label('Quick Templates')
                        ->options([
                            'Approved as requested' => 'Approved as requested',
                            'Approved with conditions' => 'Approved with conditions',
                            'Emergency approved' => 'Emergency approved',
                        ])
                        ->reactive()
                        ->afterStateUpdated(fn ($state, callable $set) => $set('note', $state)),
                ])
                ->visible(fn () => $this->record->status === 'pending')
                ->action(function (array $data) {
                    $user = auth()->user();
                    $model = $this->record;
                    $isSickLeave = $model instanceof \App\Domain\SickLeaves\Models\SickLeave;

                    if (!$model) return;

                    $model->update([
                        'status' => 'approved',
                        'approved_by' => $user->id,
                        'approved_at' => now(),
                    ]);

                    // Send notifications
                    $studentId = $model->student?->user?->id;
                    if ($studentId) {
                        dispatch(new \App\Jobs\SendApprovalNotification(
                            approvalType: $isSickLeave ? 'sick_leave' : 'leave',
                            recordId: $model->id,
                            decision: 'approved',
                            note: $data['note'] ?? null,
                            studentId: $studentId,
                            rectorId: $user->id,
                            tenantId: $model->tenant_id
                        ));
                    }

                    Notification::make()
                        ->success()
                        ->title('Leave Approved')
                        ->body(($isSickLeave ? 'Sick leave' : 'Leave') . ' request has been approved.')
                        ->send();

                    return redirect()->route('filament.rector.resources.leaves.index');
                }),
            Actions\Action::make('reject')
                ->label('Reject Leave')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Reject Leave Request')
                ->form([
                    \Filament\Forms\Components\Textarea::make('rejection_reason')
                        ->label('Rejection Reason')
                        ->required()
                        ->maxLength(500),
                ])
                ->visible(fn () => $this->record->status === 'pending')
                ->action(function (array $data) {
                    $user = auth()->user();
                    $model = $this->record;
                    $isSickLeave = $model instanceof \App\Domain\SickLeaves\Models\SickLeave;

                    if (!$model) return;

                    $model->update([
                        'status' => 'rejected',
                        'rejection_reason' => $data['rejection_reason'],
                        'approved_by' => $user->id,
                        'approved_at' => now(),
                    ]);

                    // Send notifications
                    $studentId = $model->student?->user?->id;
                    if ($studentId) {
                        dispatch(new \App\Jobs\SendApprovalNotification(
                            approvalType: $isSickLeave ? 'sick_leave' : 'leave',
                            recordId: $model->id,
                            decision: 'rejected',
                            note: $data['rejection_reason'],
                            studentId: $studentId,
                            rectorId: $user->id,
                            tenantId: $model->tenant_id
                        ));
                    }

                    Notification::make()
                        ->success()
                        ->title('Leave Rejected')
                        ->body(($isSickLeave ? 'Sick leave' : 'Leave') . ' request has been rejected.')
                        ->send();

                    return redirect()->route('filament.rector.resources.leaves.index');
                }),
        ];
    }
}
