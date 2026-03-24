<?php
declare(strict_types=1);

namespace Heirloom\Tests;

use PHPUnit\Framework\TestCase;

class FaviconTest extends TestCase
{
    private string $publicDir;

    protected function setUp(): void
    {
        $this->publicDir = dirname(__DIR__) . '/public/';
    }

    public function testFaviconIcoExists(): void
    {
        $this->assertFileExists($this->publicDir . 'favicon.ico');
    }

    public function testFavicon16Exists(): void
    {
        $this->assertFileExists($this->publicDir . 'favicon-16x16.png');
    }

    public function testFavicon32Exists(): void
    {
        $this->assertFileExists($this->publicDir . 'favicon-32x32.png');
    }

    public function testAppleTouchIconExists(): void
    {
        $this->assertFileExists($this->publicDir . 'apple-touch-icon.png');
    }

    public function testLogoExists(): void
    {
        $this->assertFileExists($this->publicDir . 'logo.png');
    }

    public function testFavicon16IsPng(): void
    {
        $info = getimagesize($this->publicDir . 'favicon-16x16.png');
        $this->assertSame(16, $info[0]);
        $this->assertSame(16, $info[1]);
        $this->assertSame(IMAGETYPE_PNG, $info[2]);
    }

    public function testFavicon32IsPng(): void
    {
        $info = getimagesize($this->publicDir . 'favicon-32x32.png');
        $this->assertSame(32, $info[0]);
        $this->assertSame(32, $info[1]);
        $this->assertSame(IMAGETYPE_PNG, $info[2]);
    }

    public function testAppleTouchIconIs180(): void
    {
        $info = getimagesize($this->publicDir . 'apple-touch-icon.png');
        $this->assertSame(180, $info[0]);
        $this->assertSame(180, $info[1]);
    }

    public function testLayoutContainsFaviconLinks(): void
    {
        $layout = file_get_contents(dirname(__DIR__) . '/templates/layout.php');
        $this->assertStringContainsString('favicon.ico', $layout);
        $this->assertStringContainsString('favicon-32x32.png', $layout);
        $this->assertStringContainsString('apple-touch-icon.png', $layout);
    }

    public function testLayoutContainsLogoImage(): void
    {
        $layout = file_get_contents(dirname(__DIR__) . '/templates/layout.php');
        $this->assertStringContainsString('logo.png', $layout);
        $this->assertStringContainsString('nav-logo', $layout);
    }
}
