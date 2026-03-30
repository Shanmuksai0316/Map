<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Report what is configured on the server (SMS, FCM, etc.) without printing secrets.
 * Run on server: php artisan config:check-server
 */
class CheckServerConfigCommand extends Command
{
    protected $signature = 'config:check-server';
    protected $description = 'Check server configuration (SMS, FCM) – reports status only, no secrets printed. Run on server to see what is configured.';

    public function handle(): int
    {
        $this->info('=== Server configuration status ===');
        $this->newLine();

        $this->sectionSms();
        $this->newLine();
        $this->sectionFcm();
        $this->newLine();
        $this->sectionApp();

        return 0;
    }

    private function sectionSms(): void
    {
        $this->line('<fg=cyan>SMS (OTP / notifications)</fg=cyan>');

        $msg91Enabled = (bool) config('services.msg91.enabled', false);
        $msg91Key = config('services.msg91.key');
        $msg91HasKey = !empty($msg91Key);
        $msg91OtpTemplate = config('services.msg91.templates.otp_login');

        $this->line(sprintf(
            '  MSG91:  enabled=%s  has_key=%s  otp_login_template=%s',
            $msg91Enabled ? 'yes' : 'no',
            $msg91HasKey ? 'yes' : 'no',
            $msg91OtpTemplate ?: '(not set)'
        ));

        $stplEnabled = (bool) config('services.stpl.enabled', false);
        $stplKey = config('services.stpl.api_key');
        $stplHasKey = !empty($stplKey);
        $stplOtpTemplate = config('services.stpl.templates.otp_login');

        $this->line(sprintf(
            '  STPL:   enabled=%s  has_key=%s  otp_login_template=%s',
            $stplEnabled ? 'yes' : 'no',
            $stplHasKey ? 'yes' : 'no',
            $stplOtpTemplate ? 'set' : '(not set)'
        ));

        $smsUsable = ($msg91Enabled && $msg91HasKey) || ($stplEnabled && $stplHasKey);
        if ($smsUsable) {
            $this->line('  → SMS can be sent (at least one provider configured).');
        } else {
            $this->warn('  → No SMS provider usable. Set MSG91_ENABLED=true and MSG91_API_KEY, or STPL_ENABLED and STPL_API_KEY.');
        }
    }

    private function sectionFcm(): void
    {
        $this->line('<fg=cyan>FCM (Push notifications)</fg=cyan>');

        $fcmEnabled = (bool) config('services.fcm.enabled', false);
        $fcmProjectId = config('services.fcm.project_id');
        $hasServiceAccount = !empty(config('services.fcm.service_account_json')) || !empty(config('services.fcm.service_account_path'));
        $hasServerKey = !empty(config('services.fcm.server_key'));

        $this->line(sprintf(
            '  FCM:    enabled=%s  project_id=%s  auth=%s',
            $fcmEnabled ? 'yes' : 'no',
            $fcmProjectId ?: '(not set)',
            $hasServiceAccount ? 'service_account' : ($hasServerKey ? 'server_key' : 'none')
        ));

        if ($fcmEnabled && ($hasServiceAccount || $hasServerKey)) {
            $this->line('  → Push notifications can be sent.');
        } elseif (!$fcmEnabled) {
            $this->warn('  → FCM disabled. Set FCM_ENABLED=true and add service account or server key.');
        } else {
            $this->warn('  → FCM enabled but no credentials. Set FCM_SERVICE_ACCOUNT_PATH or FCM_SERVER_KEY.');
        }
    }

    private function sectionApp(): void
    {
        $this->line('<fg=cyan>App</fg=cyan>');
        $this->line('  env=' . (app()->environment()));
        $this->line('  debug=' . (config('app.debug') ? 'true' : 'false'));
    }
}
