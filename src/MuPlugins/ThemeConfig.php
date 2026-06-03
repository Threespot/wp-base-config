<?php

namespace Threespot\Wp\MuPlugins;

/**
 * Generic theme-level setup (post type support, archive titles, excerpt format, etc.).
 *
 * Nav menu registration intentionally stays in the site theme — menus differ per project.
 *
 * Filters:
 *   threespot/theme/excerpt_length — int (default: 25)
 *   threespot/theme/excerpt_more   — string (default: '…')
 */
class ThemeConfig
{
    /**
     * Wire all theme-level hooks. Called once from bootstrap.php.
     *
     * `after_setup_theme` runs at priority 20 so the theme's own setup (priority 10)
     * gets a chance to register its menus, image sizes, etc. before we pile on.
     */
    public static function register(): void
    {
        add_action('after_setup_theme', [self::class, 'addThemeSupport'], 20);
        add_filter('get_the_archive_title', [self::class, 'filterArchiveTitle']);
        add_filter('excerpt_length', [self::class, 'filterExcerptLength']);
        add_filter('excerpt_more', [self::class, 'filterExcerptMore']);
        add_filter('protected_title_format', [self::class, 'filterProtectedTitleFormat']);
        add_filter('wpseo_title', [self::class, 'filterYoastTitle']);
        add_action('save_post', [self::class, 'removeDefaultCategoryWhenOtherPresent']);
    }

    /**
     * Register the theme-support flags every Threespot site wants.
     * Runs on `after_setup_theme`, the canonical place for these calls.
     */
    public static function addThemeSupport(): void
    {
        // Disable WP's default block patterns
        remove_theme_support('core-block-patterns');

        add_theme_support('title-tag');
        add_theme_support('post-thumbnails');
        add_theme_support('responsive-embeds');
        add_theme_support('align-wide');

        add_post_type_support('page', 'excerpt');
    }

    /**
     * Strip "Category:", "Tag:", "Author:" prefixes from archive titles.
     *
     * @link https://wordpress.stackexchange.com/a/179590/185703
     */
    public static function filterArchiveTitle(string $title): string
    {
        if (is_tax()) {
            return single_term_title('', false);
        }
        if (is_post_type_archive()) {
            return post_type_archive_title('', false);
        }
        if (is_author()) {
            return 'Posts by ' . get_the_author();
        }
        if (is_category()) {
            return single_cat_title('', false);
        }
        if (is_tag()) {
            return single_tag_title('Tag: ', false);
        }
        return $title;
    }

    /**
     * Excerpt word count. Filter callback for WP's `excerpt_length`.
     */
    public static function filterExcerptLength(): int
    {
        return (int) apply_filters('threespot/theme/excerpt_length', 25);
    }

    /**
     * String appended to truncated excerpts. Filter callback for WP's `excerpt_more`.
     */
    public static function filterExcerptMore(): string
    {
        return (string) apply_filters('threespot/theme/excerpt_more', '…');
    }

    /**
     * Remove the "Protected:" prefix from password-protected pages.
     */
    public static function filterProtectedTitleFormat(): string
    {
        return '%s';
    }

    /**
     * Strip HTML from document title via Yoast hook.
     */
    public static function filterYoastTitle(string $title): string
    {
        return wp_strip_all_tags(html_entity_decode($title, ENT_QUOTES | ENT_HTML5));
    }

    /**
     * Remove the default "Uncategorized" category when another is set.
     *
     * @link https://wordpress.stackexchange.com/a/254691/185703
     */
    public static function removeDefaultCategoryWhenOtherPresent(int $post_id): void
    {
        // default_category is stored as a string — cast to int for comparison
        $default_category = (int) get_option('default_category');
        $has_default_category = has_category($default_category, $post_id);
        $post_categories = get_the_category($post_id);

        if ($has_default_category && count($post_categories) > 1) {
            wp_remove_object_terms($post_id, $default_category, 'category');
        }
    }
}
