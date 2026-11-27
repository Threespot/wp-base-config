<?php
/**
 * threespot/wp-base-config bootstrap.
 *
 * Registers all MU-plugin modules. The site-level mu-plugin file at
 * web/app/mu-plugins/threespot-wp-base-config.php requires this file
 * once WordPress has booted enough for add_action / add_filter to exist.
 * Under WP-CLI it also registers the package's `wp threespot ...` commands.
 */

use Threespot\Wp\Cli\SearchwpCommand;
use Threespot\Wp\MuPlugins\AcfConfig;
use Threespot\Wp\MuPlugins\AcornConfig;
use Threespot\Wp\MuPlugins\AdminConfig;
use Threespot\Wp\MuPlugins\AssetConfig;
use Threespot\Wp\MuPlugins\BlockConfig;
use Threespot\Wp\MuPlugins\ContentTypesConfig;
use Threespot\Wp\MuPlugins\CriticalConfig;
use Threespot\Wp\MuPlugins\FacetwpConfig;
use Threespot\Wp\MuPlugins\LoginConfig;
use Threespot\Wp\MuPlugins\SmtpConfig;
use Threespot\Wp\MuPlugins\ThemeConfig;

AcornConfig::register();
LoginConfig::register();
SmtpConfig::register();
AcfConfig::register();
ThemeConfig::register();
BlockConfig::register();
ContentTypesConfig::register();
AdminConfig::register();
AssetConfig::register();
CriticalConfig::register();
FacetwpConfig::register();

if (defined('WP_CLI') && WP_CLI) {
    // Shares the `threespot` parent command with threespot/wp-fixtures
    WP_CLI::add_command('threespot searchwp', SearchwpCommand::class);
}
