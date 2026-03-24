<?php
declare(strict_types=1);

namespace Heirloom\Tests\Controllers;

use PHPUnit\Framework\TestCase;

/**
 * Tests that controller 404 paths use Template::render('error', ...)
 * rather than raw echo '<h1>Painting not found</h1>' output.
 *
 * These are source-code inspections: we verify the controller source does NOT
 * contain raw HTML echo for 404 responses, and instead delegates to the error
 * template for consistent, styled, HTML-escaped error pages.
 */
class Controller404Test extends TestCase
{
    private function readControllerSource(string $filename): string
    {
        $path = __DIR__ . '/../../src/Controllers/' . $filename;
        $this->assertFileExists($path);
        return file_get_contents($path);
    }

    // ---------------------------------------------------------------
    // GalleryController::show() — must use Template::render for 404
    // ---------------------------------------------------------------

    public function testGalleryControllerShowDoesNotUseRawEchoFor404(): void
    {
        $source = $this->readControllerSource('GalleryController.php');

        $this->assertStringNotContainsString(
            "echo '<h1>Painting not found</h1>'",
            $source,
            'GalleryController::show() should not use raw echo for 404 — use Template::render(\'error\', ...) instead'
        );
    }

    public function testGalleryControllerShowUsesErrorTemplate(): void
    {
        $source = $this->readControllerSource('GalleryController.php');

        // After the !$painting check, should call Template::render('error', ...)
        $this->assertStringContainsString(
            "Template::render('error'",
            $source,
            'GalleryController::show() should render the error template for missing paintings'
        );
    }

    // ---------------------------------------------------------------
    // AdminController::managePainting() — must use Template::render for 404
    // ---------------------------------------------------------------

    public function testAdminControllerManagePaintingDoesNotUseRawEchoFor404(): void
    {
        $source = $this->readControllerSource('AdminController.php');

        $this->assertStringNotContainsString(
            "echo '<h1>Painting not found</h1>'",
            $source,
            'AdminController::managePainting() should not use raw echo for 404 — use Template::render(\'error\', ...) instead'
        );
    }

    public function testAdminControllerManagePaintingUsesErrorTemplate(): void
    {
        $source = $this->readControllerSource('AdminController.php');

        // The managePainting 404 path should use the error template
        $this->assertStringContainsString(
            "Template::render('error'",
            $source,
            'AdminController::managePainting() should render the error template for missing paintings'
        );
    }
}
