<?php

namespace App\Filament\CollegeMgmt\Http\Responses;

use Filament\Http\Responses\Auth\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class CollegeMgmtLoginResponse implements LoginResponseContract
{
    public function toResponse($request): Response
    {
        return new RedirectResponse('/college-mgmt');
    }
}
