<?php

namespace App\Filament\Rector\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class Profile extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static string $view = 'filament.rector.pages.profile';

    protected static ?string $title = 'My Profile';

    protected static bool $shouldRegisterNavigation = false;

    public function mount(): void
    {
        abort_unless(Auth::check(), 403);
    }

    public function getUserData(): array
    {
        $user = Auth::user();
        $tenant = tenant();

        return [
            'name' => $user->name,
            'role' => $user->roles->first()?->name ?? 'Rector',
            'unique_id' => $user->id,
            'phone' => $user->phone,
            'email' => $user->email ?? 'N/A',
            'college' => $tenant?->name ?? 'N/A',
            'tenant_code' => $tenant?->code ?? 'N/A',
        ];
    }

    public static function canAccess(): bool
    {
        return Auth::check();
    }
}

