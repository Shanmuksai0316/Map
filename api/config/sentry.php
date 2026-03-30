<?php

/**
 * Sentry Laravel SDK configuration file.
 *
 * @see https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/
 */
return [

    // @see https://docs.sentry.io/product/sentry-basics/dsn-explainer/
    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),

    // @see https://spotlightjs.com/
    // 'spotlight' => env('SENTRY_SPOTLIGHT', false),

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#logger
    // 'logger' => Sentry\Logger\DebugFileLogger::class, // By default this will log to `storage_path('logs/sentry.log')`

    // The release version of your application
    // Example with dynamic git hash: trim(exec('git --git-dir ' . base_path('.git') . ' log --pretty="%h" -n1 HEAD'))
    'release' => env('SENTRY_RELEASE'),

    // When left empty or `null` the Laravel environment will be used (usually discovered from `APP_ENV` in your `.env`)
    'environment' => env('SENTRY_ENVIRONMENT'),

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#sample_rate
    'sample_rate' => env('SENTRY_SAMPLE_RATE') === null ? 1.0 : (float) env('SENTRY_SAMPLE_RATE'),

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#traces_sample_rate
    'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE') === null ? null : (float) env('SENTRY_TRACES_SAMPLE_RATE'),

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#profiles-sample-rate
    'profiles_sample_rate' => env('SENTRY_PROFILES_SAMPLE_RATE') === null ? null : (float) env('SENTRY_PROFILES_SAMPLE_RATE'),

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#enable_logs
    'enable_logs' => env('SENTRY_ENABLE_LOGS', false),

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#send_default_pii
    'send_default_pii' => env('SENTRY_SEND_DEFAULT_PII', false),

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#ignore_exceptions
    'ignore_exceptions' => [
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
    ],

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#ignore_transactions
    'ignore_transactions' => [
        // Ignore Laravel's default health URL
        '/up',
    ],

    // Breadcrumb specific configuration
    'breadcrumbs' => [
        // Capture Laravel logs as breadcrumbs
        'logs' => env('SENTRY_BREADCRUMBS_LOGS_ENABLED', true),

        // Capture Laravel cache events (hits, writes etc.) as breadcrumbs
        'cache' => env('SENTRY_BREADCRUMBS_CACHE_ENABLED', true),

        // Capture Livewire components like routes as breadcrumbs
        'livewire' => env('SENTRY_BREADCRUMBS_LIVEWIRE_ENABLED', true),

        // Capture SQL queries as breadcrumbs
        'sql_queries' => env('SENTRY_BREADCRUMBS_SQL_QUERIES_ENABLED', true),

        // Capture SQL query bindings (parameters) in SQL query breadcrumbs
        'sql_bindings' => env('SENTRY_BREADCRUMBS_SQL_BINDINGS_ENABLED', false),

        // Capture queue job events as breadcrumbs
        'queue_info' => env('SENTRY_BREADCRUMBS_QUEUE_INFO_ENABLED', true),

        // Capture HTTP client requests as breadcrumbs
        'http_client_requests' => env('SENTRY_BREADCRUMBS_HTTP_CLIENT_REQUESTS_ENABLED', true),

        // Capture send notifications as breadcrumbs
        'notifications' => env('SENTRY_BREADCRUMBS_NOTIFICATIONS_ENABLED', true),
    ],

    // Performance monitoring specific configuration
    'tracing' => [
        // Trace queue job executions as transactions
        'queue_job_transactions' => env('SENTRY_TRACE_QUEUE_ENABLED', true),

        // Capture queue jobs as spans
        'queue_jobs' => env('SENTRY_TRACE_QUEUE_JOBS_ENABLED', true),

        // Capture SQL queries as spans
        'sql_queries' => env('SENTRY_TRACE_SQL_QUERIES_ENABLED', true),

        // Capture SQL query bindings (parameters) in SQL query spans
        'sql_bindings' => env('SENTRY_TRACE_SQL_BINDINGS_ENABLED', false),

        // Capture where the Laravel application lives (used in Laravel 11)
        'view_components' => env('SENTRY_TRACE_VIEW_COMPONENTS_ENABLED', true),

        // Capture where the Laravel application lives (used in Laravel 11)
        'livewire' => env('SENTRY_TRACE_LIVEWIRE_ENABLED', true),

        // Capture cache events (hits, writes etc.) as spans
        'cache' => env('SENTRY_TRACE_CACHE_ENABLED', true),

        // Capture HTTP client requests as spans
        'http_client_requests' => env('SENTRY_TRACE_HTTP_CLIENT_REQUESTS_ENABLED', true),

        // Capture send notifications as spans
        'notifications' => env('SENTRY_TRACE_NOTIFICATIONS_ENABLED', true),
    ],

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#send_default_pii
    'send_default_pii' => env('SENTRY_SEND_DEFAULT_PII', false),

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#tags
    'tags' => [
        'project' => env('SENTRY_PROJECT_TAG', 'mapmars'),
    ],

];