<?php
declare(strict_types=1);

namespace Heirloom;

final class Paths
{
    public const PAINTINGS_URL = '/paintings/';

    public static function paintingsDir(): string
    {
        return dirname(__DIR__) . '/public/paintings/';
    }
}
