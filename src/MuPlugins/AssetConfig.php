<?php

namespace Threespot\Wp\MuPlugins;

/**
 * Front-end asset behavior: script deferral, jQuery hosting, oEmbed cleanup.
 *
 * The theme owns the actual enqueue of its main.scss / main.js / critical.scss —
 * this module only owns site-wide deferral policy and shared script behavior.
 *
 * Filters:
 *   threespot/assets/jquery_path              — string|null theme resources/-relative path to a locally-hosted jQuery
 *                                                (e.g. 'scripts/lib/jquery-4.0.0.min.js'). Null leaves WP core jQuery in
 *                                                place; otherwise the URL is derived from the theme's public/build/assets
 *                                                output.
 *   threespot/assets/jquery_version           — string (default null — no explicit version)
 *   threespot/assets/do_not_defer_scripts     — array<string> script handles to skip when adding defer
 *   threespot/assets/module_script_handles    — array<string> handles that should also get type="module"
 *   threespot/assets/disable_oembed_discovery — bool (default true)
 */
class AssetConfig
{
    private const DEFAULT_DO_NOT_DEFER_HANDLES = [
        // Default WP scripts (only loaded when logged in)
        'admin-bar',
        'regenerator-runtime',
        'wp-a11y',
        'wp-dom-ready',
        'wp-hooks',
        'wp-i18n',
        'wp-polyfill',
        'wp-polyfill-inert',
        // jQuery — some plugins break when deferred (e.g. Gravity Forms)
        'jquery-core',
        // WP Forms — WPForms uses inline JS broken by deferred reCAPTCHA
        'wpforms-recaptcha',
        // Gravity Forms — relies on in-order execution
        'gform_gravityforms',
        'gform_gravityforms_theme',
        'gform_gravityforms_theme_vendors',
        'gform_gravityforms_utils',
        'gform_json',
        'gform_placeholder',
        'gform_textarea_counter',
        // WooCommerce
        'jquery-blockui',
        'jquery-mask',
        'jquery-payment',
        'js-cookie',
        'selectWoo',
        'sourcebuster-js',
        'stripe',
        'wc-add-to-cart',
        'wc-address-i18n',
        'wc-cart',
        'wc-cart-mr-giftcard',
        'wc-checkout',
        'wc-checkout-mr-giftcard',
        'wc-country-select',
        'wc-order-attribution',
        'wc_stripe_payment_request',
        'woo-tracks',
        'woocommerce',
        'woocommerce-tokenization-form',
        'woocommerce_stripe',
    ];

    /**
     * Wire all asset-related hooks. Called once from bootstrap.php.
     *
     * The `wp_enqueue_scripts` callbacks run at priority 100 so any theme/plugin
     * registrations at the default priority 10 have already completed — we want
     * to mutate the final state, not race the registrations.
     */
    public static function register(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'replaceJqueryIfConfigured'], 100);
        add_action('wp_enqueue_scripts', [self::class, 'disableOembedDiscovery'], 100);
        add_filter('script_loader_tag', [self::class, 'deferScripts'], 20, 3);
    }

    /**
     * If the theme provides a local jQuery path, deregister core jQuery and replace it.
     * Loads at end of body — https://core.trac.wordpress.org/ticket/37110#comment:82
     *
     * The `threespot/assets/jquery_path` filter supplies a path relative to the theme's
     * resources/ dir (e.g. 'scripts/lib/jquery-4.0.0.min.js'). The URL is derived from
     * the Vite static-copy output at public/build/assets/resources/<path> — see the
     * matching `jqueryPath` option in the shared Vite config (dist/vite-base.js), which
     * copies the file there. Null leaves WP core jQuery in place.
     */
    public static function replaceJqueryIfConfigured(): void
    {
        if (is_admin() || is_customize_preview()) {
            return;
        }

        $jquery_path = apply_filters('threespot/assets/jquery_path', null);

        if (empty($jquery_path)) {
            return;
        }

        // get_theme_file_uri() resolves against the content dir (no /wp prefix in
        // Bedrock) and respects child themes.
        $jquery_url = get_theme_file_uri('public/build/assets/resources/' . ltrim($jquery_path, '/'));

        $version = apply_filters('threespot/assets/jquery_version', null);

        // "jquery" is an alias that loads "jquery-core" + "jquery-migrate"
        wp_deregister_script('jquery');
        wp_deregister_script('jquery-core');
        wp_deregister_script('jquery-migrate');

        // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion, WordPress.WP.EnqueuedResourceParameters.NoExplicitVersion
        wp_register_script('jquery-core', $jquery_url, [], $version, true);

        // Re-register "jquery" alias to avoid breaking other scripts
        // https://wordpress.stackexchange.com/a/284532/
        // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion, WordPress.WP.EnqueuedResourceParameters.NoExplicitVersion
        wp_register_script('jquery', false, ['jquery-core'], $version, true);
    }

    /**
     * Drop the oEmbed discovery <link> tags from the head.
     * Yoast can also handle this — https://yoast.com/help/yoast-seo-settings-crawl-optimization
     */
    public static function disableOembedDiscovery(): void
    {
        if (!apply_filters('threespot/assets/disable_oembed_discovery', true)) {
            return;
        }
        remove_action('wp_head', 'wp_oembed_add_discovery_links', 10);
    }

    /**
     * Add type="module" to flagged handles and "defer" to everything else,
     * excluding the do-not-defer list and certain logged-in / commerce flows.
     *
     * @link https://addyosmani.com/blog/script-priorities/
     */
    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
    public static function deferScripts(string $tag, string $handle, string $source): string
    {
        $module_handles = apply_filters('threespot/assets/module_script_handles', []);

        if (in_array($handle, $module_handles, true)) {
            // type="module" implies defer behavior in modern browsers; also adds data-handle for debugging.
            $tag = preg_replace('/><\/script>/', ' type="module" data-handle="' . $handle . '"></script>', $tag);
        }

        // Don't touch scripts when logged in or in WooCommerce cart/checkout — too many
        // WP and 3rd-party scripts depend on execution order.
        if (
            is_user_logged_in() ||
            (function_exists('\is_cart') && \is_cart()) ||
            (function_exists('\is_checkout') && \is_checkout())
        ) {
            return $tag;
        }

        $do_not_defer = apply_filters(
            'threespot/assets/do_not_defer_scripts',
            self::DEFAULT_DO_NOT_DEFER_HANDLES
        );

        if (is_admin() || is_customize_preview() || in_array($handle, $do_not_defer, true)) {
            return $tag;
        }

        // Skip handles already marked as modules above
        if (in_array($handle, $module_handles, true)) {
            return $tag;
        }

        return preg_replace('/><\/script>/', ' defer data-handle="' . $handle . '"></script>', $tag);
    }
}
