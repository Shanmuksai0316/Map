<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'fcm' => [
        'enabled' => env('FCM_ENABLED', false),
        'project_id' => env('FCM_PROJECT_ID'),
        // Service account JSON as string (recommended for environment variables)
        'service_account_json' => env('FCM_SERVICE_ACCOUNT_JSON'),
        // Or path to service account JSON file (alternative)
        'service_account_path' => env('FCM_SERVICE_ACCOUNT_PATH'),
        // Legacy server key (for backward compatibility, not used in V1)
        'server_key' => env('FCM_SERVER_KEY'),
    ],

    'msg91' => [
        'enabled' => env('MSG91_ENABLED', false),
        'key' => env('MSG91_API_KEY'),
        'sender_id' => env('MSG91_SENDER_ID', 'MAPHMS'),
        'templates' => [
            // OTP Templates
            'otp_login' => env('MSG91_OTP_LOGIN_TEMPLATE_ID', '1707176163248828919'),
            'student_welcome_otp' => env('MSG91_STUDENT_WELCOME_OTP_TEMPLATE_ID', '1707176579226173545'), // MAP_HMS_Student_Welcome_OTP

            // Approval Templates
            'approval_approved_outpass' => env('MSG91_APPROVAL_APPROVED_OUTPASS_TEMPLATE_ID', '1707176519403051360'),
            'approval_rejected_outpass' => env('MSG91_APPROVAL_REJECTED_OUTPASS_TEMPLATE_ID', '1707176519418020566'),
            'approval_approved_leave' => env('MSG91_APPROVAL_APPROVED_LEAVE_TEMPLATE_ID', '1707176519434535216'),
            'approval_rejected_leave' => env('MSG91_APPROVAL_REJECTED_LEAVE_TEMPLATE_ID', '1707176519456976264'),
            'approval_approved_sick_leave' => env('MSG91_APPROVAL_APPROVED_SICK_LEAVE_TEMPLATE_ID', '1707176519470323398'),
            'approval_rejected_sick_leave' => env('MSG91_APPROVAL_REJECTED_SICK_LEAVE_TEMPLATE_ID', '1707176519486927109'),
            'outpass_backup_code' => env('MSG91_OUTPASS_BACKUP_CODE_TEMPLATE_ID'),

            // Detailed leave decision templates (use rector approval templates for now)
            'leave_approved' => env('MSG91_LEAVE_APPROVED_TEMPLATE_ID', '1707176519434535216'),
            'leave_rejected' => env('MSG91_LEAVE_REJECTED_TEMPLATE_ID', '1707176519456976264'),
            'sick_leave_approved' => env('MSG91_SICK_LEAVE_APPROVED_TEMPLATE_ID', '1707176588371645233'),
            'sick_leave_rejected' => env('MSG91_SICK_LEAVE_REJECTED_TEMPLATE_ID', '1707176588388192649'),

            // Room change templates
            // Use MAP HMS Room Change Update template ID for approvals
            'room_change_approved' => env('MSG91_ROOM_CHANGE_APPROVED_TEMPLATE_ID', '1707176586306450881'),
            'room_change_rejected' => env('MSG91_ROOM_CHANGE_REJECTED_TEMPLATE_ID', '1707176588354428172'),
            'room_change_sla_breach' => env('MSG91_ROOM_CHANGE_SLA_BREACH_TEMPLATE_ID', '1707176588378147245'),

            // Checklist templates
            // MAP HMS Checklist Reminder (morning/afternoon)
            'checklist_morning' => env('MSG91_CHECKLIST_MORNING_TEMPLATE_ID', '1707176579440813999'),
            'checklist_afternoon' => env('MSG91_CHECKLIST_AFTERNOON_TEMPLATE_ID', '1707176579440813999'),
            // Checklist overdue
            'checklist_overdue' => env('MSG91_CHECKLIST_OVERDUE_TEMPLATE_ID', '17071765888366455555'),
            // Generic checklist template bucket (e.g. sent-back flows)
            'checklist_reminder' => env('MSG91_CHECKLIST_REMINDER_TEMPLATE_ID'),

            // Student templates
            'student_archived' => env('MSG91_STUDENT_ARCHIVED_TEMPLATE_ID', '1707176579555890852'),

            // Additional Templates
            'late_return_alert' => env('MSG91_LATE_RETURN_ALERT_TEMPLATE_ID', '1707176163293037766'),
            'emergency_alert' => env('MSG91_EMERGENCY_ALERT_TEMPLATE_ID', '1707176131647665467'),
            'attendance_alert' => env('MSG91_ATTENDANCE_ALERT_TEMPLATE_ID', '1707176131536210758'),

            // SLA Templates
            'sla_breach_outpass' => env('MSG91_SLA_BREACH_OUTPASS_TEMPLATE_ID', '1707176586313383822'),
            'sla_breach_leave' => env('MSG91_SLA_BREACH_LEAVE_TEMPLATE_ID', '1707176588396711600'),
            'sla_breach_sick_leave' => env('MSG91_SLA_BREACH_SICK_LEAVE_TEMPLATE_ID', '1707176588340529204'),
            'sla_warning_outpass' => env('MSG91_SLA_WARNING_OUTPASS_TEMPLATE_ID', '1707176588360093726'),
            'sla_warning_leave' => env('MSG91_SLA_WARNING_LEAVE_TEMPLATE_ID'),
            'sla_warning_sick_leave' => env('MSG91_SLA_WARNING_SICK_LEAVE_TEMPLATE_ID'),

            // Checkout Templates
            'checkout_reminder' => env('MSG91_CHECKOUT_REMINDER_TEMPLATE_ID', '1707176579431831599'), // MAP_HMS_Checkout_Alert
            'checkout_overdue' => env('MSG91_CHECKOUT_OVERDUE_TEMPLATE_ID', '1707176588347859514'),

            // Activation Template
            'activation_assignment' => env('MSG91_ACTIVATION_ASSIGNMENT_TEMPLATE_ID', '1707176579226173545'),
        ],
    ],

    'stpl' => [
        'enabled' => env('STPL_ENABLED', false),
        'api_key' => env('STPL_API_KEY'),
        'sender_id' => env('STPL_SENDER_ID', 'MAPHMS'),
        'route' => env('STPL_ROUTE', '4'),
        'templates' => [
            // OTP Templates
            'otp_login' => env('STPL_OTP_LOGIN_TEMPLATE_ID'),
            'student_welcome_otp' => env('STPL_STUDENT_WELCOME_OTP_TEMPLATE_ID'),
            
            // Approval Templates
            'approval_approved_outpass' => env('STPL_APPROVAL_APPROVED_OUTPASS_TEMPLATE_ID'),
            'approval_rejected_outpass' => env('STPL_APPROVAL_REJECTED_OUTPASS_TEMPLATE_ID'),
            'approval_approved_leave' => env('STPL_APPROVAL_APPROVED_LEAVE_TEMPLATE_ID'),
            'approval_rejected_leave' => env('STPL_APPROVAL_REJECTED_LEAVE_TEMPLATE_ID'),
            'approval_approved_sick_leave' => env('STPL_APPROVAL_APPROVED_SICK_LEAVE_TEMPLATE_ID'),
            'approval_rejected_sick_leave' => env('STPL_APPROVAL_REJECTED_SICK_LEAVE_TEMPLATE_ID'),
            'outpass_backup_code' => env('STPL_OUTPASS_BACKUP_CODE_TEMPLATE_ID'),
            // Detailed leave decision templates
            'leave_approved' => env('STPL_LEAVE_APPROVED_TEMPLATE_ID'),
            'leave_rejected' => env('STPL_LEAVE_REJECTED_TEMPLATE_ID'),
            'sick_leave_approved' => env('STPL_SICK_LEAVE_APPROVED_TEMPLATE_ID'),
            'sick_leave_rejected' => env('STPL_SICK_LEAVE_REJECTED_TEMPLATE_ID'),
            
            // Additional Templates
            'late_return_alert' => env('STPL_LATE_RETURN_ALERT_TEMPLATE_ID'),
            'emergency_alert' => env('STPL_EMERGENCY_ALERT_TEMPLATE_ID'),
            'attendance_alert' => env('STPL_ATTENDANCE_ALERT_TEMPLATE_ID'),
            
            // SLA Templates
            'sla_breach_outpass' => env('STPL_SLA_BREACH_OUTPASS_TEMPLATE_ID'),
            'sla_breach_leave' => env('STPL_SLA_BREACH_LEAVE_TEMPLATE_ID'),
            'sla_breach_sick_leave' => env('STPL_SLA_BREACH_SICK_LEAVE_TEMPLATE_ID'),
            'sla_warning_outpass' => env('STPL_SLA_WARNING_OUTPASS_TEMPLATE_ID'),
            'sla_warning_leave' => env('STPL_SLA_WARNING_LEAVE_TEMPLATE_ID'),
            'sla_warning_sick_leave' => env('STPL_SLA_WARNING_SICK_LEAVE_TEMPLATE_ID'),
            
            // Checkout Templates
            'checkout_reminder' => env('STPL_CHECKOUT_REMINDER_TEMPLATE_ID'),
            'checkout_overdue' => env('STPL_CHECKOUT_OVERDUE_TEMPLATE_ID'),
            
            // Activation Template
            'activation_assignment' => env('STPL_ACTIVATION_ASSIGNMENT_TEMPLATE_ID'),

            // Checklist Templates
            'checklist_morning' => env('STPL_CHECKLIST_MORNING_TEMPLATE_ID'),
            'checklist_afternoon' => env('STPL_CHECKLIST_AFTERNOON_TEMPLATE_ID'),
            'checklist_overdue' => env('STPL_CHECKLIST_OVERDUE_TEMPLATE_ID'),
            'checklist_reminder' => env('STPL_CHECKLIST_REMINDER_TEMPLATE_ID'),

            // Room change SLA breach (escalation)
            'room_change_sla_breach' => env('STPL_ROOM_CHANGE_SLA_BREACH_TEMPLATE_ID'),
        ],
    ],

'mobile_central_api' => env('CENTRAL_API_URL', 'https://api.mapservices.in/api/v1'),

];
