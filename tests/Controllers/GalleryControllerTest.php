<?php
declare(strict_types=1);

namespace Heirloom\Tests\Controllers;

use Heirloom\Database;
use Heirloom\SiteSettings;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the database queries used by GalleryController.
 *
 * We cannot call the controller methods directly because they invoke
 * header()/exit() or Template::render(), so we exercise the exact SQL
 * the controller relies on against an in-memory SQLite database.
 */
class GalleryControllerTest extends TestCase
{
    use ControllerTestSchema;

    private SiteSettings $settings;

    protected function setUp(): void
    {
        $this->createDatabase();
        $this->settings = new SiteSettings($this->db);
    }

    // ---------------------------------------------------------------
    // Gallery index: total count of available paintings
    // ---------------------------------------------------------------

    public function testCountAvailablePaintingsExcludesAwarded(): void
    {
        $user = $this->insertUser('u@test.com');
        $this->insertPainting('Available 1');
        $this->insertPainting('Available 2');
        $this->insertPainting('Awarded', $user);

        $total = (int) $this->db->scalar(
            'SELECT COUNT(*) FROM paintings WHERE awarded_to IS NULL'
        );

        $this->assertSame(2, $total);
    }

    public function testCountAvailablePaintingsReturnsZeroWhenEmpty(): void
    {
        $total = (int) $this->db->scalar(
            'SELECT COUNT(*) FROM paintings WHERE awarded_to IS NULL'
        );

        $this->assertSame(0, $total);
    }

    // ---------------------------------------------------------------
    // Pagination math (mirrors GalleryController::index logic)
    // ---------------------------------------------------------------

    public function testPaginationMathFirstPage(): void
    {
        $perPage = 3;
        for ($i = 1; $i <= 7; $i++) {
            $this->insertPainting("Painting $i");
        }

        $total = (int) $this->db->scalar('SELECT COUNT(*) FROM paintings WHERE awarded_to IS NULL');
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min(1, $totalPages);
        $offset = ($page - 1) * $perPage;

        $this->assertSame(7, $total);
        $this->assertSame(3, $totalPages);
        $this->assertSame(1, $page);
        $this->assertSame(0, $offset);
    }

    public function testPaginationMathLastPage(): void
    {
        $perPage = 3;
        for ($i = 1; $i <= 7; $i++) {
            $this->insertPainting("Painting $i");
        }

        $total = (int) $this->db->scalar('SELECT COUNT(*) FROM paintings WHERE awarded_to IS NULL');
        $totalPages = max(1, (int) ceil($total / $perPage));
        $requestedPage = 10;
        $page = min($requestedPage, $totalPages);
        $offset = ($page - 1) * $perPage;

        $this->assertSame(3, $page);
        $this->assertSame(6, $offset);
    }

    public function testPaginationWithZeroPaintingsGivesOnePage(): void
    {
        $perPage = 12;
        $total = (int) $this->db->scalar('SELECT COUNT(*) FROM paintings WHERE awarded_to IS NULL');
        $totalPages = max(1, (int) ceil($total / $perPage));

        $this->assertSame(0, $total);
        $this->assertSame(1, $totalPages);
    }

    // ---------------------------------------------------------------
    // Gallery index: paintings query with interest count
    // ---------------------------------------------------------------

    public function testGalleryQueryReturnsInterestCount(): void
    {
        $u1 = $this->insertUser('a@test.com');
        $u2 = $this->insertUser('b@test.com');

        $p1 = $this->insertPainting('P1');
        $p2 = $this->insertPainting('P2');

        $this->insertInterest($p1, $u1);
        $this->insertInterest($p1, $u2);
        $this->insertInterest($p2, $u1);

        $paintings = $this->db->fetchAll(
            'SELECT p.*, (SELECT COUNT(*) FROM interests i WHERE i.painting_id = p.id) AS interest_count
             FROM paintings p
             WHERE p.awarded_to IS NULL
             ORDER BY p.created_at DESC
             LIMIT :limit OFFSET :offset',
            [':limit' => 10, ':offset' => 0]
        );

        $this->assertCount(2, $paintings);
        // Most recently created first
        $byTitle = [];
        foreach ($paintings as $p) {
            $byTitle[$p['title']] = (int) $p['interest_count'];
        }
        $this->assertSame(2, $byTitle['P1']);
        $this->assertSame(1, $byTitle['P2']);
    }

    public function testGalleryQueryExcludesAwardedPaintings(): void
    {
        $u = $this->insertUser('u@test.com');
        $this->insertPainting('Available');
        $this->insertPainting('Gone', $u);

        $paintings = $this->db->fetchAll(
            'SELECT p.*, (SELECT COUNT(*) FROM interests i WHERE i.painting_id = p.id) AS interest_count
             FROM paintings p
             WHERE p.awarded_to IS NULL
             ORDER BY p.created_at DESC
             LIMIT :limit OFFSET :offset',
            [':limit' => 10, ':offset' => 0]
        );

        $this->assertCount(1, $paintings);
        $this->assertSame('Available', $paintings[0]['title']);
    }

    public function testGalleryQueryRespectsLimitAndOffset(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->insertPainting("P$i", null, '', "2025-01-0$i 00:00:00");
        }

        $paintings = $this->db->fetchAll(
            'SELECT p.*, (SELECT COUNT(*) FROM interests i WHERE i.painting_id = p.id) AS interest_count
             FROM paintings p
             WHERE p.awarded_to IS NULL
             ORDER BY p.created_at DESC
             LIMIT :limit OFFSET :offset',
            [':limit' => 2, ':offset' => 2]
        );

        $this->assertCount(2, $paintings);
        // Ordered by created_at DESC: P5, P4, P3, P2, P1 — offset 2 => P3, P2
        $this->assertSame('P3', $paintings[0]['title']);
        $this->assertSame('P2', $paintings[1]['title']);
    }

    // ---------------------------------------------------------------
    // User interests lookup
    // ---------------------------------------------------------------

    public function testUserInterestsLookup(): void
    {
        $u1 = $this->insertUser('user@test.com');
        $u2 = $this->insertUser('other@test.com');

        $p1 = $this->insertPainting('A');
        $p2 = $this->insertPainting('B');
        $p3 = $this->insertPainting('C');

        $this->insertInterest($p1, $u1);
        $this->insertInterest($p3, $u1);
        $this->insertInterest($p2, $u2);

        $rows = $this->db->fetchAll(
            'SELECT painting_id FROM interests WHERE user_id = :uid',
            [':uid' => $u1]
        );

        $userInterests = [];
        foreach ($rows as $row) {
            $userInterests[$row['painting_id']] = true;
        }

        $this->assertArrayHasKey($p1, $userInterests);
        $this->assertArrayHasKey($p3, $userInterests);
        $this->assertArrayNotHasKey($p2, $userInterests);
    }

    // ---------------------------------------------------------------
    // Show painting: single painting queries
    // ---------------------------------------------------------------

    public function testShowPaintingQueryReturnsRow(): void
    {
        $id = $this->insertPainting('Test Painting', null, 'A lovely piece');

        $painting = $this->db->fetchOne(
            'SELECT * FROM paintings WHERE id = :id',
            [':id' => $id]
        );

        $this->assertNotNull($painting);
        $this->assertSame('Test Painting', $painting['title']);
        $this->assertSame('A lovely piece', $painting['description']);
    }

    public function testShowPaintingQueryReturnsNullForMissing(): void
    {
        $painting = $this->db->fetchOne(
            'SELECT * FROM paintings WHERE id = :id',
            [':id' => 999]
        );

        $this->assertNull($painting);
    }

    public function testShowPaintingInterestCheck(): void
    {
        $u = $this->insertUser('u@test.com');
        $p = $this->insertPainting('P');
        $this->insertInterest($p, $u);

        $hasInterest = (bool) $this->db->fetchOne(
            'SELECT 1 FROM interests WHERE painting_id = :pid AND user_id = :uid',
            [':pid' => $p, ':uid' => $u]
        );

        $this->assertTrue($hasInterest);
    }

    public function testShowPaintingInterestCheckReturnsFalseWhenNone(): void
    {
        $u = $this->insertUser('u@test.com');
        $p = $this->insertPainting('P');

        $hasInterest = (bool) $this->db->fetchOne(
            'SELECT 1 FROM interests WHERE painting_id = :pid AND user_id = :uid',
            [':pid' => $p, ':uid' => $u]
        );

        $this->assertFalse($hasInterest);
    }

    public function testShowPaintingInterestCountQuery(): void
    {
        $u1 = $this->insertUser('a@test.com');
        $u2 = $this->insertUser('b@test.com');
        $p = $this->insertPainting('P');

        $this->insertInterest($p, $u1);
        $this->insertInterest($p, $u2);

        $count = (int) $this->db->scalar(
            'SELECT COUNT(*) FROM interests WHERE painting_id = :pid',
            [':pid' => $p]
        );

        $this->assertSame(2, $count);
    }

    // ---------------------------------------------------------------
    // Express interest: toggle logic (insert / delete)
    // ---------------------------------------------------------------

    public function testExpressInterestInsertsNewInterest(): void
    {
        $u = $this->insertUser('u@test.com');
        $p = $this->insertPainting('P');

        // No existing interest — insert
        $existing = $this->db->fetchOne(
            'SELECT 1 FROM interests WHERE painting_id = :pid AND user_id = :uid',
            [':pid' => $p, ':uid' => $u]
        );
        $this->assertNull($existing);

        $this->db->execute(
            'INSERT INTO interests (painting_id, user_id, message) VALUES (:pid, :uid, :msg)',
            [':pid' => $p, ':uid' => $u, ':msg' => 'I love it']
        );

        $interest = $this->db->fetchOne(
            'SELECT * FROM interests WHERE painting_id = :pid AND user_id = :uid',
            [':pid' => $p, ':uid' => $u]
        );
        $this->assertNotNull($interest);
        $this->assertSame('I love it', $interest['message']);
    }

    public function testExpressInterestTogglesOffExisting(): void
    {
        $u = $this->insertUser('u@test.com');
        $p = $this->insertPainting('P');
        $this->insertInterest($p, $u);

        // Existing interest found — delete
        $this->db->execute(
            'DELETE FROM interests WHERE painting_id = :pid AND user_id = :uid',
            [':pid' => $p, ':uid' => $u]
        );

        $remaining = $this->db->fetchOne(
            'SELECT 1 FROM interests WHERE painting_id = :pid AND user_id = :uid',
            [':pid' => $p, ':uid' => $u]
        );
        $this->assertNull($remaining);
    }

    public function testExpressInterestOnlyAffectsAvailablePaintings(): void
    {
        $u = $this->insertUser('u@test.com');
        $pAwarded = $this->insertPainting('Awarded', $u);

        $painting = $this->db->fetchOne(
            'SELECT * FROM paintings WHERE id = :id AND awarded_to IS NULL',
            [':id' => $pAwarded]
        );

        $this->assertNull($painting, 'Awarded painting should not be returned for interest expression');
    }

    // ---------------------------------------------------------------
    // Gallery per-page setting
    // ---------------------------------------------------------------

    public function testGalleryPerPageSetting(): void
    {
        $this->insertSetting('gallery_per_page', '6');

        $perPage = $this->settings->getInt('gallery_per_page', 12);
        $this->assertSame(6, $perPage);
    }

    public function testGalleryPerPageSettingDefaultsTo12(): void
    {
        $perPage = $this->settings->getInt('gallery_per_page', 12);
        $this->assertSame(12, $perPage);
    }
}
