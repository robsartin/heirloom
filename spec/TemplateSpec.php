<?php
declare(strict_types=1);

namespace spec\Heirloom;

use Heirloom\Template;
use PhpSpec\ObjectBehavior;

class TemplateSpec extends ObjectBehavior
{
    function it_is_initializable(): void
    {
        $this->shouldHaveType(Template::class);
    }

    function it_escapes_html_tags(): void
    {
        $this::escape('<b>bold</b>')
            ->shouldReturn('&lt;b&gt;bold&lt;/b&gt;');
    }

    function it_escapes_double_quotes(): void
    {
        $this::escape('say "hello"')
            ->shouldReturn('say &quot;hello&quot;');
    }

    function it_escapes_single_quotes(): void
    {
        $this::escape("it's")
            ->shouldReturn('it&apos;s');
    }

    function it_escapes_ampersands(): void
    {
        $this::escape('a & b')
            ->shouldReturn('a &amp; b');
    }

    function it_leaves_plain_text_unchanged(): void
    {
        $this::escape('hello world')
            ->shouldReturn('hello world');
    }

    function it_handles_empty_string(): void
    {
        $this::escape('')
            ->shouldReturn('');
    }

    function it_prevents_xss_via_script_injection(): void
    {
        $this::escape('<script>alert("xss")</script>')
            ->shouldReturn('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;');
    }
}
