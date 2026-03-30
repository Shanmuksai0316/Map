<?php

namespace App\Services\Reports;

final class Reports
{
    /** @var array<string, callable> */
    private static array $map = [];

    public static function register(string $name, callable $fn): void
    {
        self::$map[$name] = $fn;
    }

    public static function run(string $name, array $params): \Generator
    {
        if (!isset(self::$map[$name])) {
            throw new \InvalidArgumentException("Report '{$name}' not found");
        }
        
        return (self::$map[$name])($params);
    }

    public static function getAvailableReports(): array
    {
        return array_keys(self::$map);
    }
}
