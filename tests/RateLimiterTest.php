<?php
declare(strict_types=1);

namespace Heirloom\Tests;

use Heirloom\Database;
use Heirloom\RateLimiter;
use PDO;
use PHPUnit\Framework\TestCase;

class RateLimiterTest extends TestCase
{
    private Database $db;
    private RateLimiter $limiter;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("CREATE TABLE login_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            identifier TEXT NOT NULL,
            attempted_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");

        $this->db = new Database($pdo);
        $this->limiter = new RateLimiter($this->db, maxAttempts: 5, windowMinutes: 15);
    }

    public function testIsAllowedReturnsTrueWithNoAttempts(): void
    {
        $this->assertTrue($this->limiter->isAllowed('test@example.com'));
    }

    public function testIsAllowedReturnsTrueUnderLimit(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->limiter->record('test@example.com');
        }
        $this->assertTrue($this->limiter->isAllowed('test@example.com'));
    }

    public function testIsAllowedReturnsFalseAtLimit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->record('test@example.com');
        }
        $this->assertFalse($this->limiter->isAllowed('test@example.com'));
    }

    public function testIsAllowedReturnsFalseOverLimit(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->limiter->record('test@example.com');
        }
        $this->assertFalse($this->limiter->isAllowed('test@example.com'));
    }

    public function testDifferentIdentifiersAreIndependent(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->record('blocked@example.com');
        }
        $this->assertFalse($this->limiter->isAllowed('blocked@example.com'));
        $this->assertTrue($this->limiter->isAllowed('free@example.com'));
    }

    public function testRecordIncreasesCount(): void
    {
        $this->assertSame(0, $this->limiter->getAttemptCount('test@example.com'));
        $this->limiter->record('test@example.com');
        $this->assertSame(1, $this->limiter->getAttemptCount('test@example.com'));
    }

    public function testRemainingAttemptsDecreasesCorrectly(): void
    {
        $this->assertSame(5, $this->limiter->remainingAttempts('test@example.com'));
        $this->limiter->record('test@example.com');
        $this->assertSame(4, $this->limiter->remainingAttempts('test@example.com'));
    }

    public function testRemainingAttemptsNeverGoesNegative(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->limiter->record('test@example.com');
        }
        $this->assertSame(0, $this->limiter->remainingAttempts('test@example.com'));
    }

    public function testClearRemovesAttempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->record('test@example.com');
        }
        $this->assertFalse($this->limiter->isAllowed('test@example.com'));

        $this->limiter->clear('test@example.com');
        $this->assertTrue($this->limiter->isAllowed('test@example.com'));
    }
}
