<?php
declare(strict_types=1);

namespace Heirloom\Tests;

use Heirloom\Router;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    public function testGetRouteMatchesExactPath(): void
    {
        $called = false;
        $this->router->get('/', function () use (&$called) {
            $called = true;
        });

        $this->router->dispatch('GET', '/');
        $this->assertTrue($called);
    }

    public function testPostRouteMatchesExactPath(): void
    {
        $called = false;
        $this->router->post('/submit', function () use (&$called) {
            $called = true;
        });

        $this->router->dispatch('POST', '/submit');
        $this->assertTrue($called);
    }

    public function testParameterizedRouteExtractsValue(): void
    {
        $capturedId = null;
        $this->router->get('/painting/{id}', function (string $id) use (&$capturedId) {
            $capturedId = $id;
        });

        $this->router->dispatch('GET', '/painting/42');
        $this->assertSame('42', $capturedId);
    }

    public function testMultipleParametersExtracted(): void
    {
        $capturedA = null;
        $capturedB = null;
        $this->router->get('/a/{x}/b/{y}', function (string $x, string $y) use (&$capturedA, &$capturedB) {
            $capturedA = $x;
            $capturedB = $y;
        });

        $this->router->dispatch('GET', '/a/hello/b/world');
        $this->assertSame('hello', $capturedA);
        $this->assertSame('world', $capturedB);
    }

    public function testTrailingSlashIsNormalized(): void
    {
        $called = false;
        $this->router->get('/about', function () use (&$called) {
            $called = true;
        });

        $this->router->dispatch('GET', '/about/');
        $this->assertTrue($called);
    }

    public function testNoMatchReturns404(): void
    {
        $this->router->get('/exists', function () {});

        ob_start();
        $this->router->dispatch('GET', '/nope');
        $output = ob_get_clean();

        $this->assertStringContainsString('404', $output);
    }

    public function testGetRouteDoesNotMatchPost(): void
    {
        $called = false;
        $this->router->get('/only-get', function () use (&$called) {
            $called = true;
        });

        ob_start();
        $this->router->dispatch('POST', '/only-get');
        ob_end_clean();

        $this->assertFalse($called);
    }

    public function testFirstMatchingRouteWins(): void
    {
        $which = '';
        $this->router->get('/test', function () use (&$which) {
            $which = 'first';
        });
        $this->router->get('/test', function () use (&$which) {
            $which = 'second';
        });

        $this->router->dispatch('GET', '/test');
        $this->assertSame('first', $which);
    }

    public function testParameterDoesNotMatchSlash(): void
    {
        $called = false;
        $this->router->get('/item/{id}', function () use (&$called) {
            $called = true;
        });

        ob_start();
        $this->router->dispatch('GET', '/item/1/extra');
        ob_end_clean();

        $this->assertFalse($called);
    }

    public function testRootSlashMatchesRootRoute(): void
    {
        $called = false;
        $this->router->get('/', function () use (&$called) {
            $called = true;
        });

        $this->router->dispatch('GET', '');
        $this->assertTrue($called);
    }

    public function testArrayHandlerIsCalled(): void
    {
        $obj = new class {
            public bool $called = false;
            public function handle(): void { $this->called = true; }
        };

        $this->router->get('/test', [$obj, 'handle']);
        $this->router->dispatch('GET', '/test');
        $this->assertTrue($obj->called);
    }
}
