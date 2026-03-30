<?php

namespace App\Filament\Admin\Pages\Auth;

use Filament\Pages\Auth\Login as BaseLogin;
use Filament\Support\Enums\MaxWidth;

class Login extends BaseLogin
{
    /** Custom two-column login view (left: illustration + copy, right: form). */
    protected static string $view = 'filament.admin.pages.auth.login';

    /** Wide content so 70/30 grid (image | form) displays side-by-side on desktop. */
    public function getMaxWidth(): MaxWidth | string | null
    {
        return MaxWidth::SevenExtraLarge;
    }

    // Enable password reset functionality
    protected function hasPasswordReset(): bool
    {
        return true;
    }

    /**
     * Override mount to prevent errors when panel URL is accessed before initialization
     */
    public function mount(): void
    {
        try {
            parent::mount();
        } catch (\Illuminate\Routing\Exceptions\RouteNotFoundException $e) {
            // If route not found (e.g., discovered pages have missing routes), continue anyway
            // The login page doesn't need navigation items
            \Illuminate\Support\Facades\Log::warning('Admin Login mount error: Route not found (non-critical)', [
                'error' => $e->getMessage(),
                'route' => $e->getMessage(),
            ]);
            // Don't rethrow - allow login page to render even if navigation fails
        } catch (\Exception $e) {
            // If parent mount fails (e.g., panel URL issue), continue anyway
            // The login page doesn't need navigation items
            \Illuminate\Support\Facades\Log::warning('Admin Login mount error (non-critical)', [
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 300),
            ]);
            // Don't rethrow - allow login page to render even if navigation fails
        }
    }
}

