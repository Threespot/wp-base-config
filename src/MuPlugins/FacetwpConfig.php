<?php

namespace Threespot\Wp\MuPlugins;

use function Threespot\Wp\Helpers\facetwp_add_href;
use function Threespot\Wp\Helpers\facetwp_add_labelledby;
use function Threespot\Wp\Helpers\facetwp_page_path;
use function Threespot\Wp\Helpers\facetwp_page_query_args;
use function Threespot\Wp\Helpers\facetwp_request_get;

/**
 * FacetWP fixes shared by GIH, NMHC and Mosaic.
 *
 * Only the New Relic fix is on by default: it's the one all three sites use.
 * The rest are opt-in, because at least one site does it differently and
 * would break with them on (see each method).
 *
 * mu-plugins load before plugins, so register() can't tell whether FacetWP is
 * active. Every hook is added regardless and checks for FacetWP when it runs.
 *
 * Every callback is safe to run alongside a site's own copy of the same fix,
 * since closures can't be unhooked: the markup filters run late (priority
 * 100) and leave anything the site already changed.
 *
 * Filters:
 *   threespot/facetwp/disable_newrelic_on_refresh — bool (default true) turn off New Relic's browser snippet on FacetWP refreshes
 *   threespot/facetwp/disable_a11y_script         — bool (default false) force FacetWP's "Load a11y support" setting off
 *   threespot/facetwp/pager_link_hrefs            — bool (default false) give pager links a real href
 *   threespot/facetwp/page_url_args               — array<string, string> query args for a pager link's href; receives the page number
 *   threespot/facetwp/select_labelledby           — bool (default false) add aria-labelledby to dropdown and sort <select>s
 *   threespot/facetwp/select_label_id             — string id the <select> points at (default 'facet-label-<name>'); receives the facet array. '' skips that facet.
 */
class FacetwpConfig
{
    private const DEFAULT_DISABLE_NEWRELIC_ON_REFRESH = true;

    private const DEFAULT_DISABLE_A11Y_SCRIPT = false;

    private const DEFAULT_PAGER_LINK_HREFS = false;

    private const DEFAULT_SELECT_LABELLEDBY = false;

    private const DEFAULT_LABEL_ID_PREFIX = 'facet-label-';

    /** Facet types whose <select> gets aria-labelledby. */
    private const LABELLED_FACET_TYPES = ['dropdown', 'sort'];

    /**
     * Wire all FacetWP hooks. Called once from bootstrap.php.
     */
    public static function register(): void
    {
        // FacetWP sets its refresh flag on init at priority 20
        add_action('init', [self::class, 'disableNewRelicOnRefresh'], 21);
        add_filter('option_facetwp_settings', [self::class, 'disableA11yScript']);
        add_filter('facetwp_facet_pager_link', [self::class, 'addPagerLinkHref'], 100, 2);
        add_filter('facetwp_facet_html', [self::class, 'addSelectLabelledby'], 100, 2);
    }

    /**
     * Turn off New Relic's browser snippet on FacetWP refreshes.
     *
     * New Relic's PHP agent (on Pantheon) adds its snippet to HTML by regex. A
     * refresh returns the page inside JSON, so the snippet breaks the JSON and
     * every facet spins forever. Pages, admin and login keep the snippet.
     * newrelic_disable_autorum() exists only where the agent runs, never on
     * Lando.
     *
     * @link https://facetwp.com/help-center/using-facetwp-with/new-relic/
     */
    public static function disableNewRelicOnRefresh(): void
    {
        if (!apply_filters('threespot/facetwp/disable_newrelic_on_refresh', self::DEFAULT_DISABLE_NEWRELIC_ON_REFRESH)) {
            return;
        }

        if (function_exists('newrelic_disable_autorum') && function_exists('FWP') && !empty(FWP()->request->is_refresh)) {
            newrelic_disable_autorum();
        }
    }

    /**
     * Force FacetWP's "Load a11y support" setting off.
     *
     * Its script names every <select> after the selected option ("All"), which
     * overrides the visible label, and renames the pager links. The
     * facetwp_load_a11y filter can only turn it on, so the stored setting is
     * changed as it's read.
     *
     * Off by default: a site whose pager links have no href (FacetWP's
     * default) needs the script for keyboard access, since it gives each link
     * tabindex="0". Turn this on only with real pager links or buttons.
     *
     * @param mixed $value The facetwp_settings option, a JSON string
     * @return mixed
     */
    public static function disableA11yScript($value)
    {
        if (!apply_filters('threespot/facetwp/disable_a11y_script', self::DEFAULT_DISABLE_A11Y_SCRIPT)) {
            return $value;
        }

        $settings = is_string($value) ? json_decode($value, true) : null;

        if (!is_array($settings) || ($settings['settings']['load_a11y'] ?? 'no') === 'no') {
            return $value;
        }

        $settings['settings']['load_a11y'] = 'no';

        return wp_json_encode($settings);
    }

    /**
     * Give each pager link a real href carrying the current filters, so deep
     * pages work without JavaScript and can be crawled.
     *
     * Off by default: FacetWP's click handler doesn't call preventDefault(),
     * so with an href every pager click also loads the page. Turn this on
     * together with a delegated click listener that calls preventDefault() on
     * a.facetwp-page[href]. A link that already has an href, a <button> and the
     * dots are left alone.
     *
     * @param mixed $html The link's HTML
     * @param array<string, mixed> $params FacetWP's page, label and extra_class
     * @return mixed
     */
    public static function addPagerLinkHref($html, $params)
    {
        if (!is_string($html) || !function_exists('FWP')) {
            return $html;
        }

        if (!apply_filters('threespot/facetwp/pager_link_hrefs', self::DEFAULT_PAGER_LINK_HREFS)) {
            return $html;
        }

        $page = (int) ($params['page'] ?? 0);

        if ($page < 1 || ($params['extra_class'] ?? '') === 'dots') {
            return $html;
        }

        return facetwp_add_href($html, self::pageUrl($page));
    }

    /**
     * The URL of a page of results, carrying the current facet selections and
     * the request's other query args.
     */
    public static function pageUrl(int $page): string
    {
        $fwp = FWP();
        $selections = [];

        foreach ((array) ($fwp->facet->facets ?? []) as $name => $facet) {
            if (($facet['type'] ?? '') !== 'pager') {
                $selections[(string) $name] = (array) ($facet['selected_values'] ?? []);
            }
        }

        $uri = (string) ($fwp->facet->http_params['uri'] ?? '');
        $get = facetwp_request_get((array) ($fwp->facet->http_params['get'] ?? []), !empty($fwp->request->is_refresh));

        if ($uri === '') {
            $uri = (string) wp_parse_url(isset($_SERVER['REQUEST_URI']) ? wp_unslash((string) $_SERVER['REQUEST_URI']) : '', PHP_URL_PATH);
        }

        $path = facetwp_page_path($uri, (string) wp_parse_url(home_url('/'), PHP_URL_PATH));
        $args = apply_filters('threespot/facetwp/page_url_args', facetwp_page_query_args($get, $selections, $page), $page);
        $url = home_url($path === '' ? '/' : '/' . $path . '/');

        return empty($args) ? $url : $url . '?' . http_build_query((array) $args, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Point each dropdown and sort <select> at its visible label.
     *
     * FacetWP replaces everything inside the facet container on refresh, so
     * the label sits outside it and the <select> points at it by id. Off by
     * default: the theme must print the label with that id. A <select> that
     * already has aria-labelledby, aria-label or an id is left alone.
     *
     * @param mixed $html The facet's HTML
     * @param array<string, mixed> $args FacetWP's facet args
     * @return mixed
     */
    public static function addSelectLabelledby($html, $args)
    {
        if (!is_string($html) || $html === '') {
            return $html;
        }

        if (!apply_filters('threespot/facetwp/select_labelledby', self::DEFAULT_SELECT_LABELLEDBY)) {
            return $html;
        }

        $facet = (array) ($args['facet'] ?? []);
        $name = (string) ($facet['name'] ?? '');

        if ($name === '' || !in_array($facet['type'] ?? '', self::LABELLED_FACET_TYPES, true)) {
            return $html;
        }

        $label_id = (string) apply_filters('threespot/facetwp/select_label_id', self::DEFAULT_LABEL_ID_PREFIX . $name, $facet);

        return $label_id === '' ? $html : facetwp_add_labelledby($html, $label_id);
    }
}
