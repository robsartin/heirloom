<?php
declare(strict_types=1);

namespace Heirloom;

class LogMailer implements Mailer
{
    /** @var EmailMessage[] */
    private array $messages = [];

    public function send(EmailMessage $message): bool
    {
        $this->messages[] = $message;
        error_log("Email to {$message->to}: {$message->subject}");
        error_log("Body: {$message->textBody}");
        return true;
    }

    public function getLastMessage(): ?EmailMessage
    {
        return $this->messages ? end($this->messages) : null;
    }

    /** @return EmailMessage[] */
    public function getAllMessages(): array
    {
        return $this->messages;
    }
}
