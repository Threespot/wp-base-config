<?php

namespace Threespot\Wp\MuPlugins;

/**
 * Block editor (Gutenberg) configuration.
 *
 * Filters:
 *   threespot/blocks/category        — array { 'slug', 'title', 'icon' } for the custom block category
 *   threespot/blocks/pattern_category — array { 'slug', 'label' } for the custom pattern category
 *   threespot/blocks/editor_css_urls — array<string> URLs of editor stylesheets, loaded into the editor canvas in order
 *   threespot/blocks/editor_js_urls  — array<string> URLs of editor JS bundles, output as <script type="module"> tags in order
 *   threespot/blocks/vimeo_color     — string Vimeo brand color (default: 'ff5100')
 *                                       Used by Threespot\Wp\Helpers\format_video_iframe().
 *   threespot/blocks/disabled_heading_levels — array<int> heading levels to disable (default: [1])
 *   threespot/blocks/disabled_block_bindings_sources — array<string> block-bindings source names
 *                                       to unregister (default: ['core/post-data', 'core/post-meta'])
 *   threespot/blocks/cover_default_overlay_class — string class copied onto the cover wrapper when
 *                                       the cover has no overlay color (default: 'has-dark-background-color')
 */
class BlockConfig
{
    public const DEFAULT_DISABLED_BLOCK_BINDINGS_SOURCES = [
        'core/post-data',
        'core/post-meta',
    ];

    /**
     * Wire all block-editor hooks. Called once from bootstrap.php.
     */
    public static function register(): void
    {
        add_filter('style_loader_src', [self::class, 'forceAbsoluteStyleUrls'], 10, 2);
        add_filter('block_categories_all', [self::class, 'addBlockCategory'], 10);
        add_action('init', [self::class, 'addPatternCategory'], 10);
        add_action('init', [self::class, 'unregisterBlockBindingsSources'], 100);
        add_filter('the_content', [self::class, 'stripEmptyParagraphs']);
        // Priority 20: ACF registers wpautop on acf_the_content at priority 10 when the
        // plugin loads (after mu-plugins), so at 10 this would run before wpautop.
        add_filter('acf_the_content', [self::class, 'stripEmptyParagraphs'], 20);
        add_filter('wp_content_img_tag', [self::class, 'stripAutoSizesFromContent']);
        add_filter('wp_get_attachment_image_attributes', [self::class, 'stripAutoSizesFromAttachment']);
        add_filter('register_block_type_args', [self::class, 'disableHeadingLevels'], 10, 2);
        add_filter('render_block', [self::class, 'customizeBlockMarkup'], 10, 2);
        add_action('enqueue_block_editor_assets', [self::class, 'enqueueEditorAssets']);
        add_filter('block_editor_settings_all', [self::class, 'addEditorStyles']);
    }

    /**
     * Convert relative style URLs to absolute so they work in block pattern preview iframes.
     */
    public static function forceAbsoluteStyleUrls(string $src, string $handle): string
    {
        if (str_starts_with($src, '/')) {
            $src = home_url() . $src;
        }
        return $src;
    }

    /**
     * Prepend a custom block category.
     */
    public static function addBlockCategory(array $categories): array
    {
        $default = [
            'slug' => 'threespotblock',
            'title' => 'Custom Blocks',
            'icon' => null,
        ];

        $new_category = apply_filters('threespot/blocks/category', $default);

        array_unshift($categories, $new_category);

        return $categories;
    }

    /**
     * Register a matching custom block-pattern category.
     */
    public static function addPatternCategory(): void
    {
        if (!class_exists('WP_Block_Patterns_Registry')) {
            return;
        }

        $default = [
            'slug' => 'threespotblock',
            'label' => 'Custom Patterns',
        ];

        $pattern_category = apply_filters('threespot/blocks/pattern_category', $default);

        register_block_pattern_category($pattern_category['slug'], [
            'label' => $pattern_category['label'],
        ]);
    }

    /**
     * Unregister default block-bindings sources (WP 6.7+).
     *
     * Runs late on init so it fires after core's own registration.
     *
     * @link https://developer.wordpress.org/reference/functions/unregister_block_bindings_source/
     */
    public static function unregisterBlockBindingsSources(): void
    {
        if (!function_exists('unregister_block_bindings_source')) {
            return;
        }

        $sources = apply_filters(
            'threespot/blocks/disabled_block_bindings_sources',
            self::DEFAULT_DISABLED_BLOCK_BINDINGS_SOURCES
        );

        foreach ($sources as $source) {
            unregister_block_bindings_source($source);
        }
    }

    /**
     * Strip empty paragraphs from post content and ACF wysiwyg output.
     *
     * Catches Gutenberg's <p></p> / <p class=""></p> plus the TinyMCE-style
     * empties wpautop produces from ACF wysiwyg fields: <p>&nbsp;</p>,
     * whitespace-only, and <p><br></p>.
     *
     * CSS could hide them, but they still affect first/last/nth-child selectors.
     */
    public static function stripEmptyParagraphs(string $content): string
    {
        return preg_replace('/<p(?: class="")?>(?:\s|&nbsp;|<br ?\/?>)*<\/p>/', '', $content);
    }

    /**
     * Remove "auto" from sizes attribute (added in WP 6.7) — not yet supported in all browsers.
     *
     * @link https://core.trac.wordpress.org/ticket/61847#comment:23
     */
    public static function stripAutoSizesFromContent(string $image): string
    {
        return str_replace(' sizes="auto, ', ' sizes="', $image);
    }

    /**
     * Same "auto," strip as stripAutoSizesFromContent(), but applied to the
     * attribute array returned by `wp_get_attachment_image_attributes()`.
     * Covers images rendered outside post content (template tags, ACF, etc.).
     */
    public static function stripAutoSizesFromAttachment(array $attr): array
    {
        if (isset($attr['sizes'])) {
            $attr['sizes'] = preg_replace('/^auto, /', '', $attr['sizes']);
        }
        return $attr;
    }

    /**
     * Disable specified heading levels in core/heading (default: h1).
     * Requires Gutenberg 19+ (WP 6.7+).
     *
     * @link https://github.com/WordPress/gutenberg/pull/63535
     */
    public static function disableHeadingLevels(array $args, string $block_type): array
    {
        if ($block_type !== 'core/heading') {
            return $args;
        }

        $disabled = apply_filters('threespot/blocks/disabled_heading_levels', [1]);
        $allowed = array_values(array_diff([1, 2, 3, 4, 5, 6], $disabled));

        $args['attributes']['levelOptions']['default'] = $allowed;

        return $args;
    }

    /**
     * Add classes and attributes to default blocks' rendered markup.
     *
     * @link https://developer.wordpress.org/reference/hooks/render_block/
     */
    public static function customizeBlockMarkup(string $block_content, array $block): string
    {
        // Accordion: add u-richtext to panel wrapper (admin needs manual style in gutenberg.scss)
        if ($block['blockName'] === 'core/accordion') {
            $block_content = str_replace('wp-block-accordion-panel ', 'wp-block-accordion-panel u-richtext ', $block_content);
        }

        // Heading: <h1> → <h2> for accessibility (page title is the only <h1>)
        // https://github.com/WordPress/gutenberg/issues/15160#issuecomment-908586929
        if ($block['blockName'] === 'core/heading') {
            $block_content = str_replace('<h1', '<h2', $block_content);
            $block_content = str_replace('</h1', '</h2', $block_content);
        }

        // Cover: add u-richtext to inner container (admin needs manual style in gutenberg.scss)
        if ($block['blockName'] === 'core/cover') {
            $block_content = str_replace('wp-block-cover__inner-container', 'wp-block-cover__inner-container u-richtext', $block_content);
        }

        // Media & Text: add u-richtext + lazy-load images
        if ($block['blockName'] === 'core/media-text') {
            $block_content = str_replace('wp-block-media-text__content', 'wp-block-media-text__content u-richtext', $block_content);
            $block_content = str_replace('<img', '<img loading="lazy"', $block_content);
        }

        // Details: add u-richtext
        if ($block['blockName'] === 'core/details') {
            $block_content = str_replace('wp-block-details ', 'wp-block-details u-richtext ', $block_content);
        }

        // File: hide embed, add file info to button
        if ($block['blockName'] === 'core/file') {
            // Hide inaccessible PDF viewer from screen readers
            $block_content = str_replace('<object class="wp-block-file__embed"', '<object aria-hidden="true" class="wp-block-file__embed"', $block_content);

            $id = $block['attrs']['id'] ?? null;
            $path = $id ? get_attached_file($id) : false;

            // Append file type and size info to button, e.g. "Download (PDF 123KB)"
            if ($path && file_exists($path)) {
                $type = strtoupper(pathinfo($path, PATHINFO_EXTENSION));
                $size = \Threespot\Wp\Helpers\bytes_to_human_size((int) filesize($path), 0);
                $suffix = trim($type . ' ' . $size);

                if ($suffix !== '') {
                    // Append the meta to the existing download button label, whatever it is.
                    $block_content = preg_replace_callback(
                        '#(<a\b[^>]*\bwp-block-file__button\b[^>]*>)(.*?)(</a>)#s',
                        static function (array $m) use ($suffix): string {
                            return $m[1] . $m[2] . ' <span class="info">(' . $suffix . ')</span>' . $m[3];
                        },
                        $block_content,
                        1
                    );
                }
            }
        }

        // Embeds: lazy load, allow encrypted-media
        if ($block['blockName'] === 'core/embed') {
            // lazy-load iframes — https://web.dev/articles/iframe-lazy-loading
            $block_content = str_replace('<iframe ', '<iframe loading="lazy" ', $block_content);

            // Allow the Encrypted Media Extensions API so media providers (e.g. SoundCloud)
            // don’t trigger a Permissions-Policy console violation, like this:
            // [Violation] Permissions policy violation: encrypted-media is not allowed in this document.
            $block_content = str_replace('<iframe ', '<iframe allow="encrypted-media" ', $block_content);
        }

        return $block_content;
    }

    /**
     * Enqueue custom Gutenberg JS in the editor only.
     *
     * @link https://developer.wordpress.org/block-editor/how-to-guides/enqueueing-assets-in-the-editor/
     */
    public static function enqueueEditorAssets(): void
    {
        if (!is_admin()) {
            // enqueue_block_editor_assets also fires on the front end — bail there
            return;
        }

        $js_urls = array_filter(apply_filters('threespot/blocks/editor_js_urls', []));

        if (empty($js_urls)) {
            return;
        }

        // Output via admin_head so the scripts appear after <!DOCTYPE html> (avoids quirks mode).
        add_action('admin_head', function () use ($js_urls) {
            foreach ($js_urls as $js_url) {
                printf(
                    '<script type="module" src="%s"></script>' . "\n",
                    esc_url($js_url)
                );
            }
        });
    }

    /**
     * Load custom stylesheets into the editor canvas.
     */
    public static function addEditorStyles(array $settings): array
    {
        $css_urls = apply_filters('threespot/blocks/editor_css_urls', []);

        foreach ($css_urls as $css_url) {
            if (empty($css_url)) {
                continue;
            }
            $settings['styles'][] = [
                'css' => "@import url('" . esc_url($css_url) . "')",
            ];
        }

        return $settings;
    }
}
