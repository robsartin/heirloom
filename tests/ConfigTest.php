<?php
declare(strict_types=1);

namespace Heirloom\Tests;

use Heirloom\Config;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/heirloom_test_' . uniqid();
        mkdir($this->tmpDir);
        // Clear any state from prior tests
        foreach ($_ENV as $k => $v) {
            if (str_starts_with($k, 'TEST_CFG_')) {
                unset($_ENV[$k]);
            }
        }
    }

    protected function tearDown(): void
    {
        foreach (scandir($this->tmpDir) as $f) {
            if ($f !== '.' && $f !== '..') {
                unlink($this->tmpDir . '/' . $f);
            }
        }
        rmdir($this->tmpDir);
    }

    private function writeEnv(string $content): string
    {
        $path = $this->tmpDir . '/.env';
        file_put_contents($path, $content);
        return $path;
    }

    public function testLoadParsesKeyValuePairs(): void
    {
        $path = $this->writeEnv("TEST_CFG_FOO=bar\nTEST_CFG_BAZ=qux\n");
        Config::load($path);

        $this->assertSame('bar', Config::get('TEST_CFG_FOO'));
        $this->assertSame('qux', Config::get('TEST_CFG_BAZ'));
    }

    public function testLoadSkipsComments(): void
    {
        $path = $this->writeEnv("# this is a comment\nTEST_CFG_REAL=value\n");
        Config::load($path);

        $this->assertSame('value', Config::get('TEST_CFG_REAL'));
    }

    public function testLoadSkipsBlankLines(): void
    {
        $path = $this->writeEnv("\n\nTEST_CFG_SPACED=yes\n\n");
        Config::load($path);

        $this->assertSame('yes', Config::get('TEST_CFG_SPACED'));
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        $this->assertSame('fallback', Config::get('TEST_CFG_NONEXISTENT_KEY', 'fallback'));
    }

    public function testGetReturnsEmptyStringDefaultWhenNoDefaultProvided(): void
    {
        $this->assertSame('', Config::get('TEST_CFG_ALSO_NONEXISTENT'));
    }

    public function testLoadHandlesMissingFile(): void
    {
        // Should not throw
        Config::load('/nonexistent/path/.env');
        $this->assertTrue(true);
    }

    public function testLoadHandlesValuesContainingEquals(): void
    {
        $path = $this->writeEnv("TEST_CFG_DSN=mysql:host=127.0.0.1;port=3306\n");
        Config::load($path);

        $this->assertSame('mysql:host=127.0.0.1;port=3306', Config::get('TEST_CFG_DSN'));
    }

    public function testEnvVariableTakesPrecedenceOverFileValue(): void
    {
        $_ENV['TEST_CFG_PREEXIST'] = 'from_env';
        $path = $this->writeEnv("TEST_CFG_PREEXIST=from_file\n");
        Config::load($path);

        $this->assertSame('from_env', Config::get('TEST_CFG_PREEXIST'));
        unset($_ENV['TEST_CFG_PREEXIST']);
    }
}
