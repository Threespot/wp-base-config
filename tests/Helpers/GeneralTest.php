<?php

namespace Threespot\Wp\Tests\Helpers;

use PHPUnit\Framework\TestCase;

use function Threespot\Wp\Helpers\bytes_to_human_size;
use function Threespot\Wp\Helpers\clean_wysiwyg_markup;
use function Threespot\Wp\Helpers\nowrap;
use function Threespot\Wp\Helpers\obfuscate;
use function Threespot\Wp\Helpers\strip_p_tags;
use function Threespot\Wp\Helpers\strip_quotes;
use function Threespot\Wp\Helpers\trim_excerpt;

class GeneralTest extends TestCase
{
    public function test_bytes_to_human_size_zero(): void
    {
        $this->assertSame('0.0B', bytes_to_human_size(0));
    }

    public function test_bytes_to_human_size_kilobytes(): void
    {
        $this->assertSame('1.5KB', bytes_to_human_size(1536));
    }

    public function test_bytes_to_human_size_megabytes(): void
    {
        $this->assertSame('1.0MB', bytes_to_human_size(1024 * 1024));
    }

    public function test_strip_p_tags_removes_open_and_close(): void
    {
        $this->assertSame('Hello world', strip_p_tags('<p>Hello world</p>'));
    }

    public function test_strip_p_tags_handles_multiple(): void
    {
        $this->assertSame('Onetwo', strip_p_tags('<p>One</p><p>two</p>'));
    }

    public function test_nowrap_joins_last_two_short_words_with_nbsp(): void
    {
        $this->assertSame('The quick brown&nbsp;fox', nowrap('The quick brown fox'));
    }

    public function test_nowrap_leaves_long_last_words_alone(): void
    {
        $this->assertSame(
            'The quick brown elephant',
            nowrap('The quick brown elephant')
        );
    }

    public function test_nowrap_returns_input_when_under_min_words(): void
    {
        $this->assertSame('Solo', nowrap('Solo'));
    }

    public function test_nowrap_strips_trailing_period_before_length_check(): void
    {
        // "fox." -> "fox" for length check; combined "brown" + "fox" = 8 chars, joined.
        $this->assertSame('The quick brown&nbsp;fox.', nowrap('The quick brown fox.'));
    }

    public function test_trim_excerpt_returns_empty_for_empty_input(): void
    {
        $this->assertSame('', trim_excerpt(''));
        $this->assertSame('', trim_excerpt(null));
    }

    public function test_trim_excerpt_truncates_to_word_count(): void
    {
        $excerpt = 'one two three four five six seven eight nine ten';
        $this->assertSame('one two three…', trim_excerpt($excerpt, 3));
    }

    public function test_trim_excerpt_preserves_ending_punctuation(): void
    {
        $excerpt = 'A short sentence.';
        $this->assertSame('A short sentence.', trim_excerpt($excerpt, 25));
    }

    public function test_trim_excerpt_appends_ellipsis_when_truncated(): void
    {
        $excerpt = 'Long sentence without punctuation here please';
        $this->assertSame('Long sentence without…', trim_excerpt($excerpt, 3));
    }

    public function test_clean_wysiwyg_strips_mce_spans_with_contents(): void
    {
        $input = '<p>Hello <span data-mce-bogus="all">noise</span>world</p>';
        $this->assertSame('<p>Hello world</p>', clean_wysiwyg_markup($input));
    }

    public function test_clean_wysiwyg_unwraps_plain_spans(): void
    {
        $input = '<p>Hello <span>kept</span> world</p>';
        $this->assertSame('<p>Hello kept world</p>', clean_wysiwyg_markup($input));
    }

    public function test_clean_wysiwyg_strips_inline_styles(): void
    {
        $input = '<p style="color:red">Hello</p>';
        $this->assertSame('<p>Hello</p>', clean_wysiwyg_markup($input));
    }

    public function test_clean_wysiwyg_removes_empty_tags(): void
    {
        $input = '<p>Hello</p><div></div>';
        $this->assertSame('<p>Hello</p>', clean_wysiwyg_markup($input));
    }

    public function test_strip_quotes_handles_straight_quotes(): void
    {
        $this->assertSame('hello', strip_quotes('"hello"'));
    }

    public function test_strip_quotes_handles_curly_quotes(): void
    {
        $this->assertSame('hello', strip_quotes('“hello”'));
    }

    public function test_strip_quotes_handles_entity_quotes(): void
    {
        $this->assertSame('hello', strip_quotes('&ldquo;hello&rdquo;'));
    }

    public function test_strip_quotes_handles_quotes_around_wrapping_tags(): void
    {
        $this->assertSame('<p>hello</p>', strip_quotes('"<p>hello</p>"'));
    }

    public function test_strip_quotes_leaves_unquoted_text_alone(): void
    {
        $this->assertSame('hello world', strip_quotes('hello world'));
    }

    public function test_obfuscate_encodes_ascii_to_numeric_entities(): void
    {
        $result = obfuscate('a');
        $this->assertSame('&#97;', $result);
    }

    public function test_obfuscate_returns_empty_for_empty_input(): void
    {
        $this->assertSame('', obfuscate(''));
    }

    public function test_obfuscate_encodes_full_email(): void
    {
        $result = obfuscate('a@b.c');
        // Just spot-check that all chars became entities and the count matches.
        $this->assertStringContainsString('&#97;', $result);
        $this->assertStringContainsString('&#64;', $result);
        $this->assertStringContainsString('&#98;', $result);
        $this->assertSame(5, substr_count($result, '&#'));
    }
}
