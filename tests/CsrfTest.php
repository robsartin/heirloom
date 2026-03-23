<?php
declare(strict_types=1);

namespace Heirloom\Tests;

use Heirloom\Csrf;
use PHPUnit\Framework\TestCase;

class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        unset($_SESSION['csrf_token']);
    }

    public function testGenerateTokenReturnsString(): void
    {
        $token = Csrf::generateToken();
        $this->assertIsString($token);
        $this->assertNotEmpty($token);
    }

    public function testGenerateTokenIs64HexChars(): void
    {
        $token = Csrf::generateToken();
        $this->assertSame(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function testGenerateTokenStoresInSession(): void
    {
        $token = Csrf::generateToken();
        $this->assertSame($token, $_SESSION['csrf_token']);
    }

    public function testGenerateTokenReturnsSameTokenIfAlreadySet(): void
    {
        $token1 = Csrf::generateToken();
        $token2 = Csrf::generateToken();
        $this->assertSame($token1, $token2);
    }

    public function testValidateReturnsTrueForCorrectToken(): void
    {
        $token = Csrf::generateToken();
        $this->assertTrue(Csrf::validate($token));
    }

    public function testValidateReturnsFalseForWrongToken(): void
    {
        Csrf::generateToken();
        $this->assertFalse(Csrf::validate('wrong-token'));
    }

    public function testValidateReturnsFalseForEmptyToken(): void
    {
        Csrf::generateToken();
        $this->assertFalse(Csrf::validate(''));
    }

    public function testValidateReturnsFalseWhenNoSessionToken(): void
    {
        $this->assertFalse(Csrf::validate('anything'));
    }

    public function testHiddenFieldReturnsHtmlInput(): void
    {
        $token = Csrf::generateToken();
        $html = Csrf::hiddenField();
        $this->assertStringContainsString('type="hidden"', $html);
        $this->assertStringContainsString('name="_csrf_token"', $html);
        $this->assertStringContainsString("value=\"$token\"", $html);
    }
}
