<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Heirloom\Config;

Config::load(__DIR__ . '/.env');

$host = Config::get('DB_HOST', '127.0.0.1');
$port = Config::get('DB_PORT', '3306');
$name = Config::get('DB_NAME', 'heirloom');
$user = Config::get('DB_USER', 'root');
$pass = Config::get('DB_PASS', '');

// Connect without database to create it if needed
$pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `$name`");

$pdo->exec("
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL DEFAULT '',
    password_hash VARCHAR(255) NULL,
    is_admin TINYINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS magic_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    used TINYINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_email (email)
) ENGINE=InnoDB;
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS paintings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL DEFAULT '',
    awarded_to INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (awarded_to) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_awarded (awarded_to)
) ENGINE=InnoDB;
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS interests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    painting_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_painting_user (painting_id, user_id),
    FOREIGN KEY (painting_id) REFERENCES paintings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
");

// Seed admin user
$adminEmail = 'rob.sartin@gmail.com';
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
$stmt->execute([':email' => $adminEmail]);
$existing = $stmt->fetch();

if (!$existing) {
    $hash = password_hash('foo', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (email, name, password_hash, is_admin) VALUES (:email, :name, :hash, 1)');
    $stmt->execute([':email' => $adminEmail, ':name' => 'Rob Sartin', ':hash' => $hash]);
    echo "Admin user created: $adminEmail\n";
} else {
    echo "Admin user already exists.\n";
}

// Seed test user
$testEmail = 'f@f.com';
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
$stmt->execute([':email' => $testEmail]);
if (!$stmt->fetch()) {
    $hash = password_hash('f', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (email, name, password_hash) VALUES (:email, :name, :hash)');
    $stmt->execute([':email' => $testEmail, ':name' => 'Test User', ':hash' => $hash]);
    echo "Test user created: $testEmail (password: f)\n";
} else {
    echo "Test user already exists.\n";
}

echo "Migration complete.\n";
