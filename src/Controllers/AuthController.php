<?php
declare(strict_types=1);

namespace Heirloom\Controllers;

use Heirloom\Auth;
use Heirloom\Config;
use Heirloom\Database;
use Heirloom\RateLimiter;
use Heirloom\SiteSettings;
use Heirloom\Template;
use League\OAuth2\Client\Provider\Google;

/**
 * Handles all public-facing authentication routes: login (password and magic-link),
 * registration, Google OAuth, password management, and user profile updates.
 */
class AuthController
{
    private RateLimiter $rateLimiter;

    public function __construct(private Database $db, private Auth $auth, private SiteSettings $settings)
    {
        $this->rateLimiter = new RateLimiter($db);
    }

    public function loginForm(): void
    {
        if ($this->auth->isLoggedIn()) {
            header('Location: /');
            exit;
        }
        Template::render('login', [
            'error' => $_SESSION['auth_error'] ?? null,
            'success' => $_SESSION['auth_success'] ?? null,
            'auth' => $this->auth,
        ]);
        unset($_SESSION['auth_error'], $_SESSION['auth_success']);
    }

    public function login(): void
    {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$email) {
            $_SESSION['auth_error'] = 'Email is required.';
            header('Location: /login');
            exit;
        }

        $identifier = Auth::normalizeEmail($email);
        if (!$this->rateLimiter->isAllowed($identifier)) {
            $remaining = $this->rateLimiter->remainingAttempts($identifier);
            $_SESSION['auth_error'] = 'Too many login attempts. Please try again in 15 minutes.';
            header('Location: /login');
            exit;
        }

        if ($password !== '') {
            $user = $this->auth->attemptPasswordLogin($email, $password);
            if ($user) {
                $this->rateLimiter->clear($identifier);
                $this->auth->loginUser((int) $user['id']);
                header('Location: ' . $this->auth->consumeRedirect());
                exit;
            }
            $this->rateLimiter->record($identifier);
            $_SESSION['auth_error'] = 'Invalid email or password.';
            header('Location: /login');
            exit;
        }

        // If the user explicitly requested "forgot password", set a session flag
        // so that after magic link login they are redirected to /set-password.
        if (!empty($_POST['forgot_password'])) {
            $_SESSION['forgot_password'] = true;
        }

        $this->rateLimiter->record($identifier);
        $token = $this->auth->createMagicLink($email);
        $sent = $this->auth->sendMagicLink($email, $token);

        if ($sent) {
            $_SESSION['auth_success'] = 'Check your email for a login link! (Expires in 1 hour)';
        } else {
            $_SESSION['auth_error'] = 'Failed to send login link. Please try again.';
        }
        header('Location: /login');
        exit;
    }

    public function registerForm(): void
    {
        if ($this->auth->isLoggedIn()) {
            header('Location: /');
            exit;
        }
        if (!$this->settings->getBool('registration_open', true)) {
            Template::render('register', [
                'error' => 'Registration is currently closed.',
                'auth' => $this->auth,
                'closed' => true,
            ]);
            return;
        }
        Template::render('register', [
            'error' => $_SESSION['auth_error'] ?? null,
            'auth' => $this->auth,
            'closed' => false,
        ]);
        unset($_SESSION['auth_error']);
    }

    public function register(): void
    {
        if (!$this->settings->getBool('registration_open', true)) {
            $_SESSION['auth_error'] = 'Registration is currently closed.';
            header('Location: /login');
            exit;
        }
        $email = Auth::normalizeEmail($_POST['email'] ?? '');
        $name = trim($_POST['name'] ?? '');

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['auth_error'] = 'Valid email is required.';
            header('Location: /register');
            exit;
        }

        if (!$this->rateLimiter->isAllowed($email)) {
            $_SESSION['auth_error'] = 'Too many attempts. Please try again in 15 minutes.';
            header('Location: /register');
            exit;
        }

        if (!$name) {
            $_SESSION['auth_error'] = 'Name is required.';
            header('Location: /register');
            exit;
        }

        $this->auth->findOrCreateUserByEmail($email, $name);
        $this->rateLimiter->record($email);

        $token = $this->auth->createMagicLink($email);
        $sent = $this->auth->sendMagicLink($email, $token);

        if ($sent) {
            $_SESSION['auth_success'] = 'Check your email for a login link!';
        } else {
            $_SESSION['auth_error'] = 'Account created but failed to send login link.';
        }
        header('Location: /login');
        exit;
    }

    public function magicLogin(string $token): void
    {
        $email = $this->auth->consumeMagicLink($token);
        if (!$email) {
            $_SESSION['auth_error'] = 'Invalid or expired login link.';
            header('Location: /login');
            exit;
        }

        $user = $this->auth->findOrCreateUserByEmail($email);
        $this->auth->loginUser((int) $user['id']);

        // If user has no password, or explicitly requested password reset, prompt to set one
        $forgotPassword = !empty($_SESSION['forgot_password']);
        unset($_SESSION['forgot_password']);

        if (!$user['password_hash'] || $forgotPassword) {
            header('Location: /set-password');
            exit;
        }

        header('Location: ' . $this->auth->consumeRedirect());
        exit;
    }

    public function setPasswordForm(): void
    {
        $this->auth->requireLogin();
        Template::render('set-password', [
            'auth' => $this->auth,
            'error' => $_SESSION['auth_error'] ?? null,
            'success' => $_SESSION['auth_success'] ?? null,
        ]);
        unset($_SESSION['auth_error'], $_SESSION['auth_success']);
    }

    /**
     * Validate a password against complexity requirements.
     *
     * @return string|null Error message, or null if the password is acceptable.
     */
    public static function validatePassword(string $password): ?string
    {
        if (strlen($password) < 12) {
            return 'Password must be at least 12 characters.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return 'Password must contain at least one uppercase letter.';
        }
        if (!preg_match('/[a-z]/', $password)) {
            return 'Password must contain at least one lowercase letter.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            return 'Password must contain at least one number.';
        }
        return null;
    }

    public function setPassword(): void
    {
        $this->auth->requireLogin();

        $identifier = 'password_change_' . $this->auth->userId();
        if (!$this->rateLimiter->isAllowed($identifier)) {
            $_SESSION['auth_error'] = 'Too many attempts. Please try again in 15 minutes.';
            header('Location: /set-password');
            exit;
        }
        $this->rateLimiter->record($identifier);

        $password = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';

        $validationError = self::validatePassword($password);
        if ($validationError !== null) {
            $_SESSION['auth_error'] = $validationError;
            header('Location: /set-password');
            exit;
        }
        if ($password !== $confirm) {
            $_SESSION['auth_error'] = 'Passwords do not match.';
            header('Location: /set-password');
            exit;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $this->db->execute(
            'UPDATE users SET password_hash = :hash WHERE id = :id',
            [':hash' => $hash, ':id' => $this->auth->userId()]
        );

        $_SESSION['auth_success'] = 'Password set successfully!';
        header('Location: /');
        exit;
    }

    public function profileForm(): void
    {
        $this->auth->requireLogin();
        $user = $this->auth->user();
        $awardedPaintings = $this->db->fetchAll(
            'SELECT p.title, p.filename, p.awarded_at, p.tracking_number FROM paintings p WHERE p.awarded_to = :uid ORDER BY p.awarded_at DESC',
            [':uid' => $this->auth->userId()]
        );
        Template::render('profile', [
            'auth' => $this->auth,
            'user' => $user,
            'awardedPaintings' => $awardedPaintings,
            'success' => $_SESSION['auth_success'] ?? null,
            'error' => $_SESSION['auth_error'] ?? null,
        ]);
        unset($_SESSION['auth_success'], $_SESSION['auth_error']);
    }

    public function updateProfile(): void
    {
        $this->auth->requireLogin();
        $address = trim($_POST['shipping_address'] ?? '');

        $this->db->execute(
            'UPDATE users SET shipping_address = :addr WHERE id = :id',
            [':addr' => $address !== '' ? $address : null, ':id' => $this->auth->userId()]
        );

        $_SESSION['auth_success'] = 'Shipping address updated.';
        header('Location: /profile');
        exit;
    }

    public function googleRedirect(): void
    {
        $provider = $this->getGoogleProvider();
        $authUrl = $provider->getAuthorizationUrl(['scope' => ['email', 'profile']]);
        $_SESSION['oauth2state'] = $provider->getState();
        header('Location: ' . $authUrl);
        exit;
    }

    public function googleCallback(): void
    {
        $provider = $this->getGoogleProvider();

        if (empty($_GET['state']) || ($_GET['state'] !== ($_SESSION['oauth2state'] ?? ''))) {
            unset($_SESSION['oauth2state']);
            $_SESSION['auth_error'] = 'Invalid OAuth state.';
            header('Location: /login');
            exit;
        }

        try {
            $token = $provider->getAccessToken('authorization_code', ['code' => $_GET['code']]);
            $googleUser = $provider->getResourceOwner($token);
            $email = strtolower($googleUser->getEmail());

            $user = $this->auth->findUserByEmail($email);
            if (!$user) {
                $_SESSION['auth_error'] = 'No account found for that Google email. Please register first.';
                header('Location: /register');
                exit;
            }

            $this->auth->loginUser((int) $user['id']);
            header('Location: ' . $this->auth->consumeRedirect());
            exit;
        } catch (\Exception $e) {
            error_log('OAuth error: ' . $e->getMessage());
            $_SESSION['auth_error'] = 'Google login failed. Please try again.';
            header('Location: /login');
            exit;
        }
    }

    public function logout(): void
    {
        $this->auth->logout();
        header('Location: /');
        exit;
    }

    private function getGoogleProvider(): Google
    {
        return new Google([
            'clientId' => Config::get('GOOGLE_CLIENT_ID'),
            'clientSecret' => Config::get('GOOGLE_CLIENT_SECRET'),
            'redirectUri' => Config::get('GOOGLE_REDIRECT_URI'),
        ]);
    }
}
