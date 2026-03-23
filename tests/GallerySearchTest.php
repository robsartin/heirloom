<?php
declare(strict_types=1);

namespace Heirloom\Tests;

use Heirloom\Auth;
use Heirloom\Controllers\GalleryController;
use Heirloom\Database;
use Heirloom\SiteSettings;
use Heirloom\Template;
use PHPUnit\Framework\TestCase;

class GallerySearchTest extends TestCase
{
    private Database $db;
    private Auth $auth;
    private SiteSettings $settings;
    private array $capturedQueries;

    protected function setUp(): void
    {
        $this->capturedQueries = [];
        $this->db = $this->createMock(Database::class);
        $this->auth = $this->createMock(Auth::class);
        $this->settings = $this->createMock(SiteSettings::class);

        $this->settings->method('getInt')->willReturn(12);
        $this->settings->method('get')->willReturn(SiteSettings::DEFAULT_SITE_NAME);
        $this->auth->method('isLoggedIn')->willReturn(true);
        $this->auth->method('userId')->willReturn(1);
        $this->auth->method('user')->willReturn([
            'id' => 1, 'name' => 'Test', 'email' => 'test@example.com', 'is_admin' => 0,
        ]);
        $this->auth->method('isAdmin')->willReturn(false);

        $this->db->method('scalar')->willReturn(0);
        $this->db->method('fetchAll')->willReturnCallback(function (string $sql, array $params = []) {
            $this->capturedQueries[] = ['sql' => $sql, 'params' => $params];
            return [];
        });

        Template::setGlobal('siteName', SiteSettings::DEFAULT_SITE_NAME);
        Template::setGlobal('contactEmail', '');
    }

    protected function tearDown(): void
    {
        $_GET = [];
    }

    private function runIndex(): void
    {
        $controller = new GalleryController($this->db, $this->auth, $this->settings);
        ob_start();
        $controller->index();
        ob_end_clean();
    }

    private function findQuery(string $substring): ?array
    {
        foreach ($this->capturedQueries as $q) {
            if (str_contains($q['sql'], $substring)) {
                return $q;
            }
        }
        return null;
    }

    public function testSearchByTitleLike(): void
    {
        $_GET = ['q' => 'sunset', 'page' => '1'];

        $this->db = $this->createMock(Database::class);
        $this->db->method('fetchAll')->willReturn([]);
        $this->db->expects($this->atLeastOnce())
            ->method('scalar')
            ->with(
                $this->callback(fn(string $sql) =>
                    str_contains($sql, 'LIKE') && str_contains($sql, 'title')
                ),
                $this->callback(fn(array $p) =>
                    ($p[':search'] ?? '') === '%sunset%'
                )
            )
            ->willReturn(0);

        $controller = new GalleryController($this->db, $this->auth, $this->settings);
        ob_start();
        $controller->index();
        ob_end_clean();
    }

    public function testSortByInterestCountDesc(): void
    {
        $_GET = ['sort' => 'wanted', 'page' => '1'];
        $this->runIndex();

        $q = $this->findQuery('ORDER BY interest_count DESC');
        $this->assertNotNull($q, 'Expected ORDER BY interest_count DESC in a query');
    }

    public function testSortByTitleAsc(): void
    {
        $_GET = ['sort' => 'title', 'page' => '1'];
        $this->runIndex();

        $q = $this->findQuery('ORDER BY p.title ASC');
        $this->assertNotNull($q, 'Expected ORDER BY p.title ASC in a query');
    }

    public function testSearchWithPagination(): void
    {
        $_GET = ['q' => 'lake', 'page' => '2'];

        $this->db = $this->createMock(Database::class);
        $captured = [];
        $this->db->method('fetchAll')->willReturnCallback(function (string $sql, array $params = []) use (&$captured) {
            $captured[] = ['sql' => $sql, 'params' => $params];
            return [];
        });
        $this->db->method('scalar')->willReturn(24);

        $controller = new GalleryController($this->db, $this->auth, $this->settings);
        ob_start();
        $controller->index();
        ob_end_clean();

        $paintingQuery = null;
        foreach ($captured as $q) {
            if (str_contains($q['sql'], 'LIKE') && str_contains($q['sql'], 'LIMIT')) {
                $paintingQuery = $q;
                break;
            }
        }
        $this->assertNotNull($paintingQuery, 'Expected painting query with LIKE and LIMIT');
        $this->assertSame('%lake%', $paintingQuery['params'][':search']);
        $this->assertSame(12, $paintingQuery['params'][':offset']);
    }

    public function testDefaultSortIsNewest(): void
    {
        $_GET = ['page' => '1'];
        $this->runIndex();

        $q = $this->findQuery('ORDER BY p.created_at DESC');
        $this->assertNotNull($q, 'Expected ORDER BY p.created_at DESC in a query');
    }
}
