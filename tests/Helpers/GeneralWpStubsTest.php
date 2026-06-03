<?php

namespace Threespot\Wp\Tests\Helpers;

use Brain\Monkey\Functions;
use Threespot\Wp\Tests\BrainMonkeyTestCase;

use function Threespot\Wp\Helpers\format_video_iframe;
use function Threespot\Wp\Helpers\get_custom_post_types;
use function Threespot\Wp\Helpers\is_external;

class GeneralWpStubsTest extends BrainMonkeyTestCase
{
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

    public function test_is_external_treats_pantheon_dev_urls_as_internal(): void
    {
        Functions\when('home_url')->justReturn('https://dev-mysite.pantheonsite.io');

        // On Pantheon dev/test, all URLs are treated as internal to avoid
        // false-positives during pre-launch QA.
        $this->assertFalse(is_external('https://google.com'));
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
