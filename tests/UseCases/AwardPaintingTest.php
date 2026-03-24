<?php
declare(strict_types=1);

namespace Heirloom\Tests\UseCases;

use Heirloom\Adapters\SqlPaintingRepository;
use Heirloom\Adapters\SqlUserRepository;
use Heirloom\Database;
use Heirloom\UseCases\AwardPainting;
use PDO;
use PHPUnit\Framework\TestCase;

class AwardPaintingTest extends TestCase
{
    private Database $db;
    private AwardPainting $useCase;

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
        $paintingRepo = new SqlPaintingRepository($this->db);
        $userRepo = new SqlUserRepository($this->db);
        $this->useCase = new AwardPainting($paintingRepo, $userRepo);
    }

    public function testAwardSetsAwardedToAndLogs(): void
    {
        $this->db->execute("INSERT INTO paintings (title, description, filename) VALUES ('Sunset', '', 'sunset.jpg')");
        $this->db->execute("INSERT INTO users (email, name) VALUES ('winner@example.com', 'Winner')");
        $this->db->execute("INSERT INTO users (email, name) VALUES ('admin@example.com', 'Admin')");

        $result = $this->useCase->award(1, 1, 2);

        // Painting should be awarded
        $painting = $this->db->fetchOne('SELECT * FROM paintings WHERE id = 1');
        $this->assertSame(1, (int) $painting['awarded_to']);
        $this->assertNotNull($painting['awarded_at']);

        // Award log should exist
        $log = $this->db->fetchOne('SELECT * FROM award_log WHERE painting_id = 1');
        $this->assertSame('awarded', $log['action']);
        $this->assertSame(1, (int) $log['user_id']);
        $this->assertSame(2, (int) $log['awarded_by']);

        // Result should contain winner email
        $this->assertSame('winner@example.com', $result['winner_email']);
        $this->assertSame('Sunset', $result['painting_title']);
    }

    public function testAwardReturnsLoserEmails(): void
    {
        $this->db->execute("INSERT INTO paintings (title, description, filename) VALUES ('Sunset', '', 'sunset.jpg')");
        $this->db->execute("INSERT INTO users (email, name) VALUES ('winner@example.com', 'Winner')");
        $this->db->execute("INSERT INTO users (email, name) VALUES ('loser1@example.com', 'Loser1')");
        $this->db->execute("INSERT INTO users (email, name) VALUES ('loser2@example.com', 'Loser2')");
        $this->db->execute("INSERT INTO users (email, name) VALUES ('admin@example.com', 'Admin')");

        // All three users expressed interest
        $this->db->execute("INSERT INTO interests (painting_id, user_id) VALUES (1, 1)");
        $this->db->execute("INSERT INTO interests (painting_id, user_id) VALUES (1, 2)");
        $this->db->execute("INSERT INTO interests (painting_id, user_id) VALUES (1, 3)");

        $result = $this->useCase->award(1, 1, 4);

        $this->assertSame('winner@example.com', $result['winner_email']);
        $this->assertEqualsCanonicalizing(
            ['loser1@example.com', 'loser2@example.com'],
            $result['loser_emails']
        );
    }

    public function testUnassignClearsAwardedToAndLogs(): void
    {
        $this->db->execute("INSERT INTO paintings (title, description, filename, awarded_to, awarded_at, tracking_number) VALUES ('Sunset', '', 'sunset.jpg', 1, '2026-01-01', 'TRACK123')");
        $this->db->execute("INSERT INTO users (email, name) VALUES ('winner@example.com', 'Winner')");
        $this->db->execute("INSERT INTO users (email, name) VALUES ('admin@example.com', 'Admin')");

        $this->useCase->unassign(1, 2);

        $painting = $this->db->fetchOne('SELECT * FROM paintings WHERE id = 1');
        $this->assertNull($painting['awarded_to']);
        $this->assertNull($painting['awarded_at']);
        $this->assertNull($painting['tracking_number']);

        $log = $this->db->fetchOne('SELECT * FROM award_log WHERE painting_id = 1');
        $this->assertSame('unassigned', $log['action']);
        $this->assertSame(2, (int) $log['awarded_by']);
    }

    public function testAwardReturnsEmptyLoserEmailsWhenNoOtherInterest(): void
    {
        $this->db->execute("INSERT INTO paintings (title, description, filename) VALUES ('Sunset', '', 'sunset.jpg')");
        $this->db->execute("INSERT INTO users (email, name) VALUES ('winner@example.com', 'Winner')");
        $this->db->execute("INSERT INTO users (email, name) VALUES ('admin@example.com', 'Admin')");

        $result = $this->useCase->award(1, 1, 2);

        $this->assertSame([], $result['loser_emails']);
    }
}
