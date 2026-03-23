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

    public function testEscapeHandlesNestedHtmlEntities(): void
    {
        $this->assertSame('&amp;amp;', Template::escape('&amp;'));
    }

    public function testEscapeDoesNotDoubleEscape(): void
    {
        $once = Template::escape('<b>');
        $twice = Template::escape($once);
        $this->assertSame('&amp;lt;b&amp;gt;', $twice);
    }

    public function testRenderOutputsTemplateContent(): void
    {
        $tmpDir = sys_get_temp_dir() . '/heirloom_tpl_' . uniqid();
        mkdir($tmpDir);
        file_put_contents($tmpDir . '/test.php', 'Hello <?= $name ?>!');
        file_put_contents($tmpDir . '/layout.php', '<?= $content ?>');

        // Use reflection to temporarily override baseDir
        $ref = new \ReflectionClass(Template::class);
        $prop = $ref->getProperty('baseDir');

        $original = $prop->getValue();
        $prop->setValue(null, $tmpDir);

        ob_start();
        Template::render('test', ['name' => 'World', 'noLayout' => true]);
        $output = ob_get_clean();

        $prop->setValue(null, $original);

        unlink($tmpDir . '/test.php');
        unlink($tmpDir . '/layout.php');
        rmdir($tmpDir);

        $this->assertSame('Hello World!', $output);
    }

    public function testRenderWithLayoutWrapsContent(): void
    {
        $tmpDir = sys_get_temp_dir() . '/heirloom_tpl_' . uniqid();
        mkdir($tmpDir);
        file_put_contents($tmpDir . '/inner.php', 'INNER');
        file_put_contents($tmpDir . '/layout.php', '<div><?= $content ?></div>');

        $ref = new \ReflectionClass(Template::class);
        $prop = $ref->getProperty('baseDir');

        $original = $prop->getValue();
        $prop->setValue(null, $tmpDir);

        ob_start();
        Template::render('inner', []);
        $output = ob_get_clean();

        $prop->setValue(null, $original);

        unlink($tmpDir . '/inner.php');
        unlink($tmpDir . '/layout.php');
        rmdir($tmpDir);

        $this->assertSame('<div>INNER</div>', $output);
    }
}
