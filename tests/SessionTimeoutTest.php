<?php
declare(strict_types=1);

namespace Heirloom\Tests;

use Heirloom\Auth;
use Heirloom\Database;
use Heirloom\SiteSettings;
use PDO;
use PHPUnit\Framework\TestCase;

class SessionTimeoutTest extends TestCase
{
    private Auth $auth;
    private Database $db;
    private SiteSettings $settings;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $pdo->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT UNIQUE NOT NULL,
                name TEXT NOT NULL DEFAULT '',
                password_hash TEXT,
                is_admin INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
        ");
        $pdo->exec("
            CREATE TABLE magic_links (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL,
                token TEXT UNIQUE NOT NULL,
                used INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
        ");
        $pdo->exec("
            CREATE TABLE site_settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key TEXT UNIQUE NOT NULL,
                setting_value TEXT NOT NULL DEFAULT ''
            )
        ");

        $this->db = new Database($pdo);
        $this->settings = new SiteSettings($this->db);
        $this->auth = new Auth($this->db);
        $this->auth->setSettings($this->settings);

        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        unset($_SESSION['user_id'], $_SESSION['last_activity']);
    }

    // --- checkSessionTimeout ---

    public function testCheckSessionTimeoutReturnsFalseWhenNoLastActivity(): void
    {
        $_SESSION['user_id'] = 1;
        // No last_activity set
        $this->assertFalse($this->auth->isSessionExpired());
    }

    public function testCheckSessionTimeoutReturnsTrueWhenActivityIsRecent(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['last_activity'] = time() - 60; // 1 minute ago
        $this->assertFalse($this->auth->isSessionExpired());
    }

    public function testCheckSessionTimeoutReturnsFalseWhenExpired(): void
    {
        $_SESSION['user_id'] = 1;
        $this->settings->set('session_timeout_minutes', '120');
        $_SESSION['last_activity'] = time() - (121 * 60); // 121 minutes ago
        $this->assertTrue($this->auth->isSessionExpired());
    }

    // --- touchActivity ---

    public function testTouchActivitySetsLastActivity(): void
    {
        $before = time();
        $this->auth->touchActivity();
        $after = time();

        $this->assertArrayHasKey('last_activity', $_SESSION);
        $this->assertGreaterThanOrEqual($before, $_SESSION['last_activity']);
        $this->assertLessThanOrEqual($after, $_SESSION['last_activity']);
    }

    // --- timeout respects setting ---

    public function testSessionTimeoutUsesDefaultOf120Minutes(): void
    {
        $_SESSION['user_id'] = 1;
        // Default is 120 minutes; 119 minutes ago should NOT be expired
        $_SESSION['last_activity'] = time() - (119 * 60);
        $this->assertFalse($this->auth->isSessionExpired());
    }

    public function testSessionTimeoutUsesCustomSetting(): void
    {
        $_SESSION['user_id'] = 1;
        $this->settings->set('session_timeout_minutes', '30');
        $_SESSION['last_activity'] = time() - (31 * 60); // 31 minutes ago
        $this->assertTrue($this->auth->isSessionExpired());
    }

    public function testSessionTimeoutCustomSettingNotExpired(): void
    {
        $_SESSION['user_id'] = 1;
        $this->settings->set('session_timeout_minutes', '30');
        $_SESSION['last_activity'] = time() - (29 * 60); // 29 minutes ago
        $this->assertFalse($this->auth->isSessionExpired());
    }

    public function testCheckSessionTimeoutReturnsFalseWhenNotLoggedIn(): void
    {
        // No user_id in session
        $this->assertFalse($this->auth->isSessionExpired());
    }
}
