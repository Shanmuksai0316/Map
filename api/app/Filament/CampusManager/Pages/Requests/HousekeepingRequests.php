<?php

namespace App\Filament\CampusManager\Pages\Requests;

use App\Domain\Tickets\Models\Ticket;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;

class HousekeepingRequests extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'Housekeeping';

    protected static ?string $navigationGroup = 'Requests';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.campus-manager.pages.requests.housekeeping-requests';

    public function getHeading(): string
    {
        return 'Housekeeping Requests';
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
                \Log::warning('HousekeepingRequests: tenant() call failed', ['error' => $e->getMessage()]);
            }
            
            if (!$tenantId) {
                $user = auth()->user();
                $tenantId = $user?->tenant_id;
            }
            
            if (!$tenantId) {
                \Log::warning('HousekeepingRequests: No tenant ID found', [
                    'user_id' => auth()->id(),
                ]);
            }
            
            return $table
                ->query(function (Builder $query) use ($tenantId) {
                    try {
                        $ticketQuery = Ticket::query()
                            ->where('category', 'housekeeping');
                        
                        // Explicitly scope by tenant_id if we have it
                        if ($tenantId) {
                            $ticketQuery->where('tenant_id', $tenantId);
                        }
                        
                        // Eager load relationships with error handling
                        try {
                            $ticketQuery->with([
                                'reporterStudent' => function ($q) {
                                    $q->with(['user']);
                                },
                            ]);
                        } catch (\Exception $e) {
                            \Log::warning('HousekeepingRequests: Error loading reporterStudent relationship', [
                                'error' => $e->getMessage(),
                            ]);
                        }
                        
                        return $ticketQuery;
                    } catch (\Exception $e) {
                        \Log::error('HousekeepingRequests: Error in query builder', [
                            'error' => $e->getMessage(),
                            'trace' => substr($e->getTraceAsString(), 0, 500),
                            'tenant_id' => $tenantId,
                        ]);
                        // Return empty query on error
                        return Ticket::query()->whereRaw('1 = 0');
                    }
                })
            ->columns([
                Tables\Columns\TextColumn::make('request_id')
                    ->label('Request ID')
                    ->getStateUsing(fn (Ticket $record) => 'HK-' . str_pad($record->id, 4, '0', STR_PAD_LEFT))
                    ->searchable(query: fn (Builder $query, string $search) => 
                        $query->where('id', 'like', '%' . preg_replace('/[^0-9]/', '', $search) . '%')
                    ),

                Tables\Columns\TextColumn::make('student_room')
                    ->label('Student Name - Room')
                    ->getStateUsing(function (Ticket $record) {
                        try {
                            // Get student name safely
                            $studentName = 'Unknown';
                            try {
                                if ($record->reporterStudent && $record->reporterStudent->user) {
                                    $studentName = $record->reporterStudent->user->name ?? 'Unknown';
                                } elseif (method_exists($record, 'getReporterNameAttribute')) {
                                    $studentName = $record->reporter_name ?? 'Unknown';
                                }
                            } catch (\Exception $e) {
                                \Log::warning('HousekeepingRequests: Error getting student name', [
                                    'ticket_id' => $record->id,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                            
                            // Get room number safely
                            $room = '—';
                            try {
                                if ($record->reporterStudent) {
                                    $allocation = $record->reporterStudent->roomAllocations()
                                        ->where('is_active', true)
                                        ->with(['roomBed.room'])
                                        ->first();
                                    if ($allocation && $allocation->roomBed && $allocation->roomBed->room) {
                                        $room = $allocation->roomBed->room->number ?? '—';
                                    }
                                }
                            } catch (\Exception $e) {
                                \Log::warning('HousekeepingRequests: Error getting room number', [
                                    'ticket_id' => $record->id,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                            
                            return "{$studentName} - {$room}";
                        } catch (\Exception $e) {
                            \Log::warning('HousekeepingRequests: Error getting student_room', [
                                'ticket_id' => $record->id,
                                'error' => $e->getMessage(),
                            ]);
                            return 'Unknown - —';
                        }
                    })
                    ->searchable(query: function (Builder $query, string $search) {
                        return $query->whereHas('reporterStudent', function ($q) use ($search) {
                            $q->whereHas('user', function ($userQ) use ($search) {
                                $userQ->where('name', 'ilike', "%{$search}%");
                            });
                        });
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        if (is_null($state)) {
                            return 'Unknown';
                        }
                        $state = (string) $state;
                        return match($state) {
                            'open' => 'Pending',
                            'in_progress' => 'In Progress',
                            'resolved', 'closed' => 'Resolved',
                            default => ucfirst($state),
                        };
                    })
                    ->color(function ($state) {
                        if (is_null($state)) {
                            return 'gray';
                        }
                        $state = (string) $state;
                        return match($state) {
                            'open' => 'warning',
                            'in_progress' => 'info',
                            'resolved', 'closed' => 'success',
                            default => 'gray',
                        };
                    }),

                Tables\Columns\TextColumn::make('delayed')
                    ->label('SLA')
                    ->badge()
                    ->getStateUsing(fn (Ticket $record) => $record->isDelayed() ? 'Delayed' : 'On time')
                    ->color(fn (Ticket $record) => $record->isDelayed() ? 'danger' : 'success')
                    ->formatStateUsing(fn (Ticket $record) => $record->isDelayed() ? 'Delayed' : 'On time'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'open' => 'Pending',
                        'in_progress' => 'In Progress',
                        'resolved' => 'Resolved',
                    ])
                    ->placeholder('All'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Housekeeping')
                    ->modalContent(function (Ticket $record) {
                        try {
                            $status = $record->status ?? 'unknown';
                            $statusLabel = match($status) {
                                'open' => 'Pending',
                                'in_progress' => 'In Progress',
                                'resolved', 'closed' => 'Resolved',
                                default => ucfirst($status),
                            };
                            
                            // Get student name safely
                            $studentName = 'Unknown';
                            try {
                                if ($record->reporterStudent && $record->reporterStudent->user) {
                                    $studentName = $record->reporterStudent->user->name ?? 'Unknown';
                                } elseif (method_exists($record, 'getReporterNameAttribute')) {
                                    $studentName = $record->reporter_name ?? 'Unknown';
                                }
                            } catch (\Exception $e) {
                                \Log::warning('HousekeepingRequests: Error getting student name in modal', [
                                    'ticket_id' => $record->id,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                            
                            // Get room number safely
                            $roomNumber = '—';
                            try {
                                if ($record->reporterStudent) {
                                    $allocation = $record->reporterStudent->roomAllocations()
                                        ->where('is_active', true)
                                        ->with(['roomBed.room'])
                                        ->first();
                                    if ($allocation && $allocation->roomBed && $allocation->roomBed->room) {
                                        $roomNumber = $allocation->roomBed->room->number ?? '—';
                                    }
                                }
                            } catch (\Exception $e) {
                                \Log::warning('HousekeepingRequests: Error getting room number in modal', [
                                    'ticket_id' => $record->id,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                            
                            return view('filament.campus-manager.pages.requests.request-modal', [
                                'title' => 'Housekeeping',
                                'requestId' => 'HK-' . str_pad($record->id, 4, '0', STR_PAD_LEFT),
                                'studentName' => $studentName,
                                'roomNumber' => $roomNumber,
                                'submittedAt' => $record->created_at?->format('d M Y, h:i A') ?? '—',
                                'status' => $statusLabel,
                                'description' => $record->description ?? '—',
                            ]);
                        } catch (\Exception $e) {
                            \Log::error('HousekeepingRequests: Error in modal content', [
                                'ticket_id' => $record->id,
                                'error' => $e->getMessage(),
                            ]);
                            return view('filament.campus-manager.pages.requests.request-modal', [
                                'title' => 'Housekeeping',
                                'requestId' => 'HK-' . str_pad($record->id, 4, '0', STR_PAD_LEFT),
                                'studentName' => 'Unknown',
                                'roomNumber' => '—',
                                'submittedAt' => '—',
                                'status' => 'Unknown',
                                'description' => 'Error loading details',
                            ]);
                        }
                    })
                    ->modalWidth('md')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No housekeeping requests')
            ->emptyStateDescription('There are no housekeeping requests at this time.');
        } catch (\Exception $e) {
            \Log::error('HousekeepingRequests: Error in table()', [
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 500),
            ]);
            
            // Return a safe table query that won't crash
            return $table
                ->query(Ticket::query()->whereRaw('1 = 0')) // Empty query
                ->columns([])
                ->emptyStateHeading('Error loading requests')
                ->emptyStateDescription('Please refresh the page or contact support if the issue persists.');
        }
    }
}

