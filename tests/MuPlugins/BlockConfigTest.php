<?php

namespace Threespot\Wp\Tests\MuPlugins;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Threespot\Wp\MuPlugins\BlockConfig;

/**
 * Behavior tests for BlockConfig::stripEmptyParagraphs().
 *
 * Extends TestCase rather than BrainMonkeyTestCase because the method is a
 * pure string transform that calls no WordPress functions.
 */
class BlockConfigTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function emptyParagraphs(): iterable
    {
        yield 'bare' => ['<p></p>'];
        yield 'empty class (Gutenberg)' => ['<p class=""></p>'];
        yield 'block class (Gutenberg)' => ['<p class="wp-block-paragraph"></p>'];
        yield 'multiple attributes' => ['<p class="x" id="y" style="z"></p>'];
        yield 'nbsp entity (TinyMCE)' => ['<p>&nbsp;</p>'];
        yield 'literal U+00A0 (pasted from Word)' => ["<p>\u{00A0}</p>"];
        yield 'whitespace only' => ["<p>\n\t </p>"];
        yield 'br' => ['<p><br></p>'];
        yield 'self-closing br' => ['<p><br /></p>'];
        yield 'mixed' => ["<p>&nbsp;\u{00A0}<br/>\n</p>"];
        yield 'adjacent' => ['<p></p><p></p>'];
        yield 'newline-separated' => ["<p>&nbsp;</p>\n<p>&nbsp;</p>"];
    }

    #[DataProvider('emptyParagraphs')]
    public function test_strips_empty_paragraph(string $html): void
    {
        $result = BlockConfig::stripEmptyParagraphs('<a>before</a>' . $html . '<a>after</a>');

        // Whitespace between stripped paragraphs may remain; only <p> must be gone.
        $this->assertStringNotContainsString('<p', $result);
        $this->assertStringStartsWith('<a>before</a>', $result);
        $this->assertStringEndsWith('<a>after</a>', $result);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonEmptyParagraphs(): iterable
    {
        yield 'text' => ['<p>Keep me</p>'];
        yield 'text with class' => ['<p class="lead">Keep me</p>'];
        yield 'nbsp around text' => ['<p>&nbsp;Keep&nbsp;</p>'];
        yield 'more anchor' => ['<p><span id="more-1"></span></p>'];
        yield 'other tag starting with p' => ['<pre></pre><param><picture></picture>'];
        // Word indents with long runs of NBSP; must not hit the backtrack limit.
        yield 'many leading NBSP then text' => ['<p>' . str_repeat("\u{00A0}", 40) . 'Indented</p>'];
    }

    #[DataProvider('nonEmptyParagraphs')]
    public function test_keeps_non_empty_paragraph(string $html): void
    {
        $this->assertSame($html, BlockConfig::stripEmptyParagraphs($html));
    }

    public function test_returns_input_unchanged_on_malformed_utf8(): void
    {
        $html = "<p>caf\xE9</p><p></p>";

        $this->assertSame($html, BlockConfig::stripEmptyParagraphs($html));
    }
}
