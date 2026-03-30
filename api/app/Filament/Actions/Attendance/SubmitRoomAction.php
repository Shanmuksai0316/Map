<?php

namespace App\Filament\Actions\Attendance;

use App\Domain\Attendance\Models\AttendanceSession;
use App\Models\Room;
use Filament\Actions\Action;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class SubmitRoomAction extends Action
{
    public static function getDefaultName(): string
    {
        return 'submitRoom';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Submit Room')
            ->icon('heroicon-o-paper-airplane')
            ->color('primary')
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading('Submit Room')
            ->modalDescription('Are you sure you want to submit this room? This will finalize the attendance for all students.')
            ->modalSubmitActionLabel('Submit')
            ->action(function (AttendanceSession $session, Room $room): void {
                $this->submitRoom($session, $room);
            });
    }

    private function submitRoom(AttendanceSession $session, Room $room): void
    {
        try {
            $user = auth()->user();
            $token = $user->createToken('filament')->plainTextToken;

            $response = Http::withToken($token)
                ->post("/api/v1/attendance/sessions/{$session->id}/rooms/{$room->id}/submit");

            if ($response->successful()) {
                $this->success('Room submitted successfully');
                $this->redirect(request()->url(), navigate: true);
            } else {
                $status = $response->status();
                $data = $response->json();
                
                if ($status === 422) {
                    $message = $data['errors']['room'][0] ?? 'All non-leave students must be marked before submit.';
                    $this->danger($message);
                } else {
                    $message = $data['message'] ?? 'Failed to submit room';
                    $this->danger($message);
                }
            }
        } catch (\Exception $e) {
            $this->danger('Failed to submit room: ' . $e->getMessage());
        }
    }
}


