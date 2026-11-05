<?php

namespace Threespot\Wp\Tests\Helpers;

use Brain\Monkey\Functions;
use Threespot\Wp\Tests\BrainMonkeyTestCase;

use function Threespot\Wp\Helpers\format_video_iframe;
use function Threespot\Wp\Helpers\get_custom_post_types;
use function Threespot\Wp\Helpers\is_external;
use function Threespot\Wp\Helpers\is_live_env;
use function Threespot\Wp\Helpers\is_pantheon;
use function Threespot\Wp\Helpers\is_production;

class GeneralWpStubsTest extends BrainMonkeyTestCase
{
    /* -----------------------------------------------------------------
     * is_pantheon, is_live_env, is_production
     * ----------------------------------------------------------------- */

    public function test_environment_checks_on_each_environment(): void
    {
        // [PANTHEON_ENVIRONMENT, is_pantheon(), is_live_env()]
        $cases = [
            [null, false, false],
            ['lando', false, false],
            ['dev', true, false],
            ['pr-12', true, false],
            ['test', true, false],
            ['live', true, true],
        ];

        foreach ($cases as [$env, $pantheon, $live]) {
            $this->withEnv('PANTHEON_ENVIRONMENT', $env, function () use ($env, $pantheon, $live) {
                $label = $env ?? '(unset)';
                $this->assertSame($pantheon, is_pantheon(), "is_pantheon() on $label");
                $this->assertSame($pantheon, is_production(), "is_production() on $label");
                $this->assertSame($live, is_live_env(), "is_live_env() on $label");
            });
        }
    }

    public function test_environment_checks_apply_their_filters(): void
    {
        $this->withEnv('PANTHEON_ENVIRONMENT', 'live', function () {
            \Brain\Monkey\Filters\expectApplied('threespot/helpers/is_live_env')->once()->with(true)->andReturn(false);
            $this->assertFalse(is_live_env());

            // is_production() keeps its old filter, on top of is_pantheon()'s
            \Brain\Monkey\Filters\expectApplied('threespot/helpers/is_pantheon')->once()->with(true)->andReturn(true);
            \Brain\Monkey\Filters\expectApplied('threespot/helpers/is_production')->once()->with(true)->andReturn(false);
            $this->assertFalse(is_production());
        });
    }

    public function test_is_pantheon_filter_reaches_is_production_but_not_the_reverse(): void
    {
        $this->withEnv('PANTHEON_ENVIRONMENT', 'test', function () {
            // A hook on is_pantheon changes is_production() too
            \Brain\Monkey\Filters\expectApplied('threespot/helpers/is_pantheon')->twice()->with(true)->andReturn(false);
            \Brain\Monkey\Filters\expectApplied('threespot/helpers/is_production')->once()->with(false)->andReturn(false);
            $this->assertFalse(is_production());

            // is_pantheon() never applies the is_production filter (the once() above)
            $this->assertFalse(is_pantheon());
        });
    }

    public function test_is_production_filter_can_turn_it_on_off_pantheon(): void
    {
        // e.g. a site that switches GTM on locally to test it
        $this->withEnv('PANTHEON_ENVIRONMENT', 'lando', function () {
            \Brain\Monkey\Filters\expectApplied('threespot/helpers/is_production')->once()->with(false)->andReturn(true);
            $this->assertTrue(is_production());
        });
    }

    /* -----------------------------------------------------------------
     * is_external
     * ----------------------------------------------------------------- */

    public function test_is_external_treats_same_host_as_internal(): void
    {
        Functions\when('home_url')->justReturn('https://example.com');

        $this->assertFalse(is_external('https://example.com/about'));
    }

    public function test_is_external_treats_relative_urls_as_internal(): void
    {
        Functions\when('home_url')->justReturn('https://example.com');

        $this->assertFalse(is_external('/relative-path'));
    }

    public function test_is_external_returns_true_for_different_host(): void
    {
        Functions\when('home_url')->justReturn('https://example.com');

        $this->assertTrue(is_external('https://google.com'));
    }

    public function test_is_external_still_detects_external_links_on_pantheon(): void
    {
        Functions\when('home_url')->justReturn('https://dev-mysite.pantheonsite.io');

        $this->assertTrue(is_external('https://google.com'));
        $this->assertFalse(is_external('https://dev-mysite.pantheonsite.io/about'));
    }

    public function test_is_external_treats_this_sites_pantheon_environments_as_internal(): void
    {
        Functions\when('home_url')->justReturn('https://mysite.lndo.site');
        $this->withEnv('PANTHEON_SITE_NAME', 'mysite', function () {
            $this->assertFalse(is_external('https://dev-mysite.pantheonsite.io/about'));
            $this->assertFalse(is_external('https://test-mysite.pantheonsite.io/'));
            $this->assertFalse(is_external('https://pr-12-mysite.pantheonsite.io/'));
        });
    }

    public function test_is_external_treats_other_pantheon_sites_as_external(): void
    {
        Functions\when('home_url')->justReturn('https://mysite.lndo.site');
        $this->withEnv('PANTHEON_SITE_NAME', 'mysite', function () {
            $this->assertTrue(is_external('https://dev-othersite.pantheonsite.io/'));
            $this->assertTrue(is_external('https://dev-notmysite.pantheonsite.io/'));
        });
    }

    public function test_is_external_treats_any_pantheon_url_as_internal_without_a_site_name(): void
    {
        Functions\when('home_url')->justReturn('https://mysite.test');
        $this->withEnv('PANTHEON_SITE_NAME', null, function () {
            $this->assertFalse(is_external('https://dev-mysite.pantheonsite.io/'));
        });
    }

    public function test_is_external_treats_subdomains_as_internal(): void
    {
        Functions\when('home_url')->justReturn('https://example.com');

        $this->assertFalse(is_external('https://members.example.com/account'));
    }

    public function test_is_external_requires_a_dot_before_the_domain(): void
    {
        Functions\when('home_url')->justReturn('https://example.com');

        $this->assertTrue(is_external('https://evilexample.com/'));
        $this->assertTrue(is_external('https://example.com.evil.net/'));
    }

    public function test_is_external_ignores_www_on_either_side(): void
    {
        Functions\when('home_url')->justReturn('https://www.example.com');

        $this->assertFalse(is_external('https://example.com/about'));
        $this->assertFalse(is_external('https://members.example.com/'));

        Functions\when('home_url')->justReturn('https://example.com');

        $this->assertFalse(is_external('https://www.example.com/about'));
    }

    public function test_is_external_ignores_case_and_ports(): void
    {
        Functions\when('home_url')->justReturn('https://Example.com');

        $this->assertFalse(is_external('HTTPS://EXAMPLE.COM:8443/about'));
    }

    public function test_is_external_handles_protocol_relative_urls(): void
    {
        Functions\when('home_url')->justReturn('https://example.com');

        $this->assertFalse(is_external('//example.com/about'));
        $this->assertTrue(is_external('//google.com/'));
    }

    public function test_is_external_treats_links_without_a_host_as_internal(): void
    {
        Functions\when('home_url')->justReturn('https://example.com');

        $this->assertFalse(is_external('#main'));
        $this->assertFalse(is_external('mailto:info@example.com'));
        $this->assertFalse(is_external(''));
        $this->assertFalse(is_external(null));
    }

    /**
     * Set an environment variable for one block of assertions, then restore it.
     *
     * @param string $name
     * @param string|null $value null removes the variable
     * @param callable $assertions
     */
    private function withEnv(string $name, ?string $value, callable $assertions): void
    {
        $had = array_key_exists($name, $_ENV);
        $old = $_ENV[$name] ?? null;

        if ($value === null) {
            unset($_ENV[$name]);
        } else {
            $_ENV[$name] = $value;
        }

        try {
            $assertions();
        } finally {
            if ($had) {
                $_ENV[$name] = $old;
            } else {
                unset($_ENV[$name]);
            }
        }
    }

    /* -----------------------------------------------------------------
     * get_custom_post_types
     * ----------------------------------------------------------------- */

    public function test_get_custom_post_types_appends_page_and_post(): void
    {
        Functions\when('get_post_types')->justReturn(['event' => 'event']);
        // apply_filters returns the default unchanged
        Functions\when('apply_filters')->returnArg(2);

        $result = get_custom_post_types();

        $this->assertContains('event', $result);
        $this->assertContains('page', $result);
        $this->assertContains('post', $result);
    }

    public function test_get_custom_post_types_excludes_ifso_triggers_by_default(): void
    {
        Functions\when('get_post_types')->justReturn([
            'event' => 'event',
            'ifso_triggers' => 'ifso_triggers',
        ]);
        Functions\when('apply_filters')->returnArg(2);

        $result = get_custom_post_types();

        $this->assertNotContains('ifso_triggers', $result);
    }

    public function test_get_custom_post_types_honors_filter_override(): void
    {
        Functions\when('get_post_types')->justReturn([
            'event' => 'event',
            'career' => 'career',
        ]);
        // Override the exclusion list to drop 'career'
        Functions\when('apply_filters')->alias(function ($filter, $default) {
            if ($filter === 'threespot/helpers/excluded_post_types') {
                return ['career'];
            }
            return $default;
        });

        $result = get_custom_post_types();

        $this->assertContains('event', $result);
        $this->assertNotContains('career', $result);
    }

    /* -----------------------------------------------------------------
     * format_video_iframe
     * ----------------------------------------------------------------- */

    public function test_format_video_iframe_adds_player_params(): void
    {
        Functions\when('apply_filters')->returnArg(2);
        Functions\when('add_query_arg')->alias(function ($params, $url) {
            $query = http_build_query($params);
            return $url . (str_contains($url, '?') ? '&' : '?') . $query;
        });

        $iframe = '<iframe src="https://player.vimeo.com/video/123"></iframe>';
        $result = format_video_iframe($iframe);

        $this->assertIsString($result);
        $this->assertStringContainsString('color=ff5100', $result);
        $this->assertStringContainsString('portrait=0', $result);
        $this->assertStringContainsString('enablejsapi=1', $result);
        $this->assertStringContainsString('frameborder="0"', $result);
        $this->assertStringContainsString('loading="lazy"', $result);
    }

    public function test_format_video_iframe_honors_vimeo_color_filter(): void
    {
        Functions\when('apply_filters')->alias(function ($filter, $default) {
            if ($filter === 'threespot/blocks/vimeo_color') {
                return '0066ff';
            }
            return $default;
        });
        Functions\when('add_query_arg')->alias(function ($params, $url) {
            return $url . '?' . http_build_query($params);
        });

        $iframe = '<iframe src="https://player.vimeo.com/video/123"></iframe>';
        $result = format_video_iframe($iframe);

        $this->assertStringContainsString('color=0066ff', $result);
        $this->assertStringNotContainsString('color=ff5100', $result);
    }

    public function test_format_video_iframe_returns_false_for_missing_src(): void
    {
        Functions\when('apply_filters')->returnArg(2);
        // add_query_arg shouldn't be reached
        Functions\when('add_query_arg')->justReturn('');

        $iframe = '<iframe></iframe>';
        $this->assertFalse(format_video_iframe($iframe));
    }
}
