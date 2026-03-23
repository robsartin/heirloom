<?php
declare(strict_types=1);

namespace Heirloom;

/**
 * Provides per-session CSRF token generation, validation, and hidden-field rendering.
 */
class Csrf
{
    public static function generateToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validate(string $token): bool
    {
        if (!isset($_SESSION['csrf_token']) || $token === '') {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function hiddenField(): string
    {
        $token = self::generateToken();
        return '<input type="hidden" name="_csrf_token" value="' . Template::escape($token) . '">';
    }
}
