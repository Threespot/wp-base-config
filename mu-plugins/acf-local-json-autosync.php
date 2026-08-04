<?php
/**
 * Plugin Name: ACF Force Sync (local dev only)
 * Description: Turns on ACF Extended's force_sync module on local (Lando) only.
 *   When you open the dashboard, the Field Groups screen, or a field group's
 *   edit screen, committed acf-json field groups auto-import into the DB, and
 *   groups whose JSON file was deleted are removed from the DB — no manual
 *   "Sync" click. This keeps every developer's DB in step with the committed
 *   JSON, so a stale local copy can't silently revert a teammate's field-group
 *   changes. Never runs on Pantheon.
 */

// Local only. Lando sets PANTHEON_ENVIRONMENT to 'lando'; every real Pantheon
// environment (dev/multidev/test/live) uses its own name. Mirrors the
// Threespot\Wp\Helpers\is_production() convention in wp-base-config. (getenv()
// rather than $_ENV: same OS-injected value, without the unsanitized-superglobal
// phpcs flag.)
if (getenv('PANTHEON_ENVIRONMENT') !== 'lando') {
    return;
}

// Force ACF Extended's force_sync module on for this request only. It's a
// read-time filter (see ACFE_Pro::settings()), never persisted to the DB, so it
// stays immune to `lando pull/push --database` and never leaks to Pantheon.
// ACFE guards its import against the acf-json timestamp rewrite, so this won't
// dirty the committed JSON files the way a manual import would.
add_filter('acfe/settings/modules/force_sync', '__return_true');

// Also delete DB field groups whose acf-json file was removed, so deleting a
// field group in one branch propagates on pull instead of leaving an orphaned
// group in a teammate's DB.
add_filter('acfe/settings/modules/force_sync/delete', '__return_true');

// Also sync when opening a single field group's edit screen (post.php?post=…),
// not just the dashboard / Field Groups list. The module exposes its screen
// check via this filter (see acfe_pro_force_sync::current_screen()). Its
// current_screen hook fires before post.php renders the edit form, so a
// newer committed JSON imports first and the form shows the fresh data.
add_filter('acfe/modules/force_sync/rule', function ($rule, $screen) {
    return $rule || acf_is_screen('acf-field-group');
}, 10, 2);
