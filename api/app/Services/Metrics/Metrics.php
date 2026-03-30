<?php

namespace App\Services\Metrics;

use Illuminate\Support\Facades\Log;

class Metrics
{
    private static bool $enabled = false;
    private static ?string $region = null;
    private static ?string $namespace = null;

    public static function init(): void
    {
        self::$enabled = !empty(env('AWS_REGION')) && !empty(env('CW_METRICS_NAMESPACE'));
        self::$region = env('AWS_REGION');
        self::$namespace = env('CW_METRICS_NAMESPACE', 'MAP-HMS');
    }

    /**
     * Reset metrics state (for testing)
     */
    public static function reset(): void
    {
        self::$enabled = false;
        self::$region = null;
        self::$namespace = null;
    }

    /**
     * Send a custom metric to CloudWatch
     */
    public static function count(string $name, int $value = 1, array $dimensions = []): bool
    {
        if (!self::$enabled) {
            try {
                Log::debug('Metrics disabled - no AWS configuration', [
                    'metric_name' => $name,
                    'value' => $value,
                    'dimensions' => $dimensions
                ]);
            } catch (\Exception $e) {
                // Ignore logging errors in testing
            }
            return true;
        }

        try {
            // Add default dimensions
            $environment = 'testing';
            try {
                $environment = app()->environment();
            } catch (\Exception $e) {
                // Fallback to testing in unit tests
            }
            
            $defaultDimensions = [
                'Environment' => $environment,
                'Application' => 'MAP-HMS',
            ];

            $dimensions = array_merge($defaultDimensions, $dimensions);

            // For v1.0, we'll just log the metrics
            // TODO: Implement actual CloudWatch API calls in future version
            Log::info('Custom metric', [
                'metric_name' => $name,
                'value' => $value,
                'dimensions' => $dimensions,
                'namespace' => self::$namespace,
                'region' => self::$region,
                'timestamp' => now()->toISOString(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send metric', [
                'metric_name' => $name,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send a gauge metric (current value)
     */
    public static function gauge(string $name, float $value, array $dimensions = []): bool
    {
        return self::count($name, (int) $value, $dimensions);
    }

    /**
     * Send a timing metric (duration in milliseconds)
     */
    public static function timing(string $name, int $milliseconds, array $dimensions = []): bool
    {
        return self::count($name, $milliseconds, $dimensions);
    }

    /**
     * Check if metrics are enabled
     */
    public static function isEnabled(): bool
    {
        return self::$enabled;
    }
}
