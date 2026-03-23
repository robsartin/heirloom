<?php
declare(strict_types=1);

namespace Heirloom;

/**
 * Immutable value object representing an outbound email with HTML and plain-text bodies.
 * If no plain-text body is provided, one is derived by stripping HTML tags.
 */
class EmailMessage
{
    public readonly string $textBody;

    public function __construct(
        public readonly string $to,
        public readonly string $subject,
        public readonly string $htmlBody,
        ?string $textBody = null,
    ) {
        $this->textBody = $textBody ?? strip_tags($this->htmlBody);
    }
}
