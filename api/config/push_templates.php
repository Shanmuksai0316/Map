<?php

declare(strict_types=1);

/**
 * Central registry of push notification templates, grouped by role.
 *
 * Keys are stable template IDs (used from code), values define:
 *  - role:    High-level audience this template is for (Campus Manager, Rector, etc.)
 *  - purpose: Short description of why this exists
 *  - title:   Default title template (may contain {placeholders})
 *  - body:    Default body template (may contain {placeholders})
 */

return [
    // ───────────────────────────────────────── Campus Manager ─────────────────────────────────────────
    'campus_manager.request_created' => [
        'role' => 'Campus Manager',
        'purpose' => 'New request exists (ops visibility).',
        'title' => 'New request #{request_id}',
        'body' => '{summary}',
    ],
    'campus_manager.request_status_changed' => [
        'role' => 'Campus Manager',
        'purpose' => 'Progress/closure update for a request.',
        'title' => 'Request #{request_id} {status_label}',
        'body' => '{summary}',
    ],
    'campus_manager.request_comment_added' => [
        'role' => 'Campus Manager',
        'purpose' => 'Discussion update on a request.',
        'title' => 'New update on request #{request_id}',
        'body' => '{comment_author}: {comment_snippet}',
    ],
    'campus_manager.outpass_submitted' => [
        'role' => 'Campus Manager',
        'purpose' => 'Approval needed for out-pass.',
        'title' => 'New out-pass requires approval',
        'body' => 'Student {student_name}, {time_range}.',
    ],
    'campus_manager.notice_published' => [
        'role' => 'Campus Manager',
        'purpose' => 'Targeted notice / announcement.',
        'title' => 'New notice: {notice_title}',
        'body' => '{notice_body}',
    ],
    'campus_manager.request_reminder' => [
        'role' => 'Campus Manager',
        'purpose' => 'Reminder for pending request.',
        'title' => 'Reminder: Request #{request_id}',
        'body' => 'Pending action. Please review.',
    ],
    'campus_manager.checkout_outpass' => [
        'role' => 'Campus Manager',
        'purpose' => 'Student checked out on an out-pass.',
        'title' => 'Student checked out',
        'body' => '{student_name} checked out for out-pass #{outpass_id} at {time}.',
    ],
    'campus_manager.checkout_leave' => [
        'role' => 'Campus Manager',
        'purpose' => 'Student checked out on leave.',
        'title' => 'Student checked out (leave)',
        'body' => '{student_name} checked out for leave #{leave_id} at {time}.',
    ],

    // ───────────────────────────────────────── Rector ─────────────────────────────────────────
    'rector.outpass_submitted' => [
        'role' => 'Rector',
        'purpose' => 'Approval needed for out-pass.',
        'title' => 'New out-pass requires approval',
        'body' => 'Student {student_name}, {time_range}.',
    ],
    'rector.leave_submitted' => [
        'role' => 'Rector',
        'purpose' => 'Approval needed for leave.',
        'title' => 'New leave request requires approval',
        'body' => 'Student {student_name}, {date_range}.',
    ],
    'rector.guest_entry_submitted' => [
        'role' => 'Rector',
        'purpose' => 'Approval/awareness for guest entry.',
        'title' => 'New guest entry request',
        'body' => 'Student {student_name}, visit on {visit_date}.',
    ],
    'rector.notice_published' => [
        'role' => 'Rector',
        'purpose' => 'Targeted notice / announcement.',
        'title' => 'New notice: {notice_title}',
        'body' => '{notice_body}',
    ],
    'rector.gate_pass_used' => [
        'role' => 'Rector',
        'purpose' => 'Confirm gate pass was used.',
        'title' => 'Gate pass used',
        'body' => 'Out-pass #{outpass_id} used at {time}. Student: {student_name}.',
    ],

    // ───────────────────────────────────────── Warden ─────────────────────────────────────────
    'warden.request_created' => [
        'role' => 'Warden',
        'purpose' => 'New request in their area.',
        'title' => 'New request #{request_id}',
        'body' => '{summary}',
    ],
    'warden.request_status_changed' => [
        'role' => 'Warden',
        'purpose' => 'Progress/closure update.',
        'title' => 'Request #{request_id} {status_label}',
        'body' => '{summary}',
    ],
    'warden.request_comment_added' => [
        'role' => 'Warden',
        'purpose' => 'Discussion update.',
        'title' => 'New update on request #{request_id}',
        'body' => '{comment_author}: {comment_snippet}',
    ],
    'warden.notice_published' => [
        'role' => 'Warden',
        'purpose' => 'Notice / announcement.',
        'title' => 'New notice: {notice_title}',
        'body' => '{notice_body}',
    ],
    'warden.checkout_outpass' => [
        'role' => 'Warden',
        'purpose' => 'Student checkout on out-pass.',
        'title' => 'Student checked out',
        'body' => '{student_name} checked out for out-pass #{outpass_id} at {time}.',
    ],
    'warden.checkout_leave' => [
        'role' => 'Warden',
        'purpose' => 'Student checkout on leave.',
        'title' => 'Student checked out (leave)',
        'body' => '{student_name} checked out for leave #{leave_id} at {time}.',
    ],
    'warden.guest_entry_approved' => [
        'role' => 'Warden',
        'purpose' => 'Guest entry approved – guests arriving.',
        'title' => 'Guest entry approved',
        'body' => 'Student {student_name} will receive {guests} on {visit_date}.',
    ],

    // ───────────────────────────────────────── HK Supervisor ─────────────────────────────────────────
    'hk_supervisor.request_created' => [
        'role' => 'HK Supervisor',
        'purpose' => 'New HK request.',
        'title' => 'New request #{request_id}',
        'body' => '{summary}',
    ],
    'hk_supervisor.request_status_changed' => [
        'role' => 'HK Supervisor',
        'purpose' => 'HK request progress update.',
        'title' => 'Request #{request_id} {status_label}',
        'body' => '{summary}',
    ],
    'hk_supervisor.request_comment_added' => [
        'role' => 'HK Supervisor',
        'purpose' => 'HK discussion update.',
        'title' => 'New update on request #{request_id}',
        'body' => '{comment_author}: {comment_snippet}',
    ],
    'hk_supervisor.notice_published' => [
        'role' => 'HK Supervisor',
        'purpose' => 'Notice / announcement.',
        'title' => 'New notice: {notice_title}',
        'body' => '{notice_body}',
    ],
    'hk_supervisor.request_reminder' => [
        'role' => 'HK Supervisor',
        'purpose' => 'Reminder for pending HK request.',
        'title' => 'Reminder: Request #{request_id}',
        'body' => 'Pending action. Please review.',
    ],

    // ───────────────────────────────────────── RM Supervisor ─────────────────────────────────────────
    'rm_supervisor.request_created' => [
        'role' => 'RM Supervisor',
        'purpose' => 'New R&M request.',
        'title' => 'New request #{request_id}',
        'body' => '{summary}',
    ],
    'rm_supervisor.request_status_changed' => [
        'role' => 'RM Supervisor',
        'purpose' => 'R&M request update.',
        'title' => 'Request #{request_id} {status_label}',
        'body' => '{summary}',
    ],
    'rm_supervisor.request_comment_added' => [
        'role' => 'RM Supervisor',
        'purpose' => 'R&M discussion update.',
        'title' => 'New update on request #{request_id}',
        'body' => '{comment_author}: {comment_snippet}',
    ],
    'rm_supervisor.notice_published' => [
        'role' => 'RM Supervisor',
        'purpose' => 'Notice / announcement.',
        'title' => 'New notice: {notice_title}',
        'body' => '{notice_body}',
    ],
    'rm_supervisor.request_reminder' => [
        'role' => 'RM Supervisor',
        'purpose' => 'Reminder for pending R&M request.',
        'title' => 'Reminder: Request #{request_id}',
        'body' => 'Pending action. Please review.',
    ],

    // ───────────────────────────────────────── Guard ─────────────────────────────────────────
    'guard.notice_published' => [
        'role' => 'Guard',
        'purpose' => 'Targeted notice.',
        'title' => 'New notice: {notice_title}',
        'body' => '{notice_body}',
    ],
    'guard.outpass_gate_action' => [
        'role' => 'Guard',
        'purpose' => 'Gate action required on approved out-pass.',
        'title' => 'Gate action required',
        'body' => 'Out-pass #{outpass_id}, student {student_name}, {time_range}.',
    ],
    'guard.post_change' => [
        'role' => 'Guard',
        'purpose' => 'Guard post/location change.',
        'title' => 'Post change',
        'body' => 'Move to post {post_name} from {time}.',
    ],
    'guard.gate_pass_scanned' => [
        'role' => 'Guard',
        'purpose' => 'Entry allowed notification.',
        'title' => 'Gate pass scanned',
        'body' => 'Entry allowed for {student_name}. Time: {time}.',
    ],
    'guard.gate_pass_denied' => [
        'role' => 'Guard',
        'purpose' => 'Entry denied notification.',
        'title' => 'Gate pass denied',
        'body' => 'Out-pass #{outpass_id} is expired/invalid.',
    ],
    'guard.checkout_outpass' => [
        'role' => 'Guard',
        'purpose' => 'Student checkout (out-pass).',
        'title' => 'Student checked out',
        'body' => '{student_name} checked out for out-pass #{outpass_id} at {time}.',
    ],
    'guard.checkout_leave' => [
        'role' => 'Guard',
        'purpose' => 'Student checkout (leave).',
        'title' => 'Student checked out (leave)',
        'body' => '{student_name} checked out for leave #{leave_id} at {time}.',
    ],
    'guard.gate_pass_used' => [
        'role' => 'Guard',
        'purpose' => 'Confirm gate pass used.',
        'title' => 'Gate pass used',
        'body' => 'Out-pass #{outpass_id} used at {time}. Student: {student_name}.',
    ],

    // ───────────────────────────────────────── Laundry Manager ─────────────────────────────────────────
    'laundry_manager.notice_published' => [
        'role' => 'Laundry Manager',
        'purpose' => 'Targeted notice.',
        'title' => 'New notice: {notice_title}',
        'body' => '{notice_body}',
    ],
    'laundry_manager.checklist_assigned' => [
        'role' => 'Laundry Manager',
        'purpose' => 'Checklist assigned.',
        'title' => 'New checklist assigned',
        'body' => 'Please start your assigned checklist.',
    ],
    'laundry_manager.checklist_overdue' => [
        'role' => 'Laundry Manager',
        'purpose' => 'Checklist overdue.',
        'title' => 'Checklist overdue',
        'body' => 'Submit your assigned checklist now.',
    ],

    // ───────────────────────────────────────── Sports Manager ─────────────────────────────────────────
    'sports_manager.notice_published' => [
        'role' => 'Sports Manager',
        'purpose' => 'Targeted sports/ops notice.',
        'title' => 'New notice: {notice_title}',
        'body' => '{notice_body}',
    ],
    'sports_manager.booking_confirmed' => [
        'role' => 'Sports Manager',
        'purpose' => 'Facility booking confirmed.',
        'title' => 'Booking confirmed',
        'body' => '{facility_name}, {time_range}.',
    ],
    'sports_manager.booking_canceled' => [
        'role' => 'Sports Manager',
        'purpose' => 'Facility booking canceled.',
        'title' => 'Booking canceled',
        'body' => 'Reason: {reason}.',
    ],

    // ───────────────────────────────────────── Student App ─────────────────────────────────────────
    'student.outpass_decision' => [
        'role' => 'Student',
        'purpose' => 'Out-pass decision notification.',
        'title' => 'Out-pass {decision_label}',
        'body' => '{message}',
    ],
    'student.leave_decision' => [
        'role' => 'Student',
        'purpose' => 'Leave decision notification.',
        'title' => 'Leave {decision_label}',
        'body' => 'Valid from {from} to {to}. Note: {note}.',
    ],
    'student.notice_published' => [
        'role' => 'Student',
        'purpose' => 'Targeted notice.',
        'title' => 'New notice: {notice_title}',
        'body' => '{notice_body}',
    ],
    'student.room_allocation_approved' => [
        'role' => 'Student',
        'purpose' => 'Room/hostel allocation approval.',
        'title' => 'Room allocation approved',
        'body' => 'You have been allocated to {hostel_name}, Floor {floor_number}, Room {room_number}, Bed {bed_label}, effective from {effective_date}.',
    ],
    'student.laundry_status' => [
        'role' => 'Student',
        'purpose' => 'Laundry status update.',
        'title' => 'Laundry status update',
        'body' => '{status_message}',
    ],

    'student.checkout_reminder' => [
        'role' => 'Student',
        'purpose' => 'Upcoming checkout reminder.',
        'title' => 'Checkout reminder',
        'body' => 'Reminder: checkout for {student_name} is due in {days_remaining} (Hostel: {hostel_name}). Please complete the checkout checklist.',
    ],
    'student.checkout_overdue' => [
        'role' => 'Student',
        'purpose' => 'Checkout overdue reminder.',
        'title' => 'Checkout overdue',
        'body' => 'Checkout for {student_name} is overdue since {since_date}. Please complete checkout immediately.',
    ],

    'student.outpass_used' => [
        'role' => 'Student',
        'purpose' => 'Confirm that an out-pass was used at the gate.',
        'title' => 'Out-pass used',
        'body' => 'Your out-pass #{outpass_id} was used at {time}.',
    ],
    'student.parcel_informed' => [
        'role' => 'Student',
        'purpose' => 'Parcel arrived – warden has informed you.',
        'title' => 'Parcel arrived',
        'body' => 'A parcel has arrived for you (Room: {room_number}). Your 4-digit pickup code is {code}. Show this code to the warden when collecting.',
    ],
    'student.parcel_received' => [
        'role' => 'Student',
        'purpose' => 'Parcel handover completed.',
        'title' => 'Parcel received',
        'body' => 'Your parcel #{parcel_id} has been collected successfully.',
    ],

    // ───────────────────────────────────────── Warden (parcel) ─────────────────────────────────────────
    'warden.parcel_received' => [
        'role' => 'Warden',
        'purpose' => 'Parcel handover completed by another warden.',
        'title' => 'Parcel received',
        'body' => 'Parcel for {student_name} was collected (code verified).',
    ],

    // ───────────────────────────────────────── Staff All (generic) ─────────────────────────────────────────
    'staff_all.notice_published' => [
        'role' => 'Staff All',
        'purpose' => 'Generic role/tenant-specific notice.',
        'title' => 'New notice: {notice_title}',
        'body' => '{notice_body}',
    ],
    'staff_all.checklist_assigned' => [
        'role' => 'Staff All',
        'purpose' => 'Checklist assigned.',
        'title' => 'New checklist assigned',
        'body' => 'Please start your assigned checklist.',
    ],
    'staff_all.checklist_overdue' => [
        'role' => 'Staff All',
        'purpose' => 'Checklist overdue.',
        'title' => 'Checklist overdue',
        'body' => 'Submit your assigned checklist now.',
    ],
];

