<?php
declare(strict_types=1);

namespace Heirloom\Tests\Controllers;

use Heirloom\Database;
use PDO;

/**
 * Trait providing an in-memory SQLite database with the full Heirloom schema.
 *
 * SQLite differences from MySQL that we accommodate:
 * - AUTOINCREMENT instead of AUTO_INCREMENT
 * - DATETIME columns stored as TEXT; NOW() replaced with datetime('now')
 * - BOOLEAN stored as INTEGER (0/1)
 */
trait ControllerTestSchema
{
    private PDO $pdo;
    private Database $db;

    private function createDatabase(): Database
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->pdo->exec('CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL DEFAULT \'\',
            password_hash TEXT,
            shipping_address TEXT,
            is_admin INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
        )');

        $this->pdo->exec('CREATE TABLE paintings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT NOT NULL DEFAULT \'\',
            filename TEXT NOT NULL DEFAULT \'\',
            original_filename TEXT NOT NULL DEFAULT \'\',
            awarded_to INTEGER,
            awarded_at TEXT,
            tracking_number TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
            FOREIGN KEY (awarded_to) REFERENCES users(id)
        )');

        $this->pdo->exec('CREATE TABLE interests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            painting_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            message TEXT NOT NULL DEFAULT \'\',
            created_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
            FOREIGN KEY (painting_id) REFERENCES paintings(id),
            FOREIGN KEY (user_id) REFERENCES users(id),
            UNIQUE(painting_id, user_id)
        )');

        $this->pdo->exec('CREATE TABLE magic_links (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL,
            token TEXT NOT NULL,
            used INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
        )');

        $this->pdo->exec('CREATE TABLE site_settings (
            setting_key TEXT PRIMARY KEY,
            setting_value TEXT NOT NULL,
            label TEXT NOT NULL DEFAULT \'\',
            description TEXT NOT NULL DEFAULT \'\'
        )');

        $this->pdo->exec('CREATE TABLE login_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            identifier TEXT NOT NULL,
            attempted_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
        )');

        $this->pdo->exec('CREATE TABLE award_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            painting_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            awarded_by INTEGER NOT NULL,
            action TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
            FOREIGN KEY (painting_id) REFERENCES paintings(id),
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (awarded_by) REFERENCES users(id)
        )');

        $this->db = new Database($this->pdo);
        return $this->db;
    }

    /** Insert a user and return the ID. */
    private function insertUser(string $email, string $name = '', bool $isAdmin = false, ?string $passwordHash = null): int
    {
        $this->db->execute(
            'INSERT INTO users (email, name, is_admin, password_hash) VALUES (:e, :n, :a, :p)',
            [':e' => $email, ':n' => $name, ':a' => $isAdmin ? 1 : 0, ':p' => $passwordHash]
        );
        return $this->db->lastInsertId();
    }

    /** Insert a painting and return the ID. */
    private function insertPainting(string $title, ?int $awardedTo = null, string $description = '', string $createdAt = ''): int
    {
        $at = $createdAt ?: date('Y-m-d H:i:s');
        $this->db->execute(
            'INSERT INTO paintings (title, description, filename, original_filename, awarded_to, created_at)
             VALUES (:t, :d, :f, :of, :aw, :ca)',
            [
                ':t' => $title,
                ':d' => $description,
                ':f' => 'img_' . bin2hex(random_bytes(4)) . '.jpg',
                ':of' => $title . '.jpg',
                ':aw' => $awardedTo,
                ':ca' => $at,
            ]
        );
        return $this->db->lastInsertId();
    }

    /** Insert an interest and return the ID. */
    private function insertInterest(int $paintingId, int $userId, string $message = '', string $createdAt = ''): int
    {
        $at = $createdAt ?: date('Y-m-d H:i:s');
        $this->db->execute(
            'INSERT INTO interests (painting_id, user_id, message, created_at) VALUES (:p, :u, :m, :c)',
            [':p' => $paintingId, ':u' => $userId, ':m' => $message, ':c' => $at]
        );
        return $this->db->lastInsertId();
    }

    /** Insert a site setting. */
    private function insertSetting(string $key, string $value): void
    {
        $this->db->execute(
            'INSERT INTO site_settings (setting_key, setting_value) VALUES (:k, :v)',
            [':k' => $key, ':v' => $value]
        );
    }
}
