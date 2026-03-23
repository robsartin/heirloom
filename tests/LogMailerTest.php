<?php
declare(strict_types=1);

namespace Heirloom\Tests;

use Heirloom\EmailMessage;
use Heirloom\LogMailer;
use PHPUnit\Framework\TestCase;

class LogMailerTest extends TestCase
{
    public function testSendReturnsTrue(): void
    {
        $mailer = new LogMailer();
        $msg = new EmailMessage(
            to: 'test@example.com',
            subject: 'Test Subject',
            htmlBody: '<p>Body</p>'
        );

        $this->assertTrue($mailer->send($msg));
    }

    public function testSendStoresLastMessage(): void
    {
        $mailer = new LogMailer();
        $msg = new EmailMessage(
            to: 'stored@example.com',
            subject: 'Stored',
            htmlBody: '<p>Keep this</p>'
        );

        $mailer->send($msg);
        $last = $mailer->getLastMessage();

        $this->assertNotNull($last);
        $this->assertSame('stored@example.com', $last->to);
        $this->assertSame('Stored', $last->subject);
    }

    public function testGetLastMessageReturnsNullBeforeSend(): void
    {
        $mailer = new LogMailer();
        $this->assertNull($mailer->getLastMessage());
    }

    public function testGetAllMessagesReturnsAllSent(): void
    {
        $mailer = new LogMailer();
        $mailer->send(new EmailMessage('a@example.com', 'A', '<p>A</p>'));
        $mailer->send(new EmailMessage('b@example.com', 'B', '<p>B</p>'));

        $all = $mailer->getAllMessages();
        $this->assertCount(2, $all);
        $this->assertSame('a@example.com', $all[0]->to);
        $this->assertSame('b@example.com', $all[1]->to);
    }
}
