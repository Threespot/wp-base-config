<?php

namespace Threespot\Wp\Helpers;

/**
 * Detect whether the current request is running on a production environment.
 *
 * Treats any Pantheon environment other than the Lando-local override as
 * production. Sites can override by hooking the threespot/helpers/is_production
 * filter (e.g. to add a staging exclusion).
 *
 * @return bool
 */
function is_production() {
    $is_production = isset($_ENV['PANTHEON_ENVIRONMENT'])
        && $_ENV['PANTHEON_ENVIRONMENT'] !== 'lando';

    return (bool) apply_filters('threespot/helpers/is_production', $is_production);
}

/**
 * Convert bytes to human-friendly notation.
 *
 * @param int|float $size
 * @param int $decimals Number of decimal places to display. Defaults to 1.
 * @return string
 */
function bytes_to_human_size($size, $decimals = 1) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    $power = $size > 0 ? floor(log($size, 1024)) : 0;
    return number_format($size / pow(1024, $power), $decimals, '.', ',') . $units[$power];
}

/**
 * Strip <p> tags. Used when ACF wysiwyg fields are required for a heading tag.
 *
 * @param string $markup
 * @return string
 */
function strip_p_tags($markup) {
    return preg_replace('/<\/?p>/', '', $markup);
}

/**
 * Check if URL is external.
 *
 * @link https://stackoverflow.com/a/22964930/673457
 * @param string $url
 * @return bool
 */
function is_external($url) {
    $home_url = parse_url(home_url());
    $test_url = parse_url($url);

    // Ignore Pantheon URLs for local dev
    if (str_contains($home_url['host'], 'pantheonsite.io')) {
        return false;
    }

    // Ignore relative URLs
    if (empty($test_url['host'])) {
        return false;
    }

    // Check if hosts are equal
    if (strcasecmp($test_url['host'], $home_url['host']) === 0) {
        return false;
    }

    // Check if the url host is a subdomain
    return strrpos(strtolower($test_url['host']), $home_url['host']) !== strlen($test_url['host']) - strlen($home_url['host']);
}

/**
 * Append non-breaking space between last two words to prevent orphans.
 *
 * @link https://css-tricks.com/snippets/php/append-non-breaking-space-between-last-two-words/
 * @param string $text
 * @param int $minWords
 * @param int $maxCharLength
 * @return string
 */
function nowrap($text, $minWords = 2, $maxCharLength = 12) {
    $text = preg_replace('/\s+/', ' ', trim($text));
    $words = explode(' ', $text);

    if (count($words) < $minWords) {
        return $text;
    }

    $penultimate_word = $words[count($words) - 2];
    $last_word = $words[count($words) - 1];

    // Ignore “</p>” tags from last word (could be added by ACF textarea field)
    $last_word = str_replace('</p>', '', $last_word);
    $last_word = rtrim($last_word, '.');

    if (strlen($penultimate_word . $last_word) > $maxCharLength) {
        return $text;
    }

    $words[count($words) - 2] .= '&nbsp;' . $words[count($words) - 1];
    array_pop($words);

    return implode(' ', $words);
}

/**
 * Trim excerpt using custom word count.
 *
 * @param string $excerpt
 * @param int $word_count
 * @return string
 */
function trim_excerpt($excerpt, $word_count = 25) {
    if (!is_string($excerpt) || empty($excerpt)) {
        return '';
    }

    $excerpt = trim($excerpt);
    $excerpt = rtrim($excerpt, '…');

    if (str_word_count($excerpt, 0) > $word_count) {
        $words = preg_split('/\s+/', $excerpt, $word_count + 1);
        array_pop($words);
        $excerpt = implode(' ', $words);
    }

    return preg_match('/[.!?”]$/', $excerpt) ? $excerpt : $excerpt . '…';
}

/**
 * Clean up HTML markup from WYSIWYG editors.
 * Removes TinyMCE artifacts, unwanted span tags, and inline styles.
 *
 * @param string $content
 * @return string
 */
function clean_wysiwyg_markup($content) {
    $content = trim($content);

    // Step 1: Remove spans with data-mce-* attributes INCLUDING their contents
    $content = preg_replace('/<span[^>]*data-mce-[^>]*>.*?<\/span>/s', '', $content);

    // Step 2: Remove all other span tags but KEEP their contents
    $content = preg_replace('/<span[^>]*>(.*?)<\/span>/s', '$1', $content);

    // Step 3: Remove all inline style attributes from any remaining tags
    $content = preg_replace('/\s+style="[^"]*"/i', '', $content);

    // Step 4: Remove empty tags
    $content = preg_replace('/<([a-z][a-z0-9]*)\b[^>]*>\s*<\/\1>/i', '', $content);

    return trim($content);
}

/**
 * Strip opening and closing quotes from text or HTML.
 * Handles straight, curly, and HTML-entity-encoded quotes.
 *
 * @param string $text
 * @return string
 */
function strip_quotes($text) {
    $text = clean_wysiwyg_markup($text);

    // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
    $result = @preg_replace([
        // Opening HTML tag(s) followed by opening quote
        '/^(\s*(?:<[^>]*>\s*)*)(?:"|“|&quot;|&ldquo;|&#8220;|&#x201C;)/us',
        // Closing quote followed by closing HTML tag(s)
        '/(?:"|”|&quot;|&rdquo;|&#8221;|&#x201D;)(\s*(?:<\/[^>]*>\s*)*)$/us',
    ], ['\1', '\1'], $text);

    return $result !== null ? $result : $text;
}

/**
 * Convert characters to HTML numeric string references (e.g. 'a' => '&#97;').
 * Use to obfuscate email addresses in mailto links.
 *
 * @link https://stackoverflow.com/a/32997549/
 * @param string $string
 * @return string
 */
function obfuscate($string) {
    if (empty($string)) {
        return $string;
    }

    return mb_encode_numericentity($string, [0x000000, 0x10ffff, 0, 0xffffff], 'UTF-8');
}

/**
 * Return list of public post types (pages, posts, and CPTs), with any
 * site-excluded types removed via the threespot/helpers/excluded_post_types filter.
 *
 * @return array<int|string, string>
 */
function get_custom_post_types() {
    $post_types = get_post_types(['public' => true, '_builtin' => false]);
    $post_types[] = 'page';
    $post_types[] = 'post';

    /**
     * Filter post types excluded from the result of get_custom_post_types().
     *
     * @param array<int, string> $excluded
     */
    $excluded = apply_filters('threespot/helpers/excluded_post_types', ['ifso_triggers']);

    return array_values(array_diff($post_types, $excluded));
}

/**
 * Customize ACF oEmbed video iframe markup.
 * Adds YouTube/Vimeo player params (Vimeo color via threespot/blocks/vimeo_color filter)
 * and frameborder/loading attributes.
 *
 * @link https://www.advancedcustomfields.com/resources/oembed/
 * @param string $iframe
 * @return string|false
 */
function format_video_iframe($iframe) {
    preg_match('/src="(.+?)"/', $iframe, $matches);

    if (count($matches) === 0) {
        return false;
    }

    $src = $matches[1];

    /** @var string $vimeo_color */
    $vimeo_color = apply_filters('threespot/blocks/vimeo_color', 'ff5100');

    $params = [
        // YouTube — https://developers.google.com/youtube/player_parameters#Parameters
        'enablejsapi' => 1,
        'modestbranding' => 1,
        'rel' => 0,
        // Vimeo — https://help.vimeo.com/hc/en-us/articles/12426260232977-Player-parameters-overview
        'color' => $vimeo_color,
        'portrait' => 0,
        'title' => 0,
    ];

    $new_src = add_query_arg($params, $src);
    $iframe = str_replace($src, $new_src, $iframe);
    $iframe = str_replace('></iframe>', ' frameborder="0" loading="lazy"></iframe>', $iframe);

    return $iframe;
}
