<?php
declare(strict_types=1);

namespace Heirloom\Tests\Controllers;

use Heirloom\Adapters\SqlPaintingRepository;
use Heirloom\Auth;
use Heirloom\Controllers\AdminController;
use Heirloom\Controllers\GalleryController;
use Heirloom\Database;
use Heirloom\Ports\PaintingRepository;
use Heirloom\SiteSettings;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests that GalleryController and AdminController accept an optional
 * PaintingRepository and default to SqlPaintingRepository when null.
 */
class ControllerRepositoryWiringTest extends TestCase
{
    use ControllerTestSchema;

    private Auth $auth;
    private SiteSettings $settings;

    protected function setUp(): void
    {
        $this->createDatabase();
        $this->auth = new Auth($this->db);
        $this->settings = new SiteSettings($this->db);
    }

    // ---------------------------------------------------------------
    // GalleryController accepts optional PaintingRepository
    // ---------------------------------------------------------------

    public function testGalleryControllerConstructorAcceptsPaintingRepository(): void
    {
        $repo = new SqlPaintingRepository($this->db);
        $controller = new GalleryController($this->db, $this->auth, $this->settings, $repo);

        $ref = new ReflectionClass($controller);
        $prop = $ref->getProperty('paintingRepo');

        $this->assertSame($repo, $prop->getValue($controller));
    }

    public function testGalleryControllerConstructorDefaultsToSqlPaintingRepository(): void
    {
        $controller = new GalleryController($this->db, $this->auth, $this->settings);

        $ref = new ReflectionClass($controller);
        $prop = $ref->getProperty('paintingRepo');

        $this->assertInstanceOf(PaintingRepository::class, $prop->getValue($controller));
        $this->assertInstanceOf(SqlPaintingRepository::class, $prop->getValue($controller));
    }

    // ---------------------------------------------------------------
    // AdminController accepts optional PaintingRepository
    // ---------------------------------------------------------------

    public function testAdminControllerConstructorAcceptsPaintingRepository(): void
    {
        $repo = new SqlPaintingRepository($this->db);
        $controller = new AdminController($this->db, $this->auth, $this->settings, $repo);

        $ref = new ReflectionClass($controller);
        $prop = $ref->getProperty('paintingRepo');

        $this->assertSame($repo, $prop->getValue($controller));
    }

    public function testAdminControllerConstructorDefaultsToSqlPaintingRepository(): void
    {
        $controller = new AdminController($this->db, $this->auth, $this->settings);

        $ref = new ReflectionClass($controller);
        $prop = $ref->getProperty('paintingRepo');

        $this->assertInstanceOf(PaintingRepository::class, $prop->getValue($controller));
        $this->assertInstanceOf(SqlPaintingRepository::class, $prop->getValue($controller));
    }

    // ---------------------------------------------------------------
    // Backward compatibility: null is accepted and defaults correctly
    // ---------------------------------------------------------------

    public function testGalleryControllerConstructorWithExplicitNull(): void
    {
        $controller = new GalleryController($this->db, $this->auth, $this->settings, null);

        $ref = new ReflectionClass($controller);
        $prop = $ref->getProperty('paintingRepo');

        $this->assertInstanceOf(SqlPaintingRepository::class, $prop->getValue($controller));
    }

    public function testAdminControllerConstructorWithExplicitNull(): void
    {
        $controller = new AdminController($this->db, $this->auth, $this->settings, null);

        $ref = new ReflectionClass($controller);
        $prop = $ref->getProperty('paintingRepo');

        $this->assertInstanceOf(SqlPaintingRepository::class, $prop->getValue($controller));
    }
}
