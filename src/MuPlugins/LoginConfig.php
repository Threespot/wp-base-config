<?php

namespace Threespot\Wp\MuPlugins;

/**
 * Login page customization.
 *
 * Filters:
 *   threespot/login/css_url    — string|null URL for login stylesheet (default: null, no stylesheet)
 *   threespot/login/header_url — string URL for login page logo (default: home_url())
 *   threespot/login/header_text — string alt text for login page logo (default: blogname)
 */
class LoginConfig
{
    /**
     * Wire all login-page hooks. Called once from bootstrap.php.
     */
    public static function register(): void
    {
        add_action('login_enqueue_scripts', [self::class, 'enqueueLoginStyles']);
        add_filter('login_headerurl', [self::class, 'filterHeaderUrl']);
        add_filter('login_headertext', [self::class, 'filterHeaderText']);
    }

    /**
     * Enqueue the login stylesheet if the theme supplied a URL via the
     * `threespot/login/css_url` filter. Skips silently when no filter is registered.
     */
    public static function enqueueLoginStyles(): void
    {
        $url = apply_filters('threespot/login/css_url', null);

        if (!empty($url)) {
            wp_enqueue_style('threespot-login', $url, [], null);
        }
    }

    /**
     * Filter callback for WP's `login_headerurl` — the link wrapping the
     * login-page logo. Defaults to the site's home URL.
     */
    public static function filterHeaderUrl(): string
    {
        return apply_filters('threespot/login/header_url', home_url());
    }

    /**
     * Filter callback for WP's `login_headertext` — the alt text on the
     * login-page logo. Defaults to the site name (`blogname` option).
     */
    public static function filterHeaderText(): string
    {
        return apply_filters('threespot/login/header_text', get_option('blogname', 'Site Admin'));
    }
}
