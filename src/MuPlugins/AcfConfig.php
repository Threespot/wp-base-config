<?php

namespace Threespot\Wp\MuPlugins;

/**
 * Advanced Custom Fields configuration.
 *
 * Filters:
 *   threespot/acf/options_page_title — string title for the Theme Settings options page
 *   threespot/acf/options_page_slug  — string slug for the page (default: 'theme-settings')
 *   threespot/acf/options_page_capability — string capability (default: 'edit_posts')
 *   threespot/acf/wysiwyg_toolbars   — array of toolbar => buttons (merged on top of defaults)
 *
 * Also adds a "Hide Label?" toggle to every ACF field for hiding redundant labels.
 */
class AcfConfig
{
    /**
     * Wire all ACF hooks. Called once from bootstrap.php.
     *
     * Hooks all run under ACF's own namespace (`acf/init`, `acf/field_wrapper_attributes`),
     * so they're no-ops if ACF isn't installed.
     */
    public static function register(): void
    {
        add_action('acf/init', [self::class, 'addOptionsPage']);
        add_filter('acf/fields/wysiwyg/toolbars', [self::class, 'customizeWysiwygToolbars']);
        add_action('acf/render_field_settings', [self::class, 'addHideLabelSetting']);
        add_filter('acf/field_wrapper_attributes', [self::class, 'addHideLabelClass'], 10, 2);
        add_action('acf/input/admin_head', [self::class, 'printHideLabelCss']);
    }

    /**
     * Register the ACF "Theme Settings" options page if ACF Pro is active.
     *
     * `acf_add_options_page` lives in ACF Pro; the function-exists guard keeps
     * sites on ACF Free (no options pages) from fatalling.
     */
    public static function addOptionsPage(): void
    {
        if (!function_exists('acf_add_options_page')) {
            return;
        }

        $title = apply_filters('threespot/acf/options_page_title', 'Theme Settings');
        $slug = apply_filters('threespot/acf/options_page_slug', 'theme-settings');
        $capability = apply_filters('threespot/acf/options_page_capability', 'edit_posts');

        acf_add_options_page([
            'page_title' => $title,
            'menu_title' => $title,
            'menu_slug' => $slug,
            'capability' => $capability,
            'redirect' => false,
        ]);
    }

    /**
     * Register custom wysiwyg toolbars.
     *
     * @link https://www.advancedcustomfields.com/resources/customize-the-wysiwyg-toolbars/
     */
    public static function customizeWysiwygToolbars(array $toolbars): array
    {
        // Customize the "Basic" toolbar
        $toolbars['Basic'][1] = ['formatselect', 'bold', 'italic', 'bullist', 'numlist', 'link', 'unlink', 'pastetext', 'removeformat', 'charmap', 'undo', 'redo'];

        // "Very Simple" — 1 row, minimal buttons
        $toolbars['Very Simple'] = [];
        $toolbars['Very Simple'][1] = ['bold', 'italic', 'link', 'unlink', 'pastetext', 'removeformat', 'charmap', 'undo', 'redo'];

        $custom = apply_filters('threespot/acf/wysiwyg_toolbars', []);

        if (is_array($custom)) {
            foreach ($custom as $name => $rows) {
                $toolbars[$name] = $rows;
            }
        }

        return $toolbars;
    }

    /**
     * Add a "Hide Label?" toggle to every ACF field's settings.
     *
     * @link https://support.advancedcustomfields.com/forums/topic/field-label-showhide-option#post-51372
     */
    public static function addHideLabelSetting(array $field): void
    {
        acf_render_field_setting($field, [
            'label' => __('Hide Label?'),
            'instructions' => 'This will hide the label text in the admin (useful when text is redundant)',
            'name' => 'hide_label',
            'type' => 'true_false',
            'ui' => 1,
        ], true);
    }

    /**
     * Add a marker class to a field's wrapper element when its
     * "Hide Label?" setting is checked. Paired with printHideLabelCss().
     *
     * ACF's true_false setting stores 1/'1', not boolean true, so the
     * check is truthiness, not strict equality.
     */
    public static function addHideLabelClass(array $wrapper, array $field): array
    {
        if (!empty($field['hide_label'])) {
            $wrapper['class'] = trim(($wrapper['class'] ?? '') . ' threespot-hide-label');
        }
        return $wrapper;
    }

    /**
     * Print the single CSS rule backing the "Hide Label?" marker class.
     * Fires on every admin screen where ACF renders fields.
     */
    public static function printHideLabelCss(): void
    {
        echo '<style>.acf-field.threespot-hide-label > .acf-label > label {display: none;}</style>';
    }
}
