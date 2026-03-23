<?php
declare(strict_types=1);

namespace Heirloom\Tests;

use Heirloom\Template;
use PHPUnit\Framework\TestCase;

class OpenGraphTest extends TestCase
{
    private \ReflectionProperty $baseProp;
    private \ReflectionProperty $globalsProp;
    private string $originalBase;
    private array $originalGlobals;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/heirloom_og_' . uniqid();
        mkdir($this->tmpDir);

        $ref = new \ReflectionClass(Template::class);
        $this->baseProp = $ref->getProperty('baseDir');
        $this->globalsProp = $ref->getProperty('globals');

        $this->originalBase = $this->baseProp->getValue();
        $this->originalGlobals = $this->globalsProp->getValue();
    }

    protected function tearDown(): void
    {
        $this->baseProp->setValue(null, $this->originalBase);
        $this->globalsProp->setValue(null, $this->originalGlobals);

        foreach (glob($this->tmpDir . '/*.php') as $file) {
            unlink($file);
        }
        rmdir($this->tmpDir);
    }

    public function testSetGlobalStoresOgValues(): void
    {
        $this->globalsProp->setValue(null, []);

        Template::setGlobal('ogTitle', 'Sunset Over Mountains');
        Template::setGlobal('ogDescription', 'A beautiful landscape painting');
        Template::setGlobal('ogImage', 'https://example.com/uploads/sunset.jpg');

        $globals = Template::getGlobals();

        $this->assertSame('Sunset Over Mountains', $globals['ogTitle']);
        $this->assertSame('A beautiful landscape painting', $globals['ogDescription']);
        $this->assertSame('https://example.com/uploads/sunset.jpg', $globals['ogImage']);
    }

    public function testOgMetaTagsRenderedInLayout(): void
    {
        file_put_contents($this->tmpDir . '/blank.php', '');
        $layout = <<<'PHP'
<head>
<?php if (!empty($ogTitle)): ?>
<meta property="og:title" content="<?= \Heirloom\Template::escape($ogTitle) ?>">
<meta property="og:description" content="<?= \Heirloom\Template::escape($ogDescription ?? '') ?>">
<meta property="og:image" content="<?= \Heirloom\Template::escape($ogImage ?? '') ?>">
<meta property="og:type" content="website">
<?php endif; ?>
</head>
<?= $content ?>
PHP;
        file_put_contents($this->tmpDir . '/layout.php', $layout);

        $this->baseProp->setValue(null, $this->tmpDir);
        $this->globalsProp->setValue(null, []);

        Template::setGlobal('ogTitle', 'Sunset Over Mountains');
        Template::setGlobal('ogDescription', 'A beautiful landscape');
        Template::setGlobal('ogImage', 'https://example.com/uploads/sunset.jpg');
        Template::setGlobal('siteName', 'Test Gallery');

        ob_start();
        Template::render('blank', []);
        $output = ob_get_clean();

        $this->assertStringContainsString('og:title', $output);
        $this->assertStringContainsString('Sunset Over Mountains', $output);
        $this->assertStringContainsString('og:description', $output);
        $this->assertStringContainsString('A beautiful landscape', $output);
        $this->assertStringContainsString('og:image', $output);
        $this->assertStringContainsString('https://example.com/uploads/sunset.jpg', $output);
        $this->assertStringContainsString('og:type', $output);
    }

    public function testOgMetaTagsOmittedWhenNoOgTitle(): void
    {
        file_put_contents($this->tmpDir . '/blank.php', '');
        $layout = <<<'PHP'
<head>
<?php if (!empty($ogTitle)): ?>
<meta property="og:title" content="<?= \Heirloom\Template::escape($ogTitle) ?>">
<?php endif; ?>
</head>
<?= $content ?>
PHP;
        file_put_contents($this->tmpDir . '/layout.php', $layout);

        $this->baseProp->setValue(null, $this->tmpDir);
        $this->globalsProp->setValue(null, []);
        Template::setGlobal('siteName', 'Test Gallery');

        ob_start();
        Template::render('blank', []);
        $output = ob_get_clean();

        $this->assertStringNotContainsString('og:title', $output);
    }
}
