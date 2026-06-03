<?php

namespace Threespot\Wp\MuPlugins;

/**
 * Custom post-type and taxonomy registrations.
 *
 * Auto-loads site-level CPT and taxonomy definitions from a fixed
 * convention inside the site's mu-plugins directory:
 *
 *   mu-plugins/custom-post-types/post-types/<name>.php
 *   mu-plugins/custom-taxonomies/taxonomies/<name>.php
 *
 * Each definition file registers its own CPT/taxonomy via extended-cpts.
 * Archive-specific query tweaks (orderby, posts_per_archive_page, meta
 * sorts, etc.) belong in the same file as the CPT they affect.
 *
 * Any taxonomy registered with `'query_var' => true` is automatically
 * applied to the main search/archive query as a tax_query — sites do
 * not need to wire `pre_get_posts` themselves. WordPress registers the
 * public URL query var as part of `register_taxonomy()`, so this module
 * only handles translating that query var into a `tax_query` clause.
 */
class ContentTypesConfig
{
    /**
     * Wire the CPT/taxonomy auto-loader and the archive query-var dispatcher.
     * Called once from bootstrap.php.
     */
    public static function register(): void
    {
        add_action('plugins_loaded', [self::class, 'loadDefinitions']);
        add_action('pre_get_posts', [self::class, 'applyTaxonomyQueryVars']);
    }

    /**
     * Require each site-level CPT and taxonomy definition file.
     *
     * Deferred to plugins_loaded so extended-cpts is guaranteed to be
     * available regardless of how it was installed (composer autoload
     * vs. regular WP plugin).
     */
    public static function loadDefinitions(): void
    {
        if (function_exists('register_extended_post_type')) {
            foreach (glob(WPMU_PLUGIN_DIR . '/custom-post-types/post-types/*.php') ?: [] as $file) {
                require_once $file;
            }
        }
        if (function_exists('register_extended_taxonomy')) {
            foreach (glob(WPMU_PLUGIN_DIR . '/custom-taxonomies/taxonomies/*.php') ?: [] as $file) {
                require_once $file;
            }
        }
    }

    /**
     * Translate registered taxonomy query vars into tax_query clauses on
     * the main search/archive query.
     *
     * Any non-core taxonomy with a truthy `query_var` opts in automatically.
     */
    public static function applyTaxonomyQueryVars(\WP_Query $query): void
    {
        // Only mutate the main front-end search/archive query; leave admin lists,
        // secondary WP_Query instances, and singular pages alone.
        if (
            is_admin() ||
            !$query->is_main_query() ||
            !($query->is_search() || $query->is_archive())
        ) {
            return;
        }

        // Preserve any tax_query the theme already set, then append ours.
        $tax_query = $query->get('tax_query') ?: [];

        // Walk all non-core taxonomies. Each one with `query_var => true` exposes
        // a public URL parameter (e.g. ?news_type=press-release) that we translate
        // into a tax_query clause here. Multiple taxonomies AND together because
        // WP_Query's default tax_query relation is AND.
        foreach (get_taxonomies(['_builtin' => false], 'objects') as $tax) {
            if (!$tax->query_var) {
                continue;
            }
            $value = get_query_var($tax->query_var);
            if ($value === '' || $value === null || $value === []) {
                continue;
            }
            $tax_query[] = [
                'taxonomy' => $tax->name,
                'field' => 'slug',
                'terms' => $value,
                'operator' => 'IN',
                'include_children' => false,
            ];
        }

        if (!empty($tax_query)) {
            $query->set('tax_query', $tax_query);
        }
    }
}
