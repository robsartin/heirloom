<?php
declare(strict_types=1);

namespace Heirloom\Tests;

use Heirloom\Paths;
use PHPUnit\Framework\TestCase;

class PaintingsPathTest extends TestCase
{
    public function testPaintingsUrlPathConstant(): void
    {
        $this->assertSame('/paintings/', Paths::PAINTINGS_URL);
    }

    public function testPaintingsDirEndsWithPaintings(): void
    {
        $this->assertStringEndsWith('/public/paintings/', Paths::paintingsDir());
    }

    public function testPaintingsDirIsAbsolute(): void
    {
        $this->assertStringStartsWith('/', Paths::paintingsDir());
    }
}
