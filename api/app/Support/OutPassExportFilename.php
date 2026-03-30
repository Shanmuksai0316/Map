<?php

namespace App\Support;

use Illuminate\Support\Str;

class OutPassExportFilename
{
    public static function forTenant(string $tenantCode): string
    {
        return sprintf('%s/outpasses_%s_%s.csv',
            trim($tenantCode, '/'),
            now()->format('Ymd_His'),
            Str::random(6)
        );
    }
}
