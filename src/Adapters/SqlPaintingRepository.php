<?php
declare(strict_types=1);

namespace Heirloom\Adapters;

use Heirloom\Database;
use Heirloom\Ports\PaintingRepository;

final class SqlPaintingRepository implements PaintingRepository
{
    public function __construct(private Database $db) {}

    public function findById(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM paintings WHERE id = :id',
            [':id' => $id]
        );
    }

    public function findAvailableById(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM paintings WHERE id = :id AND awarded_to IS NULL',
            [':id' => $id]
        );
    }

    public function countInterests(int $paintingId): int
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM interests WHERE painting_id = :pid',
            [':pid' => $paintingId]
        );
    }

    public function hasInterest(int $paintingId, int $userId): bool
    {
        return (bool) $this->db->fetchOne(
            'SELECT 1 FROM interests WHERE painting_id = :pid AND user_id = :uid',
            [':pid' => $paintingId, ':uid' => $userId]
        );
    }

    public function addInterest(int $paintingId, int $userId, string $message): void
    {
        $this->db->execute(
            'INSERT INTO interests (painting_id, user_id, message) VALUES (:pid, :uid, :msg)',
            [':pid' => $paintingId, ':uid' => $userId, ':msg' => $message]
        );
    }

    public function removeInterest(int $paintingId, int $userId): void
    {
        $this->db->execute(
            'DELETE FROM interests WHERE painting_id = :pid AND user_id = :uid',
            [':pid' => $paintingId, ':uid' => $userId]
        );
    }

    public function award(int $paintingId, int $userId, int $awardedBy): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->execute(
            'UPDATE paintings SET awarded_to = :uid, awarded_at = :now WHERE id = :id',
            [':uid' => $userId, ':now' => $now, ':id' => $paintingId]
        );
        $this->db->execute(
            'INSERT INTO award_log (painting_id, user_id, awarded_by, action) VALUES (:pid, :uid, :aid, :action)',
            [':pid' => $paintingId, ':uid' => $userId, ':aid' => $awardedBy, ':action' => 'awarded']
        );
    }

    public function unassign(int $paintingId, int $adminId): void
    {
        $painting = $this->findById($paintingId);
        if ($painting && $painting['awarded_to']) {
            $this->db->execute(
                'INSERT INTO award_log (painting_id, user_id, awarded_by, action) VALUES (:pid, :uid, :aid, :action)',
                [':pid' => $paintingId, ':uid' => $painting['awarded_to'], ':aid' => $adminId, ':action' => 'unassigned']
            );
        }
        $this->db->execute(
            'UPDATE paintings SET awarded_to = NULL, awarded_at = NULL, tracking_number = NULL WHERE id = :id',
            [':id' => $paintingId]
        );
    }

    public function getInterestedEmails(int $paintingId, int $excludeUserId = 0): array
    {
        $sql = 'SELECT u.email FROM interests i
                JOIN users u ON u.id = i.user_id
                WHERE i.painting_id = :pid';
        $params = [':pid' => $paintingId];

        if ($excludeUserId > 0) {
            $sql .= ' AND i.user_id != :uid';
            $params[':uid'] = $excludeUserId;
        }

        $rows = $this->db->fetchAll($sql, $params);
        return array_map(fn(array $row) => $row['email'], $rows);
    }

    public function delete(int $paintingId): void
    {
        $this->db->execute('DELETE FROM paintings WHERE id = :id', [':id' => $paintingId]);
    }

    public function update(int $paintingId, string $title, string $description): void
    {
        $this->db->execute(
            'UPDATE paintings SET title = :title, description = :desc WHERE id = :id',
            [':title' => $title, ':desc' => $description, ':id' => $paintingId]
        );
    }
}
