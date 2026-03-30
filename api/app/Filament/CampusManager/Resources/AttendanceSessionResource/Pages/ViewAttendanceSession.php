<?php

namespace App\Filament\CampusManager\Resources\AttendanceSessionResource\Pages;

use App\Domain\Attendance\Models\AttendanceSession;
use App\Filament\CampusManager\Resources\AttendanceSessionResource;
use App\Support\Attendance\RoomRosterQuery;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

class ViewAttendanceSession extends ViewRecord
{
    protected static string $resource = AttendanceSessionResource::class;

    #[Url]
    public ?int $room_id = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Back to Sessions')
                ->icon('heroicon-o-arrow-left')
                ->url(fn (): string => static::getResource()::getUrl('index')),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Session Details')
                    ->schema([
                        TextEntry::make('hostel.name')
                            ->label('Hostel'),
                        TextEntry::make('kind')
                            ->label('Type')
                            ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state))),
                        TextEntry::make('scheduled_at')
                            ->label('Scheduled At')
                            ->dateTime(),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'open' => 'success',
                                'scheduled' => 'warning',
                                'closed' => 'gray',
                                default => 'gray',
                            }),
                        TextEntry::make('window')
                            ->label('Window')
                            ->getStateUsing(function (AttendanceSession $record): string {
                                $openAt = $record->metadata['open_at'] ?? null;
                                $closeAt = $record->metadata['close_at'] ?? null;
                                
                                if (!$openAt || !$closeAt) {
                                    return 'N/A';
                                }
                                
                                $open = \Carbon\Carbon::parse($openAt)->format('H:i');
                                $close = \Carbon\Carbon::parse($closeAt)->format('H:i');
                                
                                return "{$open} - {$close}";
                            }),
                    ])
                    ->columns(2),
            ]);
    }

    public function getContent(): View
    {
        $session = $this->getRecord();
        $roomSummaries = RoomRosterQuery::roomSummaries($session);
        $selectedRoom = null;
        $roster = collect();

        if ($this->room_id) {
            $selectedRoom = $roomSummaries->firstWhere('room_id', $this->room_id);
            $roster = RoomRosterQuery::roomRoster($session, $this->room_id);
        }

        return view('filament.attendance.session-view', [
            'session' => $session,
            'roomSummaries' => $roomSummaries,
            'selectedRoom' => $selectedRoom,
            'roster' => $roster,
            'canMark' => $this->canMark(),
        ]);
    }

    public function markPresent(int $studentId): void
    {
        $session = $this->getRecord();
        $room = \App\Models\Room::find($this->room_id);
        
        if (!$room) {
            $this->dispatch('notify', [
                'type' => 'danger',
                'message' => 'Room not found',
            ]);
            return;
        }

        try {
            $user = auth()->user();
            $token = $user->createToken('filament')->plainTextToken;

            $response = \Illuminate\Support\Facades\Http::withToken($token)
                ->post("/api/v1/attendance/sessions/{$session->id}/rooms/{$room->id}/mark", [
                    'student_id' => $studentId,
                    'status' => 'present',
                ]);

            if ($response->successful()) {
                $this->dispatch('notify', [
                    'type' => 'success',
                    'message' => 'Student marked as Present',
                ]);
            } else {
                $message = $response->json('message', 'Failed to mark student');
                $this->dispatch('notify', [
                    'type' => 'danger',
                    'message' => $message,
                ]);
            }
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'danger',
                'message' => 'Failed to mark student: ' . $e->getMessage(),
            ]);
        }
    }

    public function markAbsent(int $studentId): void
    {
        $session = $this->getRecord();
        $room = \App\Models\Room::find($this->room_id);
        
        if (!$room) {
            $this->dispatch('notify', [
                'type' => 'danger',
                'message' => 'Room not found',
            ]);
            return;
        }

        try {
            $user = auth()->user();
            $token = $user->createToken('filament')->plainTextToken;

            $response = \Illuminate\Support\Facades\Http::withToken($token)
                ->post("/api/v1/attendance/sessions/{$session->id}/rooms/{$room->id}/mark", [
                    'student_id' => $studentId,
                    'status' => 'absent',
                    'comment' => 'Marked absent via UI',
                ]);

            if ($response->successful()) {
                $this->dispatch('notify', [
                    'type' => 'success',
                    'message' => 'Student marked as Absent',
                ]);
            } else {
                $message = $response->json('message', 'Failed to mark student');
                $this->dispatch('notify', [
                    'type' => 'danger',
                    'message' => $message,
                ]);
            }
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'danger',
                'message' => 'Failed to mark student: ' . $e->getMessage(),
            ]);
        }
    }

    public function submitRoom(): void
    {
        $session = $this->getRecord();
        $room = \App\Models\Room::find($this->room_id);
        
        if (!$room) {
            $this->dispatch('notify', [
                'type' => 'danger',
                'message' => 'Room not found',
            ]);
            return;
        }

        try {
            $user = auth()->user();
            $token = $user->createToken('filament')->plainTextToken;

            $response = \Illuminate\Support\Facades\Http::withToken($token)
                ->post("/api/v1/attendance/sessions/{$session->id}/rooms/{$room->id}/submit");

            if ($response->successful()) {
                $this->dispatch('notify', [
                    'type' => 'success',
                    'message' => 'Room submitted successfully',
                ]);
            } else {
                $status = $response->status();
                $data = $response->json();
                
                if ($status === 422) {
                    $message = $data['errors']['room'][0] ?? 'All non-leave students must be marked before submit.';
                } else {
                    $message = $data['message'] ?? 'Failed to submit room';
                }
                
                $this->dispatch('notify', [
                    'type' => 'danger',
                    'message' => $message,
                ]);
            }
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'danger',
                'message' => 'Failed to submit room: ' . $e->getMessage(),
            ]);
        }
    }

    private function canMark(): bool
    {
        $user = auth()->user();
        $session = $this->getRecord();
        
        return $user->hasRole('Warden') && 
               $session->status === 'open' &&
               $user->can('mark', $session);
    }

    public function getTitle(): string
    {
        $session = $this->getRecord();
        return "Attendance Session - {$session->hostel->name}";
    }
}
