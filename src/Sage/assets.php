<?php

namespace Threespot\Wp\Sage;

use Illuminate\Support\Facades\Vite;

/**
 * Resolve a Vite entry path to an absolute, scheme-aware URL.
 *
 * Vite::asset() returns a path relative to the theme's public/build/
 * directory, which WordPress prepends with /wp when generating URLs.
 * This wrapper normalises that to an absolute URL.
 *
 * Only safe to call on Sage sites with Acorn loaded.
 *
 * @param string $entry Vite entry path (e.g. 'resources/styles/main.scss').
 * @return string
 */
function vite_asset_url($entry) {
    $url = Vite::asset($entry);

    if (preg_match('#^https?://#', $url) || strpos($url, '//') === 0) {
        return $url;
    }

    return home_url($url);
}

/**
 * Inline the built critical CSS in <head>, or fall back to the Vite
 * dev-server <link> tag when the built file isn't on disk yet.
 *
 * Intended to be called from a wp_head hook at high priority.
 *
 * @param string $entry Vite entry path for the critical stylesheet.
 * @return void
 */
function inline_critical_css($entry) {
    $css_path = Vite::asset($entry);
    $css_path = get_template_directory() . '/public' . explode('/public', $css_path)[1];

    if (!file_exists($css_path)) {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo Vite::withEntryPoints([$entry])->toHtml();
        return;
    }

    $css = file_get_contents($css_path);
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo "<style id=\"critical\">{$css}</style>";
}
