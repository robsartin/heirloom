<?php
declare(strict_types=1);

namespace Heirloom;

/**
 * Mailer implementation that writes messages to error_log and stores them in memory.
 * Intended for development/testing so emails can be inspected without an SMTP server.
 */
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
