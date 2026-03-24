<?php
declare(strict_types=1);

namespace Heirloom;

class ErrorHandler
{
    public static function formatError(\Throwable $e): string
    {
        $errorId = bin2hex(random_bytes(4));
        return "[Error $errorId] " . $e->getMessage();
    }
}
