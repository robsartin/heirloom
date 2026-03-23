<?php
declare(strict_types=1);

namespace Heirloom\Tests;

use Heirloom\Auth;
use Heirloom\Database;
use PDO;
use PHPUnit\Framework\TestCase;

class UserTrackingTest extends TestCase
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
                shipping_address TEXT,
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
        ");
        $pdo->exec("
            CREATE TABLE magic_links (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL,
                token TEXT UNIQUE NOT NULL,
                used INTEGER NOT NULL DEFAULT 0,
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

        $this->db = new Database($pdo);
        $this->auth = new Auth($this->db);

        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        unset($_SESSION['user_id']);
    }

    public function testAwardedPaintingsQueryReturnsEmptyWhenNoneAwarded(): void
    {
        $this->db->execute(
            "INSERT INTO users (email, name) VALUES (:e, :n)",
            [':e' => 'user@example.com', ':n' => 'User']
        );
        $uid = $this->db->lastInsertId();

        $paintings = $this->db->fetchAll(
            'SELECT p.title, p.filename, p.awarded_at, p.tracking_number FROM paintings p WHERE p.awarded_to = :uid ORDER BY p.awarded_at DESC',
            [':uid' => $uid]
        );

        $this->assertSame([], $paintings);
    }

    public function testAwardedPaintingsQueryReturnsPaintingsForUser(): void
    {
        $this->db->execute(
            "INSERT INTO users (email, name) VALUES (:e, :n)",
            [':e' => 'winner@example.com', ':n' => 'Winner']
        );
        $uid = $this->db->lastInsertId();

        $this->db->execute(
            "INSERT INTO paintings (title, description, filename, awarded_to, awarded_at, tracking_number) VALUES (:t, :d, :f, :u, :a, :tn)",
            [':t' => 'Sunset', ':d' => 'A sunset', ':f' => 'sunset.jpg', ':u' => $uid, ':a' => '2026-03-20 12:00:00', ':tn' => '1Z999AA10123456784']
        );

        $paintings = $this->db->fetchAll(
            'SELECT p.title, p.filename, p.awarded_at, p.tracking_number FROM paintings p WHERE p.awarded_to = :uid ORDER BY p.awarded_at DESC',
            [':uid' => $uid]
        );

        $this->assertCount(1, $paintings);
        $this->assertSame('Sunset', $paintings[0]['title']);
        $this->assertSame('sunset.jpg', $paintings[0]['filename']);
        $this->assertSame('1Z999AA10123456784', $paintings[0]['tracking_number']);
        $this->assertSame('2026-03-20 12:00:00', $paintings[0]['awarded_at']);
    }

    public function testAwardedPaintingsQueryDoesNotReturnOtherUsersPaintings(): void
    {
        $this->db->execute(
            "INSERT INTO users (email, name) VALUES (:e, :n)",
            [':e' => 'alice@example.com', ':n' => 'Alice']
        );
        $aliceId = $this->db->lastInsertId();

        $this->db->execute(
            "INSERT INTO users (email, name) VALUES (:e, :n)",
            [':e' => 'bob@example.com', ':n' => 'Bob']
        );
        $bobId = $this->db->lastInsertId();

        $this->db->execute(
            "INSERT INTO paintings (title, description, filename, awarded_to, awarded_at) VALUES (:t, :d, :f, :u, :a)",
            [':t' => 'Bobs Painting', ':d' => 'For Bob', ':f' => 'bob.jpg', ':u' => $bobId, ':a' => '2026-03-20 12:00:00']
        );

        $paintings = $this->db->fetchAll(
            'SELECT p.title, p.filename, p.awarded_at, p.tracking_number FROM paintings p WHERE p.awarded_to = :uid ORDER BY p.awarded_at DESC',
            [':uid' => $aliceId]
        );

        $this->assertSame([], $paintings);
    }

    public function testAwardedPaintingsWithNullTrackingNumber(): void
    {
        $this->db->execute(
            "INSERT INTO users (email, name) VALUES (:e, :n)",
            [':e' => 'notrack@example.com', ':n' => 'No Track']
        );
        $uid = $this->db->lastInsertId();

        $this->db->execute(
            "INSERT INTO paintings (title, description, filename, awarded_to, awarded_at, tracking_number) VALUES (:t, :d, :f, :u, :a, NULL)",
            [':t' => 'Mountain', ':d' => 'A mountain', ':f' => 'mountain.jpg', ':u' => $uid, ':a' => '2026-03-15 10:00:00']
        );

        $paintings = $this->db->fetchAll(
            'SELECT p.title, p.filename, p.awarded_at, p.tracking_number FROM paintings p WHERE p.awarded_to = :uid ORDER BY p.awarded_at DESC',
            [':uid' => $uid]
        );

        $this->assertCount(1, $paintings);
        $this->assertNull($paintings[0]['tracking_number']);
    }

    public function testMultipleAwardedPaintingsOrderedByDate(): void
    {
        $this->db->execute(
            "INSERT INTO users (email, name) VALUES (:e, :n)",
            [':e' => 'collector@example.com', ':n' => 'Collector']
        );
        $uid = $this->db->lastInsertId();

        $this->db->execute(
            "INSERT INTO paintings (title, description, filename, awarded_to, awarded_at) VALUES (:t, :d, :f, :u, :a)",
            [':t' => 'Older', ':d' => 'Older painting', ':f' => 'older.jpg', ':u' => $uid, ':a' => '2026-01-01 00:00:00']
        );
        $this->db->execute(
            "INSERT INTO paintings (title, description, filename, awarded_to, awarded_at) VALUES (:t, :d, :f, :u, :a)",
            [':t' => 'Newer', ':d' => 'Newer painting', ':f' => 'newer.jpg', ':u' => $uid, ':a' => '2026-03-01 00:00:00']
        );

        $paintings = $this->db->fetchAll(
            'SELECT p.title, p.filename, p.awarded_at, p.tracking_number FROM paintings p WHERE p.awarded_to = :uid ORDER BY p.awarded_at DESC',
            [':uid' => $uid]
        );

        $this->assertCount(2, $paintings);
        $this->assertSame('Newer', $paintings[0]['title']);
        $this->assertSame('Older', $paintings[1]['title']);
    }
}
