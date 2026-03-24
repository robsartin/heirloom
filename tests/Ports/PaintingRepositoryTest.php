<?php
declare(strict_types=1);

namespace Heirloom\Tests\Ports;

use Heirloom\Database;
use Heirloom\Ports\PaintingRepository;
use Heirloom\Adapters\SqlPaintingRepository;
use PDO;
use PHPUnit\Framework\TestCase;

class PaintingRepositoryTest extends TestCase
{
    private Database $db;
    private PaintingRepository $repo;

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
        $this->repo = new SqlPaintingRepository($this->db);
    }

    public function testFindByIdReturnsNullForMissing(): void
    {
        $this->assertNull($this->repo->findById(999));
    }

    public function testFindByIdReturnsPainting(): void
    {
        $this->db->execute(
            "INSERT INTO paintings (title, description, filename) VALUES ('Sunset', 'A sunset', 'sunset.jpg')"
        );
        $painting = $this->repo->findById(1);
        $this->assertNotNull($painting);
        $this->assertSame('Sunset', $painting['title']);
    }

    public function testFindAvailableByIdReturnsNullWhenAwarded(): void
    {
        $this->db->execute(
            "INSERT INTO paintings (title, description, filename, awarded_to) VALUES ('Gone', '', 'gone.jpg', 1)"
        );
        $this->assertNull($this->repo->findAvailableById(1));
    }

    public function testFindAvailableByIdReturnsPainting(): void
    {
        $this->db->execute(
            "INSERT INTO paintings (title, description, filename) VALUES ('Available', '', 'avail.jpg')"
        );
        $this->assertNotNull($this->repo->findAvailableById(1));
    }

    public function testCountInterests(): void
    {
        $this->db->execute("INSERT INTO paintings (title, description, filename) VALUES ('P1', '', 'p1.jpg')");
        $this->db->execute("INSERT INTO users (email, name) VALUES ('a@b.com', 'A')");
        $this->db->execute("INSERT INTO users (email, name) VALUES ('c@d.com', 'C')");
        $this->db->execute("INSERT INTO interests (painting_id, user_id) VALUES (1, 1)");
        $this->db->execute("INSERT INTO interests (painting_id, user_id) VALUES (1, 2)");

        $this->assertSame(2, $this->repo->countInterests(1));
    }

    public function testHasInterest(): void
    {
        $this->db->execute("INSERT INTO paintings (title, description, filename) VALUES ('P1', '', 'p1.jpg')");
        $this->db->execute("INSERT INTO users (email, name) VALUES ('a@b.com', 'A')");
        $this->db->execute("INSERT INTO interests (painting_id, user_id) VALUES (1, 1)");

        $this->assertTrue($this->repo->hasInterest(1, 1));
        $this->assertFalse($this->repo->hasInterest(1, 99));
    }

    public function testAddInterest(): void
    {
        $this->db->execute("INSERT INTO paintings (title, description, filename) VALUES ('P1', '', 'p1.jpg')");
        $this->assertFalse($this->repo->hasInterest(1, 1));

        $this->repo->addInterest(1, 1, 'I want it');
        $this->assertTrue($this->repo->hasInterest(1, 1));
    }

    public function testRemoveInterest(): void
    {
        $this->db->execute("INSERT INTO paintings (title, description, filename) VALUES ('P1', '', 'p1.jpg')");
        $this->db->execute("INSERT INTO interests (painting_id, user_id, message) VALUES (1, 1, '')");

        $this->repo->removeInterest(1, 1);
        $this->assertFalse($this->repo->hasInterest(1, 1));
    }

    public function testAwardPainting(): void
    {
        $this->db->execute("INSERT INTO paintings (title, description, filename) VALUES ('P1', '', 'p1.jpg')");
        $this->db->execute("INSERT INTO users (email, name) VALUES ('a@b.com', 'Winner')");

        $this->repo->award(1, 1, 2);

        $painting = $this->repo->findById(1);
        $this->assertSame(1, (int) $painting['awarded_to']);

        $log = $this->db->fetchOne("SELECT * FROM award_log WHERE painting_id = 1");
        $this->assertSame('awarded', $log['action']);
        $this->assertSame(2, (int) $log['awarded_by']);
    }

    public function testUnassignPainting(): void
    {
        $this->db->execute("INSERT INTO paintings (title, description, filename, awarded_to, tracking_number) VALUES ('P1', '', 'p1.jpg', 1, 'TRACK123')");
        $this->db->execute("INSERT INTO users (email, name) VALUES ('a@b.com', 'A')");

        $this->repo->unassign(1, 2);

        $painting = $this->repo->findById(1);
        $this->assertNull($painting['awarded_to']);
        $this->assertNull($painting['tracking_number']);

        $log = $this->db->fetchOne("SELECT * FROM award_log WHERE painting_id = 1");
        $this->assertSame('unassigned', $log['action']);
    }

    public function testGetInterestedEmails(): void
    {
        $this->db->execute("INSERT INTO paintings (title, description, filename) VALUES ('P1', '', 'p1.jpg')");
        $this->db->execute("INSERT INTO users (email, name) VALUES ('a@b.com', 'A')");
        $this->db->execute("INSERT INTO users (email, name) VALUES ('c@d.com', 'C')");
        $this->db->execute("INSERT INTO interests (painting_id, user_id) VALUES (1, 1)");
        $this->db->execute("INSERT INTO interests (painting_id, user_id) VALUES (1, 2)");

        $emails = $this->repo->getInterestedEmails(1, excludeUserId: 1);
        $this->assertSame(['c@d.com'], $emails);
    }

    public function testDeletePainting(): void
    {
        $this->db->execute("INSERT INTO paintings (title, description, filename) VALUES ('P1', '', 'p1.jpg')");
        $this->repo->delete(1);
        $this->assertNull($this->repo->findById(1));
    }

    public function testUpdatePainting(): void
    {
        $this->db->execute("INSERT INTO paintings (title, description, filename) VALUES ('Old', 'old desc', 'p1.jpg')");
        $this->repo->update(1, 'New Title', 'new desc');

        $painting = $this->repo->findById(1);
        $this->assertSame('New Title', $painting['title']);
        $this->assertSame('new desc', $painting['description']);
    }
}
