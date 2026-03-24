<?php
declare(strict_types=1);

namespace Heirloom\Tests\UseCases;

use Heirloom\Adapters\SqlPaintingRepository;
use Heirloom\Database;
use Heirloom\UseCases\ExpressInterest;
use PDO;
use PHPUnit\Framework\TestCase;

class ExpressInterestTest extends TestCase
{
    private Database $db;
    private ExpressInterest $useCase;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $pdo->exec("CREATE TABLE paintings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT NOT NULL DEFAULT '',
            filename TEXT NOT NULL,
            original_filename TEXT NOT NULL DEFAULT '',
            awarded_to INTEGER NULL,
            awarded_at TEXT NULL,
            tracking_number TEXT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");
        $pdo->exec("CREATE TABLE interests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            painting_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            message TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            UNIQUE(painting_id, user_id)
        )");
        $pdo->exec("CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT UNIQUE NOT NULL,
            name TEXT NOT NULL DEFAULT '',
            password_hash TEXT,
            is_admin INTEGER NOT NULL DEFAULT 0,
            shipping_address TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");
        $pdo->exec("CREATE TABLE award_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            painting_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            awarded_by INTEGER NOT NULL,
            action TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");

        $this->db = new Database($pdo);
        $repo = new SqlPaintingRepository($this->db);
        $this->useCase = new ExpressInterest($repo);
    }

    public function testToggleInterestOnAddsInterest(): void
    {
        $this->db->execute("INSERT INTO paintings (title, description, filename) VALUES ('Sunset', '', 'sunset.jpg')");
        $this->db->execute("INSERT INTO users (email, name) VALUES ('alice@example.com', 'Alice')");

        $result = $this->useCase->execute(1, 1, 'I love this painting');

        $this->assertNotNull($result);
        $this->assertSame('on', $result['toggled']);

        // Verify the interest was actually added
        $row = $this->db->fetchOne('SELECT * FROM interests WHERE painting_id = 1 AND user_id = 1');
        $this->assertNotNull($row);
        $this->assertSame('I love this painting', $row['message']);
    }

    public function testToggleInterestOffRemovesInterest(): void
    {
        $this->db->execute("INSERT INTO paintings (title, description, filename) VALUES ('Sunset', '', 'sunset.jpg')");
        $this->db->execute("INSERT INTO users (email, name) VALUES ('alice@example.com', 'Alice')");
        $this->db->execute("INSERT INTO interests (painting_id, user_id, message) VALUES (1, 1, 'old message')");

        $result = $this->useCase->execute(1, 1, '');

        $this->assertNotNull($result);
        $this->assertSame('off', $result['toggled']);

        // Verify the interest was removed
        $row = $this->db->fetchOne('SELECT * FROM interests WHERE painting_id = 1 AND user_id = 1');
        $this->assertNull($row);
    }

    public function testReturnsFalseWhenPaintingNotAvailable(): void
    {
        // No painting exists
        $result = $this->useCase->execute(999, 1, 'hello');
        $this->assertNull($result);
    }

    public function testReturnsFalseWhenPaintingAlreadyAwarded(): void
    {
        $this->db->execute("INSERT INTO paintings (title, description, filename, awarded_to) VALUES ('Gone', '', 'gone.jpg', 42)");

        $result = $this->useCase->execute(1, 1, 'I want it');
        $this->assertNull($result);
    }

    public function testRejectsMessageOver1000Characters(): void
    {
        $this->db->execute("INSERT INTO paintings (title, description, filename) VALUES ('Sunset', '', 'sunset.jpg')");
        $this->db->execute("INSERT INTO users (email, name) VALUES ('alice@example.com', 'Alice')");

        $longMessage = str_repeat('a', 1001);
        $result = $this->useCase->execute(1, 1, $longMessage);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('1000 characters', $result['error']);
    }

    public function testAcceptsMessageExactly1000Characters(): void
    {
        $this->db->execute("INSERT INTO paintings (title, description, filename) VALUES ('Sunset', '', 'sunset.jpg')");
        $this->db->execute("INSERT INTO users (email, name) VALUES ('alice@example.com', 'Alice')");

        $message = str_repeat('a', 1000);
        $result = $this->useCase->execute(1, 1, $message);

        $this->assertNotNull($result);
        $this->assertSame('on', $result['toggled']);
    }
}
