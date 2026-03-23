<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Heirloom\Config;
use Heirloom\Database;
use Heirloom\Router;
use Heirloom\Auth;
use Heirloom\SiteSettings;
use Heirloom\Controllers\GalleryController;
use Heirloom\Controllers\AuthController;
use Heirloom\Controllers\AdminController;

Config::load(__DIR__ . '/../.env');

session_start();

$db = Database::getInstance();
$settings = new SiteSettings($db);
$auth = new Auth($db);
$auth->setSettings($settings);
$router = new Router();

$gallery = new GalleryController($db, $auth, $settings);
$authCtrl = new AuthController($db, $auth, $settings);
$admin = new AdminController($db, $auth, $settings);

// Public routes
$router->get('/', [$gallery, 'index']);
$router->get('/painting/{id}', [$gallery, 'show']);
$router->post('/painting/{id}/interest', [$gallery, 'expressInterest']);

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
$router->get('/admin/settings', [$admin, 'settingsForm']);
$router->post('/admin/settings', [$admin, 'updateSettings']);

$router->dispatch($_SERVER['REQUEST_METHOD'], parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
