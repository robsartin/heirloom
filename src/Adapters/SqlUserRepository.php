<?php
declare(strict_types=1);

namespace Heirloom\Adapters;

use Heirloom\Database;
use Heirloom\Ports\UserRepository;

class SqlUserRepository implements UserRepository
{
    public function __construct(private Database $db) {}

    public function findById(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM users WHERE id = :id',
            [':id' => $id]
        );
    }

    public function findByEmail(string $email): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM users WHERE email = :email',
            [':email' => self::normalizeEmail($email)]
        );
    }

    public function findOrCreate(string $email, string $name = ''): array
    {
        $email = self::normalizeEmail($email);
        $existing = $this->db->fetchOne(
            'SELECT * FROM users WHERE email = :email',
            [':email' => $email]
        );
        if ($existing) {
            return $existing;
        }

        $this->db->execute(
            'INSERT INTO users (email, name) VALUES (:email, :name)',
            [':email' => $email, ':name' => $name]
        );

        return $this->db->fetchOne(
            'SELECT * FROM users WHERE id = :id',
            [':id' => $this->db->lastInsertId()]
        );
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $this->db->execute(
            'UPDATE users SET password_hash = :hash WHERE id = :id',
            [':hash' => $passwordHash, ':id' => $id]
        );
    }

    public function updateShippingAddress(int $id, ?string $address): void
    {
        $this->db->execute(
            'UPDATE users SET shipping_address = :addr WHERE id = :id',
            [':addr' => $address, ':id' => $id]
        );
    }

    private static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}
