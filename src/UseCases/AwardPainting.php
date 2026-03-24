<?php
declare(strict_types=1);

namespace Heirloom\UseCases;

use Heirloom\Ports\PaintingRepository;

class AwardPainting
{
    public function __construct(private PaintingRepository $paintings) {}

    /**
     * Award a painting to a user.
     *
     * @return array{winner_email: string|null, loser_emails: string[], painting_title: string}
     */
    public function award(int $paintingId, int $userId, int $adminId): array
    {
        $this->paintings->award($paintingId, $userId, $adminId);

        $painting = $this->paintings->findById($paintingId);
        $winnerEmail = $this->paintings->findUserEmailById($userId);
        $loserEmails = $this->paintings->getInterestedEmails($paintingId, $userId);

        return [
            'winner_email' => $winnerEmail,
            'loser_emails' => $loserEmails,
            'painting_title' => $painting['title'] ?? '',
        ];
    }

    public function unassign(int $paintingId, int $adminId): void
    {
        $this->paintings->unassign($paintingId, $adminId);
    }
}
