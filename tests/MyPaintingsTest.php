<?php
declare(strict_types=1);

namespace Heirloom\Tests;

use Heirloom\Auth;
use Heirloom\Database;
use Heirloom\Controllers\GalleryController;
use PDO;
use PHPUnit\Framework\TestCase;

class MyPaintingsTest extends TestCase
{
    private Database $db;
    private Auth $auth;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $pdo->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT UNIQUE NOT NULL,
                name TEXT NOT NULL DEFAULT '',
                password_hash TEXT,
                is_admin INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
        ");
        $pdo->exec("
            CREATE TABLE paintings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                description TEXT NOT NULL DEFAULT '',
                filename TEXT NOT NULL,
                original_filename TEXT NOT NULL DEFAULT '',
                awarded_to INTEGER,
                awarded_at TEXT,
                tracking_number TEXT,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY (awarded_to) REFERENCES users(id) ON DELETE SET NULL
            )
        ");
        $pdo->exec("
            CREATE TABLE interests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                painting_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                message TEXT NOT NULL DEFAULT '',
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY (painting_id) REFERENCES paintings(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");

        $this->db = new Database($pdo);
        $this->auth = new Auth($this->db);

        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        unset($_SESSION['user_id']);
    }

    private function createUser(string $email = 'user@example.com', string $name = 'Test User'): int
    {
        $this->db->execute(
            "INSERT INTO users (email, name) VALUES (:e, :n)",
            [':e' => $email, ':n' => $name]
        );
        return $this->db->lastInsertId();
    }

    private function createPainting(string $title, ?int $awardedTo = null, ?string $trackingNumber = null): int
    {
        $this->db->execute(
            "INSERT INTO paintings (title, filename, awarded_to, awarded_at, tracking_number) VALUES (:t, :f, :a, :at, :tn)",
            [
                ':t' => $title,
                ':f' => 'test.jpg',
                ':a' => $awardedTo,
                ':at' => $awardedTo ? date('Y-m-d H:i:s') : null,
                ':tn' => $trackingNumber,
            ]
        );
        return $this->db->lastInsertId();
    }

    private function addInterest(int $paintingId, int $userId): void
    {
        $this->db->execute(
            "INSERT INTO interests (painting_id, user_id) VALUES (:pid, :uid)",
            [':pid' => $paintingId, ':uid' => $userId]
        );
    }

    // --- Paintings I Want: user expressed interest, not yet awarded ---

    public function testPaintingsIWantReturnsUnawarded(): void
    {
        $userId = $this->createUser();
        $paintingId = $this->createPainting('Sunset');
        $this->addInterest($paintingId, $userId);

        $results = $this->db->fetchAll(
            'SELECT p.* FROM paintings p
             JOIN interests i ON i.painting_id = p.id
             WHERE i.user_id = :uid AND p.awarded_to IS NULL',
            [':uid' => $userId]
        );

        $this->assertCount(1, $results);
        $this->assertSame('Sunset', $results[0]['title']);
    }

    public function testPaintingsIWantExcludesAwarded(): void
    {
        $userId = $this->createUser();
        $otherUser = $this->createUser('other@example.com', 'Other');
        $paintingId = $this->createPainting('Awarded Painting', $otherUser);
        $this->addInterest($paintingId, $userId);

        $results = $this->db->fetchAll(
            'SELECT p.* FROM paintings p
             JOIN interests i ON i.painting_id = p.id
             WHERE i.user_id = :uid AND p.awarded_to IS NULL',
            [':uid' => $userId]
        );

        $this->assertCount(0, $results);
    }

    // --- Paintings I Was Awarded ---

    public function testPaintingsAwardedToUser(): void
    {
        $userId = $this->createUser();
        $this->createPainting('My Prize', $userId, 'TRACK123');

        $results = $this->db->fetchAll(
            'SELECT * FROM paintings WHERE awarded_to = :uid',
            [':uid' => $userId]
        );

        $this->assertCount(1, $results);
        $this->assertSame('My Prize', $results[0]['title']);
        $this->assertSame('TRACK123', $results[0]['tracking_number']);
    }

    public function testPaintingsAwardedExcludesOtherUsers(): void
    {
        $userId = $this->createUser();
        $otherUser = $this->createUser('other@example.com', 'Other');
        $this->createPainting('Not Mine', $otherUser);

        $results = $this->db->fetchAll(
            'SELECT * FROM paintings WHERE awarded_to = :uid',
            [':uid' => $userId]
        );

        $this->assertCount(0, $results);
    }

    // --- No Longer Available: user wanted but awarded to someone else ---

    public function testNoLongerAvailableShowsAwardedToOthers(): void
    {
        $userId = $this->createUser();
        $otherUser = $this->createUser('winner@example.com', 'Winner');
        $paintingId = $this->createPainting('Gone Painting', $otherUser);
        $this->addInterest($paintingId, $userId);

        $results = $this->db->fetchAll(
            'SELECT p.* FROM paintings p
             JOIN interests i ON i.painting_id = p.id
             WHERE i.user_id = :uid AND p.awarded_to IS NOT NULL AND p.awarded_to != :uid2',
            [':uid' => $userId, ':uid2' => $userId]
        );

        $this->assertCount(1, $results);
        $this->assertSame('Gone Painting', $results[0]['title']);
    }

    public function testNoLongerAvailableExcludesSelfAwarded(): void
    {
        $userId = $this->createUser();
        $paintingId = $this->createPainting('Won By Me', $userId);
        $this->addInterest($paintingId, $userId);

        $results = $this->db->fetchAll(
            'SELECT p.* FROM paintings p
             JOIN interests i ON i.painting_id = p.id
             WHERE i.user_id = :uid AND p.awarded_to IS NOT NULL AND p.awarded_to != :uid2',
            [':uid' => $userId, ':uid2' => $userId]
        );

        $this->assertCount(0, $results);
    }

    public function testNoLongerAvailableExcludesUnawarded(): void
    {
        $userId = $this->createUser();
        $paintingId = $this->createPainting('Still Available');
        $this->addInterest($paintingId, $userId);

        $results = $this->db->fetchAll(
            'SELECT p.* FROM paintings p
             JOIN interests i ON i.painting_id = p.id
             WHERE i.user_id = :uid AND p.awarded_to IS NOT NULL AND p.awarded_to != :uid2',
            [':uid' => $userId, ':uid2' => $userId]
        );

        $this->assertCount(0, $results);
    }
}
