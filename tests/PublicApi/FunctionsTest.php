<?php

namespace Threespot\Wp\Tests\PublicApi;

use Brain\Monkey\Functions;
use Threespot\Wp\Tests\BrainMonkeyTestCase;

/**
 * Exercise the threespot_keep_* / threespot_remove_* helper pairs.
 *
 * Each test stubs add_filter to capture the registered callback, then runs
 * the callback against a sample list to verify the semantics:
 *   - remove_*(x) → list gains x
 *   - keep_*(x)   → list loses x
 */
class FunctionsTest extends BrainMonkeyTestCase
{
    /**
     * @var array<string, callable>
     */
    private array $captured = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->captured = [];

        Functions\when('add_filter')->alias(function ($filter, $cb, $priority = 10, $args = 1) {
            $this->captured[$filter][] = $cb;
            return true;
        });
    }

    /**
     * Invoke every callback registered against $filter, threading $initial through them.
     */
    private function applyCapturedFilters(string $filter, array $initial): array
    {
        $value = $initial;
        foreach ($this->captured[$filter] ?? [] as $cb) {
            $value = $cb($value);
        }
        return $value;
    }

    /**
     * As applyCapturedFilters, but for scalar filters (bool, string, int).
     *
     * @param mixed $initial
     * @return mixed
     */
    private function applyCapturedFilterValue(string $filter, $initial)
    {
        $value = $initial;
        foreach ($this->captured[$filter] ?? [] as $cb) {
            $value = $cb($value);
        }
        return $value;
    }

    /* ---------------- Dashboard widgets ---------------- */

    public function test_remove_dashboard_widget_adds_to_removal_list(): void
    {
        threespot_remove_dashboard_widget('foo', 'bar');

        $result = $this->applyCapturedFilters('threespot/admin/dashboard_widgets_removed', ['existing']);

        $this->assertContains('existing', $result);
        $this->assertContains('foo', $result);
        $this->assertContains('bar', $result);
    }

    public function test_keep_dashboard_widget_removes_from_removal_list(): void
    {
        threespot_keep_dashboard_widget('wpseo-dashboard-overview');

        $result = $this->applyCapturedFilters('threespot/admin/dashboard_widgets_removed', [
            'dashboard_primary',
            'wpseo-dashboard-overview',
        ]);

        $this->assertContains('dashboard_primary', $result);
        $this->assertNotContains('wpseo-dashboard-overview', $result);
    }

    public function test_keep_and_remove_combine_correctly(): void
    {
        threespot_remove_dashboard_widget('custom_widget');
        threespot_keep_dashboard_widget('dashboard_primary');

        $result = $this->applyCapturedFilters('threespot/admin/dashboard_widgets_removed', [
            'dashboard_primary',
            'dashboard_secondary',
        ]);

        $this->assertContains('custom_widget', $result);
        $this->assertContains('dashboard_secondary', $result);
        $this->assertNotContains('dashboard_primary', $result);
    }

    public function test_remove_dashboard_widget_dedupes(): void
    {
        threespot_remove_dashboard_widget('foo');

        $result = $this->applyCapturedFilters('threespot/admin/dashboard_widgets_removed', ['foo']);

        $this->assertSame(['foo'], $result);
    }

    /* ---------------- Admin bar nodes ---------------- */

    public function test_remove_admin_bar_node(): void
    {
        threespot_remove_admin_bar_node('my-node');

        $result = $this->applyCapturedFilters('threespot/admin/admin_bar_nodes_removed', []);
        $this->assertSame(['my-node'], $result);
    }

    public function test_keep_admin_bar_node(): void
    {
        threespot_keep_admin_bar_node('wp-logo');

        $result = $this->applyCapturedFilters('threespot/admin/admin_bar_nodes_removed', ['wp-logo', 'comments']);
        $this->assertSame(['comments'], $result);
    }

    /* ---------------- Customizer sections ---------------- */

    public function test_remove_customizer_section(): void
    {
        threespot_remove_customizer_section('section_x');

        $result = $this->applyCapturedFilters('threespot/admin/customizer_sections_removed', []);
        $this->assertSame(['section_x'], $result);
    }

    public function test_keep_customizer_section(): void
    {
        threespot_keep_customizer_section('colors');

        $result = $this->applyCapturedFilters('threespot/admin/customizer_sections_removed', ['colors', 'custom_css']);
        $this->assertSame(['custom_css'], $result);
    }

    /* ---------------- User roles ---------------- */

    public function test_remove_user_role(): void
    {
        threespot_remove_user_role('subscriber');

        $result = $this->applyCapturedFilters('threespot/admin/user_roles_removed', []);
        $this->assertContains('subscriber', $result);
    }

    public function test_keep_user_role(): void
    {
        threespot_keep_user_role('wpseo_manager');

        $result = $this->applyCapturedFilters('threespot/admin/user_roles_removed', ['wpseo_manager']);
        $this->assertSame([], $result);
    }

    /* ---------------- Menu pages ---------------- */

    public function test_remove_menu_page(): void
    {
        threespot_remove_menu_page('options-discussion.php');

        $result = $this->applyCapturedFilters('threespot/admin/menu_pages_removed', []);
        $this->assertContains('options-discussion.php', $result);
    }

    public function test_keep_menu_page(): void
    {
        threespot_keep_menu_page('edit-comments.php');

        $result = $this->applyCapturedFilters('threespot/admin/menu_pages_removed', ['edit-comments.php']);
        $this->assertSame([], $result);
    }

    /* ---------------- Comments ---------------- */

    public function test_enable_comments_for_named_post_types(): void
    {
        threespot_enable_comments('post', 'page');

        $result = $this->applyCapturedFilters('threespot/admin/disable_comments_post_types', [
            'post',
            'page',
            'news',
        ]);

        $this->assertSame(['news'], $result);
    }

    public function test_disable_comments_adds_post_types(): void
    {
        threespot_disable_comments('event');

        $result = $this->applyCapturedFilters('threespot/admin/disable_comments_post_types', ['post']);

        $this->assertContains('post', $result);
        $this->assertContains('event', $result);
    }

    public function test_enable_comments_with_no_args_clears_the_whole_disable_list(): void
    {
        threespot_enable_comments();

        $result = $this->applyCapturedFilters('threespot/admin/disable_comments_post_types', [
            'post',
            'page',
            'news',
        ]);

        $this->assertSame([], $result);
    }

    public function test_enable_comments_with_no_args_also_restores_the_comments_ui(): void
    {
        threespot_enable_comments();

        // The redirect stops...
        $this->assertFalse($this->applyCapturedFilterValue('threespot/admin/redirect_comments_screen', true));

        // ...and the menu page + admin-bar node come back off their removal lists.
        $this->assertSame(
            [],
            $this->applyCapturedFilters('threespot/admin/menu_pages_removed', ['edit-comments.php']),
        );
        $this->assertSame(
            [],
            $this->applyCapturedFilters('threespot/admin/admin_bar_nodes_removed', ['comments']),
        );
    }

    /* ---------------- Closed metaboxes ---------------- */

    public function test_collapse_metabox(): void
    {
        threespot_collapse_metabox('my_metabox');

        $result = $this->applyCapturedFilters('threespot/admin/closed_metaboxes', []);
        $this->assertContains('my_metabox', $result);
    }

    public function test_uncollapse_metabox(): void
    {
        threespot_uncollapse_metabox('wpseo_meta');

        $result = $this->applyCapturedFilters('threespot/admin/closed_metaboxes', ['wpseo_meta']);
        $this->assertSame([], $result);
    }

    /* ---------------- Screen options columns ---------------- */

    public function test_hide_screen_options_column(): void
    {
        threespot_hide_screen_options_column('custom-col');

        $result = $this->applyCapturedFilters('threespot/admin/screen_options_hidden_columns', []);
        $this->assertContains('custom-col', $result);
    }

    public function test_show_screen_options_column(): void
    {
        threespot_show_screen_options_column('wpseo-focuskw');

        $result = $this->applyCapturedFilters('threespot/admin/screen_options_hidden_columns', ['wpseo-focuskw']);
        $this->assertSame([], $result);
    }

    /* ---------------- Nav menu taxonomies ---------------- */

    public function test_hide_taxonomy_from_nav_menus(): void
    {
        threespot_hide_taxonomy_from_nav_menus('tag');

        $result = $this->applyCapturedFilters('threespot/admin/taxonomies_hidden_from_nav_menus', []);
        $this->assertContains('tag', $result);
    }

    public function test_show_taxonomy_in_nav_menus(): void
    {
        threespot_show_taxonomy_in_nav_menus('category');

        $result = $this->applyCapturedFilters('threespot/admin/taxonomies_hidden_from_nav_menus', ['category']);
        $this->assertSame([], $result);
    }

    /* ---------------- Excluded post types ---------------- */

    public function test_exclude_post_type(): void
    {
        threespot_exclude_post_type('career');

        $result = $this->applyCapturedFilters('threespot/helpers/excluded_post_types', []);
        $this->assertContains('career', $result);
    }

    public function test_include_post_type(): void
    {
        threespot_include_post_type('ifso_triggers');

        $result = $this->applyCapturedFilters('threespot/helpers/excluded_post_types', ['ifso_triggers']);
        $this->assertSame([], $result);
    }

    /* ---------------- Defer scripts ---------------- */

    public function test_no_defer_script_adds_to_skip_list(): void
    {
        // The list represents scripts NOT to defer, so no_defer_script adds to it.
        threespot_no_defer_script('my-plugin-js');

        $result = $this->applyCapturedFilters('threespot/assets/do_not_defer_scripts', []);
        $this->assertContains('my-plugin-js', $result);
    }

    public function test_defer_script_removes_from_skip_list(): void
    {
        // defer_script removes the handle so it WILL be deferred.
        threespot_defer_script('jquery-core');

        $result = $this->applyCapturedFilters('threespot/assets/do_not_defer_scripts', ['jquery-core', 'admin-bar']);
        $this->assertSame(['admin-bar'], $result);
    }

    public function test_defer_script_variadic(): void
    {
        threespot_defer_script('a', 'b', 'c');

        $result = $this->applyCapturedFilters('threespot/assets/do_not_defer_scripts', ['a', 'b', 'c', 'd']);
        $this->assertSame(['d'], $result);
    }
}
