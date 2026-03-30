<?php

return [
    'room_changes' => [
        'sla_hours' => (int) env('ROOM_CHANGE_SLA_HOURS', 24),
        'escalation_cooldown_minutes' => (int) env('ROOM_CHANGE_ESCALATION_COOLDOWN', 120),
    ],
    'checklists' => [
        'morning_hour' => (int) env('CHECKLIST_REMINDER_MORNING_HOUR', 9),
        'afternoon_hour' => (int) env('CHECKLIST_REMINDER_AFTERNOON_HOUR', 15),
        'overdue_grace_hours' => (int) env('CHECKLIST_OVERDUE_GRACE_HOURS', 4),
    ],
];

