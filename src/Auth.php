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

    private function sessionTimeoutMinutes(): int
    {
        return $this->settings ? $this->settings->getInt('session_timeout_minutes', 120) : 120;
    }

    /**
     * Check whether the current session has expired due to inactivity.
     * Returns true if the session is expired, false otherwise.
     */
    public function isSessionExpired(): bool
    {
        if (!$this->isLoggedIn()) {
            return false;
        }
        if (!isset($_SESSION['last_activity'])) {
            return false;
        }
        $timeout = $this->sessionTimeoutMinutes() * 60;
        return (time() - (int) $_SESSION['last_activity']) > $timeout;
    }

    /**
     * If the session has timed out, log out and redirect to /login.
     */
    public function checkSessionTimeout(): void
    {
        if ($this->isSessionExpired()) {
            $this->logout();
            session_start();
            $_SESSION['flash_message'] = 'Your session has expired due to inactivity. Please log in again.';
            header('Location: /login');
            exit;
        }
    }

    /**
     * Record current time as last activity for session timeout tracking.
     */
    public function touchActivity(): void
    {
        $_SESSION['last_activity'] = time();
    }

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function fetchUserByEmail(string $email): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM users WHERE email = :email',
            [':email' => self::normalizeEmail($email)]
        );
    }

    public function attemptPasswordLogin(string $email, string $password): ?array
    {
        $user = $this->fetchUserByEmail($email);
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
        return $this->fetchUserByEmail($email);
    }

    public function findOrCreateUserByEmail(string $email, string $name = ''): array
    {
        $email = self::normalizeEmail($email);
        $user = $this->fetchUserByEmail($email);
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

    private function sendEmail(EmailMessage $message): bool
    {
        $mailer = $this->mailer ?? new LogMailer();
        try {
            return $mailer->send($message);
        } catch (\Exception $e) {
            error_log("Mail error: " . $e->getMessage());
            return false;
        }
    }

    public function sendMagicLink(string $email, string $token): bool
    {
        return $this->sendEmail($this->buildMagicLinkEmail($email, $token));
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
        return $this->sendEmail($this->buildAwardEmail($email, $paintingTitle));
    }

    public function buildLoserEmail(string $email, string $paintingTitle): EmailMessage
    {
        $name = $this->siteName();

        $subject = "Update on a painting you wanted - $name";
        $htmlBody = <<<HTML
<h2>Painting Update</h2>
<p>We wanted to let you know that the painting <strong>$paintingTitle</strong> from $name has been awarded to another recipient.</p>
<p>Keep an eye on the gallery for more paintings you might love!</p>
HTML;
        $textBody = "We wanted to let you know that the painting \"$paintingTitle\" from $name has been awarded to another recipient. Keep an eye on the gallery for more paintings you might love!";

        return new EmailMessage($email, $subject, $htmlBody, $textBody);
    }

    /**
     * @param string[] $loserEmails
     */
    public function sendLoserNotifications(array $loserEmails, string $paintingTitle): void
    {
        foreach ($loserEmails as $email) {
            $this->sendEmail($this->buildLoserEmail($email, $paintingTitle));
        }
    }
}
