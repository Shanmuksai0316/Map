<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PushNotifier
{
    public function sendToUser(int $userId, string $title, string $body, array $data = []): void
    {
        $serverKey = config('services.fcm.server_key');
        if (blank($serverKey)) {
            Log::info('PushNotifier noop', compact('userId','title','body','data'));
            return;
        }

        $tokens = DB::table('push_device_tokens')->where('user_id',$userId)->pluck('token')->all();
        if (empty($tokens)) return;

        $payload = [
            'registration_ids' => $tokens,
            'notification' => ['title'=>$title,'body'=>$body],
            'data' => $data,
        ];

        Http::withToken($serverKey)
            ->acceptJson()
            ->post('https://fcm.googleapis.com/fcm/send', $payload)
            ->throw(); // swallow in real env if needed
    }
}
