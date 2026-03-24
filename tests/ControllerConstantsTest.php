<?php
declare(strict_types=1);

namespace Heirloom\Tests;

use Heirloom\Controllers\Routes;
use Heirloom\Controllers\Flash;
use PHPUnit\Framework\TestCase;

class ControllerConstantsTest extends TestCase
{
    // --- Route constants ---

    public function testLoginRouteConstant(): void
    {
        $this->assertSame('/login', Routes::LOGIN);
    }

    public function testRegisterRouteConstant(): void
    {
        $this->assertSame('/register', Routes::REGISTER);
    }

    public function testSetPasswordRouteConstant(): void
    {
        $this->assertSame('/set-password', Routes::SET_PASSWORD);
    }

    public function testProfileRouteConstant(): void
    {
        $this->assertSame('/profile', Routes::PROFILE);
    }

    public function testAdminDashboardRouteConstant(): void
    {
        $this->assertSame('/admin', Routes::ADMIN);
    }

    public function testAdminUploadRouteConstant(): void
    {
        $this->assertSame('/admin/upload', Routes::ADMIN_UPLOAD);
    }

    public function testAdminSettingsRouteConstant(): void
    {
        $this->assertSame('/admin/settings', Routes::ADMIN_SETTINGS);
    }

    public function testAdminInviteRouteConstant(): void
    {
        $this->assertSame('/admin/invite', Routes::ADMIN_INVITE);
    }

    public function testAdminPaintingRouteHelper(): void
    {
        $this->assertSame('/admin/painting/42', Routes::adminPainting('42'));
    }

    public function testPaintingRouteHelper(): void
    {
        $this->assertSame('/painting/7', Routes::painting('7'));
    }

    // --- Flash key constants ---

    public function testAuthErrorFlashKey(): void
    {
        $this->assertSame('auth_error', Flash::AUTH_ERROR);
    }

    public function testAuthSuccessFlashKey(): void
    {
        $this->assertSame('auth_success', Flash::AUTH_SUCCESS);
    }

    public function testAdminErrorFlashKey(): void
    {
        $this->assertSame('admin_error', Flash::ADMIN_ERROR);
    }

    public function testAdminSuccessFlashKey(): void
    {
        $this->assertSame('admin_success', Flash::ADMIN_SUCCESS);
    }

    public function testUploadErrorFlashKey(): void
    {
        $this->assertSame('upload_error', Flash::UPLOAD_ERROR);
    }

    public function testUploadSuccessFlashKey(): void
    {
        $this->assertSame('upload_success', Flash::UPLOAD_SUCCESS);
    }

    public function testGalleryErrorFlashKey(): void
    {
        $this->assertSame('gallery_error', Flash::GALLERY_ERROR);
    }

    // --- Shared error message constants ---

    public function testValidEmailRequiredMessage(): void
    {
        $this->assertSame('Valid email is required.', Flash::MSG_VALID_EMAIL_REQUIRED);
    }

    public function testTooManyAttemptsMessage(): void
    {
        $this->assertSame('Too many attempts. Please try again in 15 minutes.', Flash::MSG_TOO_MANY_ATTEMPTS);
    }
}
