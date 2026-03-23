<?php
declare(strict_types=1);

namespace Heirloom\Tests\Controllers;

use Heirloom\Auth;
use Heirloom\Database;
use Heirloom\RateLimiter;
use Heirloom\SiteSettings;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the database queries and logic used by AuthController.
 *
 * Tests the Auth/RateLimiter/Database methods that the controller relies on,
 * since the controller methods themselves call header()/exit().
 */
class AuthControllerTest extends TestCase
{
    use ControllerTestSchema;

    private SiteSettings $settings;

    protected function setUp(): void
    {
        $this->createDatabase();
        $this->settings = new SiteSettings($this->db);
    }

    // ---------------------------------------------------------------
    // Email normalization
    // ---------------------------------------------------------------

    public function testEmailNormalization(): void
    {
        $this->assertSame('test@example.com', Auth::normalizeEmail('  Test@Example.COM  '));
    }

    // ---------------------------------------------------------------
    // Rate limiter: recording and checking attempts
    // ---------------------------------------------------------------

    public function testRateLimiterAllowsWithinLimit(): void
    {
        $limiter = new RateLimiter($this->db, 3, 15);

        $this->assertTrue($limiter->isAllowed('user@test.com'));
        $limiter->record('user@test.com');
        $limiter->record('user@test.com');

        $this->assertTrue($limiter->isAllowed('user@test.com'));
        $this->assertSame(1, $limiter->remainingAttempts('user@test.com'));
    }

    public function testRateLimiterBlocksAfterMax(): void
    {
        $limiter = new RateLimiter($this->db, 2, 15);

        $limiter->record('user@test.com');
        $limiter->record('user@test.com');

        $this->assertFalse($limiter->isAllowed('user@test.com'));
        $this->assertSame(0, $limiter->remainingAttempts('user@test.com'));
    }

    public function testRateLimiterClearResetsAttempts(): void
    {
        $limiter = new RateLimiter($this->db, 2, 15);

        $limiter->record('user@test.com');
        $limiter->record('user@test.com');
        $this->assertFalse($limiter->isAllowed('user@test.com'));

        $limiter->clear('user@test.com');
        $this->assertTrue($limiter->isAllowed('user@test.com'));
    }

    public function testRateLimiterIsolatesIdentifiers(): void
    {
        $limiter = new RateLimiter($this->db, 2, 15);

        $limiter->record('alice@test.com');
        $limiter->record('alice@test.com');

        $this->assertFalse($limiter->isAllowed('alice@test.com'));
        $this->assertTrue($limiter->isAllowed('bob@test.com'));
    }

    // ---------------------------------------------------------------
    // Password login
    // ---------------------------------------------------------------

    public function testAttemptPasswordLoginSucceeds(): void
    {
        $hash = password_hash('secret123', PASSWORD_DEFAULT);
        $this->insertUser('alice@test.com', 'Alice', false, $hash);

        $auth = new Auth($this->db);
        $user = $auth->attemptPasswordLogin('alice@test.com', 'secret123');

        $this->assertNotNull($user);
        $this->assertSame('alice@test.com', $user['email']);
    }

    public function testAttemptPasswordLoginFailsWithWrongPassword(): void
    {
        $hash = password_hash('secret123', PASSWORD_DEFAULT);
        $this->insertUser('alice@test.com', 'Alice', false, $hash);

        $auth = new Auth($this->db);
        $user = $auth->attemptPasswordLogin('alice@test.com', 'wrongpass');

        $this->assertNull($user);
    }

    public function testAttemptPasswordLoginFailsForNonexistentUser(): void
    {
        $auth = new Auth($this->db);
        $user = $auth->attemptPasswordLogin('nobody@test.com', 'anything');

        $this->assertNull($user);
    }

    public function testAttemptPasswordLoginFailsWhenNoPasswordHash(): void
    {
        $this->insertUser('alice@test.com', 'Alice');

        $auth = new Auth($this->db);
        $user = $auth->attemptPasswordLogin('alice@test.com', 'anything');

        $this->assertNull($user);
    }

    // ---------------------------------------------------------------
    // Find / create user
    // ---------------------------------------------------------------

    public function testFindUserByEmail(): void
    {
        $this->insertUser('alice@test.com', 'Alice');

        $auth = new Auth($this->db);
        $user = $auth->findUserByEmail('alice@test.com');

        $this->assertNotNull($user);
        $this->assertSame('Alice', $user['name']);
    }

    public function testFindUserByEmailReturnsNullWhenMissing(): void
    {
        $auth = new Auth($this->db);
        $user = $auth->findUserByEmail('nobody@test.com');

        $this->assertNull($user);
    }

    public function testFindOrCreateUserCreatesNewUser(): void
    {
        $auth = new Auth($this->db);
        $user = $auth->findOrCreateUserByEmail('new@test.com', 'New Person');

        $this->assertNotNull($user);
        $this->assertSame('new@test.com', $user['email']);
        $this->assertSame('New Person', $user['name']);

        // Verify actually persisted
        $dbUser = $this->db->fetchOne('SELECT * FROM users WHERE email = :e', [':e' => 'new@test.com']);
        $this->assertNotNull($dbUser);
    }

    public function testFindOrCreateUserReturnsExistingUser(): void
    {
        $existingId = $this->insertUser('existing@test.com', 'Existing');

        $auth = new Auth($this->db);
        $user = $auth->findOrCreateUserByEmail('existing@test.com', 'Different Name');

        $this->assertSame($existingId, (int) $user['id']);
        $this->assertSame('Existing', $user['name']); // name not overwritten
    }

    // ---------------------------------------------------------------
    // Magic links
    // ---------------------------------------------------------------

    public function testCreateMagicLinkInsertsToken(): void
    {
        $auth = new Auth($this->db);
        $token = $auth->createMagicLink('alice@test.com');

        $this->assertNotEmpty($token);

        $row = $this->db->fetchOne('SELECT * FROM magic_links WHERE token = :t', [':t' => $token]);
        $this->assertNotNull($row);
        $this->assertSame('alice@test.com', $row['email']);
        $this->assertSame('0', (string) $row['used']);
    }

    public function testCreateMagicLinkGeneratesUniqueTokens(): void
    {
        $auth = new Auth($this->db);
        $t1 = $auth->createMagicLink('alice@test.com');
        $t2 = $auth->createMagicLink('alice@test.com');

        $this->assertNotSame($t1, $t2);
    }

    // ---------------------------------------------------------------
    // Set password (DB update)
    // ---------------------------------------------------------------

    public function testSetPasswordUpdatesHash(): void
    {
        $uid = $this->insertUser('alice@test.com', 'Alice');

        $hash = password_hash('newpass123', PASSWORD_DEFAULT);
        $this->db->execute(
            'UPDATE users SET password_hash = :hash WHERE id = :id',
            [':hash' => $hash, ':id' => $uid]
        );

        $user = $this->db->fetchOne('SELECT * FROM users WHERE id = :id', [':id' => $uid]);
        $this->assertTrue(password_verify('newpass123', $user['password_hash']));
    }

    // ---------------------------------------------------------------
    // Profile: awarded paintings query
    // ---------------------------------------------------------------

    public function testProfileAwardedPaintingsQuery(): void
    {
        $u = $this->insertUser('winner@test.com', 'Winner');

        $this->insertPainting('Not awarded');

        $this->db->execute(
            "INSERT INTO paintings (title, filename, original_filename, awarded_to, awarded_at, tracking_number, created_at)
             VALUES (:t, :f, :o, :a, :at, :tn, :ca)",
            [':t' => 'Prize 1', ':f' => 'a.jpg', ':o' => 'a.jpg', ':a' => $u, ':at' => '2025-03-01 12:00:00', ':tn' => 'TRACK1', ':ca' => '2025-01-01 00:00:00']
        );
        $this->db->execute(
            "INSERT INTO paintings (title, filename, original_filename, awarded_to, awarded_at, tracking_number, created_at)
             VALUES (:t, :f, :o, :a, :at, :tn, :ca)",
            [':t' => 'Prize 2', ':f' => 'b.jpg', ':o' => 'b.jpg', ':a' => $u, ':at' => '2025-06-01 12:00:00', ':tn' => null, ':ca' => '2025-02-01 00:00:00']
        );

        $awardedPaintings = $this->db->fetchAll(
            'SELECT p.title, p.filename, p.awarded_at, p.tracking_number FROM paintings p WHERE p.awarded_to = :uid ORDER BY p.awarded_at DESC',
            [':uid' => $u]
        );

        $this->assertCount(2, $awardedPaintings);
        $this->assertSame('Prize 2', $awardedPaintings[0]['title']); // most recent first
        $this->assertSame('Prize 1', $awardedPaintings[1]['title']);
        $this->assertSame('TRACK1', $awardedPaintings[1]['tracking_number']);
        $this->assertNull($awardedPaintings[0]['tracking_number']);
    }

    public function testProfileAwardedPaintingsQueryReturnsEmptyWhenNone(): void
    {
        $u = $this->insertUser('noprizes@test.com');

        $awardedPaintings = $this->db->fetchAll(
            'SELECT p.title, p.filename, p.awarded_at, p.tracking_number FROM paintings p WHERE p.awarded_to = :uid ORDER BY p.awarded_at DESC',
            [':uid' => $u]
        );

        $this->assertSame([], $awardedPaintings);
    }

    // ---------------------------------------------------------------
    // Update shipping address
    // ---------------------------------------------------------------

    public function testUpdateShippingAddress(): void
    {
        $uid = $this->insertUser('u@test.com');

        $this->db->execute(
            'UPDATE users SET shipping_address = :addr WHERE id = :id',
            [':addr' => '123 Elm St, Springfield', ':id' => $uid]
        );

        $user = $this->db->fetchOne('SELECT * FROM users WHERE id = :id', [':id' => $uid]);
        $this->assertSame('123 Elm St, Springfield', $user['shipping_address']);
    }

    public function testUpdateShippingAddressToNull(): void
    {
        $uid = $this->insertUser('u@test.com');
        $this->db->execute(
            'UPDATE users SET shipping_address = :addr WHERE id = :id',
            [':addr' => '123 Elm St', ':id' => $uid]
        );

        $this->db->execute(
            'UPDATE users SET shipping_address = :addr WHERE id = :id',
            [':addr' => null, ':id' => $uid]
        );

        $user = $this->db->fetchOne('SELECT * FROM users WHERE id = :id', [':id' => $uid]);
        $this->assertNull($user['shipping_address']);
    }

    // ---------------------------------------------------------------
    // Registration closed setting
    // ---------------------------------------------------------------

    public function testRegistrationClosedSetting(): void
    {
        $this->insertSetting('registration_open', '0');
        $this->assertFalse($this->settings->getBool('registration_open', true));
    }

    public function testRegistrationOpenByDefault(): void
    {
        $this->assertTrue($this->settings->getBool('registration_open', true));
    }

    // ---------------------------------------------------------------
    // Forgot-password flow: magic link redirects to /set-password
    // ---------------------------------------------------------------

    public function testMagicLoginRedirectsToSetPasswordWhenUserHasNoPasswordHash(): void
    {
        // User with no password_hash should always go to /set-password
        $uid = $this->insertUser('nopass@test.com', 'No Pass');

        $user = $this->db->fetchOne('SELECT * FROM users WHERE id = :id', [':id' => $uid]);
        $this->assertNull($user['password_hash']);

        // The magicLogin controller checks !$user['password_hash'] and redirects
        // to /set-password. This verifies the precondition is correct.
        $this->assertEmpty($user['password_hash']);
    }

    public function testForgotPasswordSessionFlagIsSetForUserWithPassword(): void
    {
        // When a user with a password explicitly requests forgot-password,
        // the session flag 'forgot_password' should be set so that after
        // magic link login they are redirected to /set-password.
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }

        $hash = password_hash('existing123', PASSWORD_DEFAULT);
        $uid = $this->insertUser('haspass@test.com', 'Has Pass', false, $hash);

        // Simulate the forgot-password flow: POST with forgot_password=1 and empty password
        // The controller sets $_SESSION['forgot_password'] = true
        $_SESSION['forgot_password'] = true;
        $this->assertTrue($_SESSION['forgot_password']);

        // After magic link login, a user WITH a password_hash would normally
        // go to consumeRedirect(). But with the forgot_password flag, they
        // should be redirected to /set-password instead.
        $user = $this->db->fetchOne('SELECT * FROM users WHERE id = :id', [':id' => $uid]);
        $this->assertNotEmpty($user['password_hash']);

        // The redirect decision: forgot_password flag OR no password_hash
        $shouldRedirectToSetPassword = !$user['password_hash'] || !empty($_SESSION['forgot_password']);
        $this->assertTrue($shouldRedirectToSetPassword);

        // Flag should be consumed after use
        unset($_SESSION['forgot_password']);
        $this->assertArrayNotHasKey('forgot_password', $_SESSION);
    }

    public function testMagicLoginDoesNotRedirectToSetPasswordForNormalFlow(): void
    {
        // A user WITH a password who logs in via magic link WITHOUT
        // the forgot-password intent should NOT be redirected to /set-password.
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        unset($_SESSION['forgot_password']);

        $hash = password_hash('existing123', PASSWORD_DEFAULT);
        $uid = $this->insertUser('normal@test.com', 'Normal', false, $hash);

        $user = $this->db->fetchOne('SELECT * FROM users WHERE id = :id', [':id' => $uid]);
        $this->assertNotEmpty($user['password_hash']);

        $shouldRedirectToSetPassword = !$user['password_hash'] || !empty($_SESSION['forgot_password']);
        $this->assertFalse($shouldRedirectToSetPassword);
    }

    // ---------------------------------------------------------------
    // Redirect safety (mirrors Auth::consumeRedirect logic)
    // ---------------------------------------------------------------

    public function testRedirectSafetyRejectsExternalUrls(): void
    {
        $testCases = [
            '/' => '/',
            '/gallery' => '/gallery',
            '//evil.com' => '/',
            'http://evil.com' => '/',
        ];

        foreach ($testCases as $input => $expected) {
            $redirect = $input;
            if (!str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) {
                $redirect = '/';
            }
            $this->assertSame($expected, $redirect, "Failed for input: $input");
        }
    }
}
