<?php
declare(strict_types=1);

namespace Heirloom;

/**
 * Contract for sending outbound email messages.
 * Implementations include SmtpMailer (production) and LogMailer (dev/test).
 */
interface Mailer
{
    public function send(EmailMessage $message): bool;
}
