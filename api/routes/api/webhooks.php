<?php

use App\Http\Controllers\Api\V1\SendGridWebhookController;

// Webhooks do NOT require authentication (verified via signature)
Route::prefix('webhooks')->group(function () {
    // SendGrid Email Webhooks
    Route::post('/sendgrid', [SendGridWebhookController::class, 'handle']);
    
    // TODO: MSG91 SMS Webhooks (when needed)
    // Route::post('/msg91', [Msg91WebhookController::class, 'handle']);
});

