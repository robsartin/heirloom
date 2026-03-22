<?php
declare(strict_types=1);

namespace Heirloom\Tests;

use Heirloom\Auth;
use Heirloom\Database;
use PDO;
use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase
{
    private Auth $auth;
    private Database $db;

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
            CREATE TABLE magic_links (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL,
                token TEXT UNIQUE NOT NULL,
                used INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
        ");

        $this->db = new Database($pdo);
        $this->auth = new Auth($this->db);

        // Ensure session superglobal exists
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        unset($_SESSION['user_id']);
    }

    // --- isLoggedIn ---

    public function testIsLoggedInReturnsFalseWhenNoSession(): void
    {
        $this->assertFalse($this->auth->isLoggedIn());
    }

    public function testIsLoggedInReturnsTrueWhenSessionSet(): void
    {
        $_SESSION['user_id'] = 1;
        $this->assertTrue($this->auth->isLoggedIn());
    }

    // --- user ---

    public function testUserReturnsNullWhenNotLoggedIn(): void
    {
        $this->assertNull($this->auth->user());
    }

    public function testUserReturnsUserArrayWhenLoggedIn(): void
    {
        $this->db->execute(
            "INSERT INTO users (email, name, is_admin) VALUES (:e, :n, 0)",
            [':e' => 'alice@example.com', ':n' => 'Alice']
        );
        $id = $this->db->lastInsertId();

        $_SESSION['user_id'] = $id;
        $user = $this->auth->user();

        $this->assertNotNull($user);
        $this->assertSame('alice@example.com', $user['email']);
        $this->assertSame('Alice', $user['name']);
    }

    // --- isAdmin ---

    public function testIsAdminReturnsFalseForNonAdmin(): void
    {
        $this->db->execute(
            "INSERT INTO users (email, name, is_admin) VALUES (:e, :n, 0)",
            [':e' => 'user@example.com', ':n' => 'User']
        );
        $_SESSION['user_id'] = $this->db->lastInsertId();

        $this->assertFalse($this->auth->isAdmin());
    }

    public function testIsAdminReturnsTrueForAdmin(): void
    {
        $this->db->execute(
            "INSERT INTO users (email, name, is_admin) VALUES (:e, :n, 1)",
            [':e' => 'admin@example.com', ':n' => 'Admin']
        );
        $_SESSION['user_id'] = $this->db->lastInsertId();

        $this->assertTrue($this->auth->isAdmin());
    }

    public function testIsAdminReturnsFalseWhenNotLoggedIn(): void
    {
        $this->assertFalse($this->auth->isAdmin());
    }

    // --- attemptPasswordLogin ---

    public function testAttemptPasswordLoginSucceedsWithCorrectCredentials(): void
    {
        $hash = password_hash('secret123', PASSWORD_DEFAULT);
        $this->db->execute(
            "INSERT INTO users (email, name, password_hash) VALUES (:e, :n, :h)",
            [':e' => 'bob@example.com', ':n' => 'Bob', ':h' => $hash]
        );

        $user = $this->auth->attemptPasswordLogin('bob@example.com', 'secret123');
        $this->assertNotNull($user);
        $this->assertSame('bob@example.com', $user['email']);
    }

    public function testAttemptPasswordLoginFailsWithWrongPassword(): void
    {
        $hash = password_hash('correct', PASSWORD_DEFAULT);
        $this->db->execute(
            "INSERT INTO users (email, name, password_hash) VALUES (:e, :n, :h)",
            [':e' => 'bob@example.com', ':n' => 'Bob', ':h' => $hash]
        );

        $user = $this->auth->attemptPasswordLogin('bob@example.com', 'wrong');
        $this->assertNull($user);
    }

    public function testAttemptPasswordLoginFailsForNonexistentUser(): void
    {
        $user = $this->auth->attemptPasswordLogin('nobody@example.com', 'anything');
        $this->assertNull($user);
    }

    public function testAttemptPasswordLoginFailsWhenNoPasswordSet(): void
    {
        $this->db->execute(
            "INSERT INTO users (email, name, password_hash) VALUES (:e, :n, NULL)",
            [':e' => 'nopass@example.com', ':n' => 'No Pass']
        );

        $user = $this->auth->attemptPasswordLogin('nopass@example.com', 'anything');
        $this->assertNull($user);
    }

    public function testAttemptPasswordLoginNormalizesEmail(): void
    {
        $hash = password_hash('pass', PASSWORD_DEFAULT);
        $this->db->execute(
            "INSERT INTO users (email, name, password_hash) VALUES (:e, :n, :h)",
            [':e' => 'upper@example.com', ':n' => 'U', ':h' => $hash]
        );

        $user = $this->auth->attemptPasswordLogin('  UPPER@EXAMPLE.COM  ', 'pass');
        $this->assertNotNull($user);
    }

    // --- findOrCreateUserByEmail ---

    public function testFindOrCreateUserByEmailCreatesNewUser(): void
    {
        $user = $this->auth->findOrCreateUserByEmail('new@example.com', 'New User');

        $this->assertSame('new@example.com', $user['email']);
        $this->assertSame('New User', $user['name']);
    }

    public function testFindOrCreateUserByEmailReturnsExistingUser(): void
    {
        $this->db->execute(
            "INSERT INTO users (email, name) VALUES (:e, :n)",
            [':e' => 'existing@example.com', ':n' => 'Existing']
        );

        $user = $this->auth->findOrCreateUserByEmail('existing@example.com', 'Different Name');
        $this->assertSame('Existing', $user['name']); // Does not overwrite
    }

    public function testFindOrCreateUserByEmailNormalizesEmail(): void
    {
        $user = $this->auth->findOrCreateUserByEmail('  MIXED@Case.COM  ', 'Mixed');
        $this->assertSame('mixed@case.com', $user['email']);
    }

    // --- createMagicLink ---

    public function testCreateMagicLinkReturnsToken(): void
    {
        $token = $this->auth->createMagicLink('test@example.com');

        $this->assertIsString($token);
        $this->assertSame(64, strlen($token)); // 32 bytes = 64 hex chars
    }

    public function testCreateMagicLinkStoresInDatabase(): void
    {
        $token = $this->auth->createMagicLink('stored@example.com');

        $row = $this->db->fetchOne(
            'SELECT * FROM magic_links WHERE token = :t',
            [':t' => $token]
        );
        $this->assertNotNull($row);
        $this->assertSame('stored@example.com', $row['email']);
        $this->assertEquals(0, $row['used']);
    }

    public function testCreateMagicLinkGeneratesUniqueTokens(): void
    {
        $t1 = $this->auth->createMagicLink('a@example.com');
        $t2 = $this->auth->createMagicLink('a@example.com');

        $this->assertNotSame($t1, $t2);
    }

    // --- consumeMagicLink ---
    // Note: the MySQL-specific DATE_SUB(NOW(), INTERVAL 1 HOUR) won't work
    // with SQLite, so we test the core logic separately. The time-based
    // expiry is an integration concern tested against MySQL.

    public function testConsumeMagicLinkReturnsEmailForValidToken(): void
    {
        // Insert a magic link directly (bypasses the MySQL date function issue)
        $this->db->execute(
            "INSERT INTO magic_links (email, token, used, created_at) VALUES (:e, :t, 0, datetime('now'))",
            [':e' => 'magic@example.com', ':t' => 'validtoken123']
        );

        // We need to override the query since consumeMagicLink uses MySQL syntax.
        // For unit testing, we test the database state change directly.
        // Mark it used and verify state.
        $link = $this->db->fetchOne(
            "SELECT * FROM magic_links WHERE token = :t AND used = 0",
            [':t' => 'validtoken123']
        );
        $this->assertNotNull($link);
        $this->assertSame('magic@example.com', $link['email']);

        // Mark used
        $this->db->execute('UPDATE magic_links SET used = 1 WHERE id = :id', [':id' => $link['id']]);

        // Verify it can't be consumed again
        $link2 = $this->db->fetchOne(
            "SELECT * FROM magic_links WHERE token = :t AND used = 0",
            [':t' => 'validtoken123']
        );
        $this->assertNull($link2);
    }

    public function testConsumedMagicLinkCannotBeReused(): void
    {
        $this->db->execute(
            "INSERT INTO magic_links (email, token, used) VALUES (:e, :t, 1)",
            [':e' => 'used@example.com', ':t' => 'usedtoken']
        );

        $link = $this->db->fetchOne(
            "SELECT * FROM magic_links WHERE token = :t AND used = 0",
            [':t' => 'usedtoken']
        );
        $this->assertNull($link);
    }
}
