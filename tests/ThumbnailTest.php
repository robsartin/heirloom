<?php
declare(strict_types=1);

namespace Heirloom\Tests;

use Heirloom\Thumbnail;
use PHPUnit\Framework\TestCase;

class ThumbnailTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/heirloom_thumb_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->tmpDir . '/*');
        if ($files) {
            array_map('unlink', $files);
        }
        rmdir($this->tmpDir);
    }

    public function testGeneratesThumbnailSmallerThanOriginalJpeg(): void
    {
        $source = $this->tmpDir . '/original.jpg';
        $dest = $this->tmpDir . '/original_thumb.jpg';

        $img = imagecreatetruecolor(1200, 800);
        imagejpeg($img, $source);
        unset($img);

        $result = Thumbnail::generateThumbnail($source, $dest, 400);

        $this->assertTrue($result);
        $this->assertFileExists($dest);

        $info = getimagesize($dest);
        $this->assertSame(400, $info[0]);
        $this->assertLessThan(800, $info[1]);
    }

    public function testPreservesAspectRatio(): void
    {
        $source = $this->tmpDir . '/wide.jpg';
        $dest = $this->tmpDir . '/wide_thumb.jpg';

        // 1600x800 => aspect ratio 2:1
        $img = imagecreatetruecolor(1600, 800);
        imagejpeg($img, $source);
        unset($img);

        Thumbnail::generateThumbnail($source, $dest, 400);

        $info = getimagesize($dest);
        $this->assertSame(400, $info[0]);
        $this->assertSame(200, $info[1]);
    }

    public function testHandlesPngFormat(): void
    {
        $source = $this->tmpDir . '/original.png';
        $dest = $this->tmpDir . '/original_thumb.png';

        $img = imagecreatetruecolor(1000, 500);
        imagepng($img, $source);
        unset($img);

        $result = Thumbnail::generateThumbnail($source, $dest, 400);

        $this->assertTrue($result);
        $this->assertFileExists($dest);

        $info = getimagesize($dest);
        $this->assertSame(400, $info[0]);
        $this->assertSame(200, $info[1]);
        $this->assertSame(IMAGETYPE_PNG, $info[2]);
    }

    public function testHandlesJpegFormat(): void
    {
        $source = $this->tmpDir . '/photo.jpg';
        $dest = $this->tmpDir . '/photo_thumb.jpg';

        $img = imagecreatetruecolor(800, 600);
        imagejpeg($img, $source);
        unset($img);

        Thumbnail::generateThumbnail($source, $dest, 400);

        $info = getimagesize($dest);
        $this->assertSame(IMAGETYPE_JPEG, $info[2]);
    }

    public function testReturnsFalseForUnsupportedFormat(): void
    {
        $source = $this->tmpDir . '/fake.bmp';
        $dest = $this->tmpDir . '/fake_thumb.bmp';
        file_put_contents($source, 'not a real image');

        $result = Thumbnail::generateThumbnail($source, $dest);

        $this->assertFalse($result);
        $this->assertFileDoesNotExist($dest);
    }

    public function testDoesNotUpscaleSmallImages(): void
    {
        $source = $this->tmpDir . '/small.jpg';
        $dest = $this->tmpDir . '/small_thumb.jpg';

        $img = imagecreatetruecolor(200, 150);
        imagejpeg($img, $source);
        unset($img);

        Thumbnail::generateThumbnail($source, $dest, 400);

        $info = getimagesize($dest);
        $this->assertSame(200, $info[0]);
        $this->assertSame(150, $info[1]);
    }

    public function testThumbOrOriginalReturnsThumbWhenExists(): void
    {
        $original = $this->tmpDir . '/abc123.jpg';
        $thumb = $this->tmpDir . '/abc123_thumb.jpg';
        file_put_contents($original, 'original');
        file_put_contents($thumb, 'thumb');

        $result = Thumbnail::thumbOrOriginal('abc123.jpg', $this->tmpDir . '/');
        $this->assertSame('abc123_thumb.jpg', $result);
    }

    public function testThumbOrOriginalFallsBackToOriginal(): void
    {
        $original = $this->tmpDir . '/noThumb.png';
        file_put_contents($original, 'original');

        $result = Thumbnail::thumbOrOriginal('noThumb.png', $this->tmpDir . '/');
        $this->assertSame('noThumb.png', $result);
    }
}
