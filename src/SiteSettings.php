<?php
declare(strict_types=1);

namespace Heirloom;

/**
 * Read/write access to the site_settings database table, providing typed getters
 * (string, int, bool) with configurable defaults.
 */
class SiteSettings
{
    public const DEFAULT_SITE_NAME = 'Heirloom Gallery';

    public function __construct(private Database $db) {}

    private function fetchValue(string $key): ?string
    {
        $row = $this->db->fetchOne(
            'SELECT setting_value FROM site_settings WHERE setting_key = :k',
            [':k' => $key]
        );
        return $row ? $row['setting_value'] : null;
    }

    public function get(string $key, string $default = ''): string
    {
        return $this->fetchValue($key) ?? $default;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $val = $this->fetchValue($key);
        return $val !== null ? (int) $val : $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $val = $this->fetchValue($key);
        if ($val === null) {
            return $default;
        }
        return in_array($val, ['1', 'true', 'yes'], true);
    }

    public function set(string $key, string $value): void
    {
        $existing = $this->db->fetchOne(
            'SELECT 1 FROM site_settings WHERE setting_key = :k',
            [':k' => $key]
        );
        if ($existing) {
            $this->db->execute(
                'UPDATE site_settings SET setting_value = :v WHERE setting_key = :k',
                [':v' => $value, ':k' => $key]
            );
        } else {
            $this->db->execute(
                'INSERT INTO site_settings (setting_key, setting_value) VALUES (:k, :v)',
                [':k' => $key, ':v' => $value]
            );
        }
    }

    /**
     * @param array<string, string> $values Map of setting_key => setting_value to upsert
     */
    public function setBulk(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function getAll(): array
    {
        return $this->db->fetchAll('SELECT * FROM site_settings ORDER BY setting_key');
    }
}
