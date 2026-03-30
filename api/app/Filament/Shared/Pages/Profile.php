<?php

namespace App\Filament\Shared\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class Profile extends Page
{
    protected static ?string $title = 'My Profile';

    protected static ?string $slug = 'profile';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.shared.pages.profile';

    public function mount(): void
    {
        abort_unless(Auth::check(), 403);
    }

    public function getUserData(): array
    {
        $user = Auth::user();
        $tenant = function_exists('tenant') ? tenant() : null;

        return [
            'name' => $user?->name ?? 'User',
            'role' => $user?->roles?->first()?->name ?? ($user?->kind ? str_replace('_', ' ', ucfirst($user->kind)) : 'Staff'),
            'unique_id' => $user?->id ?? '—',
            'phone' => $user?->phone ?? 'N/A',
            'email' => $user?->email ?? 'N/A',
            'college' => $tenant?->name ?? ($user?->tenant?->name ?? 'N/A'),
            'tenant_code' => $tenant?->code ?? 'N/A',
        ];
    }

    public static function canAccess(): bool
    {
        return Auth::check();
    }
}
