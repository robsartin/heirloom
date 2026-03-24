<?php
declare(strict_types=1);

namespace Heirloom\Tests;

use Heirloom\Controllers\FlashRedirect;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the FlashRedirect trait's session-setting helper.
 *
 * Since redirectWithFlash() calls header() + exit (returning `never`),
 * we test the extracted setFlash() method directly, verifying that it
 * correctly sets the session key/value pair.
 */
class FlashRedirectTest extends TestCase
{
    private object $controller;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];

        // Anonymous class that uses the trait so we can test it
        $this->controller = new class {
            use FlashRedirect;

            // Expose the protected method for testing
            public function testSetFlash(string $key, string $message): void
            {
                $this->setFlash($key, $message);
            }
        };
    }

    public function testSetFlashSetsSessionValue(): void
    {
        $this->controller->testSetFlash('admin_error', 'Something went wrong.');

        $this->assertSame('Something went wrong.', $_SESSION['admin_error']);
    }

    public function testSetFlashOverwritesPreviousValue(): void
    {
        $_SESSION['auth_error'] = 'Old message';

        $this->controller->testSetFlash('auth_error', 'New message');

        $this->assertSame('New message', $_SESSION['auth_error']);
    }

    public function testSetFlashWorksWithDifferentKeys(): void
    {
        $this->controller->testSetFlash('upload_success', 'Upload complete.');
        $this->controller->testSetFlash('admin_error', 'Validation failed.');

        $this->assertSame('Upload complete.', $_SESSION['upload_success']);
        $this->assertSame('Validation failed.', $_SESSION['admin_error']);
    }

    public function testSetFlashWithEmptyMessage(): void
    {
        $this->controller->testSetFlash('auth_error', '');

        $this->assertSame('', $_SESSION['auth_error']);
    }

    public function testSetFlashDoesNotAffectOtherSessionKeys(): void
    {
        $_SESSION['existing_key'] = 'untouched';

        $this->controller->testSetFlash('admin_success', 'Done.');

        $this->assertSame('untouched', $_SESSION['existing_key']);
        $this->assertSame('Done.', $_SESSION['admin_success']);
    }
}
