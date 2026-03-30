<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SystemSettingsService
{
    /**
     * Cache key prefix for settings.
     */
    private const CACHE_PREFIX = 'system_settings:';

    /**
     * Cache TTL in seconds (1 hour).
     */
    private const CACHE_TTL = 3600;

    /**
     * Default settings values.
     */
    private const DEFAULTS = [
        // Branding
        'app_name' => 'MAP HMS',
        'app_logo' => null,
        'support_email' => 'support@mapmars.com',

        // Notifications
        'sms_enabled' => true,
        'email_enabled' => true,
        'push_enabled' => true,

        // Security
        'otp_expiry_minutes' => 5,
        'max_login_attempts' => 5,
        'lockout_minutes' => 15,
        'session_timeout_minutes' => 60,

        // Modules
        'module_sports' => true,
        'module_laundry' => true,
        'module_security' => true,
        'module_feedback' => false,

        // Maintenance
        'maintenance_mode' => false,
        'maintenance_message' => 'System is under maintenance. Please try again later.',
    ];

    /**
     * Get a setting value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = self::CACHE_PREFIX . $key;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($key, $default) {
            $row = DB::table('system_settings')->where('key', $key)->first();

            if (!$row) {
                return $default ?? (self::DEFAULTS[$key] ?? null);
            }

            return $this->castValue($row->value, $row->type ?? 'string');
        });
    }

    /**
     * Set a setting value.
     */
    public function set(string $key, mixed $value): void
    {
        $type = $this->inferType($value);
        $storedValue = $this->serializeValue($value, $type);

        DB::table('system_settings')->updateOrInsert(
            ['key' => $key],
            [
                'value' => $storedValue,
                'type' => $type,
                'updated_at' => now(),
            ]
        );

        // Clear cache
        Cache::forget(self::CACHE_PREFIX . $key);
    }

    /**
     * Get multiple settings by prefix.
     */
    public function getGroup(string $prefix): array
    {
        $rows = DB::table('system_settings')
            ->where('key', 'like', $prefix . '%')
            ->get();

        $settings = [];
        foreach ($rows as $row) {
            $settings[$row->key] = $this->castValue($row->value, $row->type ?? 'string');
        }

        // Fill in defaults for missing keys
        foreach (self::DEFAULTS as $key => $default) {
            if (str_starts_with($key, $prefix) && !isset($settings[$key])) {
                $settings[$key] = $default;
            }
        }

        return $settings;
    }

    /**
     * Get all settings.
     */
    public function all(): array
    {
        $rows = DB::table('system_settings')->get();

        $settings = [];
        foreach ($rows as $row) {
            $settings[$row->key] = $this->castValue($row->value, $row->type ?? 'string');
        }

        // Fill in defaults
        foreach (self::DEFAULTS as $key => $default) {
            if (!isset($settings[$key])) {
                $settings[$key] = $default;
            }
        }

        return $settings;
    }

    /**
     * Set multiple settings at once.
     */
    public function setMany(array $settings): void
    {
        DB::transaction(function () use ($settings) {
            foreach ($settings as $key => $value) {
                $this->set($key, $value);
            }
        });
    }

    /**
     * Reset a setting to its default value.
     */
    public function reset(string $key): void
    {
        DB::table('system_settings')->where('key', $key)->delete();
        Cache::forget(self::CACHE_PREFIX . $key);
    }

    /**
     * Clear all settings cache.
     */
    public function clearCache(): void
    {
        // Clear all cached settings
        foreach (array_keys(self::DEFAULTS) as $key) {
            Cache::forget(self::CACHE_PREFIX . $key);
        }

        // Also clear any other cached settings from DB
        $keys = DB::table('system_settings')->pluck('key');
        foreach ($keys as $key) {
            Cache::forget(self::CACHE_PREFIX . $key);
        }
    }

    /**
     * Check if maintenance mode is enabled.
     */
    public function isMaintenanceMode(): bool
    {
        return (bool) $this->get('maintenance_mode', false);
    }

    /**
     * Check if a module is enabled.
     */
    public function isModuleEnabled(string $module): bool
    {
        return (bool) $this->get('module_' . $module, true);
    }

    /**
     * Cast a stored value to its proper type.
     */
    private function castValue(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean', 'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer', 'int' => (int) $value,
            'float', 'double' => (float) $value,
            'array', 'json' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Serialize a value for storage.
     */
    private function serializeValue(mixed $value, string $type): string
    {
        return match ($type) {
            'boolean', 'bool' => $value ? '1' : '0',
            'array', 'json' => json_encode($value),
            default => (string) $value,
        };
    }

    /**
     * Infer the type of a value.
     */
    private function inferType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'float',
            is_array($value) => 'json',
            default => 'string',
        };
    }
}

