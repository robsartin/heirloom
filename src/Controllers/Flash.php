<?php
declare(strict_types=1);

namespace Heirloom\Controllers;

final class Flash
{
    public const AUTH_ERROR = 'auth_error';
    public const AUTH_SUCCESS = 'auth_success';
    public const ADMIN_ERROR = 'admin_error';
    public const ADMIN_SUCCESS = 'admin_success';
    public const UPLOAD_ERROR = 'upload_error';
    public const UPLOAD_SUCCESS = 'upload_success';
    public const GALLERY_ERROR = 'gallery_error';

    public const MSG_VALID_EMAIL_REQUIRED = 'Valid email is required.';
    public const MSG_TOO_MANY_ATTEMPTS = 'Too many attempts. Please try again in 15 minutes.';
}
