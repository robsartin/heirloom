<?php
declare(strict_types=1);

namespace Heirloom;

class Auth
{
    private ?array $cachedUser = null;
    private bool $userFetched = false;
    private ?SiteSettings $settings = null;
    private ?Mailer $mailer = null;

    public function __construct(private Database $db) {}

    public function setSettings(SiteSettings $settings): void
    {
        $this->settings = $settings;
    }

    public function setMailer(Mailer $mailer): void
    {
        $this->mailer = $mailer;
    }

    private function magicLinkExpiryMinutes(): int
    {
        return $this->settings ? $this->settings->getInt('magic_link_expiry_minutes', 60) : 60;
    }

    private function siteName(): string
    {
        return $this->settings ? $this->settings->get('site_name', SiteSettings::DEFAULT_SITE_NAME) : SiteSettings::DEFAULT_SITE_NAME;
    }

    public function user(): ?array
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        if (!$this->userFetched) {
            $this->cachedUser = $this->db->fetchOne(
                'SELECT * FROM users WHERE id = :id',
                [':id' => $_SESSION['user_id']]
            );
            $this->userFetched = true;
        }
        return $this->cachedUser;
    }

    public function userId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public function isAdmin(): bool
    {
        $user = $this->user();
        return $user && (bool) $user['is_admin'];
    }

    public function requireLogin(): void
    {
        if (!$this->isLoggedIn()) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            header('Location: /login');
            exit;
        }
    }

    public function requireAdmin(): void
    {
        $this->requireLogin();
        if (!$this->isAdmin()) {
            http_response_code(403);
            echo '<h1>403 Forbidden</h1>';
            exit;
        }
    }

    public function loginUser(int $userId): void
    {
        $_SESSION['user_id'] = $userId;
        $this->cachedUser = null;
        $this->userFetched = false;
        session_regenerate_id(true);
    }

    public function consumeRedirect(): string
    {
        $redirect = $_SESSION['redirect_after_login'] ?? '/';
        unset($_SESSION['redirect_after_login']);
        if (!str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) {
            return '/';
        }
        return $redirect;
    }

    public function logout(): void
    {
        session_destroy();
    }

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    public function attemptPasswordLogin(string $email, string $password): ?array
    {
        $user = $this->db->fetchOne(
            'SELECT * FROM users WHERE email = :email',
            [':email' => self::normalizeEmail($email)]
        );
        if (!$user || !$user['password_hash']) {
            return null;
        }
        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }
        return $user;
    }

    public function findUserByEmail(string $email): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM users WHERE email = :email',
            [':email' => self::normalizeEmail($email)]
        );
    }

    public function findOrCreateUserByEmail(string $email, string $name = ''): array
    {
        $email = self::normalizeEmail($email);
        $user = $this->db->fetchOne(
            'SELECT * FROM users WHERE email = :email',
            [':email' => $email]
        );
        if ($user) {
            return $user;
        }
        $this->db->execute(
            'INSERT INTO users (email, name) VALUES (:email, :name)',
            [':email' => $email, ':name' => $name]
        );
        return $this->db->fetchOne(
            'SELECT * FROM users WHERE id = :id',
            [':id' => $this->db->lastInsertId()]
        );
    }

    public function createMagicLink(string $email): string
    {
        $token = bin2hex(random_bytes(32));
        $this->db->execute(
            'INSERT INTO magic_links (email, token) VALUES (:email, :token)',
            [':email' => self::normalizeEmail($email), ':token' => $token]
        );
        return $token;
    }

    public function consumeMagicLink(string $token): ?string
    {
        $minutes = (int) $this->magicLinkExpiryMinutes();
        $link = $this->db->fetchOne(
            "SELECT * FROM magic_links WHERE token = :token AND used = 0 AND created_at > DATE_SUB(NOW(), INTERVAL $minutes MINUTE)",
            [':token' => $token]
        );
        if (!$link) {
            return null;
        }
        $this->db->execute(
            'UPDATE magic_links SET used = 1 WHERE id = :id',
            [':id' => $link['id']]
        );
        return $link['email'];
    }

    public function buildMagicLinkEmail(string $email, string $token): EmailMessage
    {
        $url = Config::get('APP_URL') . '/auth/magic/' . $token;
        $name = $this->siteName();
        $expiry = $this->magicLinkExpiryMinutes();

        $subject = "Your login link - $name";
        $htmlBody = <<<HTML
<h2>Welcome to $name</h2>
<p>Click the link below to log in. This link expires in $expiry minutes and can only be used once.</p>
<p><a href="$url">Log in to $name</a></p>
<p>If you didn't request this, you can safely ignore this email.</p>
HTML;
        $textBody = "Log in to $name: $url (expires in $expiry minutes, single use)";

        return new EmailMessage($email, $subject, $htmlBody, $textBody);
    }

    public function sendMagicLink(string $email, string $token): bool
    {
        $message = $this->buildMagicLinkEmail($email, $token);
        $mailer = $this->mailer ?? new LogMailer();

        try {
            return $mailer->send($message);
        } catch (\Exception $e) {
            error_log("Mail error: " . $e->getMessage());
            return false;
        }
    }

    public function buildAwardEmail(string $recipientEmail, string $paintingTitle): EmailMessage
    {
        $name = $this->siteName();

        $subject = "A painting has been awarded to you - $name";
        $htmlBody = <<<HTML
<h2>Congratulations!</h2>
<p>You have been awarded the painting <strong>$paintingTitle</strong> from $name.</p>
<p>We will be in touch with shipping details soon.</p>
HTML;
        $textBody = "Congratulations! You have been awarded the painting \"$paintingTitle\" from $name. We will be in touch with shipping details soon.";

        return new EmailMessage($recipientEmail, $subject, $htmlBody, $textBody);
    }

    public function sendAwardNotification(string $email, string $paintingTitle): bool
    {
        $message = $this->buildAwardEmail($email, $paintingTitle);
        $mailer = $this->mailer ?? new LogMailer();

        try {
            return $mailer->send($message);
        } catch (\Exception $e) {
            error_log("Mail error: " . $e->getMessage());
            return false;
        }
    }
}
