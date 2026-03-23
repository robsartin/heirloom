<?php
declare(strict_types=1);

namespace Heirloom;

/**
 * Loads configuration from a .env file and provides static access to values,
 * falling back to $_ENV for environment-level overrides.
 */
class Config
{
    private static array $values = [];

    public static function load(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                self::$values[$key] = $value;
                if (!isset($_ENV[$key])) {
                    $_ENV[$key] = $value;
                }
            }
        }
    }

    public static function get(string $key, string $default = ''): string
    {
        return $_ENV[$key] ?? self::$values[$key] ?? $default;
    }
}
