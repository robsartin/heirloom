<?php

namespace spec\Heirloom;

use Heirloom\Router;
use PhpSpec\ObjectBehavior;

class RouterSpec extends ObjectBehavior
{
    function it_is_initializable(): void
    {
        $this->shouldHaveType(Router::class);
    }

    function it_dispatches_a_get_route_to_its_handler(): void
    {
        $called = false;
        $this->get('/hello', function () use (&$called) { $called = true; });
        $this->dispatch('GET', '/hello');
        if (!$called) {
            throw new \Exception('Handler was not called');
        }
    }

    function it_dispatches_a_post_route(): void
    {
        $called = false;
        $this->post('/submit', function () use (&$called) { $called = true; });
        $this->dispatch('POST', '/submit');
        if (!$called) {
            throw new \Exception('Handler was not called');
        }
    }

    function it_extracts_named_parameters(): void
    {
        $captured = null;
        $this->get('/item/{id}', function (string $id) use (&$captured) { $captured = $id; });
        $this->dispatch('GET', '/item/99');
        if ($captured !== '99') {
            throw new \Exception("Expected '99', got '$captured'");
        }
    }

    function it_normalizes_trailing_slashes(): void
    {
        $called = false;
        $this->get('/about', function () use (&$called) { $called = true; });
        $this->dispatch('GET', '/about/');
        if (!$called) {
            throw new \Exception('Trailing slash not normalized');
        }
    }

    function it_outputs_404_when_no_route_matches(): void
    {
        $this->get('/exists', function () {});
        ob_start();
        $this->dispatch('GET', '/nope');
        $output = ob_get_clean();
        if (strpos($output, '404') === false) {
            throw new \Exception('Expected 404 output');
        }
    }
}
