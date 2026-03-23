<?php
declare(strict_types=1);

namespace Heirloom\Tests;

use Heirloom\Template;
use PHPUnit\Framework\TestCase;

class ErrorPageTest extends TestCase
{
    public function testRenderErrorTemplateWithNoLayout(): void
    {
        ob_start();
        Template::render('error', [
            'code' => 404,
            'message' => 'The page you requested could not be found.',
            'noLayout' => true,
        ]);
        $output = ob_get_clean();

        $this->assertStringContainsString('404', $output);
        $this->assertStringContainsString('The page you requested could not be found.', $output);
        $this->assertStringContainsString('Back to Gallery', $output);
        // noLayout means no <!DOCTYPE html> wrapper
        $this->assertStringNotContainsString('<!DOCTYPE html>', $output);
    }

    public function testRenderError403(): void
    {
        ob_start();
        Template::render('error', [
            'code' => 403,
            'message' => 'Forbidden',
            'noLayout' => true,
        ]);
        $output = ob_get_clean();

        $this->assertStringContainsString('403', $output);
        $this->assertStringContainsString('Forbidden', $output);
    }

    public function testRenderError500(): void
    {
        ob_start();
        Template::render('error', [
            'code' => 500,
            'message' => 'An unexpected error occurred. Please try again later.',
            'noLayout' => true,
        ]);
        $output = ob_get_clean();

        $this->assertStringContainsString('500', $output);
        $this->assertStringContainsString('An unexpected error occurred.', $output);
    }

    public function testErrorTemplateEscapesHtml(): void
    {
        ob_start();
        Template::render('error', [
            'code' => 400,
            'message' => '<script>alert("xss")</script>',
            'noLayout' => true,
        ]);
        $output = ob_get_clean();

        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }
}
