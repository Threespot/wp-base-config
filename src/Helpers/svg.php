<?php

namespace Threespot\Wp\Helpers;

/**
 * Maximum recursion depth for append_icon()'s fully-wrapped-tag handling.
 * Well-formed input strips one tag per level, so this is only reached by
 * pathological nesting; it caps the call stack regardless.
 */
const APPEND_ICON_MAX_DEPTH = 10;

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
        // Get file by path
        $svg_path = get_theme_file_path("resources/images/{$params['file']}.svg");
    } else {
        // Get file by ID
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

    // Load SVG content with libxml security options
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

  	// Remove unnecessary attributes for inline SVGs
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

	// Set height attribute
    if (!empty($params['height'])) {
        $svg->documentElement->setAttribute('height', $params['height']);

    	// Automatically calculate the width if not set
        if (empty($params['width']) && $boxHeight > 0) {
            $params['width'] = round((float) $params['height'] * ($boxWidth / $boxHeight), 3);
            $svg->documentElement->setAttribute('width', (string) $params['width']);
        }
    }

  	// Set width attribute
    if (!empty($params['width'])) {
        $svg->documentElement->setAttribute('width', $params['width']);

    	// Automatically calculate the height if not set
        if (empty($params['height']) && $boxWidth > 0) {
            $params['height'] = round((float) $params['width'] * ($boxHeight / $boxWidth), 3);
            $svg->documentElement->setAttribute('height', (string) $params['height']);
        }
    }

	// Set class attribute
    if (!empty($params['class'])) {
        $svg->documentElement->setAttribute('class', $params['class']);
    }

	// Handle sprite usage
    // Note: Automatically use sprite if the file path contains "/sprite/"
    // Note: Sprites won't work in the admin since WP uses an iframe for the editor content
    if (
        ($params['sprite'] || str_contains($svg_path, '/sprite/')) &&
        !is_admin()
    ) {
		// Remove child nodes from original SVG
        while ($svg->documentElement->hasChildNodes()) {
            $svg->documentElement->removeChild($svg->documentElement->firstChild);
        }

		// Create <use> element
		// Note: createDocumentFragment() triggers an undefined-namespace warning
		// https://bugs.php.net/bug.php?id=44773
		// https://stackoverflow.com/a/59299852/673457
        $use = $svg->createElement('use');

		// Get filename by removing subfolders from path
        $filename = basename($params['file']);

        // All sprite symbols are prefixed with "sprite" (see svg-sprite.blade.php)
        $use->setAttribute('href', "#sprite-{$filename}");

        $svg->documentElement->appendChild($use);
    }

    $svg_markup = $svg->saveXML($svg->documentElement);

    // For inline SVGs, append a random suffix to IDs to avoid collisions on the page
    if (!$params['sprite'] && $params['unique_ids']) {
		// Generate random number
		// https://www.php.net/manual/en/function.random-bytes.php
        $guid = '_' . bin2hex(random_bytes(3));

		// Find IDs, save to $id_matches array
		// NOTE: $id_matches[0] includes the markup (e.g. id="foo")
		//       $id_matches[1] only includes the ID (e.g. foo)
        preg_match_all('/\bid="(\S+)"/', $svg_markup, $id_matches);

		// Use array_filter() to remove empty and falsey nested arrays
		// https://www.php.net/manual/en/function.array-filter.php
        if (!empty(array_filter($id_matches))) {
            // Append $guid to IDs
            foreach ($id_matches[0] as $id_match) {
				// $id_match includes the attribute and quotes, e.g. id="chev",
				// so we need to remove the closing quote using substr(), append
				// the guid, then add back the closing quote.
                $svg_markup = str_replace($id_match, substr($id_match, 0, -1) . $guid . '"', $svg_markup);
            }

            // Append $guid to ID references (e.g. xlink:href="#foo", filter="url(#foo)")
            foreach ($id_matches[1] as $id_match) {
				// Capture group 1: Either `(#` or `"#`
				// Capture group 2: ID string
				// Capture group 1: Either `"` or `)`
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
 * The text may contain inline HTML. Three layouts are handled so the markup
 * stays valid:
 *   - text fully wrapped in a single tag (<em>La Belle</em>) recurses into
 *     the inner content and re-wraps it;
 *   - text ending in closing tags (Read <a>more</a>) gets the <span>
 *     inserted before those closing tags;
 *   - spaces inside tag attributes are ignored when locating the last word.
 *
 * @param array $params {
 *     @type string $text
 *     @type string $class Class applied to the wrapping <span>. Default 'u-nowrap'.
 *     @type array  $svg   Params forwarded to svg().
 * }
 * @param int $depth Internal recursion counter for the fully-wrapped-tag case.
 *                   Each level strips one wrapping tag, so depth is bounded by
 *                   the input in practice; APPEND_ICON_MAX_DEPTH caps it
 *                   regardless to protect against pathological nesting.
 * @return string
 */
function append_icon($params, $depth = 0) {
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
    // Backfill svg defaults so callers can pass a partial svg array
    $params['svg'] = array_merge($defaults['svg'], $params['svg']);

	// Ensure text and an SVG filename are provided
    if (empty($params['text']) || empty($params['svg']['file'])) {
        return svg_error('Text or SVG file not specified.');
    }

	// Generate SVG markup
    $svg = svg($params['svg']);

    // Strip HTML tags for word counting purposes
    $text_plain = wp_strip_all_tags($params['text']);
    $words = explode(' ', $text_plain);

    // Single word
    if (count($words) === 1) {
        return '<span class="' . esc_attr($params['class']) . '">' . $params['text'] . $svg . '</span>';
    }

    // Check if text is entirely wrapped in an HTML tag (e.g. <em>Example Title</em>)
    // This regex matches opening tag, content, closing tag. Guarded by $depth so
    // malformed or pathologically nested input can't recurse without bound; once
    // the cap is hit the text falls through to the non-recursive handling below.
    if ($depth < APPEND_ICON_MAX_DEPTH
        && preg_match('/^<(\w+)(?:\s[^>]*)?>(.+)<\/\1>$/s', $params['text'], $matches)) {
        $tag_with_attrs = substr($params['text'], 0, strpos($params['text'], '>') + 1); // e.g. "<em>" or "<a href='...'>"
        $tag_name = $matches[1];
        $inner_content = $matches[2];

        // Process the inner content recursively
        $processed_inner = append_icon([
            'text' => $inner_content,
            'class' => $params['class'],
            'svg' => $params['svg'],
        ], $depth + 1);

        // Wrap the processed content back in the original tag
        return $tag_with_attrs . $processed_inner . '</' . $tag_name . '>';
    }

    // If text ends with one or more closing tags, insert the wrapper <span>
    // before the closing tags so inline markup remains valid.
    if (preg_match('/((?:<\/[a-zA-Z][\w:-]*>\s*)+)$/', $params['text'], $matches, PREG_OFFSET_CAPTURE)) {
        $closing_tags = $matches[1][0];
        $closing_tags_pos = $matches[1][1];
        $text_without_closing_tags = rtrim(substr($params['text'], 0, $closing_tags_pos));

        // Find last space not inside an HTML tag (strrpos could match spaces in tag attributes)
        preg_match_all('/ (?=[^<>]*(?:<|$))/s', $text_without_closing_tags, $space_matches, PREG_OFFSET_CAPTURE);
        $last_space_pos = !empty($space_matches[0]) ? end($space_matches[0])[1] : false;

        if ($last_space_pos !== false) {
            $text_before = substr($text_without_closing_tags, 0, $last_space_pos);
            $last_fragment = substr($text_without_closing_tags, $last_space_pos + 1);

            if ($last_fragment !== '' && strpos($last_fragment, '<') === false) {
                return $text_before . ' <span class="' . esc_attr($params['class']) . '">' . $last_fragment . $svg . '</span>' . $closing_tags;
            }
        }
    }

    // Find the last space not inside an HTML tag (strrpos could match spaces in tag attributes)
    preg_match_all('/ (?=[^<>]*(?:<|$))/s', $params['text'], $space_matches, PREG_OFFSET_CAPTURE);
    $last_space_pos = !empty($space_matches[0]) ? end($space_matches[0])[1] : false;

    if ($last_space_pos !== false) {
        // Split the original text preserving HTML
        $text_before = substr($params['text'], 0, $last_space_pos);
        $text_after = substr($params['text'], $last_space_pos + 1);
        return $text_before . ' <span class="' . esc_attr($params['class']) . '">' . $text_after . $svg . '</span>';
    }

    // Fallback: no space found, wrap everything
    return '<span class="' . esc_attr($params['class']) . '">' . $params['text'] . $svg . '</span>';
}
