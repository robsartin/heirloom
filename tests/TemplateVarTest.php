<?php
declare(strict_types=1);

namespace Heirloom\Tests;

use Heirloom\Controllers\TemplateVar;
use PHPUnit\Framework\TestCase;

class TemplateVarTest extends TestCase
{
    public function testAuthConstant(): void
    {
        $this->assertSame('auth', TemplateVar::AUTH);
    }

    public function testErrorConstant(): void
    {
        $this->assertSame('error', TemplateVar::ERROR);
    }

    public function testSuccessConstant(): void
    {
        $this->assertSame('success', TemplateVar::SUCCESS);
    }

    public function testClosedConstant(): void
    {
        $this->assertSame('closed', TemplateVar::CLOSED);
    }

    public function testCodeConstant(): void
    {
        $this->assertSame('code', TemplateVar::CODE);
    }

    public function testMessageConstant(): void
    {
        $this->assertSame('message', TemplateVar::MESSAGE);
    }

    public function testNoLayoutConstant(): void
    {
        $this->assertSame('noLayout', TemplateVar::NO_LAYOUT);
    }
}
