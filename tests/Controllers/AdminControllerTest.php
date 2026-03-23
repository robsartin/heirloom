<?php
declare(strict_types=1);

namespace Heirloom\Tests\Controllers;

use Heirloom\Database;
use Heirloom\SiteSettings;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the database queries used by AdminController.
 *
 * Exercises the exact SQL the controller relies on for dashboard queries,
 * filtering, sorting, painting management, awarding, and unassigning.
 */
class AdminControllerTest extends TestCase
{
    use ControllerTestSchema;

    private SiteSettings $settings;

    protected function setUp(): void
    {
        $this->createDatabase();
        $this->settings = new SiteSettings($this->db);
    }

    // ---------------------------------------------------------------
    // Dashboard: filters
    // ---------------------------------------------------------------

    public function testDashboardFilterAvailableExcludesAwarded(): void
    {
        $u = $this->insertUser('u@test.com');
        $this->insertPainting('Available');
        $this->insertPainting('Awarded', $u);

        $where = 'WHERE p.awarded_to IS NULL'; // default filter
        $total = (int) $this->db->scalar("SELECT COUNT(*) FROM paintings p $where");

        $this->assertSame(1, $total);
    }

    public function testDashboardFilterAwardedShowsOnlyAwarded(): void
    {
        $u = $this->insertUser('u@test.com');
        $this->insertPainting('Available');
        $this->insertPainting('Awarded', $u);

        $where = 'WHERE p.awarded_to IS NOT NULL';
        $total = (int) $this->db->scalar("SELECT COUNT(*) FROM paintings p $where");

        $this->assertSame(1, $total);
    }

    public function testDashboardFilterAllReturnsEverything(): void
    {
        $u = $this->insertUser('u@test.com');
        $this->insertPainting('Available');
        $this->insertPainting('Awarded', $u);

        $where = '';
        $total = (int) $this->db->scalar("SELECT COUNT(*) FROM paintings p $where");

        $this->assertSame(2, $total);
    }

    public function testDashboardFilterWantedShowsOnlyAvailableWithInterests(): void
    {
        $u = $this->insertUser('u@test.com');
        $pWanted = $this->insertPainting('Wanted');
        $this->insertPainting('Ignored');
        $this->insertPainting('Awarded', $u);

        $this->insertInterest($pWanted, $u);

        $where = 'WHERE p.awarded_to IS NULL AND EXISTS (SELECT 1 FROM interests i2 WHERE i2.painting_id = p.id)';
        $total = (int) $this->db->scalar("SELECT COUNT(*) FROM paintings p $where");

        $this->assertSame(1, $total);
    }

    // ---------------------------------------------------------------
    // Dashboard: sort direction whitelist
    // ---------------------------------------------------------------

    public function testSortDirectionWhitelist(): void
    {
        // Mirrors the controller logic
        $dir = strtolower('desc') === 'asc' ? 'ASC' : 'DESC';
        $this->assertSame('DESC', $dir);

        $dir = strtolower('asc') === 'asc' ? 'ASC' : 'DESC';
        $this->assertSame('ASC', $dir);

        $dir = strtolower('DROP TABLE') === 'asc' ? 'ASC' : 'DESC';
        $this->assertSame('DESC', $dir);
    }

    // ---------------------------------------------------------------
    // Dashboard: allowed sort columns
    // ---------------------------------------------------------------

    public function testAllowedSortColumnsMapping(): void
    {
        $allowedSorts = [
            'title' => 'p.title',
            'interest_count' => 'interest_count',
            'last_interest_at' => 'last_interest_at',
            'created_at' => 'p.created_at',
        ];

        $this->assertSame('p.title', $allowedSorts['title'] ?? 'p.created_at');
        $this->assertSame('p.created_at', $allowedSorts['bogus'] ?? 'p.created_at');
    }

    // ---------------------------------------------------------------
    // Dashboard: full query with interest_count and last_interest_at
    // ---------------------------------------------------------------

    public function testDashboardQueryReturnsInterestCountAndLastInterest(): void
    {
        $admin = $this->insertUser('admin@test.com', 'Admin', true);
        $u1 = $this->insertUser('a@test.com', 'A');
        $u2 = $this->insertUser('b@test.com', 'B');

        $p = $this->insertPainting('Painting');
        $this->insertInterest($p, $u1, '', '2025-01-01 10:00:00');
        $this->insertInterest($p, $u2, '', '2025-06-15 14:00:00');

        $where = 'WHERE p.awarded_to IS NULL';
        $paintings = $this->db->fetchAll(
            "SELECT p.*,
                (SELECT COUNT(*) FROM interests i WHERE i.painting_id = p.id) AS interest_count,
                (SELECT MAX(i3.created_at) FROM interests i3 WHERE i3.painting_id = p.id) AS last_interest_at,
                u.name AS awarded_name, u.email AS awarded_email
             FROM paintings p
             LEFT JOIN users u ON u.id = p.awarded_to
             $where
             ORDER BY p.created_at DESC
             LIMIT :limit OFFSET :offset",
            [':limit' => 20, ':offset' => 0]
        );

        $this->assertCount(1, $paintings);
        $this->assertSame(2, (int) $paintings[0]['interest_count']);
        $this->assertSame('2025-06-15 14:00:00', $paintings[0]['last_interest_at']);
        $this->assertNull($paintings[0]['awarded_name']);
    }

    public function testDashboardQueryShowsAwardedUserInfo(): void
    {
        $recipient = $this->insertUser('winner@test.com', 'Alice');
        $this->insertPainting('Gift', $recipient);

        $where = 'WHERE p.awarded_to IS NOT NULL';
        $paintings = $this->db->fetchAll(
            "SELECT p.*,
                (SELECT COUNT(*) FROM interests i WHERE i.painting_id = p.id) AS interest_count,
                (SELECT MAX(i3.created_at) FROM interests i3 WHERE i3.painting_id = p.id) AS last_interest_at,
                u.name AS awarded_name, u.email AS awarded_email
             FROM paintings p
             LEFT JOIN users u ON u.id = p.awarded_to
             $where
             ORDER BY p.created_at DESC
             LIMIT :limit OFFSET :offset",
            [':limit' => 20, ':offset' => 0]
        );

        $this->assertCount(1, $paintings);
        $this->assertSame('Alice', $paintings[0]['awarded_name']);
        $this->assertSame('winner@test.com', $paintings[0]['awarded_email']);
    }

    public function testDashboardQuerySortByTitle(): void
    {
        $this->insertPainting('Zebra');
        $this->insertPainting('Apple');
        $this->insertPainting('Mango');

        $paintings = $this->db->fetchAll(
            "SELECT p.*,
                (SELECT COUNT(*) FROM interests i WHERE i.painting_id = p.id) AS interest_count,
                (SELECT MAX(i3.created_at) FROM interests i3 WHERE i3.painting_id = p.id) AS last_interest_at,
                u.name AS awarded_name, u.email AS awarded_email
             FROM paintings p
             LEFT JOIN users u ON u.id = p.awarded_to
             WHERE p.awarded_to IS NULL
             ORDER BY p.title ASC
             LIMIT :limit OFFSET :offset",
            [':limit' => 20, ':offset' => 0]
        );

        $this->assertSame('Apple', $paintings[0]['title']);
        $this->assertSame('Mango', $paintings[1]['title']);
        $this->assertSame('Zebra', $paintings[2]['title']);
    }

    public function testDashboardQuerySortByInterestCount(): void
    {
        $u1 = $this->insertUser('a@test.com');
        $u2 = $this->insertUser('b@test.com');

        $pLow = $this->insertPainting('Low');
        $pHigh = $this->insertPainting('High');

        $this->insertInterest($pHigh, $u1);
        $this->insertInterest($pHigh, $u2);
        $this->insertInterest($pLow, $u1);

        $paintings = $this->db->fetchAll(
            "SELECT p.*,
                (SELECT COUNT(*) FROM interests i WHERE i.painting_id = p.id) AS interest_count,
                (SELECT MAX(i3.created_at) FROM interests i3 WHERE i3.painting_id = p.id) AS last_interest_at,
                u.name AS awarded_name, u.email AS awarded_email
             FROM paintings p
             LEFT JOIN users u ON u.id = p.awarded_to
             WHERE p.awarded_to IS NULL
             ORDER BY interest_count DESC
             LIMIT :limit OFFSET :offset",
            [':limit' => 20, ':offset' => 0]
        );

        $this->assertSame('High', $paintings[0]['title']);
        $this->assertSame('Low', $paintings[1]['title']);
    }

    // ---------------------------------------------------------------
    // Manage painting: interests with user info
    // ---------------------------------------------------------------

    public function testManagePaintingInterestsQuery(): void
    {
        $u1 = $this->insertUser('alice@test.com', 'Alice');
        $u2 = $this->insertUser('bob@test.com', 'Bob');

        $p = $this->insertPainting('P');
        $this->insertInterest($p, $u1, 'Please!', '2025-01-01 00:00:00');
        $this->insertInterest($p, $u2, 'Me too!', '2025-01-02 00:00:00');

        $interests = $this->db->fetchAll(
            'SELECT i.*, u.name, u.email, u.shipping_address FROM interests i
             JOIN users u ON u.id = i.user_id
             WHERE i.painting_id = :pid
             ORDER BY i.created_at ASC',
            [':pid' => $p]
        );

        $this->assertCount(2, $interests);
        $this->assertSame('Alice', $interests[0]['name']);
        $this->assertSame('alice@test.com', $interests[0]['email']);
        $this->assertSame('Please!', $interests[0]['message']);
        $this->assertSame('Bob', $interests[1]['name']);
    }

    public function testManagePaintingAwardedUserLookup(): void
    {
        $u = $this->insertUser('winner@test.com', 'Winner');
        $this->db->execute(
            'UPDATE users SET shipping_address = :a WHERE id = :id',
            [':a' => '123 Main St', ':id' => $u]
        );

        $p = $this->insertPainting('Gift', $u);
        $painting = $this->db->fetchOne('SELECT * FROM paintings WHERE id = :id', [':id' => $p]);

        $awardedUser = $this->db->fetchOne(
            'SELECT id, name, email, shipping_address FROM users WHERE id = :id',
            [':id' => $painting['awarded_to']]
        );

        $this->assertNotNull($awardedUser);
        $this->assertSame('Winner', $awardedUser['name']);
        $this->assertSame('123 Main St', $awardedUser['shipping_address']);
    }

    // ---------------------------------------------------------------
    // Award painting: update + log
    // ---------------------------------------------------------------

    public function testAwardPaintingUpdatesRow(): void
    {
        $admin = $this->insertUser('admin@test.com', 'Admin', true);
        $user = $this->insertUser('user@test.com', 'User');
        $p = $this->insertPainting('Prize');

        $this->db->execute(
            'UPDATE paintings SET awarded_to = :uid, awarded_at = datetime(\'now\') WHERE id = :id',
            [':uid' => $user, ':id' => $p]
        );

        $painting = $this->db->fetchOne('SELECT * FROM paintings WHERE id = :id', [':id' => $p]);
        $this->assertSame($user, (int) $painting['awarded_to']);
        $this->assertNotNull($painting['awarded_at']);
    }

    public function testAwardPaintingCreatesLogEntry(): void
    {
        $admin = $this->insertUser('admin@test.com', 'Admin', true);
        $user = $this->insertUser('user@test.com', 'User');
        $p = $this->insertPainting('Prize');

        $this->db->execute(
            'INSERT INTO award_log (painting_id, user_id, awarded_by, action) VALUES (:pid, :uid, :aid, :action)',
            [':pid' => $p, ':uid' => $user, ':aid' => $admin, ':action' => 'awarded']
        );

        $log = $this->db->fetchAll('SELECT * FROM award_log WHERE painting_id = :pid', [':pid' => $p]);
        $this->assertCount(1, $log);
        $this->assertSame('awarded', $log[0]['action']);
        $this->assertSame($user, (int) $log[0]['user_id']);
        $this->assertSame($admin, (int) $log[0]['awarded_by']);
    }

    // ---------------------------------------------------------------
    // Unassign painting: clear + log
    // ---------------------------------------------------------------

    public function testUnassignPaintingClearsFields(): void
    {
        $user = $this->insertUser('user@test.com');
        $p = $this->insertPainting('Prize', $user);

        $this->db->execute(
            'UPDATE paintings SET awarded_to = NULL, awarded_at = NULL, tracking_number = NULL WHERE id = :id',
            [':id' => $p]
        );

        $painting = $this->db->fetchOne('SELECT * FROM paintings WHERE id = :id', [':id' => $p]);
        $this->assertNull($painting['awarded_to']);
        $this->assertNull($painting['awarded_at']);
        $this->assertNull($painting['tracking_number']);
    }

    public function testUnassignPaintingCreatesLogEntry(): void
    {
        $admin = $this->insertUser('admin@test.com', 'Admin', true);
        $user = $this->insertUser('user@test.com', 'User');
        $p = $this->insertPainting('Prize', $user);

        $painting = $this->db->fetchOne('SELECT awarded_to FROM paintings WHERE id = :id', [':id' => $p]);

        $this->db->execute(
            'INSERT INTO award_log (painting_id, user_id, awarded_by, action) VALUES (:pid, :uid, :aid, :action)',
            [':pid' => $p, ':uid' => $painting['awarded_to'], ':aid' => $admin, ':action' => 'unassigned']
        );

        $log = $this->db->fetchAll('SELECT * FROM award_log WHERE painting_id = :pid', [':pid' => $p]);
        $this->assertCount(1, $log);
        $this->assertSame('unassigned', $log[0]['action']);
    }

    // ---------------------------------------------------------------
    // Award log query (with user joins)
    // ---------------------------------------------------------------

    public function testAwardLogQueryWithJoins(): void
    {
        $admin = $this->insertUser('admin@test.com', 'Admin', true);
        $user = $this->insertUser('user@test.com', 'User');
        $p = $this->insertPainting('Art');

        $this->db->execute(
            'INSERT INTO award_log (painting_id, user_id, awarded_by, action, created_at)
             VALUES (:pid, :uid, :aid, :action, :at)',
            [':pid' => $p, ':uid' => $user, ':aid' => $admin, ':action' => 'awarded', ':at' => '2025-01-01 12:00:00']
        );
        $this->db->execute(
            'INSERT INTO award_log (painting_id, user_id, awarded_by, action, created_at)
             VALUES (:pid, :uid, :aid, :action, :at)',
            [':pid' => $p, ':uid' => $user, ':aid' => $admin, ':action' => 'unassigned', ':at' => '2025-02-01 12:00:00']
        );

        $awardLog = $this->db->fetchAll(
            'SELECT al.*, u.name AS user_name, u.email AS user_email, adm.name AS admin_name
             FROM award_log al
             JOIN users u ON u.id = al.user_id
             JOIN users adm ON adm.id = al.awarded_by
             WHERE al.painting_id = :pid
             ORDER BY al.created_at DESC',
            [':pid' => $p]
        );

        $this->assertCount(2, $awardLog);
        $this->assertSame('unassigned', $awardLog[0]['action']); // most recent first
        $this->assertSame('awarded', $awardLog[1]['action']);
        $this->assertSame('User', $awardLog[0]['user_name']);
        $this->assertSame('Admin', $awardLog[0]['admin_name']);
    }

    // ---------------------------------------------------------------
    // Edit painting
    // ---------------------------------------------------------------

    public function testEditPaintingUpdatesFields(): void
    {
        $p = $this->insertPainting('Old Title', null, 'Old desc');

        $this->db->execute(
            'UPDATE paintings SET title = :title, description = :desc WHERE id = :id',
            [':title' => 'New Title', ':desc' => 'New desc', ':id' => $p]
        );

        $painting = $this->db->fetchOne('SELECT * FROM paintings WHERE id = :id', [':id' => $p]);
        $this->assertSame('New Title', $painting['title']);
        $this->assertSame('New desc', $painting['description']);
    }

    // ---------------------------------------------------------------
    // Update tracking number
    // ---------------------------------------------------------------

    public function testUpdateTrackingNumber(): void
    {
        $u = $this->insertUser('u@test.com');
        $p = $this->insertPainting('Prize', $u);

        $this->db->execute(
            'UPDATE paintings SET tracking_number = :tn WHERE id = :id',
            [':tn' => 'TRACK123', ':id' => $p]
        );

        $painting = $this->db->fetchOne('SELECT * FROM paintings WHERE id = :id', [':id' => $p]);
        $this->assertSame('TRACK123', $painting['tracking_number']);
    }

    public function testUpdateTrackingNumberToNull(): void
    {
        $u = $this->insertUser('u@test.com');
        $p = $this->insertPainting('Prize', $u);

        $this->db->execute(
            'UPDATE paintings SET tracking_number = :tn WHERE id = :id',
            [':tn' => 'TRACK123', ':id' => $p]
        );

        $this->db->execute(
            'UPDATE paintings SET tracking_number = :tn WHERE id = :id',
            [':tn' => null, ':id' => $p]
        );

        $painting = $this->db->fetchOne('SELECT * FROM paintings WHERE id = :id', [':id' => $p]);
        $this->assertNull($painting['tracking_number']);
    }

    // ---------------------------------------------------------------
    // Delete painting
    // ---------------------------------------------------------------

    public function testDeletePaintingRemovesRow(): void
    {
        $p = $this->insertPainting('Doomed');

        $painting = $this->db->fetchOne('SELECT filename FROM paintings WHERE id = :id', [':id' => $p]);
        $this->assertNotNull($painting);

        $this->db->execute('DELETE FROM paintings WHERE id = :id', [':id' => $p]);

        $deleted = $this->db->fetchOne('SELECT * FROM paintings WHERE id = :id', [':id' => $p]);
        $this->assertNull($deleted);
    }

    // ---------------------------------------------------------------
    // Admin per-page setting
    // ---------------------------------------------------------------

    public function testAdminPerPageSetting(): void
    {
        $this->insertSetting('admin_per_page', '50');
        $perPage = $this->settings->getInt('admin_per_page', 20);
        $this->assertSame(50, $perPage);
    }

    public function testAdminPerPageDefaultsTo20(): void
    {
        $perPage = $this->settings->getInt('admin_per_page', 20);
        $this->assertSame(20, $perPage);
    }

    // ---------------------------------------------------------------
    // Upload: insert painting row
    // ---------------------------------------------------------------

    public function testUploadInsertsPaintingRow(): void
    {
        $this->db->execute(
            'INSERT INTO paintings (title, description, filename, original_filename) VALUES (:title, :desc, :file, :orig)',
            [':title' => 'New Art', ':desc' => 'A painting', ':file' => 'abc123.jpg', ':orig' => 'my_painting.jpg']
        );

        $painting = $this->db->fetchOne('SELECT * FROM paintings WHERE title = :t', [':t' => 'New Art']);
        $this->assertNotNull($painting);
        $this->assertSame('abc123.jpg', $painting['filename']);
        $this->assertSame('my_painting.jpg', $painting['original_filename']);
        $this->assertNull($painting['awarded_to']);
    }

    // ---------------------------------------------------------------
    // Upload: title generation for multi-file uploads
    // ---------------------------------------------------------------

    public function testMultiFileUploadTitleWithPrefix(): void
    {
        $title = 'Series';
        $baseName = 'painting_01';
        $paintingTitle = $title !== '' ? $title . ' - ' . $baseName : $baseName;
        $this->assertSame('Series - painting_01', $paintingTitle);
    }

    public function testMultiFileUploadTitleWithoutPrefix(): void
    {
        $title = '';
        $baseName = 'painting_01';
        $paintingTitle = $title !== '' ? $title . ' - ' . $baseName : $baseName;
        $this->assertSame('painting_01', $paintingTitle);
    }
}
