<?php
/**
 * Public helper-function override surface for threespot/wp-base-config.
 *
 * These wrap the package's internal threespot/* filters with verb-paired
 * helpers (keep/remove) so site theme bootstraps stay declarative:
 *
 *   threespot_keep_dashboard_widget('wpseo-dashboard-overview');
 *   threespot_remove_admin_bar_node('my-custom-node');
 *   threespot_no_defer_script('my-plugin-js');
 *
 * Each "keep" removes an entry from the subtractive list (so the underlying
 * item is NOT stripped). Each "remove" adds an entry to the subtractive list.
 *
 * Raw filters remain available as an escape hatch.
 */

if (!function_exists('threespot_remove_from_list')) {
    /**
     * Internal: ensure each $value is present in the array returned by $filter.
     *
     * @internal
     */
    function threespot_remove_from_list(string $filter, array $values): void
    {
        add_filter($filter, function ($list) use ($values) {
            $list = is_array($list) ? $list : [];
            return array_values(array_unique(array_merge($list, $values)));
        });
    }
}

if (!function_exists('threespot_keep_in_list')) {
    /**
     * Internal: ensure each $value is absent from the array returned by $filter.
     *
     * @internal
     */
    function threespot_keep_in_list(string $filter, array $values): void
    {
        add_filter($filter, function ($list) use ($values) {
            $list = is_array($list) ? $list : [];
            return array_values(array_diff($list, $values));
        });
    }
}

/* -------------------------------------------------------------------------
 * Dashboard widgets
 * ------------------------------------------------------------------------- */

if (!function_exists('threespot_remove_dashboard_widget')) {
    /**
     * Add dashboard-widget IDs to the removal list.
     *
     * Example: threespot_remove_dashboard_widget('my-plugin-widget');
     */
    function threespot_remove_dashboard_widget(string ...$widget_ids): void
    {
        threespot_remove_from_list('threespot/admin/dashboard_widgets_removed', $widget_ids);
    }
}

if (!function_exists('threespot_keep_dashboard_widget')) {
    /**
     * Take dashboard-widget IDs OFF the removal list — i.e. keep them visible.
     *
     * Example: threespot_keep_dashboard_widget('wpseo-dashboard-overview');
     */
    function threespot_keep_dashboard_widget(string ...$widget_ids): void
    {
        threespot_keep_in_list('threespot/admin/dashboard_widgets_removed', $widget_ids);
    }
}

/* -------------------------------------------------------------------------
 * Admin bar nodes
 * ------------------------------------------------------------------------- */

if (!function_exists('threespot_remove_admin_bar_node')) {
    /**
     * Add admin-bar node IDs to the removal list.
     *
     * Example: threespot_remove_admin_bar_node('my-plugin-node');
     */
    function threespot_remove_admin_bar_node(string ...$node_ids): void
    {
        threespot_remove_from_list('threespot/admin/admin_bar_nodes_removed', $node_ids);
    }
}

if (!function_exists('threespot_keep_admin_bar_node')) {
    /**
     * Take admin-bar node IDs OFF the removal list — i.e. keep them visible.
     *
     * Example: threespot_keep_admin_bar_node('comments');
     */
    function threespot_keep_admin_bar_node(string ...$node_ids): void
    {
        threespot_keep_in_list('threespot/admin/admin_bar_nodes_removed', $node_ids);
    }
}

/* -------------------------------------------------------------------------
 * Customizer sections
 * ------------------------------------------------------------------------- */

if (!function_exists('threespot_remove_customizer_section')) {
    /**
     * Add Customizer section IDs to the removal list.
     */
    function threespot_remove_customizer_section(string ...$section_ids): void
    {
        threespot_remove_from_list('threespot/admin/customizer_sections_removed', $section_ids);
    }
}

if (!function_exists('threespot_keep_customizer_section')) {
    /**
     * Take Customizer section IDs OFF the removal list — i.e. keep them visible.
     */
    function threespot_keep_customizer_section(string ...$section_ids): void
    {
        threespot_keep_in_list('threespot/admin/customizer_sections_removed', $section_ids);
    }
}

/* -------------------------------------------------------------------------
 * User roles
 * ------------------------------------------------------------------------- */

if (!function_exists('threespot_remove_user_role')) {
    /**
     * Add WP user-role slugs to the removal list. Roles are dropped via remove_role().
     */
    function threespot_remove_user_role(string ...$roles): void
    {
        threespot_remove_from_list('threespot/admin/user_roles_removed', $roles);
    }
}

if (!function_exists('threespot_keep_user_role')) {
    /**
     * Take WP user-role slugs OFF the removal list — i.e. keep the role available.
     */
    function threespot_keep_user_role(string ...$roles): void
    {
        threespot_keep_in_list('threespot/admin/user_roles_removed', $roles);
    }
}

/* -------------------------------------------------------------------------
 * Admin menu pages
 * ------------------------------------------------------------------------- */

if (!function_exists('threespot_remove_menu_page')) {
    /**
     * Add admin menu-page slugs (e.g. 'edit-comments.php') to the removal list.
     */
    function threespot_remove_menu_page(string ...$menu_slugs): void
    {
        threespot_remove_from_list('threespot/admin/menu_pages_removed', $menu_slugs);
    }
}

if (!function_exists('threespot_keep_menu_page')) {
    /**
     * Take admin menu-page slugs OFF the removal list — i.e. keep them in the menu.
     */
    function threespot_keep_menu_page(string ...$menu_slugs): void
    {
        threespot_keep_in_list('threespot/admin/menu_pages_removed', $menu_slugs);
    }
}

/* -------------------------------------------------------------------------
 * Default-collapsed metaboxes
 * ------------------------------------------------------------------------- */

if (!function_exists('threespot_collapse_metabox')) {
    /**
     * Add metabox IDs to the default-collapsed list. Users can still expand
     * them — only the initial state is changed.
     */
    function threespot_collapse_metabox(string ...$metabox_ids): void
    {
        threespot_remove_from_list('threespot/admin/closed_metaboxes', $metabox_ids);
    }
}

if (!function_exists('threespot_uncollapse_metabox')) {
    /**
     * Take metabox IDs OFF the default-collapsed list — i.e. show them expanded.
     */
    function threespot_uncollapse_metabox(string ...$metabox_ids): void
    {
        threespot_keep_in_list('threespot/admin/closed_metaboxes', $metabox_ids);
    }
}

/* -------------------------------------------------------------------------
 * Screen Options hidden columns (defaults applied on user_register)
 * ------------------------------------------------------------------------- */

if (!function_exists('threespot_hide_screen_options_column')) {
    /**
     * Add listing-screen column names to the default-hidden list. Applied per-user
     * on user_register; existing users keep whatever they have already saved.
     */
    function threespot_hide_screen_options_column(string ...$column_names): void
    {
        threespot_remove_from_list('threespot/admin/screen_options_hidden_columns', $column_names);
    }
}

if (!function_exists('threespot_show_screen_options_column')) {
    /**
     * Take listing-screen column names OFF the default-hidden list.
     */
    function threespot_show_screen_options_column(string ...$column_names): void
    {
        threespot_keep_in_list('threespot/admin/screen_options_hidden_columns', $column_names);
    }
}

/* -------------------------------------------------------------------------
 * Taxonomies hidden from "Add menu items"
 * ------------------------------------------------------------------------- */

if (!function_exists('threespot_hide_taxonomy_from_nav_menus')) {
    /**
     * Hide named taxonomies from the Appearance → Menus "Add menu items" picker.
     */
    function threespot_hide_taxonomy_from_nav_menus(string ...$taxonomies): void
    {
        threespot_remove_from_list('threespot/admin/taxonomies_hidden_from_nav_menus', $taxonomies);
    }
}

if (!function_exists('threespot_show_taxonomy_in_nav_menus')) {
    /**
     * Re-show named taxonomies in the Appearance → Menus "Add menu items" picker.
     */
    function threespot_show_taxonomy_in_nav_menus(string ...$taxonomies): void
    {
        threespot_keep_in_list('threespot/admin/taxonomies_hidden_from_nav_menus', $taxonomies);
    }
}

/* -------------------------------------------------------------------------
 * Post types excluded from Threespot\Wp\Helpers\get_custom_post_types()
 * ------------------------------------------------------------------------- */

if (!function_exists('threespot_exclude_post_type')) {
    /**
     * Add post types to the exclusion list used by get_custom_post_types().
     * Useful for hiding internal plugin CPTs from listings/admin loops.
     */
    function threespot_exclude_post_type(string ...$post_types): void
    {
        threespot_remove_from_list('threespot/helpers/excluded_post_types', $post_types);
    }
}

if (!function_exists('threespot_include_post_type')) {
    /**
     * Take post types OFF the exclusion list — i.e. include them in
     * get_custom_post_types() results.
     */
    function threespot_include_post_type(string ...$post_types): void
    {
        threespot_keep_in_list('threespot/helpers/excluded_post_types', $post_types);
    }
}

/* -------------------------------------------------------------------------
 * Block-bindings sources (unregistered on init, priority 100)
 * ------------------------------------------------------------------------- */

if (!function_exists('threespot_remove_block_bindings_source')) {
    /**
     * Add block-bindings source names (e.g. 'core/post-meta') to the
     * unregister list. Sources are unregistered on init at priority 100.
     */
    function threespot_remove_block_bindings_source(string ...$sources): void
    {
        threespot_remove_from_list('threespot/blocks/disabled_block_bindings_sources', $sources);
    }
}

if (!function_exists('threespot_keep_block_bindings_source')) {
    /**
     * Take block-bindings source names OFF the unregister list — i.e. keep
     * them registered.
     */
    function threespot_keep_block_bindings_source(string ...$sources): void
    {
        threespot_keep_in_list('threespot/blocks/disabled_block_bindings_sources', $sources);
    }
}

/* -------------------------------------------------------------------------
 * Script deferral (list is "scripts NOT to defer")
 *
 * defer_script(handle)    → defer this handle (remove it from the no-defer list)
 * no_defer_script(handle) → DON'T defer this handle (add it to the no-defer list)
 * ------------------------------------------------------------------------- */

if (!function_exists('threespot_no_defer_script')) {
    /**
     * Mark script handles as "must NOT be deferred". Use this for scripts that
     * break when loaded asynchronously (e.g. plugins that rely on jQuery's ready
     * timing, or in-order execution).
     */
    function threespot_no_defer_script(string ...$handles): void
    {
        threespot_remove_from_list('threespot/assets/do_not_defer_scripts', $handles);
    }
}

if (!function_exists('threespot_defer_script')) {
    /**
     * Re-enable deferral for handles previously on the no-defer list.
     */
    function threespot_defer_script(string ...$handles): void
    {
        threespot_keep_in_list('threespot/assets/do_not_defer_scripts', $handles);
    }
}
