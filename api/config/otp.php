<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OTP Automation Secret
    |--------------------------------------------------------------------------
    |
    | When set, requests bearing the matching X-Automation-Secret header can
    | receive the raw OTP in responses (even in production) for automated tests.
    | Keep this value secret and rotate regularly.
    |
    */
    'automation_secret' => env('OTP_AUTOMATION_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | OTP Automation Code
    |--------------------------------------------------------------------------
    |
    | Fixed OTP value returned when automation mode is enabled. Defaults to
    | "000000" but can be customised via environment variable.
    |
    */
    'automation_code' => env('OTP_AUTOMATION_CODE', '000000'),

    /*
    |--------------------------------------------------------------------------
    | OTP Bypass Mode (Production Testing)
    |--------------------------------------------------------------------------
    |
    | When enabled, allows a fixed bypass code to be used for OTP verification
    | without requiring actual SMS delivery. This is useful for end-to-end
    | testing in production when SMS provider is not working.
    |
    | WARNING: Disable this after testing is complete!
    |
    */
    'bypass_enabled' => env('OTP_BYPASS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | OTP Bypass Code
    |--------------------------------------------------------------------------
    |
    | The fixed OTP code that will be accepted when bypass mode is enabled.
    | Defaults to "123456".
    |
    */
    'bypass_code' => env('OTP_BYPASS_CODE', '123456'),
];

