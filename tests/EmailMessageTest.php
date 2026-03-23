<?php
declare(strict_types=1);

namespace Heirloom\Tests;

use Heirloom\EmailMessage;
use PHPUnit\Framework\TestCase;

class EmailMessageTest extends TestCase
{
    public function testConstructorSetsAllFields(): void
    {
        $msg = new EmailMessage(
            to: 'alice@example.com',
            subject: 'Hello',
            htmlBody: '<p>Hi</p>',
            textBody: 'Hi'
        );

        $this->assertSame('alice@example.com', $msg->to);
        $this->assertSame('Hello', $msg->subject);
        $this->assertSame('<p>Hi</p>', $msg->htmlBody);
        $this->assertSame('Hi', $msg->textBody);
    }

    public function testTextBodyDefaultsToStrippedHtml(): void
    {
        $msg = new EmailMessage(
            to: 'bob@example.com',
            subject: 'Test',
            htmlBody: '<h1>Title</h1><p>Body text</p>'
        );

        $this->assertSame('TitleBody text', $msg->textBody);
    }
}
