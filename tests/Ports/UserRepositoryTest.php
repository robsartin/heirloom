<?php
declare(strict_types=1);

namespace Heirloom\Tests\Ports;

use Heirloom\Adapters\SqlUserRepository;
use Heirloom\Database;
use PDO;
use PHPUnit\Framework\TestCase;

class UserRepositoryTest extends TestCase
{
    private SqlUserRepository $repo;
    private Database $db;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT UNIQUE NOT NULL,
            name TEXT NOT NULL DEFAULT '',
            password_hash TEXT,
            is_admin INTEGER NOT NULL DEFAULT 0,
            shipping_address TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");

        $this->db = new Database($pdo);
        $this->repo = new SqlUserRepository($this->db);
    }

    // --- findById ---

    public function testFindByIdReturnsNullForMissingUser(): void
    {
        $this->assertNull($this->repo->findById(999));
    }

    public function testFindByIdReturnsUserArray(): void
    {
        $this->db->execute(
            "INSERT INTO users (email, name) VALUES (:e, :n)",
            [':e' => 'alice@example.com', ':n' => 'Alice']
        );
        $id = $this->db->lastInsertId();

        $user = $this->repo->findById($id);

        $this->assertNotNull($user);
        $this->assertSame('alice@example.com', $user['email']);
        $this->assertSame('Alice', $user['name']);
        $this->assertSame($id, (int) $user['id']);
    }

    // --- findByEmail ---

    public function testFindByEmailReturnsNullForMissingEmail(): void
    {
        $this->assertNull($this->repo->findByEmail('ghost@example.com'));
    }

    public function testFindByEmailReturnsUserCaseInsensitive(): void
    {
        $this->db->execute(
            "INSERT INTO users (email, name) VALUES (:e, :n)",
            [':e' => 'bob@example.com', ':n' => 'Bob']
        );

        $user = $this->repo->findByEmail('  BOB@EXAMPLE.COM  ');

        $this->assertNotNull($user);
        $this->assertSame('bob@example.com', $user['email']);
        $this->assertSame('Bob', $user['name']);
    }

    // --- findOrCreate ---

    public function testFindOrCreateCreatesNewUserWhenNotFound(): void
    {
        $user = $this->repo->findOrCreate('new@example.com', 'New User');

        $this->assertSame('new@example.com', $user['email']);
        $this->assertSame('New User', $user['name']);
        $this->assertArrayHasKey('id', $user);
    }

    public function testFindOrCreateReturnsExistingUserWithoutModification(): void
    {
        $this->db->execute(
            "INSERT INTO users (email, name) VALUES (:e, :n)",
            [':e' => 'existing@example.com', ':n' => 'Original']
        );

        $user = $this->repo->findOrCreate('existing@example.com', 'Different Name');

        $this->assertSame('Original', $user['name']);
    }

    // --- updatePassword ---

    public function testUpdatePasswordUpdatesPasswordHash(): void
    {
        $this->db->execute(
            "INSERT INTO users (email, name) VALUES (:e, :n)",
            [':e' => 'pass@example.com', ':n' => 'Pass User']
        );
        $id = $this->db->lastInsertId();

        $hash = password_hash('newsecret', PASSWORD_DEFAULT);
        $this->repo->updatePassword($id, $hash);

        $user = $this->repo->findById($id);
        $this->assertSame($hash, $user['password_hash']);
    }

    // --- updateShippingAddress ---

    public function testUpdateShippingAddressSetsAddress(): void
    {
        $this->db->execute(
            "INSERT INTO users (email, name) VALUES (:e, :n)",
            [':e' => 'ship@example.com', ':n' => 'Shipper']
        );
        $id = $this->db->lastInsertId();

        $this->repo->updateShippingAddress($id, '123 Main St, Springfield');

        $user = $this->repo->findById($id);
        $this->assertSame('123 Main St, Springfield', $user['shipping_address']);
    }

    public function testUpdateShippingAddressWithNullClearsAddress(): void
    {
        $this->db->execute(
            "INSERT INTO users (email, name, shipping_address) VALUES (:e, :n, :a)",
            [':e' => 'clear@example.com', ':n' => 'Clearer', ':a' => 'Old Address']
        );
        $id = $this->db->lastInsertId();

        $this->repo->updateShippingAddress($id, null);

        $user = $this->repo->findById($id);
        $this->assertNull($user['shipping_address']);
    }
}
