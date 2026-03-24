<?php
declare(strict_types=1);

namespace Heirloom;

final class InputValidator
{
    public const MAX_SHIPPING_ADDRESS = 500;
    public const MAX_INTEREST_MESSAGE = 1000;
    public const MAX_PAINTING_TITLE = 255;
    public const MAX_PAINTING_DESCRIPTION = 5000;

    /**
     * @return string|null Error message if too long, null if valid.
     */
    public static function validateLength(string $value, int $max, string $fieldName): ?string
    {
        if (mb_strlen($value) > $max) {
            return "{$fieldName} must be {$max} characters or fewer.";
        }

        return null;
    }
}
