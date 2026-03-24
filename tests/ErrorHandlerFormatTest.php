<?php
declare(strict_types=1);

namespace Heirloom\Tests;

use Heirloom\ErrorHandler;
use PHPUnit\Framework\TestCase;

/**
 * Additional coverage for ErrorHandler::formatError() beyond ErrorLoggingTest.
 */
class ErrorHandlerFormatTest extends TestCase
{
    public function testFormatErrorIdIsExactlyEightHexChars(): void
    {
        $exception = new \RuntimeException('test');
        $result = ErrorHandler::formatError($exception);

        // Extract the hex ID between "[Error " and "]"
        preg_match('/\[Error ([0-9a-f]+)\]/', $result, $matches);
        $this->assertNotEmpty($matches, 'Error ID should be present in output');
        $this->assertSame(8, strlen($matches[1]), 'Error ID should be exactly 8 hex characters');
    }

    public function testFormatErrorGeneratesUniqueIds(): void
    {
        $exception = new \RuntimeException('same message');
        $ids = [];
        for ($i = 0; $i < 10; $i++) {
            $result = ErrorHandler::formatError($exception);
            preg_match('/\[Error ([0-9a-f]{8})\]/', $result, $matches);
            $ids[] = $matches[1];
        }
        // All 10 IDs should be unique (random_bytes guarantees this with overwhelming probability)
        $this->assertCount(10, array_unique($ids), 'Each call should produce a unique error ID');
    }

    public function testFormatErrorIncludesExceptionMessage(): void
    {
        $msg = 'Connection refused to database server';
        $exception = new \RuntimeException($msg);
        $result = ErrorHandler::formatError($exception);

        $this->assertStringContainsString($msg, $result);
    }

    public function testFormatErrorExcludesFilePath(): void
    {
        $exception = new \RuntimeException('oops');
        $result = ErrorHandler::formatError($exception);

        $this->assertStringNotContainsString(__FILE__, $result);
        $this->assertStringNotContainsString('.php', $result);
    }

    public function testFormatErrorExcludesStackTrace(): void
    {
        $exception = new \RuntimeException('oops');
        $result = ErrorHandler::formatError($exception);

        $this->assertStringNotContainsString($exception->getTraceAsString(), $result);
        $this->assertStringNotContainsString('#0', $result);
    }

    public function testFormatErrorOutputFormat(): void
    {
        $exception = new \RuntimeException('Something broke');
        $result = ErrorHandler::formatError($exception);

        // Should match: "[Error abcd1234] Something broke"
        $this->assertMatchesRegularExpression(
            '/^\[Error [0-9a-f]{8}\] Something broke$/',
            $result
        );
    }
}
