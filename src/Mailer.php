<?php
declare(strict_types=1);

namespace Heirloom;

interface Mailer
{
    public function send(EmailMessage $message): bool;
}
