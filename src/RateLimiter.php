<?php
declare(strict_types=1);

namespace Heirloom;

class RateLimiter
{
    public function __construct(
        private Database $db,
        private int $maxAttempts = 5,
        private int $windowMinutes = 15,
    ) {}

    public function isAllowed(string $identifier): bool
    {
        return $this->getAttemptCount($identifier) < $this->maxAttempts;
    }

    public function record(string $identifier): void
    {
        $this->db->execute(
            'INSERT INTO login_attempts (identifier) VALUES (:id)',
            [':id' => $identifier]
        );
    }

    public function getAttemptCount(string $identifier): int
    {
        $cutoff = date('Y-m-d H:i:s', time() - ($this->windowMinutes * 60));
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM login_attempts WHERE identifier = :id AND attempted_at > :cutoff",
            [':id' => $identifier, ':cutoff' => $cutoff]
        );
    }

    public function remainingAttempts(string $identifier): int
    {
        return max(0, $this->maxAttempts - $this->getAttemptCount($identifier));
    }

    public function clear(string $identifier): void
    {
        $this->db->execute(
            'DELETE FROM login_attempts WHERE identifier = :id',
            [':id' => $identifier]
        );
    }
}
