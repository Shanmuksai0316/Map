<?php

namespace App\Filament\Actions\Attendance;

use App\Domain\Attendance\Models\AttendanceSession;
use App\Models\Room;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class MarkAbsentAction extends Action
{
    public static function getDefaultName(): string
    {
        return 'markAbsent';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Mark Absent')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->size('sm')
            ->form([
                Textarea::make('comment')
                    ->label('Comment (Optional)')
                    ->placeholder('Reason for absence...')
                    ->maxLength(200)
                    ->rows(3),
            ])
            ->action(function (AttendanceSession $session, Room $room, int $studentId, array $data): void {
                $this->markStudent($session, $room, $studentId, 'absent', $data['comment'] ?? null);
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


