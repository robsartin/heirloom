<?php
declare(strict_types=1);

namespace Heirloom;

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
