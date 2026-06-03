<?php

namespace Threespot\Wp\Helpers;

/**
 * Log an SVG helper error and return it for display only when WP_DEBUG is on.
 *
 * The svg() / append_icon() return value is echoed straight into page markup,
 * so in production (WP_DEBUG off) we return an empty string rather than surface
 * error text — and any server path it may contain — to end users. The full
 * detail is always sent to the PHP error log and to Query Monitor's log panel
 * (the qm/error action is a no-op when QM is not installed).
 *
 * @internal
 * @param string $message Error detail, without the "Error: " prefix.
 * @return string "Error: {$message}" when WP_DEBUG is true, otherwise ''.
 */
function svg_error($message) {
    error_log('threespot svg(): ' . $message);
    do_action('qm/error', 'threespot svg(): ' . $message);

    return (defined('WP_DEBUG') && WP_DEBUG) ? 'Error: ' . $message : '';
}

/**
 * Return raw SVG markup via a file in /resources/images or by attachment ID.
 *
 * @param array $params {
 *     @type string $file       Filename (without .svg) in the theme's /resources/images folder.
 *     @type int    $file_id    ID of an SVG uploaded to the Media Library.
 *     @type string $class      Optional class(es) to apply to the SVG element.
 *     @type int    $width
 *     @type int    $height
 *     @type bool   $sprite     Whether to reference the file in the SVG sprite.
 *     @type bool   $unique_ids Whether to add a random hash to each ID to avoid conflicts.
 *     @type bool   $focusable  Whether the SVG should be accessible to screen readers and IE/Edge.
 * }
 * @return string Raw SVG markup, or a plain-text error message when WP_DEBUG
 *                is on (empty string otherwise — see svg_error()).
 */
function svg($params) {
    $defaults = [
        'file' => '',
        'file_id' => null,
        'class' => null,
        'width' => null,
        'height' => null,
        'sprite' => false,
        'unique_ids' => true,
        'focusable' => false,
    ];

    $params = array_merge($defaults, $params);

    if (empty($params['file']) && empty($params['file_id'])) {
        return svg_error('No SVG file specified.');
    }

    if (!empty($params['file'])) {
        $svg_path = get_theme_file_path("resources/images/{$params['file']}.svg");
    } else {
        $svg_path = get_attached_file($params['file_id']);

        if (!empty($svg_path)) {
            $file_type = wp_check_filetype(basename($svg_path));
            if ($file_type['ext'] !== 'svg') {
                return svg_error('Uploaded file is not an SVG.');
            }
        }
    }

    if (!file_exists($svg_path)) {
        return svg_error('SVG file not found at ' . $svg_path);
    }

    if (!is_readable($svg_path)) {
        return svg_error('SVG file is not readable ' . $svg_path);
    }

    if (filesize($svg_path) > 500000) {
        return svg_error('SVG file exceeds 500KB max allowed size. Consider using <img> tag instead.');
    }

    $svg_content = file_get_contents($svg_path);
    if ($svg_content === false) {
        return svg_error('Could not read SVG file.');
    }

    libxml_use_internal_errors(true);
    $svg = new \DOMDocument();

    // LIBXML_NONET prevents network access during parsing
    $svg->load($svg_path, LIBXML_NONET);

    $errors = libxml_get_errors();

    if (!empty($errors)) {
        libxml_clear_errors();
        return svg_error('Invalid SVG markup in ' . $svg_path . ': ' . wp_json_encode($errors));
    }

    if (empty($svg->documentElement)) {
        return svg_error('Invalid SVG markup for ' . $svg_path);
    }

    $svg->documentElement->removeAttribute('baseProfile');
    $svg->documentElement->removeAttribute('version');

    if (!$params['sprite']) {
        $svg->documentElement->removeAttribute('xmlns');
    }

    if (!$params['focusable']) {
        // Prevent SVG from gaining focus in IE 10+
        $svg->documentElement->setAttribute('focusable', 'false');
        // Hide SVG from screen readers
        $svg->documentElement->setAttribute('aria-hidden', 'true');
    }

    $dimensions = explode(' ', $svg->documentElement->getAttribute('viewBox'));

    $boxWidth = (float) ($dimensions[2] ?? 0);
    $boxHeight = (float) ($dimensions[3] ?? 0);

    if (!empty($params['height'])) {
        $svg->documentElement->setAttribute('height', $params['height']);

        if (empty($params['width']) && $boxHeight > 0) {
            $params['width'] = (float) $params['height'] * ($boxWidth / $boxHeight);
            $svg->documentElement->setAttribute('width', (string) $params['width']);
        }
    }

    if (!empty($params['width'])) {
        $svg->documentElement->setAttribute('width', $params['width']);

        if (empty($params['height']) && $boxWidth > 0) {
            $params['height'] = (float) $params['width'] * ($boxHeight / $boxWidth);
            $svg->documentElement->setAttribute('height', (string) $params['height']);
        }
    }

    if (!empty($params['class'])) {
        $svg->documentElement->setAttribute('class', $params['class']);
    }

    // Auto-detect sprite usage from /sprite/ in path. Sprites don't work in admin
    // because WP uses an iframe for editor content.
    if (
        ($params['sprite'] || str_contains($svg_path, '/sprite/')) &&
        !is_admin()
    ) {
        while ($svg->documentElement->hasChildNodes()) {
            $svg->documentElement->removeChild($svg->documentElement->firstChild);
        }

        // createDocumentFragment() triggers an undefined-namespace warning:
        // https://bugs.php.net/bug.php?id=44773
        $use = $svg->createElement('use');

        $filename = basename($params['file']);

        // All sprite symbols are prefixed with "sprite" (see svg-sprite.blade.php)
        $use->setAttribute('href', "#sprite-{$filename}");

        $svg->documentElement->appendChild($use);
    }

    $svg_markup = $svg->saveXML($svg->documentElement);

    // For inline SVGs, append a random suffix to IDs to avoid collisions on the page
    if (!$params['sprite'] && $params['unique_ids']) {
        $guid = '_' . bin2hex(random_bytes(3));

        // $id_matches[0] = full markup (e.g. id="foo"); $id_matches[1] = just the ID
        preg_match_all('/\bid="(\S+)"/', $svg_markup, $id_matches);

        if (!empty(array_filter($id_matches))) {
            // Append $guid to IDs
            foreach ($id_matches[0] as $id_match) {
                $svg_markup = str_replace($id_match, substr($id_match, 0, -1) . $guid . '"', $svg_markup);
            }

            // Append $guid to ID references (e.g. xlink:href="#foo", filter="url(#foo)")
            foreach ($id_matches[1] as $id_match) {
                $svg_markup = preg_replace('/([("]#)(' . $id_match . ')([")])/', '$1$2' . $guid . '$3', $svg_markup);
            }
        }
    }

    return $svg_markup;
}

/**
 * Append an SVG icon to the last word of a text run, wrapping the last word
 * in a <span> to prevent orphans.
 *
 * @param array $params {
 *     @type string $text
 *     @type string $class Class applied to the wrapping <span>. Default 'u-nowrap'.
 *     @type array  $svg   Params forwarded to svg().
 * }
 * @return string
 */
function append_icon($params) {
    $defaults = [
        'text' => '',
        'class' => 'u-nowrap',
        'svg' => [
            'file' => '',
            'class' => null,
            'width' => null,
            'height' => null,
            'sprite' => false,
        ],
    ];

    $params = array_merge($defaults, $params);

    if (empty($params['text']) || empty($params['svg']['file'])) {
        return svg_error('Text or SVG file not specified.');
    }

    $svg = svg($params['svg']);

    $words = explode(' ', $params['text']);

    if (count($words) === 1) {
        return '<span class="' . $params['class'] . '">' . $params['text'] . $svg . '</span>';
    }

    $last_word = array_pop($words);
    $text = implode(' ', $words);

    return $text . ' <span class="' . $params['class'] . '">' . $last_word . $svg . '</span>';
}
