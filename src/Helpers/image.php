<?php

namespace Threespot\Wp\Helpers;

/**
 * Calculate height from width based on aspect ratio.
 *
 * @param int $width
 * @param string $ratio  Format e.g. '16:9', '1:1', '3:2'
 * @return int
 */
function calculate_height($width, $ratio) {
    $parts = explode(':', $ratio);
    if (count($parts) !== 2) {
        return $width; // Fallback to square if invalid ratio
    }

    $ratio_width = (int) $parts[0];
    $ratio_height = (int) $parts[1];
    return (int) round($width * ($ratio_height / $ratio_width));
}

/**
 * Get all registered image sizes with their dimensions.
 * Reads the site-defined IMAGE_SIZES constant.
 *
 * @return array<string, array{0:int, 1:int, 2:int|bool}>
 */
function get_registered_image_sizes() {
    static $sizes = null;

    if ($sizes !== null) {
        return $sizes;
    }

    $sizes = [];

    if (!defined('IMAGE_SIZES')) {
        return $sizes;
    }

    foreach (IMAGE_SIZES as $base_name => $config) {
        $ratio = $config['ratio'];
        $crop = $config['crop'];

        foreach ($config['widths'] as $width) {
            // When crop is disabled, height = 0 keeps the original aspect ratio
            $height = $crop ? calculate_height($width, $ratio) : 0;
            $sizes["{$base_name}_{$width}"] = [$width, $height, $crop];
        }
    }

    return $sizes;
}

/**
 * 1px transparent GIF as a base64 data URI.
 *
 * @return string
 */
function blank_gif() {
    return 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
}

/**
 * Get the file extension of an image attachment, lowercased.
 *
 * @param int $attachment_id
 * @return string|false
 */
function get_image_file_type($attachment_id) {
    $metadata = wp_get_attachment_metadata($attachment_id);

    if (!$metadata || !isset($metadata['file'])) {
        return false;
    }

    return strtolower(pathinfo($metadata['file'], PATHINFO_EXTENSION));
}

/**
 * Get array of image size names from a base name.
 * Compatible with Better Image Sizes plugin.
 *
 * @param int $img_id
 * @param string $sizes Base size name (e.g. 'square', 'sixteen_nine')
 * @return array|false
 */
function bis_get_sizes($img_id, $sizes) {
    if (empty($img_id) || empty($sizes) || !is_string($sizes)) {
        return false;
    }

    $all_sizes = get_registered_image_sizes();

    // Match base_name followed by underscore + digits only,
    // so 'square' doesn't match 'square_scaled_360'.
    $matched_sizes = preg_grep("/^{$sizes}_\d+$/", array_keys($all_sizes));

    if (empty($matched_sizes)) {
        return false;
    }

    return $matched_sizes;
}

/**
 * Backwards-compat alias for fly_get_sizes().
 *
 * @deprecated Use bis_get_sizes() instead.
 */
function fly_get_sizes($img_id, $sizes) {
    return bis_get_sizes($img_id, $sizes);
}

/**
 * Generate "srcset" attribute using array of image sizes (see IMAGE_SIZES constant).
 * Compatible with Better Image Sizes plugin.
 *
 * @param int $img_id
 * @param array|string $sizes        Preset name OR custom size array.
 *                                   Custom format: [width => [width, height, crop], ...]
 * @param array|null   $focal_point  Optional focal point [x, y] (0.0-1.0) override.
 * @return string
 */
function bis_srcset($img_id, $sizes, $focal_point = null) {
    if (empty($img_id) || empty($sizes)) {
        return '';
    }

    if (is_string($sizes)) {
        $sizes = bis_get_sizes($img_id, $sizes);
    }

    if (is_array($sizes)) {
        $first_value = reset($sizes);

        if (is_array($first_value) && isset($first_value[0]) && isset($first_value[1])) {
            // Custom sizes: convert to assoc array keyed by width
            $custom_sizes = $sizes;
            $sizes = [];

            foreach ($custom_sizes as $key => $size_def) {
                if (is_numeric($key)) {
                    $sizes[$size_def[0]] = $size_def;
                } else {
                    $sizes[$key] = $size_def;
                }
            }
        }
    }

    $srcset = [];
    $all_sizes = get_registered_image_sizes();

    // Three ways a size can be specified, tried in order:
    //   1. Custom inline definition — [width, height, crop?] tuple
    //   2. Registered size name (string) — looked up in IMAGE_SIZES via get_registered_image_sizes()
    //   3. Legacy Fly plugin fallback — for sites that haven't migrated to BIS yet
    foreach ($sizes as $size) {
        if (is_array($size) && count($size) >= 2) {
            // Custom size: [width, height, crop, ...]
            $width = $size[0];
            $height = $size[1];
            $crop = $size[2] ?? true;

            $crop_param = resolve_bis_crop_param($img_id, $crop, $focal_point);
            $img = bis_get_attachment_image_src($img_id, [$width, $height], $crop_param);
        } elseif (isset($all_sizes[$size])) {
            list($width, $height, $crop) = $all_sizes[$size];

            $crop_param = resolve_bis_crop_param($img_id, $crop, $focal_point);
            $img = bis_get_attachment_image_src($img_id, [$width, $height], $crop_param);
        } else {
            // Fallback for sites still on the legacy Fly Dynamic Image Resizer plugin
            $img = fly_get_attachment_image_src($img_id, $size);
        }

        if (!empty($img)) {
            // BIS sometimes returns the wrong width due to WP max-image-size settings —
            // prefer the requested width when they disagree.
            $width_descriptor = $img['width'];

            if (is_array($size) && isset($size[0])) {
                $requested_width = $size[0];
                if ($width_descriptor !== $requested_width) {
                    $width_descriptor = $requested_width;
                }
            } elseif (isset($all_sizes[$size])) {
                list($preset_width) = $all_sizes[$size];
                if ($width_descriptor !== $preset_width) {
                    $width_descriptor = $preset_width;
                }
            }

            $srcset[] = "{$img['src']} {$width_descriptor}w";
        }
    }

    return implode(',', $srcset);
}

/**
 * Backwards-compat alias for fly_srcset().
 *
 * @deprecated Use bis_srcset() instead.
 */
function fly_srcset($img_id, $sizes, $focal_point = null) {
    return bis_srcset($img_id, $sizes, $focal_point);
}

/**
 * Convert an associative array to HTML attribute formatting.
 *
 * @param array $attrs
 * @return string Leading-space-prefixed attribute string, or '' if $attrs is empty.
 */
function buildAttributes(array $attrs = []) {
    if (empty($attrs)) {
        return '';
    }

    // Only run wptexturize() on attributes that may contain user-facing prose.
    // Class/id/src/etc. must NOT get curly quotes — that would break selectors and URLs.
    $textured = ['alt', 'title', 'aria-label', 'data-caption'];
    $pairs = [];

    foreach ($attrs as $attr => $value) {
        // Convert straight quotes to curly quotes for user-generated text
        if (!empty($value) && in_array($attr, $textured, true) && function_exists('wptexturize')) {
            $value = wptexturize($value);
        }

        $pairs[] = sprintf('%s="%s"', $attr, $value);
    }

    return ' ' . implode(' ', $pairs);
}

/**
 * Generate image tag markup using array of image sizes (see IMAGE_SIZES constant).
 * Compatible with Better Image Sizes plugin.
 *
 * @param int $img_id
 * @param array $attrs Attributes including:
 *   - 'ratio': Size name (e.g. 'square', 'sixteen_nine') OR custom size array.
 *   - 'focal_point': Optional [x, y] (0.0-1.0) override.
 *   - Standard img attributes ('class', 'alt', 'loading', etc.)
 * @return string
 */
function img_tag($img_id, array $attrs = []) {
    if (empty($img_id) || empty($attrs)) {
        return '';
    }

    // Support "crop" and "srcset" aliases for "ratio" (older Fly plugin sites)
    if (empty($attrs['ratio'])) {
        if (!empty($attrs['crop'])) {
            $attrs['ratio'] = $attrs['crop'];
        } elseif (!empty($attrs['srcset'])) {
            $attrs['ratio'] = $attrs['srcset'];
        }
    }

    unset($attrs['crop'], $attrs['srcset']);

    $focal_point = $attrs['focal_point'] ?? null;

    if (empty($attrs['ratio'])) {
        unset($attrs['ratio']);

        if (empty($attrs['src'])) {
            return '';
        }
    } else {
        if (!array_key_exists('src', $attrs) || empty($attrs['src'])) {
            if (array_key_exists('blank_src', $attrs) && $attrs['blank_src'] === true) {
                $attrs['src'] = blank_gif();
            } else {
                if (is_array($attrs['ratio'])) {
                    $first_value = reset($attrs['ratio']);

                    if (is_array($first_value)) {
                        // Custom size definition (assoc or indexed)
                        $img_size = $first_value;
                    } else {
                        // Legacy array of size names
                        $img_size = $attrs['ratio'][0];
                    }
                } elseif (is_string($attrs['ratio'])) {
                    $img_size = current(bis_get_sizes($img_id, $attrs['ratio']));
                } else {
                    return '';
                }

                $all_sizes = get_registered_image_sizes();

                if (is_array($img_size) && count($img_size) >= 2) {
                    $width = $img_size[0];
                    $height = $img_size[1];
                    $crop = $img_size[2] ?? true;

                    $crop_param = resolve_bis_crop_param($img_id, $crop, $focal_point);
                    $img_src = bis_get_attachment_image_src($img_id, [$width, $height], $crop_param);
                } elseif (isset($all_sizes[$img_size])) {
                    list($width, $height, $crop) = $all_sizes[$img_size];

                    $crop_param = resolve_bis_crop_param($img_id, $crop, $focal_point);
                    $img_src = bis_get_attachment_image_src($img_id, [$width, $height], $crop_param);
                } else {
                    $img_src = fly_get_attachment_image_src($img_id, $img_size);
                }

                if (empty($img_src)) {
                    return '';
                }

                $attrs['src'] = $img_src['src'];
            }
        }

        if (!empty($attrs['alt']) && substr($attrs['alt'], -1) !== '.') {
            $attrs['alt'] = str_replace('alt="', '', $attrs['alt']);
            $attrs['alt'] = esc_attr($attrs['alt']);
            // Append period to improve screen reader experience — https://axesslab.com/alt-texts/
            $attrs['alt'] .= '.';
        }

        $attrs['srcset'] = bis_srcset($img_id, $attrs['ratio'], $focal_point);
    }

    if (array_key_exists('blank_src', $attrs) && $attrs['blank_src'] === true) {
        // Blank GIF width must be at least 2w; 5w covers 4x DPR displays.
        $attrs['srcset'] = blank_gif() . ' 5w,' . $attrs['srcset'];
        unset($attrs['blank_src']);
    }

    // Add --focal-point CSS variable via inline style (BIS-provided)
    $stored_focal_point = function_exists('sanitize_focal_point')
        ? sanitize_focal_point(get_post_meta($img_id, 'focal_point', true))
        : [0.5, 0.5];

    $focal_point_x = $stored_focal_point[0] * 100;
    $focal_point_y = $stored_focal_point[1] * 100;
    $focal_point_css = "--focal-point: $focal_point_x% $focal_point_y%;";

    if (array_key_exists('style', $attrs) && !empty($attrs['style'])) {
        $attrs['style'] = $focal_point_css . $attrs['style'];
    } else {
        $attrs['style'] = $focal_point_css;
    }

    // JS-driven lazy load: swap src/srcset to data-*.
    if (array_key_exists('lazy_load', $attrs) && $attrs['lazy_load'] === true) {
        $attrs['data-src'] = $attrs['src'];
        $attrs['data-srcset'] = $attrs['srcset'];
        unset($attrs['src'], $attrs['srcset'], $attrs['lazy_load']);
    }

    unset($attrs['ratio'], $attrs['focal_point']);

    $attrs = array_merge([
        'alt' => get_img_alt($img_id) ?? '',
    ], $attrs);

    return sprintf('<img%s>', buildAttributes($attrs));
}

/**
 * Get image alt text.
 *
 * @param int $img_id
 * @return string|false
 */
function get_img_alt($img_id) {
    if (empty($img_id)) {
        return false;
    }

    return get_post_meta($img_id, '_wp_attachment_image_alt', true) ?? '';
}

/**
 * Get image focal point (Better Image Sizes plugin).
 *
 * @param int $img_id
 * @return array{0: float, 1: float}|false
 */
function get_img_focal_point($img_id) {
    if (empty($img_id)) {
        return false;
    }

    $focal_point = get_post_meta($img_id, 'focal_point', true);

    if (function_exists('sanitize_focal_point')) {
        return sanitize_focal_point($focal_point);
    }

    if (is_array($focal_point) && count($focal_point) === 2) {
        return [
            max(0, min(1, (float) $focal_point[0])),
            max(0, min(1, (float) $focal_point[1])),
        ];
    }

    return false;
}

/**
 * Resolve crop parameter for BIS.
 *
 * @param int $img_id
 * @param bool|int $crop
 * @param array|null $focal_point
 * @return array{0: float, 1: float}|false
 */
function resolve_bis_crop_param($img_id, $crop, $focal_point = null) {
    if (!$crop) {
        return false;
    }

    if ($focal_point !== null) {
        return $focal_point;
    }

    $saved_focal_point = get_img_focal_point($img_id);

    // BIS requires [x, y] for cropping; bool true would skip focal-point logic.
    return $saved_focal_point ?: [0.5, 0.5];
}

/**
 * Set image alt text.
 *
 * @param int $img_id
 * @param string $alt_text
 * @return int|bool
 */
function set_img_alt($img_id, $alt_text) {
    if (empty($img_id) || empty($alt_text)) {
        return false;
    }

    return update_post_meta($img_id, '_wp_attachment_image_alt', $alt_text);
}
