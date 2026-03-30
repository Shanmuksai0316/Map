<?php

namespace App\Filament\Rector\Pages\Requests;

use App\Domain\Visitors\Models\GuestVisit;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class GuestEntryRequests extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = null;

    protected static ?string $navigationLabel = 'Guest Entry';

    protected static ?string $navigationGroup = 'Requests';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.rector.pages.requests.guest-entry-requests';

    public function getHeading(): string
    {
        return 'Guest Entry Requests';
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $tenantId = $user?->tenant_id;
        $assignedHostelIds = DB::table('staff_assignments')
            ->where('user_id', $user?->id)
            ->where('tenant_id', $tenantId)
            ->whereNull('revoked_at')
            ->whereNotNull('hostel_id')
            ->pluck('hostel_id')
            ->toArray();

        return $table
            ->query(function () use ($tenantId, $assignedHostelIds): Builder {
                if (! $tenantId) {
                    return GuestVisit::query()->whereRaw('1 = 0');
                }

                return GuestVisit::query()
                    ->with(['student.user', 'student.roomAllocations.roomBed.room', 'hostel'])
                    ->where('tenant_id', $tenantId)
                    ->when(! empty($assignedHostelIds), fn (Builder $query): Builder => $query->whereIn('hostel_id', $assignedHostelIds));
            })
            ->columns([
                TextColumn::make('id')
                    ->label('Request ID')
                    ->formatStateUsing(fn ($state): string => 'GE-' . str_pad((string) $state, 4, '0', STR_PAD_LEFT))
                    ->searchable(),
                TextColumn::make('student_name')
                    ->label('Student Name')
                    ->getStateUsing(fn (GuestVisit $record): string => $record->student?->user?->name ?? $record->student?->full_name ?? 'Unknown')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('student.user', fn (Builder $studentQuery): Builder => $studentQuery->where('name', 'ilike', '%' . $search . '%'));
                    }),
                TextColumn::make('room')
                    ->label('Room')
                    ->getStateUsing(fn (GuestVisit $record): string => self::resolveRoomNumber($record)),
                TextColumn::make('name')
                    ->label('Guest Name')
                    ->searchable(),
                TextColumn::make('relation')
                    ->label('Relation')
                    ->default('—'),
                TextColumn::make('visit_date')
                    ->label('Arrival Date')
                    ->date('d M Y'),
                BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (string $state): string => self::statusLabel($state))
                    ->colors([
                        'warning' => fn (GuestVisit $record): bool => in_array($record->status, [GuestVisit::STATUS_PRE_REGISTERED, GuestVisit::STATUS_PENDING], true),
                        'success' => fn (GuestVisit $record): bool => in_array($record->status, [GuestVisit::STATUS_ALLOWED, GuestVisit::STATUS_APPROVED], true),
                        'danger' => fn (GuestVisit $record): bool => $record->status === GuestVisit::STATUS_DENIED,
                        'gray' => fn (GuestVisit $record): bool => in_array($record->status, [GuestVisit::STATUS_COMPLETED, GuestVisit::STATUS_CANCELLED], true),
                    ]),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending_group' => 'Pending',
                        'approved_group' => 'Approved',
                        'denied' => 'Rejected',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->placeholder('All statuses')
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        return match ($value) {
                            'pending_group' => $query->whereIn('status', [GuestVisit::STATUS_PRE_REGISTERED, GuestVisit::STATUS_PENDING]),
                            'approved_group' => $query->whereIn('status', [GuestVisit::STATUS_ALLOWED, GuestVisit::STATUS_APPROVED]),
                            'denied' => $query->where('status', GuestVisit::STATUS_DENIED),
                            'completed' => $query->where('status', GuestVisit::STATUS_COMPLETED),
                            'cancelled' => $query->where('status', GuestVisit::STATUS_CANCELLED),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Guest Entry')
                    ->modalWidth('lg')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(function (GuestVisit $record) {
                        return view('filament.rector.pages.requests.guest-entry-modal', [
                            'requestId' => 'GE-' . str_pad((string) $record->id, 4, '0', STR_PAD_LEFT),
                            'status' => self::statusLabel($record->status),
                            'studentName' => $record->student?->user?->name ?? $record->student?->full_name ?? 'Unknown',
                            'roomNumber' => self::resolveRoomNumber($record),
                            'guestName' => $record->name ?? '—',
                            'guestRelation' => $record->relation ?? '—',
                            'guestIdProof' => $record->id_proof_type && $record->id_proof_number
                                ? "{$record->id_proof_type}: {$record->id_proof_number}"
                                : '—',
                            'primaryContact' => $record->phone ?? '—',
                            'guestArrivalDate' => $record->visit_date?->format('d M Y') ?? '—',
                            'submittedAt' => $record->created_at?->format('d M Y, h:i A') ?? '—',
                            'description' => $record->description ?? '—',
                        ]);
                    }),
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Guest Entry')
                    ->modalDescription('Approve this guest entry request.')
                    ->form([
                        Textarea::make('note')
                            ->label('Approval comment')
                            ->maxLength(500)
                            ->rows(3),
                    ])
                    ->visible(fn (GuestVisit $record): bool => in_array($record->status, [GuestVisit::STATUS_PRE_REGISTERED, GuestVisit::STATUS_PENDING], true))
                    ->action(function (GuestVisit $record, array $data): void {
                        $user = auth()->user();

                        $record->forceFill([
                            'status' => GuestVisit::STATUS_ALLOWED,
                            'allowed_by_user_id' => $user?->id,
                            'allowed_at' => now(),
                            'denied_by_user_id' => null,
                            'denied_at' => null,
                            'description' => self::mergeDecisionComment(
                                $record->description,
                                $data['note'] ?? null,
                                'Approved note'
                            ),
                        ])->save();

                        Notification::make()
                            ->success()
                            ->title('Guest Entry Approved')
                            ->body('Request ' . 'GE-' . str_pad((string) $record->id, 4, '0', STR_PAD_LEFT) . ' is now approved.')
                            ->send();
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Reject Guest Entry')
                    ->form([
                        Textarea::make('reason')
                            ->label('Rejection reason')
                            ->required()
                            ->maxLength(500)
                            ->rows(3),
                    ])
                    ->visible(fn (GuestVisit $record): bool => in_array($record->status, [GuestVisit::STATUS_PRE_REGISTERED, GuestVisit::STATUS_PENDING], true))
                    ->action(function (GuestVisit $record, array $data): void {
                        $user = auth()->user();

                        $record->forceFill([
                            'status' => GuestVisit::STATUS_DENIED,
                            'allowed_by_user_id' => null,
                            'allowed_at' => null,
                            'denied_by_user_id' => $user?->id,
                            'denied_at' => now(),
                            'description' => self::mergeDecisionComment(
                                $record->description,
                                $data['reason'] ?? null,
                                'Rejection reason'
                            ),
                        ])->save();

                        Notification::make()
                            ->success()
                            ->title('Guest Entry Rejected')
                            ->body('Request ' . 'GE-' . str_pad((string) $record->id, 4, '0', STR_PAD_LEFT) . ' has been rejected.')
                            ->send();
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No guest entry requests')
            ->emptyStateDescription('There are no guest entry requests at this time.');
    }

    private static function resolveRoomNumber(GuestVisit $record): string
    {
        $allocation = $record->student?->roomAllocations?->firstWhere('is_active', true) ?? $record->student?->roomAllocations?->first();
        $room = $allocation?->roomBed?->room;

        if (! $room) {
            return '—';
        }

        return $room->number
            ?? (($room->block_code && $room->floor_code && $room->room_no)
                ? "{$room->block_code}-{$room->floor_code}{$room->room_no}"
                : ($room->room_no ?? '—'));
    }

    private static function statusLabel(string $status): string
    {
        return match ($status) {
            GuestVisit::STATUS_PRE_REGISTERED,
            GuestVisit::STATUS_PENDING => 'Pending',
            GuestVisit::STATUS_ALLOWED,
            GuestVisit::STATUS_APPROVED => 'Approved',
            GuestVisit::STATUS_DENIED => 'Rejected',
            GuestVisit::STATUS_COMPLETED => 'Completed',
            GuestVisit::STATUS_CANCELLED => 'Cancelled',
            default => ucfirst($status),
        };
    }

    private static function mergeDecisionComment(?string $existing, ?string $note, string $prefix): ?string
    {
        $note = trim((string) $note);
        if ($note === '') {
            return $existing;
        }

        $entry = $prefix . ': ' . $note;
        $existing = trim((string) $existing);

        if ($existing === '') {
            return $entry;
        }

        return $existing . PHP_EOL . $entry;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && $user->hasRole('Rector');
    }
}

