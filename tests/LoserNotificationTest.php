<?php
declare(strict_types=1);

namespace Heirloom\Tests;

use Heirloom\Auth;
use Heirloom\Database;
use Heirloom\LogMailer;
use Heirloom\SiteSettings;
use PDO;
use PHPUnit\Framework\TestCase;

class LoserNotificationTest extends TestCase
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

    public function testBuildLoserEmailReturnsEmailMessageWithCorrectRecipient(): void
    {
        $msg = $this->auth->buildLoserEmail('alice@example.com', 'Sunset over the Lake');
        $this->assertSame('alice@example.com', $msg->to);
    }

    public function testBuildLoserEmailSubjectContainsSiteName(): void
    {
        $this->settings->set('site_name', 'Art Giveaway');
        $msg = $this->auth->buildLoserEmail('bob@example.com', 'Mountain Vista');
        $this->assertStringContainsString('Art Giveaway', $msg->subject);
    }

    public function testBuildLoserEmailBodyContainsPaintingTitle(): void
    {
        $msg = $this->auth->buildLoserEmail('carol@example.com', 'Sunset over the Lake');
        $this->assertStringContainsString('Sunset over the Lake', $msg->htmlBody);
    }

    public function testBuildLoserEmailTextBodyContainsPaintingTitle(): void
    {
        $msg = $this->auth->buildLoserEmail('dan@example.com', 'Sunset over the Lake');
        $this->assertStringContainsString('Sunset over the Lake', $msg->textBody);
    }

    public function testSendLoserNotificationsEmailsEachLoser(): void
    {
        $emails = ['alice@example.com', 'bob@example.com', 'carol@example.com'];
        $this->auth->sendLoserNotifications($emails, 'Autumn Leaves');

        $messages = $this->mailer->getAllMessages();
        $this->assertCount(3, $messages);

        $recipients = array_map(fn($m) => $m->to, $messages);
        $this->assertSame($emails, $recipients);

        foreach ($messages as $msg) {
            $this->assertStringContainsString('Autumn Leaves', $msg->htmlBody);
        }
    }

    public function testSendLoserNotificationsHandlesEmptyArray(): void
    {
        $this->auth->sendLoserNotifications([], 'Empty Test');

        $messages = $this->mailer->getAllMessages();
        $this->assertCount(0, $messages);
    }
}
