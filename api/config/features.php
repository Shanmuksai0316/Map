<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Control which features are enabled in the application.
    |
    */

    // Core modules
    'attendance_module' => env('FEATURE_ATTENDANCE', true),
    'gate_module' => env('FEATURE_GATE_MODULE', true),
    'laundry_module' => true, // Always on (v1 scope)
    'sports_module' => true, // Always on (v1 scope)
    'checklists_module' => env('FEATURE_CHECKLISTS', true),
    'super_admin_staff_mgmt' => env('FEATURE_SUPER_ADMIN_STAFF_MGMT', true),
    
    // Attendance V2
    'attendance_v2' => env('FEATURE_ATTENDANCE_V2', true),
    'attendance_legacy_softkill' => env('FEATURE_ATTENDANCE_LEGACY_SOFTKILL', false),
    
    // Legacy features
    'gate_device_enforcement' => env('GATE_DEVICE_ENFORCEMENT', false),
    'notices_module' => env('NOTICES_MODULE', true),
    'super_admin_reports' => env('FEATURE_SUPER_ADMIN_REPORTS', true),

    // Onboarding v2
    'onboarding_v2' => env('FEATURE_ONBOARDING_V2', false),
];
