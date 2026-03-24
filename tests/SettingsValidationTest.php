<?php
declare(strict_types=1);

namespace Heirloom\Tests;

use Heirloom\Controllers\AdminController;
use PHPUnit\Framework\TestCase;

class SettingsValidationTest extends TestCase
{
    // ── Numeric settings: must be integers >= 1 ──

    /**
     * @dataProvider numericSettingKeysProvider
     */
    public function testNumericSettingRejectsZero(string $key): void
    {
        $errors = AdminController::validateSettings([$key => '0']);
        $this->assertArrayHasKey($key, $errors);
    }

    /**
     * @dataProvider numericSettingKeysProvider
     */
    public function testNumericSettingRejectsNegative(string $key): void
    {
        $errors = AdminController::validateSettings([$key => '-5']);
        $this->assertArrayHasKey($key, $errors);
    }

    /**
     * @dataProvider numericSettingKeysProvider
     */
    public function testNumericSettingRejectsNonInteger(string $key): void
    {
        $errors = AdminController::validateSettings([$key => 'abc']);
        $this->assertArrayHasKey($key, $errors);
    }

    /**
     * @dataProvider numericSettingKeysProvider
     */
    public function testNumericSettingRejectsFloat(string $key): void
    {
        $errors = AdminController::validateSettings([$key => '3.5']);
        $this->assertArrayHasKey($key, $errors);
    }

    /**
     * @dataProvider numericSettingKeysProvider
     */
    public function testNumericSettingAcceptsPositiveInteger(string $key): void
    {
        $errors = AdminController::validateSettings([$key => '10']);
        $this->assertArrayNotHasKey($key, $errors);
    }

    /**
     * @dataProvider numericSettingKeysProvider
     */
    public function testNumericSettingAcceptsOne(string $key): void
    {
        $errors = AdminController::validateSettings([$key => '1']);
        $this->assertArrayNotHasKey($key, $errors);
    }

    public static function numericSettingKeysProvider(): array
    {
        return [
            'magic_link_expiry_minutes' => ['magic_link_expiry_minutes'],
            'session_timeout_minutes' => ['session_timeout_minutes'],
            'gallery_per_page' => ['gallery_per_page'],
            'admin_per_page' => ['admin_per_page'],
        ];
    }

    // ── Boolean setting: registration_open ──

    public function testBooleanSettingAcceptsOne(): void
    {
        $errors = AdminController::validateSettings(['registration_open' => '1']);
        $this->assertArrayNotHasKey('registration_open', $errors);
    }

    public function testBooleanSettingAcceptsZero(): void
    {
        $errors = AdminController::validateSettings(['registration_open' => '0']);
        $this->assertArrayNotHasKey('registration_open', $errors);
    }

    public function testBooleanSettingRejectsArbitraryString(): void
    {
        $errors = AdminController::validateSettings(['registration_open' => 'yes']);
        $this->assertArrayHasKey('registration_open', $errors);
    }

    public function testBooleanSettingRejectsNumericOtherThanZeroOrOne(): void
    {
        $errors = AdminController::validateSettings(['registration_open' => '2']);
        $this->assertArrayHasKey('registration_open', $errors);
    }

    // ── Email: contact_email ──

    public function testContactEmailAcceptsValidEmail(): void
    {
        $errors = AdminController::validateSettings(['contact_email' => 'admin@example.com']);
        $this->assertArrayNotHasKey('contact_email', $errors);
    }

    public function testContactEmailAcceptsEmptyString(): void
    {
        $errors = AdminController::validateSettings(['contact_email' => '']);
        $this->assertArrayNotHasKey('contact_email', $errors);
    }

    public function testContactEmailRejectsInvalidEmail(): void
    {
        $errors = AdminController::validateSettings(['contact_email' => 'not-an-email']);
        $this->assertArrayHasKey('contact_email', $errors);
    }

    public function testContactEmailRejectsEmailWithoutDomain(): void
    {
        $errors = AdminController::validateSettings(['contact_email' => 'user@']);
        $this->assertArrayHasKey('contact_email', $errors);
    }

    // ── site_name ──

    public function testSiteNameAcceptsNormalLength(): void
    {
        $errors = AdminController::validateSettings(['site_name' => 'My Gallery']);
        $this->assertArrayNotHasKey('site_name', $errors);
    }

    public function testSiteNameRejectsOver100Characters(): void
    {
        $longName = str_repeat('A', 101);
        $errors = AdminController::validateSettings(['site_name' => $longName]);
        $this->assertArrayHasKey('site_name', $errors);
    }

    public function testSiteNameAcceptsExactly100Characters(): void
    {
        $name = str_repeat('A', 100);
        $errors = AdminController::validateSettings(['site_name' => $name]);
        $this->assertArrayNotHasKey('site_name', $errors);
    }

    // ── Mixed inputs ──

    public function testMultipleInvalidSettingsReturnMultipleErrors(): void
    {
        $errors = AdminController::validateSettings([
            'magic_link_expiry_minutes' => '0',
            'contact_email' => 'bad',
            'site_name' => str_repeat('X', 200),
        ]);
        $this->assertCount(3, $errors);
    }

    public function testUnknownSettingsPassThroughWithoutError(): void
    {
        $errors = AdminController::validateSettings(['unknown_key' => 'whatever']);
        $this->assertEmpty($errors);
    }

    public function testValidSettingsReturnNoErrors(): void
    {
        $errors = AdminController::validateSettings([
            'magic_link_expiry_minutes' => '60',
            'session_timeout_minutes' => '120',
            'gallery_per_page' => '12',
            'admin_per_page' => '20',
            'registration_open' => '1',
            'contact_email' => 'hello@example.com',
            'site_name' => 'Heirloom Gallery',
        ]);
        $this->assertEmpty($errors);
    }
}
