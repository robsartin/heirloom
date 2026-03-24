<?php
declare(strict_types=1);

namespace Heirloom\Tests;

use Heirloom\Controllers\AuthController;
use Heirloom\Database;
use Heirloom\RateLimiter;
use PDO;
use PHPUnit\Framework\TestCase;

class PasswordSecurityTest extends TestCase
{
    // -------------------------------------------------------
    // Password validation tests (via AuthController::validatePassword)
    // -------------------------------------------------------

    public function testPasswordShorterThan12CharsIsRejected(): void
    {
        $this->assertNotNull(
            AuthController::validatePassword('Short1Aa'),
            'An 8-character password should be rejected'
        );
    }

    public function testPasswordExactly12CharsWithComplexityIsAccepted(): void
    {
        $this->assertNull(
            AuthController::validatePassword('Abcdefghij1a'),
            'A 12-character password meeting all rules should pass'
        );
    }

    public function testPasswordWithoutUppercaseIsRejected(): void
    {
        $error = AuthController::validatePassword('abcdefghij1a');
        $this->assertNotNull($error);
        $this->assertStringContainsString('uppercase', $error);
    }

    public function testPasswordWithoutLowercaseIsRejected(): void
    {
        $error = AuthController::validatePassword('ABCDEFGHIJ1A');
        $this->assertNotNull($error);
        $this->assertStringContainsString('lowercase', $error);
    }

    public function testPasswordWithoutNumberIsRejected(): void
    {
        $error = AuthController::validatePassword('Abcdefghijkl');
        $this->assertNotNull($error);
        $this->assertStringContainsString('number', $error);
    }

    public function testPasswordMeetingAllRequirementsIsAccepted(): void
    {
        $this->assertNull(AuthController::validatePassword('StrongPass123'));
    }

    public function testPasswordTooShortReturnsLengthError(): void
    {
        $error = AuthController::validatePassword('Ab1');
        $this->assertNotNull($error);
        $this->assertStringContainsString('12 characters', $error);
    }

    // -------------------------------------------------------
    // Rate limiting tests for password_change_ identifier
    // -------------------------------------------------------

    private function makeRateLimiter(): RateLimiter
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("CREATE TABLE login_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            identifier TEXT NOT NULL,
            attempted_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");
        $db = new Database($pdo);
        return new RateLimiter($db, maxAttempts: 5, windowMinutes: 15);
    }

    public function testRateLimiterAllowsFirstPasswordChangeAttempt(): void
    {
        $limiter = $this->makeRateLimiter();
        $this->assertTrue($limiter->isAllowed('password_change_42'));
    }

    public function testRateLimiterBlocksAfter5PasswordChangeAttempts(): void
    {
        $limiter = $this->makeRateLimiter();
        for ($i = 0; $i < 5; $i++) {
            $limiter->record('password_change_42');
        }
        $this->assertFalse($limiter->isAllowed('password_change_42'));
    }

    public function testRateLimiterPasswordChangeIdentifierFormat(): void
    {
        $userId = 99;
        $identifier = 'password_change_' . $userId;
        $this->assertSame('password_change_99', $identifier);
    }
}
