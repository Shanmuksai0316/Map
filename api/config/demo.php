<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Demo HMAC Secret
    |--------------------------------------------------------------------------
    |
    | Secret key used to sign demo token requests. Must match the secret
    | used by the client application to generate signatures.
    |
    | Generate a secure random key:
    | php -r "echo bin2hex(random_bytes(32));"
    |
    */

    'hmac_secret' => env('DEMO_HMAC_SECRET', null),

    /*
    |--------------------------------------------------------------------------
    | Demo Token Expiration
    |--------------------------------------------------------------------------
    |
    | Default expiration time for demo tokens in hours.
    |
    */

    'token_expires_hours' => (int) env('DEMO_TOKEN_EXPIRES_HOURS', 2),

    /*
    |--------------------------------------------------------------------------
    | Demo Allowed Roles
    |--------------------------------------------------------------------------
    |
    | List of roles that can be used for demo sessions.
    |
    */

    'allowed_roles' => [
        'campus_manager',
        'rector',
        'warden',
        'guard',
        'hk_supervisor',
        'rm_supervisor',
        'laundry_manager',
        'sports_manager',
        'student',
    ],

];

