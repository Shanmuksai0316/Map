<?php

namespace App\Filament\CampusManager\Pages\Requests;

use App\Models\Domain\OutPass\OutPass;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OutpassRequests extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-right-on-rectangle';

    protected static ?string $navigationLabel = 'Outpass';

    protected static ?string $navigationGroup = 'Requests';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.campus-manager.pages.requests.outpass-requests';

    public function getHeading(): string
    {
        return 'Outpass Requests';
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
                \Log::warning('OutpassRequests: tenant() call failed', ['error' => $e->getMessage()]);
            }
            
            if (!$tenantId) {
                $user = auth()->user();
                $tenantId = $user?->tenant_id;
            }
            
            if (!$tenantId) {
                \Log::warning('OutpassRequests: No tenant ID found', [
                    'user_id' => auth()->id(),
                ]);
            }
            
            return $table
                ->query(function (Builder $query) use ($tenantId) {
                    $outPassQuery = OutPass::query()
                        ->with(['student.user', 'student.roomAllocations.roomBed.room', 'hostel']);
                    
                    // Explicitly scope by tenant_id if we have it
                    if ($tenantId) {
                        $outPassQuery->where('tenant_id', $tenantId);
                    }
                    
                    return $outPassQuery;
                })
            ->columns([
                Tables\Columns\TextColumn::make('request_id')
                    ->label('Request ID')
                    ->getStateUsing(fn (OutPass $record) => 'OP-' . str_pad($record->id, 4, '0', STR_PAD_LEFT))
                    ->searchable(query: fn (Builder $query, string $search) => 
                        $query->where('id', 'like', '%' . preg_replace('/[^0-9]/', '', $search) . '%')
                    ),

                Tables\Columns\TextColumn::make('student_name')
                    ->label('Student Name')
                    ->getStateUsing(fn (OutPass $record) => $record->student?->user?->name ?? $record->student?->full_name ?? 'Unknown')
                    ->searchable(query: fn (Builder $query, string $search) => 
                        $query->whereHas('student', fn ($q) => $q->where('full_name', 'ilike', "%{$search}%"))
                    ),

                Tables\Columns\TextColumn::make('room')
                    ->label('Room')
                    ->getStateUsing(fn (OutPass $record) => $record->student?->roomAllocations?->first()?->roomBed?->room?->number ?? '—'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        if ($state instanceof \App\Enums\OutPassStatus) {
                            $state = $state->value;
                        }
                        $state = (string) $state;
                        return match($state) {
                            'pending' => 'Pending',
                            'approved' => 'Approved',
                            'declined', 'rejected' => 'Rejected',
                            'checked_out' => 'Out',
                            'checked_in' => 'Returned',
                            'cancelled' => 'Cancelled',
                            'expired' => 'Expired',
                            default => ucfirst($state),
                        };
                    })
                    ->color(function ($state) {
                        if ($state instanceof \App\Enums\OutPassStatus) {
                            $state = $state->value;
                        }
                        $state = (string) $state;
                        return match($state) {
                            'pending' => 'warning',
                            'approved' => 'success',
                            'declined', 'rejected' => 'danger',
                            'checked_out' => 'info',
                            'checked_in' => 'gray',
                            'cancelled' => 'danger',
                            'expired' => 'gray',
                            default => 'gray',
                        };
                    }),

                Tables\Columns\TextColumn::make('requested_for')
                    ->label('Date & Time')
                    ->dateTime('d M Y, h:i A'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'declined' => 'Declined',
                        'cancelled' => 'Cancelled',
                        'expired' => 'Expired',
                    ])
                    ->placeholder('All'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Outpass')
                    ->modalContent(function (OutPass $record) {
                        $status = $record->status;
                        if ($status instanceof \App\Enums\OutPassStatus) {
                            $status = $status->value;
                        }
                        $status = (string) $status;
                        $statusLabel = match($status) {
                            'pending' => 'Pending',
                            'approved' => 'Approved',
                            'declined', 'rejected' => 'Rejected',
                            'checked_out' => 'Out',
                            'checked_in' => 'Returned',
                            'cancelled' => 'Cancelled',
                            'expired' => 'Expired',
                            default => ucfirst($status),
                        };
                        
                        return view('filament.campus-manager.pages.requests.outpass-modal', [
                            'requestId' => 'OP-' . str_pad($record->id, 4, '0', STR_PAD_LEFT),
                            'studentName' => $record->student?->user?->name ?? $record->student?->full_name ?? 'Unknown',
                            'roomNumber' => $record->student?->roomAllocations?->first()?->roomBed?->room?->number ?? '—',
                            'goingOutDate' => $record->requested_for?->format('d M Y, h:i A') ?? '—',
                            'submittedAt' => $record->created_at->format('d M Y, h:i A'),
                            'status' => $statusLabel,
                            'description' => $record->reason ?? '—',
                        ]);
                    })
                    ->modalWidth('md')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No outpass requests')
            ->emptyStateDescription('There are no outpass requests at this time.');
        } catch (\Exception $e) {
            \Log::error('OutpassRequests: Error in table()', [
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 500),
            ]);
            
            // Return a safe table query that won't crash
            return $table
                ->query(OutPass::query()->whereRaw('1 = 0')) // Empty query
                ->columns([])
                ->emptyStateHeading('Error loading requests')
                ->emptyStateDescription('Please refresh the page or contact support if the issue persists.');
        }
    }
}

