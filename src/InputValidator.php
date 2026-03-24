<?php
declare(strict_types=1);

namespace Heirloom;

final class InputValidator
{
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
