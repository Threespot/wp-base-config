<?php
/**
 * Plugin Name: Site Health Auth Guard
 * Description: Re-gates Pantheon's Site Health "compatibility" AJAX handler so only users who can view Site Health can read the active-plugin compatibility report. The bundled pantheon-mu-plugin registers wp_ajax_health-check-test-compatibility with no capability check, letting any authenticated user (down to a subscriber) enumerate the site's known-problematic active plugins. Remove this shim once Pantheon ships a fix upstream (github.com/pantheon-systems/pantheon-mu-plugin).
 * Version: 1.0.0
 * Author: Threespot
 *
 * Ref: Claude Security finding F1, 2026-07-23. Target: pantheon-mu-plugin v1.5.7,
 * inc/site-health.php:40 (registration) and :836 (unguarded handler).
 *
 * @package gih
 */

namespace GIH\SiteHealthGuard;

// Swap the handler on plugins_loaded (priority 100) so it runs after every
// mu-plugin and plugin has registered its hooks, but long before admin-ajax
// fires the wp_ajax_* action during a request. On non-Pantheon environments
// (e.g. local Lando) the upstream action is never registered, so this is a
// harmless no-op.
add_action( 'plugins_loaded', __NAMESPACE__ . '\\reguard_compatibility_ajax', 100 );

/**
 * Replace Pantheon's unguarded compatibility AJAX handler with a gated wrapper.
 *
 * @return void
 */
function reguard_compatibility_ajax() {
	$action   = 'wp_ajax_health-check-test-compatibility';
	$original = 'Pantheon\\Site_Health\\test_compatibility_ajax';

	// Only act if Pantheon's unguarded handler is actually registered, so we
	// never fight a future upstream version that has already fixed this.
	if ( false === has_action( $action, $original ) ) {
		return;
	}

	// Upstream registers at the default priority (10); match it to remove.
	remove_action( $action, $original, 10 );
	add_action( $action, __NAMESPACE__ . '\\guarded_test_compatibility_ajax' );
}

/**
 * Capability-gated wrapper around Pantheon's compatibility check.
 *
 * Blocks callers who cannot view Site Health, then delegates to the original
 * handler so authorized administrators get the exact same report as before.
 *
 * @return void
 */
function guarded_test_compatibility_ajax() {
	// 'view_site_health_checks' is the capability WordPress core requires for
	// its own Site Health AJAX endpoints; mirror it here.
	if ( ! current_user_can( 'view_site_health_checks' ) ) {
		wp_send_json_error( '', 403 );
	}

	// Delegate to Pantheon's original handler (still defined; we only detached
	// its hook, not the function) so behavior for authorized users is unchanged.
	if ( function_exists( 'Pantheon\\Site_Health\\test_compatibility_ajax' ) ) {
		\Pantheon\Site_Health\test_compatibility_ajax();
	}

	// test_compatibility_ajax() calls wp_send_json_success(), which exits; the
	// line below only runs if the upstream function was unexpectedly removed.
	wp_send_json_error( '', 500 );
}
