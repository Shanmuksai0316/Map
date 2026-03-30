<?php

namespace App\Filament\CampusManager\Pages\Requests;

use App\Domain\Leaves\Models\Leave;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LeaveRequests extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Leave';

    protected static ?string $navigationGroup = 'Requests';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.campus-manager.pages.requests.leave-requests';

    public function getHeading(): string
    {
        return 'Leave Requests';
    }

    public function table(Table $table): Table
    {
        try {
            // Get tenant ID - prioritize tenant context from subdomain, then user's tenant_id
            $tenantId = null;
            try {
                if (function_exists('tenant') && tenant()) {
                    $tenantId = tenant()->id;
                }
            } catch (\Exception $e) {
                // tenant() might not be available
                \Log::warning('LeaveRequests: tenant() call failed', ['error' => $e->getMessage()]);
            }
            
            if (!$tenantId) {
                $user = auth()->user();
                $tenantId = $user?->tenant_id;
            }
            
            if (!$tenantId) {
                \Log::warning('LeaveRequests: No tenant ID found', [
                    'user_id' => auth()->id(),
                ]);
            }
            
            return $table
                ->query(function (Builder $query) use ($tenantId) {
                    $leaveQuery = Leave::query()
                        ->with(['student.user', 'student.roomAllocations.roomBed.room']);
                    
                    // Explicitly scope by tenant_id if we have it
                    if ($tenantId) {
                        $leaveQuery->where('tenant_id', $tenantId);
                    }
                    
                    return $leaveQuery;
                })
            ->columns([
                Tables\Columns\TextColumn::make('unique_id')
                    ->label('Request ID')
                    ->searchable(),

                Tables\Columns\TextColumn::make('student_name')
                    ->label('Student Name')
                    ->getStateUsing(fn (Leave $record) => $record->student?->user?->name ?? $record->student?->full_name ?? 'Unknown')
                    ->searchable(query: fn (Builder $query, string $search) => 
                        $query->whereHas('student', fn ($q) => $q->where('full_name', 'ilike', "%{$search}%"))
                    ),

                Tables\Columns\TextColumn::make('room')
                    ->label('Room')
                    ->getStateUsing(function (Leave $record) {
                        $allocation = $record->student?->roomAllocations
                            ->where('is_active', true)
                            ->first();
                        if ($allocation && $allocation->roomBed && $allocation->roomBed->room) {
                            return $allocation->roomBed->room->number ?? $allocation->roomBed->room->room_no ?? '—';
                        }
                        return '—';
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match(strtolower($state)) {
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state) => match(strtolower($state)) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('dates')
                    ->label('From Date - To Date')
                    ->getStateUsing(function (Leave $record) {
                        if (!$record->from_date || !$record->to_date) {
                            return '—';
                        }
                        return $record->from_date->format('d M') . ' - ' . $record->to_date->format('d M Y');
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->placeholder('All'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Leave')
                    ->modalContent(function (Leave $record) {
                        // Get room number from active allocation
                        $roomNumber = '—';
                        $allocation = $record->student?->roomAllocations
                            ->where('is_active', true)
                            ->first();
                        if ($allocation && $allocation->roomBed && $allocation->roomBed->room) {
                            $roomNumber = $allocation->roomBed->room->number ?? $allocation->roomBed->room->room_no ?? '—';
                        }
                        
                        return view('filament.campus-manager.pages.requests.leave-modal', [
                            'requestId' => $record->unique_id,
                            'studentName' => $record->student?->user?->name ?? $record->student?->full_name ?? 'Unknown',
                            'roomNumber' => $roomNumber,
                            'fromDate' => $record->from_date?->format('d M Y') ?? '—',
                            'toDate' => $record->to_date?->format('d M Y') ?? '—',
                            'submittedAt' => $record->created_at->format('d M Y, h:i A'),
                            'status' => match(strtolower($record->status)) {
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                                default => ucfirst($record->status),
                            },
                            'description' => $record->reason_for_leave ?? '—',
                        ]);
                    })
                    ->modalWidth('md')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No leave requests')
            ->emptyStateDescription('There are no leave requests at this time.');
        } catch (\Exception $e) {
            \Log::error('LeaveRequests: Error in table()', [
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 500),
            ]);
            
            // Return a safe table query that won't crash
            return $table
                ->query(Leave::query()->whereRaw('1 = 0')) // Empty query
                ->columns([])
                ->emptyStateHeading('Error loading requests')
                ->emptyStateDescription('Please refresh the page or contact support if the issue persists.');
        }
    }
}

