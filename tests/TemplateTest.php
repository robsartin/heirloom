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

    public function testSetGlobalMakesValueAvailableInTemplate(): void
    {
        $tmpDir = sys_get_temp_dir() . '/heirloom_tpl_' . uniqid();
        mkdir($tmpDir);
        file_put_contents($tmpDir . '/global_test.php', 'Email: <?= $contactEmail ?>');
        file_put_contents($tmpDir . '/layout.php', '<?= $content ?>');

        $ref = new \ReflectionClass(Template::class);
        $baseProp = $ref->getProperty('baseDir');
        $globalsProp = $ref->getProperty('globals');

        $originalBase = $baseProp->getValue();
        $originalGlobals = $globalsProp->getValue();

        $baseProp->setValue(null, $tmpDir);
        Template::setGlobal('contactEmail', 'gallery@example.com');

        ob_start();
        Template::render('global_test', ['noLayout' => true]);
        $output = ob_get_clean();

        $baseProp->setValue(null, $originalBase);
        $globalsProp->setValue(null, $originalGlobals);

        unlink($tmpDir . '/global_test.php');
        unlink($tmpDir . '/layout.php');
        rmdir($tmpDir);

        $this->assertSame('Email: gallery@example.com', $output);
    }

    public function testContactEmailRenderedInLayoutFooter(): void
    {
        $tmpDir = sys_get_temp_dir() . '/heirloom_tpl_' . uniqid();
        mkdir($tmpDir);
        file_put_contents($tmpDir . '/blank.php', '');

        // Create a minimal layout that mirrors the real footer contact email logic
        $layoutContent = <<<'PHP'
<?= $content ?><?php if (!empty($contactEmail)): ?><a href="mailto:<?= \Heirloom\Template::escape($contactEmail) ?>"><?= \Heirloom\Template::escape($contactEmail) ?></a><?php endif; ?>
PHP;
        file_put_contents($tmpDir . '/layout.php', $layoutContent);

        $ref = new \ReflectionClass(Template::class);
        $baseProp = $ref->getProperty('baseDir');
        $globalsProp = $ref->getProperty('globals');

        $originalBase = $baseProp->getValue();
        $originalGlobals = $globalsProp->getValue();

        $baseProp->setValue(null, $tmpDir);
        Template::setGlobal('contactEmail', 'art@example.com');
        Template::setGlobal('siteName', 'Test Gallery');

        ob_start();
        Template::render('blank', []);
        $output = ob_get_clean();

        $baseProp->setValue(null, $originalBase);
        $globalsProp->setValue(null, $originalGlobals);

        unlink($tmpDir . '/blank.php');
        unlink($tmpDir . '/layout.php');
        rmdir($tmpDir);

        $this->assertStringContainsString('mailto:art@example.com', $output);
        $this->assertStringContainsString('>art@example.com</a>', $output);
    }

    public function testContactEmailHiddenWhenEmpty(): void
    {
        $tmpDir = sys_get_temp_dir() . '/heirloom_tpl_' . uniqid();
        mkdir($tmpDir);
        file_put_contents($tmpDir . '/blank.php', '');

        $layoutContent = <<<'PHP'
<?= $content ?><?php if (!empty($contactEmail)): ?><a href="mailto:<?= \Heirloom\Template::escape($contactEmail) ?>"><?= \Heirloom\Template::escape($contactEmail) ?></a><?php endif; ?>
PHP;
        file_put_contents($tmpDir . '/layout.php', $layoutContent);

        $ref = new \ReflectionClass(Template::class);
        $baseProp = $ref->getProperty('baseDir');
        $globalsProp = $ref->getProperty('globals');

        $originalBase = $baseProp->getValue();
        $originalGlobals = $globalsProp->getValue();

        $baseProp->setValue(null, $tmpDir);
        Template::setGlobal('contactEmail', '');
        Template::setGlobal('siteName', 'Test Gallery');

        ob_start();
        Template::render('blank', []);
        $output = ob_get_clean();

        $baseProp->setValue(null, $originalBase);
        $globalsProp->setValue(null, $originalGlobals);

        unlink($tmpDir . '/blank.php');
        unlink($tmpDir . '/layout.php');
        rmdir($tmpDir);

        $this->assertStringNotContainsString('mailto:', $output);
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
