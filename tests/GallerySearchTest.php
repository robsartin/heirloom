<?php
declare(strict_types=1);

namespace Heirloom\Tests;

use Heirloom\Auth;
use Heirloom\Controllers\GalleryController;
use Heirloom\Database;
use Heirloom\SiteSettings;
use PHPUnit\Framework\TestCase;

/**
 * Tests for gallery search and sorting (issue #40).
 *
 * Because Template::render() requires the filesystem and outputs HTML,
 * we test the controller indirectly by verifying that the Database
 * receives the correct SQL queries (LIKE clauses, ORDER BY, LIMIT/OFFSET).
 */
class GallerySearchTest extends TestCase
{
    private Database $db;
    private Auth $auth;
    private SiteSettings $settings;

    protected function setUp(): void
    {
        $this->db = $this->createMock(Database::class);
        $this->auth = $this->createMock(Auth::class);
        $this->settings = $this->createMock(SiteSettings::class);

        $this->settings->method('getInt')->willReturn(12);
        $this->auth->method('isLoggedIn')->willReturn(true);
        $this->auth->method('userId')->willReturn(1);
    }

    // ---------------------------------------------------------------
    // Search by title LIKE
    // ---------------------------------------------------------------
    public function testSearchByTitleLike(): void
    {
        $_GET = ['q' => 'sunset', 'page' => '1'];

        // The main query should contain LIKE for title OR description
        $this->db->expects($this->atLeastOnce())
            ->method('fetchAll')
            ->willReturn([]);

        $this->db->expects($this->atLeastOnce())
            ->method('scalar')
            ->with(
                $this->callback(function (string $sql): bool {
                    return str_contains($sql, 'LIKE') &&
                           str_contains($sql, 'title') &&
                           str_contains($sql, 'description');
                }),
                $this->callback(function (array $params): bool {
                    return isset($params[':search']) && $params[':search'] === '%sunset%';
                })
            )
            ->willReturn(0);

        $controller = new GalleryController($this->db, $this->auth, $this->settings);

        ob_start();
        $controller->index();
        ob_end_clean();
    }

    // ---------------------------------------------------------------
    // Sort by interest_count DESC  (sort=wanted)
    // ---------------------------------------------------------------
    public function testSortByInterestCountDesc(): void
    {
        $_GET = ['sort' => 'wanted', 'page' => '1'];

        $this->db->method('scalar')->willReturn(0);

        $this->db->expects($this->atLeastOnce())
            ->method('fetchAll')
            ->with(
                $this->callback(function (string $sql): bool {
                    return str_contains($sql, 'ORDER BY interest_count DESC');
                }),
                $this->anything()
            )
            ->willReturn([]);

        $controller = new GalleryController($this->db, $this->auth, $this->settings);

        ob_start();
        $controller->index();
        ob_end_clean();
    }

    // ---------------------------------------------------------------
    // Sort by title ASC  (sort=title)
    // ---------------------------------------------------------------
    public function testSortByTitleAsc(): void
    {
        $_GET = ['sort' => 'title', 'page' => '1'];

        $this->db->method('scalar')->willReturn(0);

        $this->db->expects($this->atLeastOnce())
            ->method('fetchAll')
            ->with(
                $this->callback(function (string $sql): bool {
                    return str_contains($sql, 'ORDER BY p.title ASC');
                }),
                $this->anything()
            )
            ->willReturn([]);

        $controller = new GalleryController($this->db, $this->auth, $this->settings);

        ob_start();
        $controller->index();
        ob_end_clean();
    }

    // ---------------------------------------------------------------
    // Search with pagination — page 2 should still pass :search
    // ---------------------------------------------------------------
    public function testSearchWithPagination(): void
    {
        $_GET = ['q' => 'lake', 'page' => '2'];

        // Return a total that gives multiple pages at 12 per page
        $this->db->method('scalar')
            ->with(
                $this->callback(function (string $sql): bool {
                    return str_contains($sql, 'LIKE');
                }),
                $this->callback(function (array $params): bool {
                    return ($params[':search'] ?? '') === '%lake%';
                })
            )
            ->willReturn(24);

        $this->db->expects($this->atLeastOnce())
            ->method('fetchAll')
            ->with(
                $this->callback(function (string $sql): bool {
                    return str_contains($sql, 'LIKE') && str_contains($sql, 'LIMIT');
                }),
                $this->callback(function (array $params): bool {
                    // Page 2 with 12 per page → offset 12
                    return ($params[':search'] ?? '') === '%lake%' &&
                           ($params[':offset'] ?? -1) === 12;
                })
            )
            ->willReturn([]);

        $controller = new GalleryController($this->db, $this->auth, $this->settings);

        ob_start();
        $controller->index();
        ob_end_clean();
    }

    // ---------------------------------------------------------------
    // Default sort (newest) when no sort param provided
    // ---------------------------------------------------------------
    public function testDefaultSortIsNewest(): void
    {
        $_GET = ['page' => '1'];

        $this->db->method('scalar')->willReturn(0);

        $this->db->expects($this->atLeastOnce())
            ->method('fetchAll')
            ->with(
                $this->callback(function (string $sql): bool {
                    return str_contains($sql, 'ORDER BY p.created_at DESC');
                }),
                $this->anything()
            )
            ->willReturn([]);

        $controller = new GalleryController($this->db, $this->auth, $this->settings);

        ob_start();
        $controller->index();
        ob_end_clean();
    }

    protected function tearDown(): void
    {
        $_GET = [];
    }
}
