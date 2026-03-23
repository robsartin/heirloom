<?php
declare(strict_types=1);

namespace Heirloom\Tests;

use Heirloom\Auth;
use Heirloom\Database;
use Heirloom\LogMailer;
use Heirloom\SiteSettings;
use PDO;
use PHPUnit\Framework\TestCase;

class MagicLinkEmailTest extends TestCase
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

    public function testSendMagicLinkGeneratesEmailWithCorrectRecipient(): void
    {
        $token = $this->auth->createMagicLink('alice@example.com');
        $this->auth->sendMagicLink('alice@example.com', $token);

        $msg = $this->mailer->getLastMessage();
        $this->assertNotNull($msg);
        $this->assertSame('alice@example.com', $msg->to);
    }

    public function testSendMagicLinkSubjectContainsSiteName(): void
    {
        $this->settings->set('site_name', 'Art Giveaway');
        $token = $this->auth->createMagicLink('bob@example.com');
        $this->auth->sendMagicLink('bob@example.com', $token);

        $msg = $this->mailer->getLastMessage();
        $this->assertStringContainsString('Art Giveaway', $msg->subject);
    }

    public function testSendMagicLinkSubjectUsesDefaultSiteNameWhenNotConfigured(): void
    {
        $token = $this->auth->createMagicLink('carol@example.com');
        $this->auth->sendMagicLink('carol@example.com', $token);

        $msg = $this->mailer->getLastMessage();
        $this->assertStringContainsString('Heirloom Gallery', $msg->subject);
    }

    public function testSendMagicLinkBodyContainsLoginUrl(): void
    {
        $token = $this->auth->createMagicLink('dan@example.com');
        $this->auth->sendMagicLink('dan@example.com', $token);

        $msg = $this->mailer->getLastMessage();
        $this->assertStringContainsString('/auth/magic/' . $token, $msg->htmlBody);
    }

    public function testSendMagicLinkBodyContainsExpiryTime(): void
    {
        $this->settings->set('magic_link_expiry_minutes', '30');
        $token = $this->auth->createMagicLink('eve@example.com');
        $this->auth->sendMagicLink('eve@example.com', $token);

        $msg = $this->mailer->getLastMessage();
        $this->assertStringContainsString('30 minutes', $msg->htmlBody);
    }

    public function testSendMagicLinkBodyContainsDefaultExpiryWhenNotConfigured(): void
    {
        $token = $this->auth->createMagicLink('frank@example.com');
        $this->auth->sendMagicLink('frank@example.com', $token);

        $msg = $this->mailer->getLastMessage();
        $this->assertStringContainsString('60 minutes', $msg->htmlBody);
    }

    public function testSendMagicLinkTextBodyContainsUrl(): void
    {
        $token = $this->auth->createMagicLink('grace@example.com');
        $this->auth->sendMagicLink('grace@example.com', $token);

        $msg = $this->mailer->getLastMessage();
        $this->assertStringContainsString('/auth/magic/' . $token, $msg->textBody);
    }

    public function testSendMagicLinkReturnsTrueOnSuccess(): void
    {
        $token = $this->auth->createMagicLink('hank@example.com');
        $result = $this->auth->sendMagicLink('hank@example.com', $token);
        $this->assertTrue($result);
    }
}
