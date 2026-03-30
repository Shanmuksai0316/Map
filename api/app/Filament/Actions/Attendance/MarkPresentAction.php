<?php

namespace App\Filament\Actions\Attendance;

use App\Domain\Attendance\Models\AttendanceSession;
use App\Models\Room;
use Filament\Actions\Action;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class MarkPresentAction extends Action
{
    public static function getDefaultName(): string
    {
        return 'markPresent';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Mark Present')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->size('sm')
            ->requiresConfirmation(false)
            ->action(function (AttendanceSession $session, Room $room, int $studentId): void {
                $this->markStudent($session, $room, $studentId, 'present');
            });
    }

    private function markStudent(AttendanceSession $session, Room $room, int $studentId, string $status, ?string $comment = null): void
    {
        try {
            $user = auth()->user();
            $token = $user->createToken('filament')->plainTextToken;

            $response = Http::withToken($token)
                ->post("/api/v1/attendance/sessions/{$session->id}/rooms/{$room->id}/mark", [
                    'student_id' => $studentId,
                    'status' => $status,
                    'comment' => $comment,
                ]);

            if ($response->successful()) {
                $this->success('Student marked as ' . ucfirst($status));
                $this->redirect(request()->url(), navigate: true);
            } else {
                $errors = $response->json('errors', []);
                $message = $response->json('message', 'Failed to mark student');
                $this->danger($message);
            }
        } catch (\Exception $e) {
            $this->danger('Failed to mark student: ' . $e->getMessage());
        }
    }
}


