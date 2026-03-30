<?php

namespace App\Filament\CampusManager\Resources\RoomChangeResource\Pages;

use App\Domain\RoomChanges\Models\RoomChange;
use App\Filament\CampusManager\Resources\RoomChangeResource;
use App\Models\RoomBed;
use App\Services\RoomChanges\RoomChangeApprovalService;
use Filament\Actions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Carbon;

class ViewRoomChange extends ViewRecord
{
    protected static string $resource = RoomChangeResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        /** @var RoomChange $record */
        $record = $this->record;

        return $infolist->schema([
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
                            'approved' => 'success',
                            'rejected' => 'danger',
                            default => 'warning',
                        })
                        ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                    TextEntry::make('submitted_at')
                        ->label('Submitted At')
                        ->dateTime('d M Y, h:i A'),
                    TextEntry::make('sla_due_at')
                        ->label('SLA Due At')
                        ->dateTime('d M Y, h:i A')
                        ->visible(fn () => $record->sla_due_at !== null),
                ])
                ->columns(2),

            Section::make('Student')
                ->schema([
                    TextEntry::make('student.user.name')
                        ->label('Student Name')
                        ->default('Unknown'),
                    TextEntry::make('student.user.phone')
                        ->label('Phone')
                        ->default('N/A'),
                    TextEntry::make('hostel.name')
                        ->label('Hostel')
                        ->default('N/A'),
                ])
                ->columns(2),

            Section::make('Request Details')
                ->schema([
                    TextEntry::make('title')
                        ->label('Title')
                        ->default('Room Change Request'),
                    TextEntry::make('description')
                        ->label('Description')
                        ->columnSpanFull(),
                    TextEntry::make('preferred_room_number')
                        ->label('Preferred Room')
                        ->placeholder('Not specified')
                        ->visible(fn () => filled($record->preferred_room_number)),
                    TextEntry::make('preferred_floor')
                        ->label('Preferred Floor')
                        ->placeholder('Not specified')
                        ->visible(fn () => filled($record->preferred_floor)),
                    TextEntry::make('sharing_preference')
                        ->label('Sharing Preference')
                        ->placeholder('Not specified')
                        ->visible(fn () => filled($record->sharing_preference)),
                    TextEntry::make('date_required')
                        ->label('Date Required')
                        ->date('d M Y')
                        ->visible(fn () => $record->date_required !== null),
                ])
                ->columns(2),

            Section::make('Decision')
                ->schema([
                    TextEntry::make('approved_at')
                        ->label('Decided At')
                        ->dateTime('d M Y, h:i A')
                        ->visible(fn () => $record->approved_at !== null),
                    TextEntry::make('approved_by')
                        ->label('Decided By (User ID)')
                        ->visible(fn () => $record->approved_by !== null),
                    TextEntry::make('rejection_reason')
                        ->label('Rejection Reason')
                        ->columnSpanFull()
                        ->visible(fn () => $record->status === 'rejected' && filled($record->rejection_reason)),
                ])
                ->columns(2)
                ->visible(fn () => $record->status !== 'pending'),
        ]);
    }

    protected function getHeaderActions(): array
    {
        /** @var RoomChange $record */
        $record = $this->record;

        return [
            Actions\Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn () => $record->status === 'pending' && (auth()->user()?->can('approve', $record) ?? false))
                ->form([
                    Select::make('room_bed_id')
                        ->label('Destination Bed')
                        ->required()
                        ->searchable()
                        ->getSearchResultsUsing(function (string $search): array {
                            return RoomBed::query()
                                ->where('status', 'available')
                                ->where(function ($q) use ($search): void {
                                    $q->where('code', 'like', '%' . $search . '%')
                                        ->orWhereHas('room', fn ($rq) => $rq->where('number', 'like', '%' . $search . '%'));
                                })
                                ->with(['room', 'hostel'])
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn (RoomBed $bed) => [
                                    $bed->id => sprintf('%s · Room %s · %s', $bed->code, $bed->room?->number, $bed->hostel?->name),
                                ])
                                ->toArray();
                        })
                        ->getOptionLabelUsing(function ($value): ?string {
                            $bed = RoomBed::with(['room', 'hostel'])->find($value);
                            return $bed ? sprintf('%s · Room %s · %s', $bed->code, $bed->room?->number, $bed->hostel?->name) : null;
                        }),
                    DateTimePicker::make('effective_from')
                        ->label('Effective From')
                        ->default(now()),
                    Textarea::make('note')
                        ->label('Notes')
                        ->maxLength(500)
                        ->rows(3),
                ])
                ->requiresConfirmation()
                ->action(function (array $data) use ($record): void {
                    $bed = RoomBed::findOrFail($data['room_bed_id']);
                    app(RoomChangeApprovalService::class)->approve(
                        $record,
                        $bed,
                        Carbon::parse($data['effective_from'] ?? now()),
                        $data['note'] ?? null
                    );

                    Notification::make()
                        ->success()
                        ->title('Room change approved')
                        ->send();
                }),

            Actions\Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->visible(fn () => $record->status === 'pending' && (auth()->user()?->can('reject', $record) ?? false))
                ->form([
                    Textarea::make('reason')
                        ->label('Reason')
                        ->required()
                        ->maxLength(500)
                        ->rows(3),
                ])
                ->requiresConfirmation()
                ->action(function (array $data) use ($record): void {
                    app(RoomChangeApprovalService::class)->reject($record, $data['reason']);

                    Notification::make()
                        ->success()
                        ->title('Room change rejected')
                        ->send();
                }),
        ];
    }
}
