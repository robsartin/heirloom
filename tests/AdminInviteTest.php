<?php
declare(strict_types=1);

namespace Heirloom\Tests;

use Heirloom\Auth;
use Heirloom\Database;
use Heirloom\LogMailer;
use Heirloom\SiteSettings;
use PDO;
use PHPUnit\Framework\TestCase;

class AdminInviteTest extends TestCase
{
    private Auth $auth;
    private Database $db;
    private LogMailer $mailer;
    private SiteSettings $settings;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $pdo->exec("CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT UNIQUE NOT NULL,
            name TEXT NOT NULL DEFAULT '',
            password_hash TEXT,
            is_admin INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");
        $pdo->exec("CREATE TABLE magic_links (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL,
            token TEXT UNIQUE NOT NULL,
            used INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");
        $pdo->exec("CREATE TABLE site_settings (
            setting_key TEXT PRIMARY KEY,
            setting_value TEXT NOT NULL DEFAULT '',
            label TEXT NOT NULL DEFAULT '',
            description TEXT NOT NULL DEFAULT ''
        )");

        $this->db = new Database($pdo);
        $this->settings = new SiteSettings($this->db);
        $this->mailer = new LogMailer();
        $this->auth = new Auth($this->db);
        $this->auth->setSettings($this->settings);
        $this->auth->setMailer($this->mailer);
    }

    public function testBuildInviteEmailContainsRecipientAddress(): void
    {
        $email = $this->auth->buildInviteEmail('newuser@example.com', 'abc123token');
        $this->assertSame('newuser@example.com', $email->to);
    }

    public function testBuildInviteEmailSubjectContainsSiteName(): void
    {
        $this->settings->set('site_name', 'Art Gallery');
        $email = $this->auth->buildInviteEmail('test@example.com', 'token123');
        $this->assertStringContainsString('Art Gallery', $email->subject);
    }

    public function testBuildInviteEmailSubjectUsesDefaultSiteName(): void
    {
        $email = $this->auth->buildInviteEmail('test@example.com', 'token123');
        $this->assertStringContainsString('Heirloom Gallery', $email->subject);
    }

    public function testBuildInviteEmailBodyContainsMagicLink(): void
    {
        $email = $this->auth->buildInviteEmail('test@example.com', 'abc123token');
        $this->assertStringContainsString('/auth/magic/abc123token', $email->htmlBody);
    }

    public function testBuildInviteEmailBodyContainsExpiry(): void
    {
        $this->settings->set('magic_link_expiry_minutes', '30');
        $email = $this->auth->buildInviteEmail('test@example.com', 'token');
        $this->assertStringContainsString('30 minutes', $email->htmlBody);
    }

    public function testBuildInviteEmailTextBodyContainsLink(): void
    {
        $email = $this->auth->buildInviteEmail('test@example.com', 'mytoken');
        $this->assertStringContainsString('/auth/magic/mytoken', $email->textBody);
    }

    public function testBuildInviteEmailIndicatesInvitation(): void
    {
        $email = $this->auth->buildInviteEmail('test@example.com', 'token');
        $this->assertStringContainsString('invited', strtolower($email->htmlBody));
    }

    public function testCreateInviteReturnsTokenAndCreatesUser(): void
    {
        $token = $this->auth->createInvite('invited@example.com', 'Invited User');

        $this->assertIsString($token);
        $this->assertSame(64, strlen($token));

        $user = $this->auth->findUserByEmail('invited@example.com');
        $this->assertNotNull($user);
        $this->assertSame('Invited User', $user['name']);
    }

    public function testCreateInviteWorksForExistingUser(): void
    {
        $this->auth->findOrCreateUserByEmail('existing@example.com', 'Existing');
        $token = $this->auth->createInvite('existing@example.com', 'New Name');

        $this->assertIsString($token);
        $user = $this->auth->findUserByEmail('existing@example.com');
        $this->assertSame('Existing', $user['name']); // doesn't overwrite
    }
}
