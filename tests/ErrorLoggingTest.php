<?php
declare(strict_types=1);

namespace Heirloom\Tests;

require_once __DIR__ . '/../src/ErrorHandler.php';

use Heirloom\ErrorHandler;
use PHPUnit\Framework\TestCase;

class ErrorLoggingTest extends TestCase
{
    public function testFormatErrorReturnsEightCharHexId(): void
    {
        $exception = new \RuntimeException('Something went wrong');
        $result = ErrorHandler::formatError($exception);

        // Extract the error ID from the formatted string: "[Error abcd1234] ..."
        $this->assertMatchesRegularExpression('/^\[Error [0-9a-f]{8}\] /', $result);
    }

    public function testFormatErrorIncludesMessage(): void
    {
        $exception = new \RuntimeException('Database connection failed');
        $result = ErrorHandler::formatError($exception);

        $this->assertStringContainsString('Database connection failed', $result);
    }

    public function testFormatErrorDoesNotIncludeFilePath(): void
    {
        $exception = new \RuntimeException('Some error');
        $result = ErrorHandler::formatError($exception);

        // Should NOT contain the file path or line number
        $this->assertStringNotContainsString($exception->getFile(), $result);
        $this->assertStringNotContainsString(':' . $exception->getLine(), $result);
    }
}
