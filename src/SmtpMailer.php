<?php
declare(strict_types=1);

namespace Heirloom;

use PHPMailer\PHPMailer\PHPMailer;

class SmtpMailer implements Mailer
{
    public function __construct(
        private string $host,
        private int $port,
        private string $username,
        private string $password,
        private string $fromEmail,
        private string $fromName = '',
    ) {}

    public function send(EmailMessage $message): bool
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $this->host;
        $mail->SMTPAuth = true;
        $mail->Username = $this->username;
        $mail->Password = $this->password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $this->port;

        $mail->setFrom($this->fromEmail, $this->fromName);
        $mail->addAddress($message->to);
        $mail->isHTML(true);
        $mail->Subject = $message->subject;
        $mail->Body = $message->htmlBody;
        $mail->AltBody = $message->textBody;

        $mail->send();
        return true;
    }
}
