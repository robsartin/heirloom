<?php
declare(strict_types=1);

namespace Heirloom\Tests;

use Heirloom\Auth;
use Heirloom\Database;
use Heirloom\LogMailer;
use Heirloom\SiteSettings;
use PDO;
use PHPUnit\Framework\TestCase;

class AwardNotificationTest extends TestCase
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

    public function testBuildAwardEmailReturnsEmailMessageWithCorrectRecipient(): void
    {
        $msg = $this->auth->buildAwardEmail('alice@example.com', 'Sunset over the Lake');
        $this->assertSame('alice@example.com', $msg->to);
    }

    public function testBuildAwardEmailSubjectContainsSiteName(): void
    {
        $this->settings->set('site_name', 'Art Giveaway');
        $msg = $this->auth->buildAwardEmail('bob@example.com', 'Mountain Vista');
        $this->assertStringContainsString('Art Giveaway', $msg->subject);
    }

    public function testBuildAwardEmailSubjectUsesDefaultSiteNameWhenNotConfigured(): void
    {
        $msg = $this->auth->buildAwardEmail('carol@example.com', 'Mountain Vista');
        $this->assertStringContainsString('Heirloom Gallery', $msg->subject);
    }

    public function testBuildAwardEmailBodyContainsPaintingTitle(): void
    {
        $msg = $this->auth->buildAwardEmail('dan@example.com', 'Sunset over the Lake');
        $this->assertStringContainsString('Sunset over the Lake', $msg->htmlBody);
    }

    public function testBuildAwardEmailTextBodyContainsPaintingTitle(): void
    {
        $msg = $this->auth->buildAwardEmail('eve@example.com', 'Sunset over the Lake');
        $this->assertStringContainsString('Sunset over the Lake', $msg->textBody);
    }

    public function testSendAwardNotificationSendsEmailViaMailer(): void
    {
        $result = $this->auth->sendAwardNotification('frank@example.com', 'Autumn Leaves');
        $this->assertTrue($result);

        $msg = $this->mailer->getLastMessage();
        $this->assertNotNull($msg);
        $this->assertSame('frank@example.com', $msg->to);
        $this->assertStringContainsString('Autumn Leaves', $msg->htmlBody);
    }

    public function testSendAwardNotificationUsesDefaultMailerWhenNoneSet(): void
    {
        $auth = new Auth($this->db);
        $auth->setSettings($this->settings);
        // No mailer set — should fall back to LogMailer without error
        $result = $auth->sendAwardNotification('grace@example.com', 'Spring Blossoms');
        $this->assertTrue($result);
    }
}
