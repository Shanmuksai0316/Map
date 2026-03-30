<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckFcmConfig extends Command
{
    protected $signature = 'push:check-config';
    protected $description = 'Check FCM (Firebase Cloud Messaging) configuration and database setup';

    public function handle()
    {
        $this->info('Checking FCM Configuration...');
        $this->newLine();

        $checks = [
            'fcm_enabled' => $this->checkFcmEnabled(),
            'service_account' => $this->checkServerKey(),
            'service_account_format' => $this->checkServerKeyFormat(),
            'database_table' => $this->checkDatabaseTable(),
        ];

        $this->newLine();
        $this->displayResults($checks);

        $allPassed = !in_array(false, $checks, true);
        
        if ($allPassed) {
            $this->info('✓ All FCM configuration checks passed!');
            return 0;
        } else {
            $this->error('✗ Some FCM configuration checks failed. Please fix the issues above.');
            return 1;
        }
    }

    private function checkFcmEnabled(): bool
    {
        $enabled = config('services.fcm.enabled', false);
        
        if ($enabled) {
            $this->line('✓ FCM is enabled');
            return true;
        } else {
            $this->warn('✗ FCM is disabled (FCM_ENABLED=false or not set)');
            return false;
        }
    }

    private function checkServerKey(): bool
    {
        $projectId = config('services.fcm.project_id');
        $serviceAccountJson = config('services.fcm.service_account_json');
        $serviceAccountPath = config('services.fcm.service_account_path');
        $serverKey = config('services.fcm.server_key');
        
        if (empty($projectId)) {
            $this->warn('✗ FCM Project ID is not configured (FCM_PROJECT_ID is empty)');
            $this->line('  → Set FCM_PROJECT_ID in your .env file');
            return false;
        }
        
        $this->line('✓ FCM Project ID is configured: ' . $projectId);
        
        // Check for V1 API (service account) or Legacy API (server key)
        if ($serviceAccountJson || $serviceAccountPath) {
            if ($serviceAccountJson) {
                $this->line('✓ FCM Service Account JSON is configured (V1 API)');
            } else {
                $this->line('✓ FCM Service Account path is configured (V1 API): ' . $serviceAccountPath);
            }
            return true;
        } elseif ($serverKey) {
            $this->line('✓ FCM Server Key is configured (Legacy API)');
            $this->line('  → Using Legacy API as fallback (V1 API preferred)');
            return true;
        } else {
            $this->warn('✗ FCM authentication not configured');
            $this->line('  → Set FCM_SERVICE_ACCOUNT_JSON (V1 API) OR FCM_SERVER_KEY (Legacy API)');
            return false;
        }
    }

    private function checkServerKeyFormat(): bool
    {
        $serviceAccountJson = config('services.fcm.service_account_json');
        $serviceAccountPath = config('services.fcm.service_account_path');
        $serverKey = config('services.fcm.server_key');
        
        // Check V1 API (service account)
        if ($serviceAccountJson || $serviceAccountPath) {
            $serviceAccount = null;
            if ($serviceAccountJson) {
                $serviceAccount = json_decode($serviceAccountJson, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->warn('✗ FCM Service Account JSON is invalid');
                    $this->line('  → Check JSON syntax in FCM_SERVICE_ACCOUNT_JSON');
                    return false;
                }
            } elseif ($serviceAccountPath) {
                if (!file_exists($serviceAccountPath)) {
                    $this->warn('✗ FCM Service Account file not found: ' . $serviceAccountPath);
                    return false;
                }
                $content = file_get_contents($serviceAccountPath);
                $serviceAccount = json_decode($content, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->warn('✗ FCM Service Account file is invalid JSON');
                    return false;
                }
            }
            
            if ($serviceAccount) {
                $required = ['type', 'project_id', 'private_key_id', 'private_key', 'client_email'];
                $missing = array_diff($required, array_keys($serviceAccount));
                if (empty($missing)) {
                    $this->line('✓ Service Account format is valid (V1 API)');
                    $this->line('  → Client Email: ' . ($serviceAccount['client_email'] ?? 'N/A'));
                    return true;
                } else {
                    $this->warn('✗ Service Account missing required fields: ' . implode(', ', $missing));
                    return false;
                }
            }
        }
        
        // Check Legacy API (server key)
        if ($serverKey) {
            if (str_starts_with($serverKey, 'AAAA-')) {
                $this->line('✓ Server Key format is valid (Legacy API)');
                return true;
            } else {
                $this->warn('⚠ Server Key format may be invalid (expected to start with AAAA-)');
                $this->line('  → Verify the key was copied correctly from Firebase Console');
                return true; // Don't fail, just warn
            }
        }
        
        return false;
    }

    private function checkDatabaseTable(): bool
    {
        try {
            $tableExists = Schema::hasTable('push_device_tokens');
            
            if ($tableExists) {
                $this->line('✓ Database table "push_device_tokens" exists');
                
                // Check if table has required columns
                $columns = Schema::getColumnListing('push_device_tokens');
                $required = ['id', 'user_id', 'token', 'platform', 'created_at'];
                $missing = array_diff($required, $columns);
                
                if (empty($missing)) {
                    $this->line('✓ Required columns are present');
                    return true;
                } else {
                    $this->warn('✗ Missing required columns: ' . implode(', ', $missing));
                    $this->line('  → Run: php artisan migrate');
                    return false;
                }
            } else {
                $this->warn('✗ Database table "push_device_tokens" does not exist');
                $this->line('  → Run: php artisan migrate');
                return false;
            }
        } catch (\Exception $e) {
            $this->error('✗ Error checking database: ' . $e->getMessage());
            return false;
        }
    }

    private function displayResults(array $checks): void
    {
        $this->table(
            ['Check', 'Status'],
            [
                ['FCM Enabled', $checks['fcm_enabled'] ? '✓ Pass' : '✗ Fail'],
                ['Service Account Configured', $checks['service_account'] ? '✓ Pass' : '✗ Fail'],
                ['Service Account Format', $checks['service_account_format'] ? '✓ Pass' : '✗ Fail'],
                ['Database Table', $checks['database_table'] ? '✓ Pass' : '✗ Fail'],
            ]
        );
    }
}

