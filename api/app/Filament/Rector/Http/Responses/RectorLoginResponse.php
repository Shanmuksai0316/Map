<?php

namespace App\Filament\Rector\Http\Responses;

use Filament\Http\Responses\Auth\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Http\RedirectResponse;

class RectorLoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        return new RedirectResponse(url('/rector'));
    }
}
