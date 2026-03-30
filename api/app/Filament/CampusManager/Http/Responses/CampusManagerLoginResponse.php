<?php

namespace App\Filament\CampusManager\Http\Responses;

use Filament\Http\Responses\Auth\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Http\RedirectResponse;

/**
 * Custom login response for the Campus Manager panel.
 *
 * IMPORTANT: This MUST return Illuminate\Http\RedirectResponse, NOT Livewire's Redirector.
 * We use `new RedirectResponse(url(...))` instead of `redirect()->to(...)` to avoid
 * Livewire context issues where Livewire intercepts the redirect and breaks the flow.
 */
class CampusManagerLoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        return new RedirectResponse(url('/campus-manager'));
    }
}
