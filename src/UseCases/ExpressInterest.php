<?php
declare(strict_types=1);

namespace Heirloom\UseCases;

use Heirloom\InputValidator;
use Heirloom\Ports\PaintingRepository;

class ExpressInterest
{
    public function __construct(private PaintingRepository $paintings) {}

    /**
     * Toggle interest on a painting for a user.
     *
     * @return array{toggled: 'on'|'off'}|array{error: string}|null
     *         Returns toggled state, a validation error, or null if painting not found/unavailable.
     */
    public function execute(int $paintingId, int $userId, string $message): ?array
    {
        $painting = $this->paintings->findAvailableById($paintingId);
        if (!$painting) {
            return null;
        }

        $lengthError = InputValidator::validateLength($message, InputValidator::MAX_INTEREST_MESSAGE, 'Interest message');
        if ($lengthError) {
            return ['error' => $lengthError];
        }

        $existing = $this->paintings->hasInterest($paintingId, $userId);

        if ($existing) {
            $this->paintings->removeInterest($paintingId, $userId);
            return ['toggled' => 'off'];
        }

        $this->paintings->addInterest($paintingId, $userId, $message);
        return ['toggled' => 'on'];
    }
}
