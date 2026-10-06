<?php
/**
 * Plugin Name: Suppress Admin Notices
 * Description: Prevents specific third-party plugin admin notices from ever rendering
 *              by short-circuiting the option each notice gates on. Cleaner than CSS
 *              because the notice markup, scripts, and AJAX endpoints never load.
 *
 * To suppress a new notice, find the option the plugin checks before rendering and
 * add an entry below. Use $site_option_overrides when the plugin reads with
 * get_site_option() instead of get_option(). If no option gates the notice, remove
 * the hooks that add it (see the Yoast SEO block at the end).
 */

// Read with get_option().
$option_overrides = [
	// FileBird "Give FileBird a review" nag.
	// Gate (Classes/Review.php): time() >= intval($option) && '0' !== $option.
	// '0' is the plugin's own "already rated" sentinel.
	'fbv_review' => '0',

	// FileBird "Create your first folder" notice.
	// Gate (Classes/Core.php): $option === false || time() >= intval($option).
	// A far-future timestamp makes the time check fail forever.
	'fbv_first_folder_notice' => PHP_INT_MAX,

	// Simple Custom Post Order "please consider rating it" nag.
	// Gate (class-simple-review.php): time() > (int) $option.
	// A far-future timestamp makes the time check fail forever.
	'simple-rate-time' => PHP_INT_MAX,
];

// Read with get_site_option().
$site_option_overrides = [
	// Yoast Duplicate Post welcome/newsletter notice.
	// Gate (admin-functions.php): intval(get_site_option(...)) === 1.
	'duplicate_post_show_notice' => 0,
];

foreach ( $option_overrides as $name => $value ) {
	add_filter( "pre_option_{$name}", static fn() => $value );
}

foreach ( $site_option_overrides as $name => $value ) {
	add_filter( "pre_site_option_{$name}", static fn() => $value );
}

// Yoast SEO "create a redirect" upsell, shown after a post is trashed or deleted,
// a term is deleted, or a slug is changed in Quick Edit.
// No option or filter gates it: WPSEO_Slug_Change_Watcher
// (admin/watchers/class-slug-change-watcher.php) adds the notice whenever those
// actions fire, so there's nothing to override. Instead, remove the watcher's hooks.
// Yoast creates the watcher on plugins_loaded priority 14 and never stores the
// instance, so find its callbacks in $wp_filter at priority 15.
add_action(
	'plugins_loaded',
	static function () {
		foreach ( [ 'wp_trash_post', 'before_delete_post', 'delete_term_taxonomy', 'admin_enqueue_scripts' ] as $hook ) {
			foreach ( $GLOBALS['wp_filter'][ $hook ]->callbacks ?? [] as $priority => $callbacks ) {
				foreach ( $callbacks as $callback ) {
					if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof WPSEO_Slug_Change_Watcher ) {
						remove_action( $hook, $callback['function'], $priority );
					}
				}
			}
		}
	},
	15
);
