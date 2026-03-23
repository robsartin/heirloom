<?php
declare(strict_types=1);

namespace Heirloom\Tests;

use Heirloom\Database;
use PDO;
use PHPUnit\Framework\TestCase;

class AdminStatsTest extends TestCase
{
    private Database $db;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $pdo->exec('CREATE TABLE paintings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT,
            filename TEXT,
            original_filename TEXT,
            awarded_to INTEGER,
            awarded_at TEXT,
            tracking_number TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )');

        $pdo->exec('CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT,
            email TEXT,
            shipping_address TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )');

        $pdo->exec('CREATE TABLE interests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            painting_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )');

        $this->db = new Database($pdo);
    }

    public function testTotalPaintingsCount(): void
    {
        $this->db->execute("INSERT INTO paintings (title, filename) VALUES ('A', 'a.jpg')");
        $this->db->execute("INSERT INTO paintings (title, filename) VALUES ('B', 'b.jpg')");
        $this->db->execute("INSERT INTO paintings (title, filename, awarded_to) VALUES ('C', 'c.jpg', 1)");

        $total = (int) $this->db->scalar('SELECT COUNT(*) FROM paintings');
        $this->assertSame(3, $total);
    }

    public function testAvailablePaintingsCount(): void
    {
        $this->db->execute("INSERT INTO paintings (title, filename) VALUES ('A', 'a.jpg')");
        $this->db->execute("INSERT INTO paintings (title, filename) VALUES ('B', 'b.jpg')");
        $this->db->execute("INSERT INTO paintings (title, filename, awarded_to) VALUES ('C', 'c.jpg', 1)");

        $available = (int) $this->db->scalar('SELECT COUNT(*) FROM paintings WHERE awarded_to IS NULL');
        $this->assertSame(2, $available);
    }

    public function testAwardedPaintingsCount(): void
    {
        $this->db->execute("INSERT INTO paintings (title, filename) VALUES ('A', 'a.jpg')");
        $this->db->execute("INSERT INTO paintings (title, filename, awarded_to) VALUES ('B', 'b.jpg', 1)");
        $this->db->execute("INSERT INTO paintings (title, filename, awarded_to) VALUES ('C', 'c.jpg', 2)");

        $awarded = (int) $this->db->scalar('SELECT COUNT(*) FROM paintings WHERE awarded_to IS NOT NULL');
        $this->assertSame(2, $awarded);
    }

    public function testTotalUsersCount(): void
    {
        $this->db->execute("INSERT INTO users (name, email) VALUES ('Alice', 'alice@example.com')");
        $this->db->execute("INSERT INTO users (name, email) VALUES ('Bob', 'bob@example.com')");

        $totalUsers = (int) $this->db->scalar('SELECT COUNT(*) FROM users');
        $this->assertSame(2, $totalUsers);
    }

    public function testTotalInterestsCount(): void
    {
        $this->db->execute("INSERT INTO paintings (title, filename) VALUES ('A', 'a.jpg')");
        $this->db->execute("INSERT INTO users (name, email) VALUES ('Alice', 'alice@example.com')");
        $this->db->execute("INSERT INTO interests (painting_id, user_id) VALUES (1, 1)");
        $this->db->execute("INSERT INTO interests (painting_id, user_id) VALUES (1, 1)");
        $this->db->execute("INSERT INTO interests (painting_id, user_id) VALUES (1, 1)");

        $totalInterests = (int) $this->db->scalar('SELECT COUNT(*) FROM interests');
        $this->assertSame(3, $totalInterests);
    }

    public function testMostWantedPaintingTitle(): void
    {
        $this->db->execute("INSERT INTO paintings (title, filename) VALUES ('Sunset', 'sunset.jpg')");
        $this->db->execute("INSERT INTO paintings (title, filename) VALUES ('Mountains', 'mountains.jpg')");
        $this->db->execute("INSERT INTO users (name, email) VALUES ('Alice', 'a@example.com')");
        $this->db->execute("INSERT INTO users (name, email) VALUES ('Bob', 'b@example.com')");
        $this->db->execute("INSERT INTO users (name, email) VALUES ('Carol', 'c@example.com')");

        // Mountains gets 3 interests, Sunset gets 1
        $this->db->execute("INSERT INTO interests (painting_id, user_id) VALUES (2, 1)");
        $this->db->execute("INSERT INTO interests (painting_id, user_id) VALUES (2, 2)");
        $this->db->execute("INSERT INTO interests (painting_id, user_id) VALUES (2, 3)");
        $this->db->execute("INSERT INTO interests (painting_id, user_id) VALUES (1, 1)");

        $mostWanted = $this->db->fetchOne(
            'SELECT p.title, COUNT(i.id) AS cnt
             FROM paintings p
             JOIN interests i ON i.painting_id = p.id
             WHERE p.awarded_to IS NULL
             GROUP BY p.id
             ORDER BY cnt DESC
             LIMIT 1'
        );

        $this->assertNotNull($mostWanted);
        $this->assertSame('Mountains', $mostWanted['title']);
    }

    public function testMostWantedReturnsNullWhenNoInterests(): void
    {
        $this->db->execute("INSERT INTO paintings (title, filename) VALUES ('Lonely', 'lonely.jpg')");

        $mostWanted = $this->db->fetchOne(
            'SELECT p.title, COUNT(i.id) AS cnt
             FROM paintings p
             JOIN interests i ON i.painting_id = p.id
             WHERE p.awarded_to IS NULL
             GROUP BY p.id
             ORDER BY cnt DESC
             LIMIT 1'
        );

        $this->assertNull($mostWanted);
    }

    public function testStatsWithEmptyDatabase(): void
    {
        $total = (int) $this->db->scalar('SELECT COUNT(*) FROM paintings');
        $available = (int) $this->db->scalar('SELECT COUNT(*) FROM paintings WHERE awarded_to IS NULL');
        $awarded = (int) $this->db->scalar('SELECT COUNT(*) FROM paintings WHERE awarded_to IS NOT NULL');
        $totalUsers = (int) $this->db->scalar('SELECT COUNT(*) FROM users');
        $totalInterests = (int) $this->db->scalar('SELECT COUNT(*) FROM interests');

        $this->assertSame(0, $total);
        $this->assertSame(0, $available);
        $this->assertSame(0, $awarded);
        $this->assertSame(0, $totalUsers);
        $this->assertSame(0, $totalInterests);
    }
}
