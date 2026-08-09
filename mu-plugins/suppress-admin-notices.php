<?php
/**
 * Plugin Name: Suppress Admin Notices
 * Description: Prevents specific third-party plugin admin notices from ever rendering
 *              by short-circuiting the option each notice gates on. Cleaner than CSS
 *              because the notice markup, scripts, and AJAX endpoints never load.
 *
 * To suppress a new notice, find the option the plugin checks before rendering and
 * add an entry below. Use $site_option_overrides when the plugin reads with
 * get_site_option() instead of get_option().
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
