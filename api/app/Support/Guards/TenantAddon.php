<?php

namespace App\Support\Guards;

class TenantAddon
{
    public static function enabled(string $addon): bool
    {
        if (! function_exists('tenant') || ! tenant()) {
            return false;
        }

        $field = "addon_{$addon}";
        return (bool) (tenant()->{$field} ?? false);
    }
}


