<?php

namespace Threespot\Wp\Helpers;

/**
 * FacetWP helpers: pure functions behind FacetwpConfig, also callable from
 * themes. None of them call FacetWP, so they load and run without it.
 */

/**
 * FacetWP's vars the search form never carries: a new keyword starts on page
 * one, and these are FacetWP's own pager, reset and count facets.
 */
const FACETWP_HIDDEN_VARS_SKIP = ['paged', 'pager', 'reset', 'result_count'];

/**
 * The request's query args as the visitor typed them.
 *
 * On page load FacetWP's http_params['get'] is WordPress's $_GET, which
 * WordPress slashes, so it's unslashed. A refresh sends the address bar's
 * query string, encoded but not slashed, so keys and string values are
 * decoded instead.
 *
 * @param array<mixed> $get FacetWP's http_params['get']
 * @param bool $is_refresh Whether this is a FacetWP refresh
 * @return array<mixed>
 */
function facetwp_request_get(array $get, bool $is_refresh): array {
    if ($is_refresh) {
        $decoded = [];

        foreach ($get as $key => $value) {
            $decoded[urldecode((string) $key)] = is_string($value) ? urldecode($value) : $value;
        }

        return $decoded;
    }

    $unslash = function ($value) use (&$unslash) {
        if (is_array($value)) {
            return array_map($unslash, $value);
        }

        return is_string($value) ? stripslashes($value) : $value;
    };

    return array_map($unslash, $get);
}

/**
 * A listing's path for its page links: FacetWP's request URI without slashes,
 * a trailing page/<n> or the home URL's subfolder (home_url() adds it back).
 *
 * @param string $uri FacetWP's http_params['uri'] or the request path
 * @param string $home_path The home URL's path, e.g. "/" or "/site/"
 * @return string e.g. "news", or "" for the home page
 */
function facetwp_page_path(string $uri, string $home_path): string {
    $path = trim((string) preg_replace('#(^|/)page/\d+/?$#', '', trim($uri, '/')), '/');
    $home = trim($home_path, '/');

    if ($home === '') {
        return $path;
    }

    if ($path === $home) {
        return '';
    }

    return str_starts_with($path, $home . '/') ? substr($path, strlen($home) + 1) : $path;
}

/**
 * The query args for a page of results: the request's own args (not FacetWP's
 * "_" vars), then each facet's current selection, then the page.
 *
 * @param array<mixed> $get The request's query args (facetwp_request_get())
 * @param array<string, array<mixed>> $selections Facet name => selected values
 * @param int $page Page number; page 1 carries no _paged
 * @return array<string, string>
 */
function facetwp_page_query_args(array $get, array $selections, int $page): array {
    $args = [];

    foreach ($get as $key => $value) {
        $key = (string) $key;

        if ($key !== '' && $key[0] !== '_' && is_scalar($value)) {
            $args[$key] = (string) $value;
        }
    }

    foreach ($selections as $name => $values) {
        $values = array_values(array_filter(array_map('strval', (array) $values), fn ($value) => $value !== ''));

        if ($values !== []) {
            $args['_' . $name] = implode(',', $values);
        }
    }

    if ($page > 1) {
        $args['_paged'] = (string) $page;
    }

    return $args;
}

/**
 * Give a pager link an href.
 *
 * Changes only the first <a> tag, and only when it has no href, so a site
 * filter that already built a real link (or a <button>) is left alone.
 *
 * @param string $html A pager link from facetwp_facet_pager_link
 * @param string $url The page's URL (escaped here)
 */
function facetwp_add_href(string $html, string $url): string {
    return (string) preg_replace_callback('/<a\b[^>]*>/', function (array $match) use ($url) {
        if (preg_match('/\shref\s*=/i', $match[0])) {
            return $match[0];
        }

        return '<a href="' . esc_url($url) . '"' . substr($match[0], 2);
    }, $html, 1);
}

/**
 * Point a facet's <select> at its visible label with aria-labelledby.
 *
 * Changes only the first <select>, and only when it has no aria-labelledby,
 * aria-label or id (an id means a <label for> may already name it), so a
 * site filter that already labelled it is left alone.
 *
 * @param string $html A facet's HTML from facetwp_facet_html
 * @param string $label_id The id of the visible label
 */
function facetwp_add_labelledby(string $html, string $label_id): string {
    return (string) preg_replace_callback('/<select\b[^>]*>/', function (array $match) use ($label_id) {
        if (preg_match('/\s(aria-labelledby|aria-label|id)\s*=/i', $match[0])) {
            return $match[0];
        }

        return '<select aria-labelledby="' . esc_attr($label_id) . '"' . substr($match[0], 7);
    }, $html, 1);
}

/**
 * The active filters to carry as hidden inputs in a GET search form, so a
 * new keyword keeps them.
 *
 * Registered facets only, so tracking args such as _ga aren't carried, and
 * none of $skip. The theme prints each pair as
 * <input type="hidden" name="<name>" value="<value>">.
 *
 * @param array<mixed> $get The request's query args, unslashed
 * @param string[] $facet_names Registered FacetWP facet names
 * @param string[] $skip Facet names to leave out
 * @return array<string, string> "_<facet>" => value
 */
function facetwp_hidden_vars(array $get, array $facet_names, array $skip = FACETWP_HIDDEN_VARS_SKIP): array {
    $vars = [];

    foreach ($facet_names as $name) {
        $value = $get['_' . $name] ?? '';

        if (in_array($name, $skip, true) || !is_scalar($value)) {
            continue;
        }

        $value = sanitize_text_field((string) $value);

        if ($value !== '') {
            $vars['_' . $name] = $value;
        }
    }

    return $vars;
}
