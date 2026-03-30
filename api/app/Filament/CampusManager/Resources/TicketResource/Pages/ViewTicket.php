<?php

namespace App\Filament\CampusManager\Resources\TicketResource\Pages;

use App\Domain\Tickets\Models\TicketComment;
use App\Filament\CampusManager\Resources\TicketResource;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('assign')
                ->label('Assign')
                ->icon('heroicon-o-user-plus')
                ->form([
                    Forms\Components\Select::make('assignee_user_id')
                        ->label('Assign to')
                        ->options(function () {
                            return User::where('tenant_id', Auth::user()->tenant_id)
                                ->whereHas('roles', function ($query) {
                                    $query->whereIn('name', ['HKSupervisor', 'RMSupervisor', 'CampusManager']);
                                })
                                ->pluck('name', 'id');
                        })
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->record->update([
                        'assignee_user_id' => $data['assignee_user_id'],
                        'updated_by_user_id' => Auth::id(),
                    ]);
                })
                ->visible(fn (): bool => Auth::user()->can('assign', $this->record)),
            Actions\Action::make('change_status')
                ->label('Change Status')
                ->icon('heroicon-o-arrow-path')
                ->form([
                    Forms\Components\Select::make('status')
                        ->label('New Status')
                        ->options(function () {
                            $allowedTransitions = $this->record->getAllowedTransitions();
                            $statusOptions = [
                                'open' => 'Open',
                                'in_progress' => 'In Progress',
                                'on_hold' => 'On Hold',
                                'resolved' => 'Resolved',
                                'closed' => 'Closed',
                            ];
                            
                            return array_intersect_key($statusOptions, array_flip($allowedTransitions));
                        })
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $updateData = [
                        'status' => $data['status'],
                        'updated_by_user_id' => Auth::id(),
                    ];

                    if (in_array($data['status'], ['resolved', 'closed'])) {
                        $updateData['closed_at'] = now();
                    }

                    $this->record->update($updateData);
                })
                ->visible(fn (): bool => Auth::user()->can('transition', $this->record)),
            Actions\Action::make('add_comment')
                ->label('Add Comment')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->form([
                    Forms\Components\Textarea::make('body')
                        ->label('Comment')
                        ->required()
                        ->rows(4),
                ])
                ->action(function (array $data): void {
                    TicketComment::create([
                        'ticket_id' => $this->record->id,
                        'user_id' => Auth::id(),
                        'body' => $data['body'],
                    ]);
                })
                ->visible(fn (): bool => Auth::user()->can('comment', $this->record)),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Ticket Information')
                    ->schema([
                        TextEntry::make('title'),
                        TextEntry::make('description'),
                        TextEntry::make('category')
                            ->badge(),
                        TextEntry::make('priority')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'low' => 'success',
                                'medium' => 'warning',
                                'high' => 'danger',
                            }),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'open' => 'warning',
                                'in_progress' => 'info',
                                'on_hold' => 'secondary',
                                'resolved' => 'success',
                                'closed' => 'danger',
                            }),
                    ])
                    ->columns(2),
                Section::make('Assignment & Timeline')
                    ->schema([
                        TextEntry::make('reporter_name')
                            ->label('Reporter'),
                        TextEntry::make('assigneeUser.name')
                            ->label('Assignee')
                            ->placeholder('Unassigned'),
                        TextEntry::make('hostel.name')
                            ->label('Hostel'),
                        TextEntry::make('sla_due_at')
                            ->label('SLA Due')
                            ->dateTime(),
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->label('Updated')
                            ->dateTime(),
                    ])
                    ->columns(2),
                Section::make('Comments')
                    ->schema([
                        TextEntry::make('comments')
                            ->label('')
                            ->formatStateUsing(function ($state) {
                                if (empty($state)) {
                                    return 'No comments yet.';
                                }
                                
                                $comments = collect($state)->map(function ($comment) {
                                    return sprintf(
                                        '<div class="mb-4 p-3 bg-gray-50 rounded-lg"><strong>%s</strong> - %s<br><span class="text-sm text-gray-600">%s</span><br>%s</div>',
                                        $comment['author']['name'] ?? 'Unknown',
                                        $comment['created_at'] ?? '',
                                        $comment['author']['role'] ?? '',
                                        $comment['body'] ?? ''
                                    );
                                })->join('');
                                
                                return new \Illuminate\Support\HtmlString($comments);
                            })
                            ->html(),
                    ])
                    ->collapsible(),
            ]);
    }
}







