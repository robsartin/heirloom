<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Heirloom\Config;
use Heirloom\Csrf;
use Heirloom\Database;
use Heirloom\Router;
use Heirloom\Auth;
use Heirloom\LogMailer;
use Heirloom\SmtpMailer;
use Heirloom\SiteSettings;
use Heirloom\Template;
use Heirloom\Controllers\GalleryController;
use Heirloom\Controllers\AuthController;
use Heirloom\Controllers\AdminController;

Config::load(__DIR__ . '/../.env');

set_exception_handler(function (\Throwable $e): void {
    error_log($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    Template::render('error', [
        'code' => 500,
        'message' => 'An unexpected error occurred. Please try again later.',
        'noLayout' => true,
    ]);
});

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
if (!empty($_SERVER['HTTPS'])) {
    ini_set('session.cookie_secure', '1');
}
session_start();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
if (!empty($_SERVER['HTTPS'])) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

$db = Database::getInstance();
$settings = new SiteSettings($db);
Template::setGlobal('siteName', $settings->get('site_name', SiteSettings::DEFAULT_SITE_NAME));
Template::setGlobal('contactEmail', $settings->get('contact_email', ''));
$auth = new Auth($db);
$auth->setSettings($settings);
$mailHost = Config::get('MAIL_HOST');
if ($mailHost) {
    $auth->setMailer(new SmtpMailer(
        host: $mailHost,
        port: (int) Config::get('MAIL_PORT', '587'),
        username: Config::get('MAIL_USERNAME'),
        password: Config::get('MAIL_PASSWORD'),
        fromEmail: Config::get('MAIL_FROM'),
        fromName: Config::get('MAIL_FROM_NAME', $settings->get('site_name', SiteSettings::DEFAULT_SITE_NAME)),
    ));
} else {
    $auth->setMailer(new LogMailer());
}
$auth->checkSessionTimeout();
$auth->touchActivity();

$router = new Router();

$gallery = new GalleryController($db, $auth, $settings);
$authCtrl = new AuthController($db, $auth, $settings);
$admin = new AdminController($db, $auth, $settings);

// Public routes
$router->get('/sitemap.xml', [$gallery, 'sitemapXml']);
$router->get('/', [$gallery, 'index']);
$router->get('/painting/{id}', [$gallery, 'show']);
$router->post('/painting/{id}/interest', [$gallery, 'expressInterest']);
$router->get('/my-paintings', [$gallery, 'myPaintings']);

// Auth routes
$router->get('/login', [$authCtrl, 'loginForm']);
$router->post('/login', [$authCtrl, 'login']);
$router->get('/register', [$authCtrl, 'registerForm']);
$router->post('/register', [$authCtrl, 'register']);
$router->get('/auth/magic/{token}', [$authCtrl, 'magicLogin']);
$router->get('/auth/google', [$authCtrl, 'googleRedirect']);
$router->get('/auth/google/callback', [$authCtrl, 'googleCallback']);
$router->get('/logout', [$authCtrl, 'logout']);
$router->get('/set-password', [$authCtrl, 'setPasswordForm']);
$router->post('/set-password', [$authCtrl, 'setPassword']);
$router->get('/profile', [$authCtrl, 'profileForm']);
$router->post('/profile', [$authCtrl, 'updateProfile']);

// Admin routes
$router->get('/admin', [$admin, 'dashboard']);
$router->get('/admin/upload', [$admin, 'uploadForm']);
$router->post('/admin/upload', [$admin, 'upload']);
$router->get('/admin/painting/{id}', [$admin, 'managePainting']);
$router->post('/admin/painting/{id}/edit', [$admin, 'edit']);
$router->post('/admin/painting/{id}/award', [$admin, 'award']);
$router->post('/admin/painting/{id}/tracking', [$admin, 'updateTracking']);
$router->post('/admin/painting/{id}/delete', [$admin, 'delete']);
$router->get('/admin/export/paintings', [$admin, 'exportPaintings']);
$router->get('/admin/export/users', [$admin, 'exportUsers']);
$router->get('/admin/settings', [$admin, 'settingsForm']);
$router->post('/admin/settings', [$admin, 'updateSettings']);
$router->get('/admin/invite', [$admin, 'inviteForm']);
$router->post('/admin/invite', [$admin, 'invite']);

// CSRF validation for all POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_csrf_token'] ?? '';
    if (!Csrf::validate($token)) {
        http_response_code(403);
        Template::render('error', [
            'code' => 403,
            'message' => 'Invalid or missing CSRF token. Please go back and try again.',
            'noLayout' => true,
        ]);
        exit;
    }
}

$router->dispatch($_SERVER['REQUEST_METHOD'], parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
