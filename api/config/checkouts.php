<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default checkout period
    |--------------------------------------------------------------------------
    |
    | Expected checkout date is set to allocation start + this many months.
    | Renew action adds the same period. Default 12 = 1 year.
    | Per-tenant override: set tenant->settings['renewal_period_months'].
    |
    */
    'default_period_months' => (int) env('CHECKOUT_DEFAULT_PERIOD_MONTHS', 12),

];
