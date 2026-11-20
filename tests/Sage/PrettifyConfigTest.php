<?php

namespace Threespot\Wp\Tests\Sage;

use PHPUnit\Framework\TestCase;

use function Threespot\Wp\Sage\prettify_config;

class PrettifyConfigTest extends TestCase
{
    public function test_returns_the_three_prettify_sections(): void
    {
        $config = prettify_config();

        $this->assertArrayHasKey('clean-up', $config);
        $this->assertArrayHasKey('nice-search', $config);
        $this->assertArrayHasKey('relative-urls', $config);
    }

    public function test_threespot_tuned_defaults_are_present(): void
    {
        $config = prettify_config();

        // Threespot keeps Gutenberg block CSS (acorn-prettify disables it by default).
        $this->assertFalse($config['clean-up']['disable-gutenberg-block-css']);

        // Threespot enables relative URLs (off by default in acorn-prettify).
        $this->assertTrue($config['relative-urls']['enabled']);
    }

    public function test_relative_url_hooks_is_a_non_empty_list(): void
    {
        $hooks = prettify_config()['relative-urls']['hooks'];

        $this->assertIsArray($hooks);
        $this->assertNotEmpty($hooks);
        $this->assertContains('the_permalink', $hooks);
    }

    public function test_term_links_stay_absolute(): void
    {
        // Yoast reads get_term_link() for term sitemaps, canonicals and
        // og:url, and sitemap <loc> values must be absolute URLs.
        $this->assertNotContains('term_link', prettify_config()['relative-urls']['hooks']);
    }

    public function test_attachment_urls_stay_absolute(): void
    {
        // Yoast's schema reads wp_get_attachment_url() for image
        // contentUrl/thumbnailUrl, which Google expects to be absolute.
        $this->assertNotContains('wp_get_attachment_url', prettify_config()['relative-urls']['hooks']);
    }
}
