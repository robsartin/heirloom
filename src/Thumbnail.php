<?php
declare(strict_types=1);

namespace Heirloom;

class Thumbnail
{
    /**
     * Generate a thumbnail that fits within maxWidth while preserving aspect ratio.
     * Does not upscale images smaller than maxWidth.
     */
    public static function generateThumbnail(string $sourcePath, string $destPath, int $maxWidth = 400): bool
    {
        $info = @getimagesize($sourcePath);
        if ($info === false) {
            return false;
        }

        $mime = $info['mime'];
        $srcWidth = $info[0];
        $srcHeight = $info[1];

        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/png' => @imagecreatefrompng($sourcePath),
            default => false,
        };

        if ($source === false) {
            return false;
        }

        // Don't upscale
        if ($srcWidth <= $maxWidth) {
            $newWidth = $srcWidth;
            $newHeight = $srcHeight;
        } else {
            $newWidth = $maxWidth;
            $newHeight = (int) round($srcHeight * ($maxWidth / $srcWidth));
        }

        $thumb = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve PNG transparency
        if ($mime === 'image/png') {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
        }

        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $srcWidth, $srcHeight);

        $result = match ($mime) {
            'image/jpeg' => imagejpeg($thumb, $destPath, 85),
            'image/png' => imagepng($thumb, $destPath, 6),
            default => false,
        };

        unset($source, $thumb);

        return $result;
    }

    /**
     * Get the thumbnail filename for a given original filename.
     * e.g., abc123.jpg -> abc123_thumb.jpg
     */
    public static function thumbFilename(string $filename): string
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $base = pathinfo($filename, PATHINFO_FILENAME);
        return $base . '_thumb.' . $ext;
    }

    /**
     * Return the thumbnail filename if the file exists on disk, otherwise the original.
     */
    public static function thumbOrOriginal(string $filename, string $uploadDir = ''): string
    {
        if ($uploadDir === '') {
            $uploadDir = Paths::paintingsDir();
        }
        $thumb = self::thumbFilename($filename);
        if (file_exists($uploadDir . $thumb)) {
            return $thumb;
        }
        return $filename;
    }
}
