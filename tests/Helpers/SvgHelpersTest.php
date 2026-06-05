<?php

namespace Threespot\Wp\Tests\Helpers;

use Brain\Monkey\Functions;
use Threespot\Wp\Tests\BrainMonkeyTestCase;

use function Threespot\Wp\Helpers\append_icon;

/**
 * Covers append_icon()'s text-splitting and HTML-aware edge cases.
 *
 * append_icon() calls svg() internally, so these tests drive the real svg()
 * against a fixture SVG (tests/fixtures/icon.svg) by stubbing the one WP
 * function svg() uses to resolve a theme path. The assertions deliberately
 * check the wrapping/placement of the <span> around the produced icon rather
 * than svg()'s exact markup.
 */
class SvgHelpersTest extends BrainMonkeyTestCase
{
    /**
     * Stub the WP functions append_icon() + svg() touch on the happy path.
     *
     * get_theme_file_path() is pointed at the fixtures dir so svg() reads a
     * real, valid SVG. wp_strip_all_tags() / esc_attr() are given faithful
     * lightweight stand-ins. is_admin() is never reached for a non-sprite
     * fixture (the sprite condition short-circuits), so it is not stubbed.
     */
    private function primeWpStubs(): void
    {
        Functions\when('get_theme_file_path')->alias(
            fn($path) => __DIR__ . '/../fixtures/' . basename($path)
        );
        Functions\when('wp_strip_all_tags')->alias(fn($text) => strip_tags($text));
        Functions\when('esc_attr')->returnArg(1);
    }

    public function test_single_word_wraps_text_and_icon(): void
    {
        $this->primeWpStubs();

        $result = append_icon([
            'text' => 'Hello',
            'svg' => ['file' => 'icon'],
        ]);

        $this->assertStringStartsWith('<span class="u-nowrap">Hello<svg', $result);
        $this->assertStringEndsWith('</span>', $result);
    }

    public function test_multi_word_wraps_last_word(): void
    {
        $this->primeWpStubs();

        $result = append_icon([
            'text' => 'Hello big world',
            'svg' => ['file' => 'icon'],
        ]);

        // Leading words stay outside; only the last word is wrapped with the icon.
        $this->assertStringContainsString('Hello big <span class="u-nowrap">world<svg', $result);
        $this->assertStringEndsWith('</span>', $result);
    }

    public function test_fully_wrapped_tag_recurses_and_rewraps(): void
    {
        $this->primeWpStubs();

        $result = append_icon([
            'text' => '<em>La Belle</em>',
            'svg' => ['file' => 'icon'],
        ]);

        // The <em> is preserved and the span is placed around the last inner word.
        $this->assertStringStartsWith('<em>La <span class="u-nowrap">Belle<svg', $result);
        $this->assertStringEndsWith('</span></em>', $result);
    }

    public function test_trailing_closing_tags_keep_span_inside(): void
    {
        $this->primeWpStubs();

        $result = append_icon([
            'text' => 'Hello <em>big world</em>',
            'svg' => ['file' => 'icon'],
        ]);

        // The wrapping <span> is inserted before the trailing </em>, not after it.
        $this->assertStringContainsString('Hello <em>big <span class="u-nowrap">world<svg', $result);
        $this->assertStringEndsWith('</span></em>', $result);
    }

    public function test_spaces_inside_attributes_are_not_split_points(): void
    {
        $this->primeWpStubs();

        $result = append_icon([
            'text' => 'Hello <span data-x="a b">world</span>',
            'svg' => ['file' => 'icon'],
        ]);

        // A strrpos-based split would break the attribute at the space; the
        // attribute must survive intact.
        $this->assertStringContainsString('data-x="a b"', $result);
        $this->assertStringContainsString('Hello <span class="u-nowrap"><span data-x="a b">world</span>', $result);
    }

    public function test_partial_svg_array_is_backfilled(): void
    {
        $this->primeWpStubs();

        // Caller supplies only 'file'; the missing svg keys must be backfilled
        // so svg() runs without notices (failOnNotice is on for this suite).
        $result = append_icon([
            'text' => 'Hi there',
            'svg' => ['file' => 'icon'],
        ]);

        $this->assertStringContainsString('<span class="u-nowrap">there<svg', $result);
    }

    public function test_missing_text_returns_error_path(): void
    {
        // svg_error() logs via do_action('qm/error', ...); stub it out.
        Functions\when('do_action')->justReturn(null);

        $result = append_icon([
            'text' => '',
            'svg' => ['file' => 'icon'],
        ]);

        // WP_DEBUG is undefined in the suite, so svg_error() returns ''.
        $this->assertSame('', $result);
    }

    public function test_deeply_nested_input_terminates_and_appends_icon_once(): void
    {
        $this->primeWpStubs();

        // Nest far deeper than APPEND_ICON_MAX_DEPTH (10). Without the depth
        // guard this would recurse on every level; with it, recursion stops and
        // the remaining markup is handled in one pass.
        $text = str_repeat('<em>', 15) . 'a b' . str_repeat('</em>', 15);

        $result = append_icon([
            'text' => $text,
            'svg' => ['file' => 'icon'],
        ]);

        $this->assertIsString($result);
        $this->assertStringContainsString('a', $result);
        $this->assertStringContainsString('b', $result);
        // The icon is appended exactly once regardless of nesting depth.
        $this->assertSame(1, substr_count($result, '<svg'));
    }
}
