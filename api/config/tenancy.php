<?php

declare(strict_types=1);

use Stancl\Tenancy\Database\Models\Domain;
use Stancl\Tenancy\Database\Models\Tenant;

return [
    'tenant_model' => \App\Models\Tenant::class,
    'id_generator' => Stancl\Tenancy\UUIDGenerator::class,

    'domain_model' => Domain::class,

    /**
     * The list of domains hosting your central app (Super Admin panel).
     *
     * Only relevant if you're using the domain or subdomain identification middleware.
     * 
     * Domains are configured in layers:
     * 1. Local development domains (always included)
     * 2. Environment-specific domains from TENANCY_CENTRAL_DOMAINS env var
     * 3. Production fallback domains (always included)
     * 
     * For staging, set: TENANCY_CENTRAL_DOMAINS="admin.staging.mapservices.in,api.staging.mapservices.in"
     * For production, the fallback domains are used.
     */
    'central_domains' => array_values(array_unique(array_filter(array_merge(
        // Local development domains (always included)
        [
            '127.0.0.1',
            'localhost',
            'admin.localhost',
            'api.localhost',
        ],
        // Environment-specific domains (from env variable)
        array_map('trim', explode(',', env('TENANCY_CENTRAL_DOMAINS', ''))),
        // Production fallback domains (always included)
        [
            'admin.mapservices.in',
            'api.mapservices.in',
        ]
    )))),

    /**
     * Tenancy bootstrappers are executed when tenancy is initialized.
     * Their responsibility is making Laravel features tenant-aware.
     *
     * Note: DatabaseTenancyBootstrapper is removed - we now use single shared database
     * with tenant_id scoping. All data is in the central database.
     *
     * To configure their behavior, see the config keys below.
     */
    'bootstrappers' => array_values(array_filter([
        // DatabaseTenancyBootstrapper removed - using single shared database with tenant_id
        // IMPORTANT: CacheTenancyBootstrapper requires a taggable cache store (Redis/Memcached).
        // For database/file/array stores it throws "This cache store does not support tagging."
        env('TENANCY_CACHE_TAGS', false) ? Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class : null,
        Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
        // Stancl\Tenancy\Bootstrappers\RedisTenancyBootstrapper::class, // Note: phpredis is needed
    ])),

    /**
     * Database tenancy config.
     * 
     * NOTE: DatabaseTenancyBootstrapper is removed. We now use a single shared PostgreSQL database
     * with tenant_id scoping. All tables are in the central database, and tenant isolation is
     * enforced via:
     * 1. Global scopes (TenantScope) for Eloquent queries
     * 2. PostgreSQL Row Level Security (RLS) policies for database-level enforcement
     * 
     * This configuration is kept for backward compatibility but is no longer used.
     */
    'database' => [
        'central_connection' => env('DB_CONNECTION', 'pgsql'),
        // All other database config is deprecated - using single shared database
    ],

    /**
     * Cache tenancy config. Used by CacheTenancyBootstrapper.
     *
     * This works for all Cache facade calls, cache() helper
     * calls and direct calls to injected cache stores.
     *
     * Each key in cache will have a tag applied on it. This tag is used to
     * scope the cache both when writing to it and when reading from it.
     *
     * You can clear cache selectively by specifying the tag.
     */
    'cache' => [
        'tag_base' => 'tenant', // This tag_base, followed by the tenant_id, will form a tag that will be applied on each cache call.
    ],

    /**
     * Filesystem tenancy config. Used by FilesystemTenancyBootstrapper.
     * https://tenancyforlaravel.com/docs/v3/tenancy-bootstrappers/#filesystem-tenancy-boostrapper.
     */
    'filesystem' => [
        /**
         * Each disk listed in the 'disks' array will be suffixed by the suffix_base, followed by the tenant_id.
         */
        'suffix_base' => 'tenant',
        'disks' => [
            'local',
            'public',
            // 's3',
        ],

        /**
         * Use this for local disks.
         *
         * See https://tenancyforlaravel.com/docs/v3/tenancy-bootstrappers/#filesystem-tenancy-boostrapper
         */
        'root_override' => [
            // Disks whose roots should be overridden after storage_path() is suffixed.
            'local' => '%storage_path%/app/',
            'public' => '%storage_path%/app/public/',
        ],

        /**
         * Should storage_path() be suffixed.
         *
         * Note: Disabling this will likely break local disk tenancy. Only disable this if you're using an external file storage service like S3.
         *
         * For the vast majority of applications, this feature should be enabled. But in some
         * edge cases, it can cause issues (like using Passport with Vapor - see #196), so
         * you may want to disable this if you are experiencing these edge case issues.
         */
        'suffix_storage_path' => true,

        /**
         * By default, asset() calls are made multi-tenant too. You can use global_asset() and mix()
         * for global, non-tenant-specific assets. However, you might have some issues when using
         * packages that use asset() calls inside the tenant app. To avoid such issues, you can
         * disable asset() helper tenancy and explicitly use tenant_asset() calls in places
         * where you want to use tenant-specific assets (product images, avatars, etc).
         */
        'asset_helper_tenancy' => false, // Disabled to fix Filament asset loading issues
    ],

    /**
     * Redis tenancy config. Used by RedisTenancyBootstrapper.
     *
     * Note: You need phpredis to use Redis tenancy.
     *
     * Note: You don't need to use this if you're using Redis only for cache.
     * Redis tenancy is only relevant if you're making direct Redis calls,
     * either using the Redis facade or by injecting it as a dependency.
     */
    'redis' => [
        'prefix_base' => 'tenant', // Each key in Redis will be prepended by this prefix_base, followed by the tenant id.
        'prefixed_connections' => [ // Redis connections whose keys are prefixed, to separate one tenant's keys from another.
            // 'default',
        ],
    ],

    /**
     * Features are classes that provide additional functionality
     * not needed for tenancy to be bootstrapped. They are run
     * regardless of whether tenancy has been initialized.
     *
     * See the documentation page for each class to
     * understand which ones you want to enable.
     */
    'features' => [
        // Stancl\Tenancy\Features\UserImpersonation::class,
        // Stancl\Tenancy\Features\TelescopeTags::class,
        // Stancl\Tenancy\Features\UniversalRoutes::class,
        // Stancl\Tenancy\Features\TenantConfig::class, // https://tenancyforlaravel.com/docs/v3/features/tenant-config
        // Stancl\Tenancy\Features\CrossDomainRedirect::class, // https://tenancyforlaravel.com/docs/v3/features/cross-domain-redirect
        // Stancl\Tenancy\Features\ViteBundler::class,
    ],

    /**
     * Should tenancy routes be registered.
     *
     * Tenancy routes include tenant asset routes. By default, this route is
     * enabled. But it may be useful to disable them if you use external
     * storage (e.g. S3 / Dropbox) or have a custom asset controller.
     */
    'routes' => true,

    /**
     * Parameters used by the tenants:migrate command.
     */
    'migration_parameters' => [
        '--force' => true, // This needs to be true to run migrations in production.
        '--path' => [database_path('migrations/tenant')],
        '--realpath' => true,
    ],

    /**
     * Parameters used by the tenants:seed command.
     */
    'seeder_parameters' => [
        '--class' => 'DatabaseSeeder', // root seeder class
        // '--force' => true, // This needs to be true to seed tenant databases in production
    ],
];
