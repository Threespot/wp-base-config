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
     * Hooks all run under ACF's own namespace (`acf/init`, `acf/prepare_field`),
     * so they're no-ops if ACF isn't installed.
     */
    public static function register(): void
    {
        add_action('acf/init', [self::class, 'addOptionsPage']);
        add_filter('acf/fields/wysiwyg/toolbars', [self::class, 'customizeWysiwygToolbars']);
        add_action('acf/render_field_settings', [self::class, 'addHideLabelSetting']);
        add_filter('acf/prepare_field', [self::class, 'maybeHideFieldLabel']);
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
     * Render a small inline <style> hiding a field's label when its
     * "Hide Label?" setting is checked. Fires per-field during ACF's render.
     *
     * `substr($field['key'], 6)` drops the leading `field_` from ACF's key,
     * leaving the suffix ACF emits as the `.acf-field-<suffix>` CSS class.
     */
    public static function maybeHideFieldLabel(array $field): array
    {
        if (array_key_exists('hide_label', $field) && $field['hide_label'] === true) {
            echo '<style type="text/css">.acf-field-', esc_attr(substr($field['key'], 6)), ' > .acf-label > label {display: none;}</style>';
        }
        return $field;
    }
}
