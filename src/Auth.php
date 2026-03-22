<?php
declare(strict_types=1);

namespace Heirloom;

use PHPMailer\PHPMailer\PHPMailer;

class Auth
{
    public function __construct(private Database $db) {}

    public function user(): ?array
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        return $this->db->fetchOne(
            'SELECT * FROM users WHERE id = :id',
            [':id' => $_SESSION['user_id']]
        );
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
        session_regenerate_id(true);
    }

    public function logout(): void
    {
        session_destroy();
    }

    public function attemptPasswordLogin(string $email, string $password): ?array
    {
        $user = $this->db->fetchOne(
            'SELECT * FROM users WHERE email = :email',
            [':email' => strtolower(trim($email))]
        );
        if (!$user || !$user['password_hash']) {
            return null;
        }
        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }
        return $user;
    }

    public function findOrCreateUserByEmail(string $email, string $name = ''): array
    {
        $email = strtolower(trim($email));
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
            [':email' => strtolower(trim($email)), ':token' => $token]
        );
        return $token;
    }

    public function consumeMagicLink(string $token): ?string
    {
        $link = $this->db->fetchOne(
            "SELECT * FROM magic_links WHERE token = :token AND used = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
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

    public function sendMagicLink(string $email, string $token): bool
    {
        $url = Config::get('APP_URL') . '/auth/magic/' . $token;

        $mailHost = Config::get('MAIL_HOST');
        if (!$mailHost) {
            // Fallback: just log for local dev
            error_log("Magic link for $email: $url");
            return true;
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $mailHost;
            $mail->SMTPAuth = true;
            $mail->Username = Config::get('MAIL_USERNAME');
            $mail->Password = Config::get('MAIL_PASSWORD');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int) Config::get('MAIL_PORT', '587');

            $mail->setFrom(Config::get('MAIL_FROM'), Config::get('MAIL_FROM_NAME', 'Heirloom'));
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Your login link - Heirloom Gallery';
            $mail->Body = "
                <h2>Welcome to Heirloom Gallery</h2>
                <p>Click the link below to log in. This link expires in 1 hour and can only be used once.</p>
                <p><a href=\"$url\">Log in to Heirloom Gallery</a></p>
                <p>If you didn't request this, you can safely ignore this email.</p>
            ";
            $mail->AltBody = "Log in to Heirloom Gallery: $url";
            $mail->send();
            return true;
        } catch (\Exception $e) {
            error_log("Mail error: " . $e->getMessage());
            return false;
        }
    }
}
