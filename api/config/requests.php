<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Request SLA (Service Level Agreement)
    |--------------------------------------------------------------------------
    | Soft maximum hours for request completion. Requests exceeding this are
    | tagged as "delayed" and campus managers are notified (in-app, no SMS).
    */
    'sla_hours' => (int) env('REQUEST_SLA_HOURS', 72),
];
