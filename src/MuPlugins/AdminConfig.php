<?php

namespace Threespot\Wp\MuPlugins;

use function Threespot\Wp\Helpers\get_custom_post_types;

/**
 * WP admin polish: dashboard widgets, admin bar, customizer sections, screen options,
 * Yoast metabox priority, robots.txt, comments disabling, SVG uploads, etc.
 *
 * Public override helpers live in src/PublicApi/functions.php — sites should reach for
 * the threespot_keep_X / threespot_remove_X helpers rather than these filters directly.
 *
 * Filters (subtractive lists):
 *   threespot/admin/dashboard_widgets_removed       — array<string>
 *   threespot/admin/admin_bar_nodes_removed         — array<string>
 *   threespot/admin/admin_bar_nodes_removed_frontend — array<string>
 *   threespot/admin/admin_bar_nodes_removed_non_internal — array<string>
 *   threespot/admin/admin_bar_nodes_removed_production — array<string>
 *   threespot/admin/customizer_sections_removed     — array<string>
 *   threespot/admin/user_roles_removed              — array<string>
 *   threespot/admin/menu_pages_removed              — array<string>
 *   threespot/admin/closed_metaboxes                — array<string>
 *   threespot/admin/site_status_tests_removed       — array<string, array<string>>  (group => [tests])
 *   threespot/admin/screen_options_hidden_columns   — array<string>
 *   threespot/admin/taxonomies_hidden_from_nav_menus — array<string>
 *
 * Filters (scalar / predicate):
 *   threespot/admin/all_css_url                     — string|null URL for stylesheet loaded on every admin page
 *   threespot/admin/fields_css_url                  — string|null URL for stylesheet loaded only on hooks in fields_css_hooks
 *   threespot/admin/fields_css_hooks                — array<string> admin hooks where fields_css_url is enqueued
 *   threespot/admin/is_internal_user                — bool predicate, default false
 *   threespot/admin/screen_options_per_page         — int (default 50)
 *   threespot/admin/tinymce_body_class              — string (default 'u-richtext')
 *   threespot/admin/disable_comments_post_types     — array<string> post types to strip comment support from
 *   threespot/admin/allow_svg_uploads               — bool (default true)
 */
class AdminConfig
{
    private const DEFAULT_DASHBOARD_WIDGETS_REMOVED = [
        'welcome_panel',
        'dashboard_primary',
        'dashboard_quick_press',
        'dashboard_secondary',
        'wpseo-dashboard-overview',
    ];

    private const DEFAULT_ADMIN_BAR_NODES_REMOVED = [
        'comments',
        'customize',
        'fwp-cache',        // FacetWP
        'gform-forms',      // Gravity Forms
        'searchwp',         // SearchWP
        'updates',
        'wp-logo',
        'wpseo-menu',       // Yoast
    ];

    private const DEFAULT_ADMIN_BAR_NODES_REMOVED_FRONTEND = [
        'search',
        'updates',
        'duplicate-post',
    ];

    private const DEFAULT_ADMIN_BAR_NODES_REMOVED_NON_INTERNAL = [
        'query-monitor',
    ];

    private const DEFAULT_ADMIN_BAR_NODES_REMOVED_PRODUCTION = [
        'pantheon-hud',
    ];

    private const DEFAULT_CUSTOMIZER_SECTIONS_REMOVED = [
        'colors',
        'custom_css',
        'static_front_page',
    ];

    private const DEFAULT_USER_ROLES_REMOVED = [
        'wpseo_manager',
        'wpseo_editor',
    ];

    private const DEFAULT_MENU_PAGES_REMOVED = [
        'edit-comments.php',
    ];

    private const DEFAULT_CLOSED_METABOXES = [
        'ame-cpe-content-permissions',  // Admin Menu Editor content permissions
        'wpseo_meta',
    ];

    private const DEFAULT_SITE_STATUS_TESTS_REMOVED = [
        'async' => ['background_updates'],
        'direct' => ['available_updates_disk_space', 'theme_version', 'update_temp_backup_writable'],
    ];

    private const DEFAULT_SCREEN_OPTIONS_HIDDEN_COLUMNS = [
        'wpseo-focuskw',
        'wpseo-linked',
        'wpseo-links',
        'wpseo-metadesc',
        'wpseo-score',
        'wpseo-score-readability',
        'wpseo-title',
    ];

    private const DEFAULT_TAXONOMIES_HIDDEN_FROM_NAV_MENUS = [
        'category',
    ];

    private const DEFAULT_FIELDS_CSS_HOOKS = [
        'edit.php',
        'edit-tags.php',
        'index.php',                       // Dashboard
        'post-new.php',
        'post.php',
        'toplevel_page_theme-settings',
        'toplevel_page_wsal-auditlog',     // WP Activity Log
    ];

    /**
     * Wire every admin-side hook. Called once from bootstrap.php.
     *
     * Some entries here aren't `add_action`/`add_filter` calls — they're
     * top-level side effects (e.g. `remove_action('wp_head', 'wp_generator')`)
     * that need to fire on every request, so they live in register() rather
     * than being deferred behind a hook.
     */
    public static function register(): void
    {
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAdminStyles']);
        add_action('wp_dashboard_setup', [self::class, 'removeDashboardWidgets']);
        add_action('admin_menu', [self::class, 'removeMenuPages']);
        add_action('admin_init', [self::class, 'disableCommentsAndRedirect'], 11);
        add_action('admin_init', [self::class, 'collapseClosedMetaboxes']);
        add_action('wp_before_admin_bar_render', [self::class, 'customizeAdminBar'], 99);
        add_filter('admin_footer_text', '__return_null');
        add_action('customize_register', [self::class, 'removeCustomizerSections'], 50);
        add_action('restrict_manage_posts', [self::class, 'addAuthorFilter']);
        add_filter('wpseo_metabox_prio', [self::class, 'lowerYoastMetaboxPriority']);
        add_action('init', [self::class, 'removeUserRoles']);
        add_filter('tiny_mce_before_init', [self::class, 'customizeTinyMce']);
        add_filter('site_status_tests', [self::class, 'removeSiteStatusTests']);

        // Remove WP version from <head>
        remove_action('wp_head', 'wp_generator');

        // Disable XML-RPC — https://kinsta.com/blog/xmlrpc-php/
        add_filter('xmlrpc_enabled', '__return_false');

        add_action('user_register', [self::class, 'setDefaultScreenOptions']);
        add_filter('robots_txt', [self::class, 'customizeRobotsTxt'], 100000, 2);
        add_filter('register_taxonomy_args', [self::class, 'hideTaxonomiesFromNavMenus'], 10, 2);

        // Allow SVG uploads (moved from svg-helpers in the legacy theme)
        add_filter('upload_mimes', [self::class, 'allowSvgUploads']);
    }

    /**
     * Enqueue admin stylesheets. Runs on every admin page via `admin_enqueue_scripts`,
     * so `$hook` is the current screen's hook suffix (e.g. `edit.php`, `post.php`).
     *
     * Both stylesheets are opt-in: the package ships no CSS itself. The consuming
     * theme supplies URLs via `add_filter('threespot/admin/<key>_css_url', ...)`;
     * with no filter wired up, the URL is `null` and the enqueue is skipped.
     *
     *   - `all_css_url`    — enqueued on every admin page (no hook check).
     *   - `fields_css_url` — enqueued only when `$hook` is in `fields_css_hooks`.
     */
    public static function enqueueAdminStyles(string $hook): void
    {
        // `$admin_css_all_url` is whatever the theme returned from the `all_css_url`
        // filter, or null if no filter is registered. `admin_enqueue_scripts` fires
        // on every admin page, so there's no hook check — if a URL exists, it loads.
        $admin_css_all_url = apply_filters('threespot/admin/all_css_url', null);

        if (!empty($admin_css_all_url)) {
            wp_enqueue_style('threespot-admin-all', $admin_css_all_url, [], null);
        }

        // Field-editor stylesheet — restricted to the listing/edit/dashboard hooks
        // in DEFAULT_FIELDS_CSS_HOOKS so it doesn't bloat unrelated admin pages.
        $admin_css_fields_url = apply_filters('threespot/admin/fields_css_url', null);

        if (empty($admin_css_fields_url)) {
            return;
        }

        $admin_css_fields_hooks = apply_filters('threespot/admin/fields_css_hooks', self::DEFAULT_FIELDS_CSS_HOOKS);

        if (!in_array($hook, $admin_css_fields_hooks, true)) {
            return;
        }

        wp_enqueue_style('threespot-admin-fields', $admin_css_fields_url, [], null);
    }

    /**
     * Strip the dashboard widgets listed by the `dashboard_widgets_removed` filter.
     * Runs on `wp_dashboard_setup` (after widgets are registered, before render).
     *
     * The "welcome_panel" pseudo-widget isn't a metabox — it's hooked to its own
     * action — so it gets a dedicated removal path.
     */
    public static function removeDashboardWidgets(): void
    {
        $widgets = apply_filters('threespot/admin/dashboard_widgets_removed', self::DEFAULT_DASHBOARD_WIDGETS_REMOVED);

        foreach ($widgets as $id) {
            if ($id === 'welcome_panel') {
                remove_action('welcome_panel', 'wp_welcome_panel');
                continue;
            }

            // We don't know whether each widget lives in 'normal' or 'side' — calls
            // for the wrong context are no-ops, so just try both.
            remove_meta_box($id, 'dashboard', 'normal');
            remove_meta_box($id, 'dashboard', 'side');
        }
    }

    /**
     * Drop top-level admin menu items listed by the `menu_pages_removed` filter.
     * Runs on `admin_menu`, where the items have already been added by core/plugins.
     */
    public static function removeMenuPages(): void
    {
        $menu_pages = apply_filters('threespot/admin/menu_pages_removed', self::DEFAULT_MENU_PAGES_REMOVED);

        foreach ($menu_pages as $page) {
            remove_menu_page($page);
        }
    }

    /**
     * Disable comment support on every custom post type and redirect anyone
     * who manually navigates to the comments admin screen.
     *
     * `is_admin()` guards against accidentally running on the front end —
     * `admin_init` is admin-only, but a defensive check costs nothing.
     */
    public static function disableCommentsAndRedirect(): void
    {
        if (!is_admin()) {
            return;
        }

        $post_types = apply_filters('threespot/admin/disable_comments_post_types', get_custom_post_types());

        foreach ($post_types as $post_type) {
            if (post_type_supports($post_type, 'comments')) {
                remove_post_type_support($post_type, 'comments');
                remove_post_type_support($post_type, 'trackbacks');
            }
        }

        // Redirect anyone hitting the comments admin page
        // https://gist.github.com/mattclements/eab5ef656b2f946c4bfb
        global $pagenow;
        if ($pagenow === 'edit-comments.php') {
            wp_safe_redirect(admin_url());
            exit;
        }
    }

    /**
     * Default-collapse the metaboxes listed by the `closed_metaboxes` filter
     * on every editable post type.
     *
     * WP stores per-user "closed metaboxes" in user options under
     * `closedpostboxes_<post_type>`. Hooking each `get_user_option_*` filter lets
     * us prepend our defaults without writing to the database, so a user who
     * later expands a box has their preference preserved.
     */
    public static function collapseClosedMetaboxes(): void
    {
        $post_types = get_custom_post_types();
        $closed_ids = apply_filters('threespot/admin/closed_metaboxes', self::DEFAULT_CLOSED_METABOXES);

        foreach ($post_types as $post_type) {
            add_filter("get_user_option_closedpostboxes_{$post_type}", function ($closed) use ($closed_ids) {
                $closed = $closed ?: [];
                return array_values(array_unique(array_merge($closed, $closed_ids)));
            }, 10, 1);
        }
    }

    /**
     * Trim the admin bar: always-removed nodes, plus context-specific lists
     * for frontend, non-internal users, and Pantheon production.
     *
     * Runs late (priority 99) on `wp_before_admin_bar_render` so plugin-added
     * nodes are already registered by the time we try to remove them.
     */
    public static function customizeAdminBar(): void
    {
        global $wp_admin_bar;

        $current_user = wp_get_current_user();
        $is_internal = (bool) apply_filters('threespot/admin/is_internal_user', false, $current_user);

        $always_removed = apply_filters('threespot/admin/admin_bar_nodes_removed', self::DEFAULT_ADMIN_BAR_NODES_REMOVED);
        foreach ($always_removed as $node) {
            $wp_admin_bar->remove_node($node);
        }

        // Remove "Howdy," prefix from greeting
        $my_account = $wp_admin_bar->get_node('my-account');
        if ($my_account) {
            $wp_admin_bar->add_node([
                'id' => 'my-account',
                'title' => str_replace('Howdy,', '', $my_account->title),
            ]);
        }

        if (!$is_internal) {
            $non_internal = apply_filters(
                'threespot/admin/admin_bar_nodes_removed_non_internal',
                self::DEFAULT_ADMIN_BAR_NODES_REMOVED_NON_INTERNAL
            );
            foreach ($non_internal as $node) {
                $wp_admin_bar->remove_node($node);
            }
        }

        if (!is_admin()) {
            $frontend = apply_filters(
                'threespot/admin/admin_bar_nodes_removed_frontend',
                self::DEFAULT_ADMIN_BAR_NODES_REMOVED_FRONTEND
            );
            foreach ($frontend as $node) {
                $wp_admin_bar->remove_node($node);
            }
        }

        if (isset($_ENV['PANTHEON_ENVIRONMENT']) && $_ENV['PANTHEON_ENVIRONMENT'] === 'live') {
            $production = apply_filters(
                'threespot/admin/admin_bar_nodes_removed_production',
                self::DEFAULT_ADMIN_BAR_NODES_REMOVED_PRODUCTION
            );
            foreach ($production as $node) {
                $wp_admin_bar->remove_node($node);
            }
        }
    }

    /**
     * Strip the Customizer sections listed by the `customizer_sections_removed`
     * filter. Runs late (priority 50) on `customize_register` so the sections
     * are already registered by core/plugins by the time we remove them.
     *
     * @param \WP_Customize_Manager $wp_customize
     */
    public static function removeCustomizerSections($wp_customize): void
    {
        $sections = apply_filters('threespot/admin/customizer_sections_removed', self::DEFAULT_CUSTOMIZER_SECTIONS_REMOVED);

        foreach ($sections as $section) {
            $wp_customize->remove_section($section);
        }
    }

    /**
     * Add "author" filter dropdown to admin listing pages.
     *
     * @link https://rudrastyh.com/wordpress/filter-posts-by-author.html
     */
    public static function addAuthorFilter(): void
    {
        $params = [
            'name' => 'author',
            'show_option_all' => 'All authors',
        ];

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['user'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $params['selected'] = absint(wp_unslash($_GET['user']));
        }

        wp_dropdown_users($params);
    }

    /**
     * Push the Yoast SEO metabox below other metaboxes. Filter callback for
     * Yoast's `wpseo_metabox_prio` — returning 'low' moves it to the side column.
     */
    public static function lowerYoastMetaboxPriority(): string
    {
        return 'low';
    }

    /**
     * Drop user roles listed by the `user_roles_removed` filter.
     *
     * `remove_role` writes to the database, so the `get_role` guard avoids
     * unnecessary writes when the role is already gone.
     */
    public static function removeUserRoles(): void
    {
        $roles = apply_filters('threespot/admin/user_roles_removed', self::DEFAULT_USER_ROLES_REMOVED);

        foreach ($roles as $role) {
            if (get_role($role)) {
                remove_role($role);
            }
        }
    }

    /**
     * Add a body_class to TinyMCE so rich-text styles apply inside the editor iframe.
     *
     * @link https://developer.wordpress.org/reference/hooks/tiny_mce_before_init/
     */
    public static function customizeTinyMce(array $mceInit): array
    {
        $mceInit['body_class'] = apply_filters('threespot/admin/tinymce_body_class', 'u-richtext');
        return $mceInit;
    }

    /**
     * Remove Pantheon-noise warnings from Site Health.
     *
     * @link https://docs.pantheon.io/wordpress-known-issues#automatic-updates
     */
    public static function removeSiteStatusTests(array $tests): array
    {
        $removed = apply_filters('threespot/admin/site_status_tests_removed', self::DEFAULT_SITE_STATUS_TESTS_REMOVED);

        foreach ($removed as $group => $test_names) {
            foreach ($test_names as $test) {
                unset($tests[$group][$test]);
            }
        }

        return $tests;
    }

    /**
     * Seed sensible defaults for a newly registered user's Screen Options.
     * Runs on `user_register` (fires once, the moment the user is created).
     *
     * Two settings per post type:
     *   - items per page on the listing screen (default 50)
     *   - hidden columns on the listing screen (default: Yoast SEO columns)
     *
     * Existing users aren't touched — they keep whatever they've already saved.
     *
     * @param int $user_id ID of the newly registered user.
     */
    public static function setDefaultScreenOptions(int $user_id): void
    {
        $post_types = get_custom_post_types();
        $items_per_page = (int) apply_filters('threespot/admin/screen_options_per_page', 50);
        $hide_columns = apply_filters('threespot/admin/screen_options_hidden_columns', self::DEFAULT_SCREEN_OPTIONS_HIDDEN_COLUMNS);

        foreach ($post_types as $post_type) {
            $meta_key = "edit_{$post_type}_per_page";
            $current_value = get_user_meta($user_id, $meta_key, true);

            if (empty($current_value)) {
                update_user_meta($user_id, $meta_key, $items_per_page);
            }

            $hidden_columns_key = "manageedit-{$post_type}columnshidden";
            $hidden_columns = get_user_meta($user_id, $hidden_columns_key, true);

            if (!is_array($hidden_columns)) {
                $hidden_columns = [];
            }

            foreach ($hide_columns as $column_name) {
                if (!in_array($column_name, $hidden_columns, true)) {
                    $hidden_columns[] = $column_name;
                }
            }

            update_user_meta($user_id, $hidden_columns_key, $hidden_columns);
        }
    }

    /**
     * Build robots.txt, preferring a per-theme robots.txt over WP defaults.
     * Runs at priority 100000 — after Yoast's 99999.
     *
     * @link https://docs.pantheon.io/bots-and-indexing
     */
    public static function customizeRobotsTxt(string $output, $public): string
    {
        if ((string) $public === '0') {
            return "User-agent: *\nDisallow: /\nDisallow: /*\nDisallow: /*?\n";
        }

        $rules = '';

        $robots_file_path = get_template_directory() . '/robots.txt';
        if (file_exists($robots_file_path)) {
            $rules .= file_get_contents($robots_file_path);
        } else {
            $rules = "User-agent: *\n";
            $rules .= "Disallow: /wp-admin/\n";
            $rules .= "Allow: /wp-admin/admin-ajax.php\n";
            $rules .= "Disallow: /wp-includes/\n";
            $rules .= "Disallow: /wp-content/plugins/\n";
            $rules .= "Disallow: /wp-content/themes/\n";
            $rules .= "Disallow: /*?*\n";
        }

        // Preserve Yoast's sitemap directive if present
        preg_match('/Sitemap: (.+)/', $output, $matches);
        $sitemap_url = !empty($matches[1]) ? trim($matches[1]) : '';
        if (!empty($sitemap_url)) {
            $rules .= "\nSitemap: " . $sitemap_url . "\n";
        }

        return $rules;
    }

    /**
     * Hide named taxonomies from the "Add menu items" picker in Appearance → Menus.
     *
     * Filter callback for `register_taxonomy_args`, which fires inside
     * `register_taxonomy()` — so this mutates the args BEFORE the taxonomy is
     * stored, rather than touching an already-registered taxonomy.
     */
    public static function hideTaxonomiesFromNavMenus(array $args, string $name): array
    {
        $hidden = apply_filters('threespot/admin/taxonomies_hidden_from_nav_menus', self::DEFAULT_TAXONOMIES_HIDDEN_FROM_NAV_MENUS);

        if (in_array($name, $hidden, true)) {
            $args['show_in_nav_menus'] = false;
        }

        return $args;
    }

    /**
     * Add `image/svg+xml` to the allowed-MIME list so editors can upload SVGs.
     * Filter callback for WP's `upload_mimes`.
     *
     * Sites that DON'T want SVG uploads (e.g. because they accept user-generated
     * uploads from untrusted editors) can return false from `allow_svg_uploads`.
     */
    public static function allowSvgUploads(array $mimes): array
    {
        if (!apply_filters('threespot/admin/allow_svg_uploads', true)) {
            return $mimes;
        }

        $mimes['svg'] = 'image/svg+xml';
        return $mimes;
    }
}
