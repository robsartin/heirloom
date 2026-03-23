<?php
declare(strict_types=1);

namespace Heirloom;

class SiteSettings
{
    public function __construct(private Database $db) {}

    public function get(string $key, string $default = ''): string
    {
        $row = $this->db->fetchOne(
            'SELECT setting_value FROM site_settings WHERE setting_key = :k',
            [':k' => $key]
        );
        return $row ? $row['setting_value'] : $default;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $val = $this->get($key, (string) $default);
        return (int) $val;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $row = $this->db->fetchOne(
            'SELECT setting_value FROM site_settings WHERE setting_key = :k',
            [':k' => $key]
        );
        if (!$row) {
            return $default;
        }
        return in_array($row['setting_value'], ['1', 'true', 'yes'], true);
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
