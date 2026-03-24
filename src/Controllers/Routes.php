<?php
declare(strict_types=1);

namespace Heirloom\Controllers;

final class Routes
{
    public const LOGIN = '/login';
    public const REGISTER = '/register';
    public const SET_PASSWORD = '/set-password';
    public const PROFILE = '/profile';
    public const ADMIN = '/admin';
    public const ADMIN_UPLOAD = '/admin/upload';
    public const ADMIN_SETTINGS = '/admin/settings';
    public const ADMIN_INVITE = '/admin/invite';

    public static function adminPainting(string $id): string
    {
        return '/admin/painting/' . $id;
    }

    public static function painting(string $id): string
    {
        return '/painting/' . $id;
    }
}
