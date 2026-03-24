<?php
declare(strict_types=1);

namespace Heirloom\Ports;

/**
 * Port for user persistence operations.
 *
 * @method array<string, mixed>|null findById(int $id)
 * @method array<string, mixed>|null findByEmail(string $email)
 * @method array<string, mixed> findOrCreate(string $email, string $name)
 */
interface UserRepository
{
    /**
     * @return array<string, mixed>|null The user row, or null if not found
     */
    public function findById(int $id): ?array;

    /**
     * Look up a user by email (case-insensitive, trimmed).
     *
     * @return array<string, mixed>|null The user row, or null if not found
     */
    public function findByEmail(string $email): ?array;

    /**
     * Return an existing user by email, or create one with the given name.
     *
     * @return array<string, mixed> The existing or newly-created user row
     */
    public function findOrCreate(string $email, string $name = ''): array;

    /**
     * Update the password hash for a user.
     */
    public function updatePassword(int $id, string $passwordHash): void;

    /**
     * Set or clear the shipping address for a user.
     */
    public function updateShippingAddress(int $id, ?string $address): void;
}
