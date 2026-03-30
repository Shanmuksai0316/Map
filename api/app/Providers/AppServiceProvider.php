<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register Horizon service provider
        if ($this->app->environment('local', 'production')) {
            $this->app->register(\Laravel\Horizon\HorizonServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Add PII redaction processor to all log channels
        $this->app->make('log')->getLogger()->pushProcessor(new \App\Logging\RedactSensitiveDataProcessor());

        // Initialize Metrics service
        \App\Services\Metrics\Metrics::init();

        // Ensure Livewire temp upload (local), tenant logo (public), and compiled views dirs exist
        // (fixes 500 on /livewire/upload-file and reduces 500 on admin/login when views dir is missing)
        try {
            $privateRoot = storage_path('app/private');
            $livewireTmp = $privateRoot . DIRECTORY_SEPARATOR . 'livewire-tmp';
            \Illuminate\Support\Facades\File::ensureDirectoryExists($privateRoot, 0775);
            \Illuminate\Support\Facades\File::ensureDirectoryExists($livewireTmp, 0775);
            \Illuminate\Support\Facades\File::ensureDirectoryExists(storage_path('framework/views'), 0775);
            \Illuminate\Support\Facades\File::ensureDirectoryExists(storage_path('framework/cache/data'), 0775);
            \Illuminate\Support\Facades\Storage::disk('public')->makeDirectory('logos');
            \Illuminate\Support\Facades\Storage::disk('public')->makeDirectory('branding/logos');
            \Illuminate\Support\Facades\Storage::disk('public')->makeDirectory('notices');
        } catch (\Throwable $e) {
            report($e);
        }

        // Force HTTPS in production so asset URLs (e.g. Filament select.js) load without mixed-content errors
        if (config('app.env') === 'production') {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
