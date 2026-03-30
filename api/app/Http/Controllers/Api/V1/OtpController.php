<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\OtpService;
use Illuminate\Http\Request;

class OtpController extends Controller
{
    public function start(Request $r, OtpService $otp)
    {
        $r->validate(['purpose'=>'required|string|in:outpass_approve,ticket_close',
                      'channel'=>'required|in:sms','to'=>'required|string']);
        $otp->start(auth()->id(), $r->purpose, $r->channel, $r->to);
        return response()->json(['ok'=>true]);
    }

    public function verify(Request $r, OtpService $otp)
    {
        $r->validate(['purpose'=>'required|string|in:outpass_approve,ticket_close',
                      'code'=>'required|string']);
        $ok = $otp->verify(auth()->id(), $r->purpose, $r->code);
        return $ok ? response()->json(['ok'=>true]) : response()->json(['ok'=>false], 422);
    }
}
