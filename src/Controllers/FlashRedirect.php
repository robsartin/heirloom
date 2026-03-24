<?php
declare(strict_types=1);

namespace Heirloom\Controllers;

/**
 * Trait providing a flash-and-redirect helper to eliminate repeated
 * $_SESSION + header('Location:') + exit patterns across controllers.
 */
trait FlashRedirect
{
    /**
     * Set a flash message in the session.
     *
     * Extracted as a separate method so it can be tested without triggering
     * header() + exit.
     */
    protected function setFlash(string $key, string $message): void
    {
        $_SESSION[$key] = $message;
    }

    /**
     * Set a flash message and redirect to the given URL.
     *
     * @return never
     */
    protected function redirectWithFlash(string $url, string $key, string $message): never
    {
        $this->setFlash($key, $message);
        header('Location: ' . $url);
        exit;
    }
}
