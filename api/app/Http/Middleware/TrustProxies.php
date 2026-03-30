<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * Configured via TRUSTED_PROXIES env variable for production security.
     * Set to '*' in development, but use specific IPs in production.
     * 
     * For Cloudflare, use their published IP ranges:
     * https://www.cloudflare.com/ips-v4 and https://www.cloudflare.com/ips-v6
     * 
     * Example production value:
     * TRUSTED_PROXIES="173.245.48.0/20,103.21.244.0/22,103.22.200.0/22,103.31.4.0/22,141.101.64.0/18,108.162.192.0/18,190.93.240.0/20,188.114.96.0/20,197.234.240.0/22,198.41.128.0/17,162.158.0.0/15,104.16.0.0/13,104.24.0.0/14,172.64.0.0/13,131.0.72.0/22"
     *
     * @var array<int, string>|string|null
     */
    protected $proxies;

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    // Use all standard X-Forwarded-* headers instead of the AWS ELB–only
    // preset, so behind Cloudflare the original Host (e.g. skyline2025.mapservices.in)
    // is preserved and tenancy can resolve the correct tenant.
    protected $headers = Request::HEADER_X_FORWARDED_ALL;

    /**
     * Bootstrap the middleware.
     */
    public function __construct()
    {
        // Load trusted proxies from environment variable
        // In production, set TRUSTED_PROXIES to Cloudflare + ALB IP ranges
        // In development, use '*' to trust all proxies
        $trustedProxies = env('TRUSTED_PROXIES', '*');
        
        if ($trustedProxies === '*') {
            $this->proxies = '*';
        } else {
            // Parse comma-separated list of CIDR ranges
            $this->proxies = array_map('trim', explode(',', $trustedProxies));
        }
    }
}
