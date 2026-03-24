<?php
declare(strict_types=1);

namespace Heirloom\Controllers;

use Heirloom\Auth;
use Heirloom\Config;
use Heirloom\Database;
use Heirloom\InputValidator;
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
    use FlashRedirect;

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
            'error' => $_SESSION[Flash::AUTH_ERROR] ?? null,
            'success' => $_SESSION[Flash::AUTH_SUCCESS] ?? null,
            'auth' => $this->auth,
        ]);
        unset($_SESSION[Flash::AUTH_ERROR], $_SESSION[Flash::AUTH_SUCCESS]);
    }

    public function login(): void
    {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$email) {
            $this->redirectWithFlash(Routes::LOGIN, Flash::AUTH_ERROR, 'Email is required.');
        }

        $identifier = Auth::normalizeEmail($email);
        if (!$this->rateLimiter->isAllowed($identifier)) {
            $remaining = $this->rateLimiter->remainingAttempts($identifier);
            $this->redirectWithFlash(Routes::LOGIN, Flash::AUTH_ERROR, 'Too many login attempts. Please try again in 15 minutes.');
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
            $this->redirectWithFlash(Routes::LOGIN, Flash::AUTH_ERROR, 'Invalid email or password.');
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
            $this->redirectWithFlash(Routes::LOGIN, Flash::AUTH_SUCCESS, 'Check your email for a login link! (Expires in 1 hour)');
        } else {
            $this->redirectWithFlash(Routes::LOGIN, Flash::AUTH_ERROR, 'Failed to send login link. Please try again.');
        }
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
            'error' => $_SESSION[Flash::AUTH_ERROR] ?? null,
            'auth' => $this->auth,
            'closed' => false,
        ]);
        unset($_SESSION[Flash::AUTH_ERROR]);
    }

    public function register(): void
    {
        if (!$this->settings->getBool('registration_open', true)) {
            $this->redirectWithFlash(Routes::LOGIN, Flash::AUTH_ERROR, 'Registration is currently closed.');
        }
        $email = Auth::normalizeEmail($_POST['email'] ?? '');
        $name = trim($_POST['name'] ?? '');

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->redirectWithFlash(Routes::REGISTER, Flash::AUTH_ERROR, Flash::MSG_VALID_EMAIL_REQUIRED);
        }

        if (!$this->rateLimiter->isAllowed($email)) {
            $this->redirectWithFlash(Routes::REGISTER, Flash::AUTH_ERROR, Flash::MSG_TOO_MANY_ATTEMPTS);
        }

        if (!$name) {
            $this->redirectWithFlash(Routes::REGISTER, Flash::AUTH_ERROR, 'Name is required.');
        }

        $this->auth->findOrCreateUserByEmail($email, $name);
        $this->rateLimiter->record($email);

        $token = $this->auth->createMagicLink($email);
        $sent = $this->auth->sendMagicLink($email, $token);

        if ($sent) {
            $this->redirectWithFlash(Routes::LOGIN, Flash::AUTH_SUCCESS, 'Check your email for a login link!');
        } else {
            $this->redirectWithFlash(Routes::LOGIN, Flash::AUTH_ERROR, 'Account created but failed to send login link.');
        }
    }

    public function magicLogin(string $token): void
    {
        $email = $this->auth->consumeMagicLink($token);
        if (!$email) {
            $this->redirectWithFlash(Routes::LOGIN, Flash::AUTH_ERROR, 'Invalid or expired login link.');
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
            'error' => $_SESSION[Flash::AUTH_ERROR] ?? null,
            'success' => $_SESSION[Flash::AUTH_SUCCESS] ?? null,
        ]);
        unset($_SESSION[Flash::AUTH_ERROR], $_SESSION[Flash::AUTH_SUCCESS]);
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
            $this->redirectWithFlash(Routes::SET_PASSWORD, Flash::AUTH_ERROR, Flash::MSG_TOO_MANY_ATTEMPTS);
        }
        $this->rateLimiter->record($identifier);

        $password = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';

        $validationError = self::validatePassword($password);
        if ($validationError !== null) {
            $this->redirectWithFlash(Routes::SET_PASSWORD, Flash::AUTH_ERROR, $validationError);
        }
        if ($password !== $confirm) {
            $this->redirectWithFlash(Routes::SET_PASSWORD, Flash::AUTH_ERROR, 'Passwords do not match.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $this->db->execute(
            'UPDATE users SET password_hash = :hash WHERE id = :id',
            [':hash' => $hash, ':id' => $this->auth->userId()]
        );

        $this->redirectWithFlash('/', Flash::AUTH_SUCCESS, 'Password set successfully!');
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
            'success' => $_SESSION[Flash::AUTH_SUCCESS] ?? null,
            'error' => $_SESSION[Flash::AUTH_ERROR] ?? null,
        ]);
        unset($_SESSION[Flash::AUTH_SUCCESS], $_SESSION[Flash::AUTH_ERROR]);
    }

    public function updateProfile(): void
    {
        $this->auth->requireLogin();
        $address = trim($_POST['shipping_address'] ?? '');

        $lengthError = InputValidator::validateLength($address, 500, 'Shipping address');
        if ($lengthError) {
            $this->redirectWithFlash(Routes::PROFILE, Flash::AUTH_ERROR, $lengthError);
        }

        $this->db->execute(
            'UPDATE users SET shipping_address = :addr WHERE id = :id',
            [':addr' => $address !== '' ? $address : null, ':id' => $this->auth->userId()]
        );

        $this->redirectWithFlash(Routes::PROFILE, Flash::AUTH_SUCCESS, 'Shipping address updated.');
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
            $this->redirectWithFlash(Routes::LOGIN, Flash::AUTH_ERROR, 'Invalid OAuth state.');
        }

        try {
            $token = $provider->getAccessToken('authorization_code', ['code' => $_GET['code']]);
            $googleUser = $provider->getResourceOwner($token);
            $email = strtolower($googleUser->getEmail());

            $user = $this->auth->findUserByEmail($email);
            if (!$user) {
                $this->redirectWithFlash(Routes::REGISTER, Flash::AUTH_ERROR, 'No account found for that Google email. Please register first.');
            }

            $this->auth->loginUser((int) $user['id']);
            header('Location: ' . $this->auth->consumeRedirect());
            exit;
        } catch (\Exception $e) {
            error_log('OAuth error: ' . $e->getMessage());
            $this->redirectWithFlash(Routes::LOGIN, Flash::AUTH_ERROR, 'Google login failed. Please try again.');
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
