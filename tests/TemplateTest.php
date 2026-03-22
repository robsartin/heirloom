<?php
declare(strict_types=1);

namespace Heirloom\Tests;

use Heirloom\Template;
use PHPUnit\Framework\TestCase;

class TemplateTest extends TestCase
{
    public function testEscapeConvertsHtmlSpecialChars(): void
    {
        $this->assertSame(
            '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;',
            Template::escape('<script>alert("xss")</script>')
        );
    }

    public function testEscapeConvertsSingleQuotes(): void
    {
        $this->assertSame(
            'it&apos;s fine',
            Template::escape("it's fine")
        );
    }

    public function testEscapeConvertsAmpersand(): void
    {
        $this->assertSame('a &amp; b', Template::escape('a & b'));
    }

    public function testEscapeLeavesPlainTextUnchanged(): void
    {
        $this->assertSame('hello world', Template::escape('hello world'));
    }

    public function testEscapeHandlesEmptyString(): void
    {
        $this->assertSame('', Template::escape(''));
    }

    public function testEscapeHandlesUtf8(): void
    {
        $this->assertSame('cafe\u{0301}', Template::escape('cafe\u{0301}'));
    }
}
