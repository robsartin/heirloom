<?php
declare(strict_types=1);

namespace Heirloom\Tests;

use Heirloom\Database;
use Heirloom\SiteSettings;
use PDO;
use PHPUnit\Framework\TestCase;

class SiteSettingsTest extends TestCase
{
    private Database $db;
    private SiteSettings $settings;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("CREATE TABLE site_settings (
            setting_key TEXT PRIMARY KEY,
            setting_value TEXT NOT NULL,
            label TEXT NOT NULL DEFAULT '',
            description TEXT NOT NULL DEFAULT ''
        )");

        $this->db = new Database($pdo);
        $this->settings = new SiteSettings($this->db);
    }

    public function testGetReturnsDefaultWhenKeyMissing(): void
    {
        $this->assertSame('60', $this->settings->get('magic_link_expiry_minutes', '60'));
    }

    public function testGetReturnsStoredValue(): void
    {
        $this->db->execute(
            "INSERT INTO site_settings (setting_key, setting_value) VALUES (:k, :v)",
            [':k' => 'magic_link_expiry_minutes', ':v' => '30']
        );

        $this->assertSame('30', $this->settings->get('magic_link_expiry_minutes', '60'));
    }

    public function testSetInsertsNewValue(): void
    {
        $this->settings->set('site_name', 'My Gallery');
        $this->assertSame('My Gallery', $this->settings->get('site_name'));
    }

    public function testSetUpdatesExistingValue(): void
    {
        $this->settings->set('site_name', 'Old Name');
        $this->settings->set('site_name', 'New Name');
        $this->assertSame('New Name', $this->settings->get('site_name'));
    }

    public function testGetIntReturnsIntegerValue(): void
    {
        $this->settings->set('magic_link_expiry_minutes', '45');
        $this->assertSame(45, $this->settings->getInt('magic_link_expiry_minutes', 60));
    }

    public function testGetIntReturnsDefaultWhenMissing(): void
    {
        $this->assertSame(60, $this->settings->getInt('nonexistent', 60));
    }

    public function testGetAllReturnsAllSettings(): void
    {
        $this->settings->set('a', '1');
        $this->settings->set('b', '2');

        $all = $this->settings->getAll();
        $this->assertCount(2, $all);
    }

    public function testSetBulkUpdatesMultipleSettings(): void
    {
        $this->settings->set('a', '1');
        $this->settings->set('b', '2');

        $this->settings->setBulk(['a' => '10', 'b' => '20']);

        $this->assertSame('10', $this->settings->get('a'));
        $this->assertSame('20', $this->settings->get('b'));
    }

    public function testGetBoolReturnsTrueForTruthyValues(): void
    {
        $this->settings->set('registration_open', '1');
        $this->assertTrue($this->settings->getBool('registration_open', false));
    }

    public function testGetBoolReturnsFalseForFalsyValues(): void
    {
        $this->settings->set('registration_open', '0');
        $this->assertFalse($this->settings->getBool('registration_open', true));
    }

    public function testGetBoolReturnsDefaultWhenMissing(): void
    {
        $this->assertTrue($this->settings->getBool('nonexistent', true));
    }
}
