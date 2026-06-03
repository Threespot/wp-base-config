<?php
/**
 * threespot/wp-base-config bootstrap.
 *
 * Registers all MU-plugin modules. The site-level mu-plugin file at
 * web/app/mu-plugins/threespot-wp-base-config.php requires this file
 * once WordPress has booted enough for add_action / add_filter to exist.
 */

use Threespot\Wp\MuPlugins\AcfConfig;
use Threespot\Wp\MuPlugins\AdminConfig;
use Threespot\Wp\MuPlugins\AssetConfig;
use Threespot\Wp\MuPlugins\BlockConfig;
use Threespot\Wp\MuPlugins\ContentTypesConfig;
use Threespot\Wp\MuPlugins\CriticalConfig;
use Threespot\Wp\MuPlugins\LoginConfig;
use Threespot\Wp\MuPlugins\SmtpConfig;
use Threespot\Wp\MuPlugins\ThemeConfig;

LoginConfig::register();
SmtpConfig::register();
AcfConfig::register();
ThemeConfig::register();
BlockConfig::register();
ContentTypesConfig::register();
AdminConfig::register();
AssetConfig::register();
CriticalConfig::register();
