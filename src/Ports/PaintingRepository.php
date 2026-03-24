<?php
declare(strict_types=1);

namespace Heirloom\Ports;

interface PaintingRepository
{
    public function findById(int $id): ?array;

    public function findAvailableById(int $id): ?array;

    public function countInterests(int $paintingId): int;

    public function hasInterest(int $paintingId, int $userId): bool;

    public function addInterest(int $paintingId, int $userId, string $message): void;

    public function removeInterest(int $paintingId, int $userId): void;

    public function award(int $paintingId, int $userId, int $awardedBy): void;

    public function unassign(int $paintingId, int $adminId): void;

    /** @return string[] */
    public function getInterestedEmails(int $paintingId, int $excludeUserId = 0): array;

    public function findUserEmailById(int $userId): ?string;

    public function delete(int $paintingId): void;

    public function update(int $paintingId, string $title, string $description): void;
}
