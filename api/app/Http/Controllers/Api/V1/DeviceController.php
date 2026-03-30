<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeviceController extends Controller
{
    public function register(Request $request)
    {
        $this->authorize('auth.login', User::class); // existing, working ability
        $data = $request->validate([
            'platform' => 'required|in:ios,android,web',
            'token'    => 'required|string|max:256',
            'meta'     => 'array'
        ]);

        $deviceId = $request->input('device_id') ?? $request->header('X-Device-Id') ?? sha1($data['token']);
        $deviceType = $request->input('device_type') ?? $data['platform'];
        $meta = $data['meta'] ?? [];
        $existing = DB::table('push_device_tokens')
            ->where('token', $data['token'])
            ->first();

        if ($existing) {
            DB::table('push_device_tokens')
                ->where('id', $existing->id)
                ->update([
                    'tenant_id' => auth()->user()->tenant_id,
                    'user_id' => auth()->id(),
                    'platform' => $data['platform'] ?? $deviceType,
                    'device_id' => $deviceId,
                    'device_type' => $deviceType,
                    'meta' => json_encode($meta),
                    'is_active' => true,
                    'deleted_at' => null,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('push_device_tokens')->insert([
                'tenant_id' => auth()->user()->tenant_id,
                'user_id' => auth()->id(),
                'platform' => $data['platform'] ?? $deviceType,
                'device_id' => $deviceId,
                'device_type' => $deviceType,
                'token' => $data['token'],
                'meta' => json_encode($meta),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['ok' => true]);
    }
}
