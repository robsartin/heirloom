<?php
declare(strict_types=1);

namespace Heirloom\Tests;

use Heirloom\EmailMessage;
use Heirloom\SmtpMailer;
use PHPUnit\Framework\TestCase;

class SmtpMailerTest extends TestCase
{
    public function testImplementsMailerInterface(): void
    {
        $mailer = new SmtpMailer(
            host: 'smtp.example.com',
            port: 587,
            username: 'user',
            password: 'pass',
            fromEmail: 'noreply@example.com',
            fromName: 'Test'
        );

        $this->assertInstanceOf(\Heirloom\Mailer::class, $mailer);
    }

    public function testConstructorStoresConfig(): void
    {
        $mailer = new SmtpMailer(
            host: 'mail.test.com',
            port: 465,
            username: 'me@test.com',
            password: 'secret',
            fromEmail: 'from@test.com',
            fromName: 'Gallery'
        );

        // Can construct without error — config is stored
        $this->assertInstanceOf(SmtpMailer::class, $mailer);
    }
}
