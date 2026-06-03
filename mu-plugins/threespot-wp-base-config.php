<?php
/**
 * Plugin Name: Threespot WP Base Config (Loader)
 * Description: Loads the threespot/wp-base-config package's mu-plugin modules and helper API.
 * Version: 0.1.0
 * Author: Threespot
 *
 * Copy or symlink this file into web/app/mu-plugins/ on each consuming site.
 * Bedrock projects: from the site root,
 *   ln -s ../../vendor/threespot/wp-base-config/mu-plugins/threespot-wp-base-config.php \
 *         web/app/mu-plugins/threespot-wp-base-config.php
 *
 * The Composer autoloader (loaded by Bedrock's config/application.php) has already
 * registered the Helpers + PublicApi function files by the time WP gets here, so this
 * file only needs to fire the MU-plugin module registrations.
 */

if (!defined('ABSPATH')) {
    exit;
}

$threespot_wp_base_config_bootstrap = dirname(__DIR__, 3) . '/vendor/threespot/wp-base-config/bootstrap.php';

if (file_exists($threespot_wp_base_config_bootstrap)) {
    require_once $threespot_wp_base_config_bootstrap;
}

unset($threespot_wp_base_config_bootstrap);
