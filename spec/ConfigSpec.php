<?php
declare(strict_types=1);

namespace spec\Heirloom;

use Heirloom\Config;
use PhpSpec\ObjectBehavior;

class ConfigSpec extends ObjectBehavior
{
    function it_is_initializable(): void
    {
        $this->shouldHaveType(Config::class);
    }

    function it_returns_default_for_missing_key(): void
    {
        $this::get('SPEC_NONEXISTENT_KEY_12345', 'default_val')
            ->shouldReturn('default_val');
    }

    function it_returns_empty_string_when_no_default(): void
    {
        $this::get('SPEC_ALSO_NONEXISTENT_12345')
            ->shouldReturn('');
    }

    function it_loads_values_from_an_env_file(): void
    {
        $dir = sys_get_temp_dir() . '/heirloom_spec_' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/.env', "SPEC_LOADED_KEY=loaded_value\n");

        $this::load($dir . '/.env');
        $this::get('SPEC_LOADED_KEY')->shouldReturn('loaded_value');

        unlink($dir . '/.env');
        rmdir($dir);
        unset($_ENV['SPEC_LOADED_KEY']);
    }

    function it_skips_comments_in_env_files(): void
    {
        $dir = sys_get_temp_dir() . '/heirloom_spec_' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/.env', "# comment\nSPEC_REAL_KEY=yes\n");

        $this::load($dir . '/.env');
        $this::get('SPEC_REAL_KEY')->shouldReturn('yes');

        unlink($dir . '/.env');
        rmdir($dir);
        unset($_ENV['SPEC_REAL_KEY']);
    }

    function it_handles_values_containing_equals_signs(): void
    {
        $dir = sys_get_temp_dir() . '/heirloom_spec_' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/.env', "SPEC_DSN=mysql:host=127.0.0.1\n");

        $this::load($dir . '/.env');
        $this::get('SPEC_DSN')->shouldReturn('mysql:host=127.0.0.1');

        unlink($dir . '/.env');
        rmdir($dir);
        unset($_ENV['SPEC_DSN']);
    }

    function it_does_not_throw_for_missing_files(): void
    {
        $this::load('/nonexistent/path/.env');
    }
}
