<?php

namespace Threespot\Wp\Tests\MuPlugins;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Threespot\Wp\MuPlugins\BlockConfig;

/**
 * Behavior tests for the core/embed branch of BlockConfig::customizeBlockMarkup().
 *
 * Extends TestCase rather than BrainMonkeyTestCase because the embed branch
 * is a pure string transform that calls no WordPress functions.
 */
class BlockConfigEmbedTest extends TestCase
{
    // Provider oEmbed markup, as WordPress stores it before our filter runs.
    private const VIMEO = '<iframe title="Digging In - Trailer" src="https://player.vimeo.com/video/825634182?dnt=1&amp;app_id=122963" width="500" height="281" frameborder="0" allow="autoplay; fullscreen; picture-in-picture; clipboard-write; encrypted-media; web-share" referrerpolicy="strict-origin-when-cross-origin"></iframe>';

    private const YOUTUBE = '<iframe title="2025 GIH Annual Conference Welcome Video" width="500" height="281" src="https://www.youtube.com/embed/dHBXNy_paa8?feature=oembed" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>';

    private const SOUNDCLOUD = '<iframe width="100%" height="400" scrolling="no" frameborder="no" allow="autoplay; encrypted-media" src="https://w.soundcloud.com/player/?visual=true&url=https%3A%2F%2Fapi.soundcloud.com%2Ftracks%2F293&show_artwork=true"></iframe>';

    private const SPOTIFY = '<iframe style="border-radius: 12px" width="100%" height="152" title="Spotify Embed: Never Gonna Give You Up" frameborder="0" allowfullscreen allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy" src="https://open.spotify.com/embed/track/4uLU6hMCjMI75M1A2tKUQC?utm_source=oembed"></iframe>';

    private static function embed(string $iframe): string
    {
        return '<figure class="wp-block-embed"><div class="wp-block-embed__wrapper">' . $iframe . '</div></figure>';
    }

    private static function render(string $content, string $block_name = 'core/embed'): string
    {
        return BlockConfig::customizeBlockMarkup($content, ['blockName' => $block_name, 'attrs' => []]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function withoutLoading(): iterable
    {
        yield 'vimeo' => [self::VIMEO];
        yield 'youtube' => [self::YOUTUBE];
        yield 'soundcloud' => [self::SOUNDCLOUD];
    }

    #[DataProvider('withoutLoading')]
    public function test_adds_loading_and_nothing_else(string $iframe): void
    {
        $content = self::embed($iframe);

        $this->assertSame(
            str_replace('<iframe ', '<iframe loading="lazy" ', $content),
            self::render($content)
        );
    }

    #[DataProvider('withoutLoading')]
    public function test_keeps_a_single_allow_attribute(string $iframe): void
    {
        $this->assertSame(1, preg_match_all('/\sallow\s*=/', self::render(self::embed($iframe))));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function withLoading(): iterable
    {
        yield 'spotify' => [self::SPOTIFY];
        yield 'eager' => ['<iframe loading="eager" src="x"></iframe>'];
    }

    #[DataProvider('withLoading')]
    public function test_keeps_provider_loading(string $iframe): void
    {
        $content = self::embed($iframe);

        $this->assertSame($content, self::render($content));
    }

    public function test_is_idempotent(): void
    {
        $once = self::render(self::embed(self::VIMEO));

        $this->assertSame($once, self::render($once));
    }

    public function test_other_blocks_keep_their_iframes_unchanged(): void
    {
        $html = '<iframe src="x"></iframe>';

        $this->assertSame($html, self::render($html, 'core/html'));
    }
}
