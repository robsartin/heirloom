<?php
declare(strict_types=1);

namespace Heirloom;

use PDO;
use PDOStatement;

/**
 * Thin PDO wrapper providing prepared-statement convenience methods and a singleton accessor.
 * Connects to MySQL using credentials from Config.
 */
class Database
{
    private static ?self $instance = null;
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        if ($pdo !== null) {
            $this->pdo = $pdo;
            return;
        }

        $host = Config::get('DB_HOST', '127.0.0.1');
        $port = Config::get('DB_PORT', '3306');
        $name = Config::get('DB_NAME', 'heirloom');
        $user = Config::get('DB_USER', 'root');
        $pass = Config::get('DB_PASS', '');

        $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @param array<string, mixed> $params Named placeholder values
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * @param array<string, mixed> $params Named placeholder values
     * @return array<string, mixed>|null A single associative row, or null if no match
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $params Named placeholder values
     * @return list<array<string, mixed>> All matching rows as associative arrays
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function execute(string $sql, array $params = []): void
    {
        $this->query($sql, $params);
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Return the first column of the first row (useful for COUNT, MAX, etc.).
     *
     * @param array<string, mixed> $params Named placeholder values
     */
    public function scalar(string $sql, array $params = []): mixed
    {
        $row = $this->fetchOne($sql, $params);
        return $row ? reset($row) : null;
    }
}
