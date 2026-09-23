<?php

namespace Threespot\Wp\Tests\MuPlugins;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Threespot\Wp\MuPlugins\AssetConfig;
use Threespot\Wp\Tests\BrainMonkeyTestCase;

/**
 * Behavior tests for AssetConfig::replaceJqueryIfConfigured().
 */
class AssetConfigTest extends BrainMonkeyTestCase
{
    /**
     * The jQuery URL must be absolute. Our prettify config makes the
     * `theme_file_uri` hook return root-relative URLs, and WP_Scripts prefixes
     * any src that isn't absolute (or under WP_CONTENT_URL) with site_url().
     * In Bedrock that is `/wp`, so a get_theme_file_uri() src became
     * `/wp/wp-content/...` and 404'd on Pantheon.
     */
    public function test_registers_absolute_url_without_theme_file_uri(): void
    {
        Functions\when('is_admin')->justReturn(false);
        Functions\when('is_customize_preview')->justReturn(false);
        Functions\when('get_stylesheet_directory_uri')
            ->justReturn('https://example.com/wp-content/themes/sage');
        Functions\expect('get_theme_file_uri')->never();
        Functions\when('wp_deregister_script')->justReturn(null);
        Filters\expectApplied('threespot/assets/jquery_path')
            ->andReturn('/scripts/lib/jquery-4.0.0.min.js');

        $registered = [];
        Functions\when('wp_register_script')->alias(
            function (string $handle, $src) use (&$registered) {
                $registered[$handle] = $src;
                return true;
            }
        );

        AssetConfig::replaceJqueryIfConfigured();

        $this->assertSame(
            'https://example.com/wp-content/themes/sage/public/build/assets/resources/scripts/lib/jquery-4.0.0.min.js',
            $registered['jquery-core']
        );
        $this->assertFalse($registered['jquery']);
    }

    public function test_leaves_core_jquery_when_no_path_configured(): void
    {
        Functions\when('is_admin')->justReturn(false);
        Functions\when('is_customize_preview')->justReturn(false);
        Functions\expect('wp_deregister_script')->never();
        Functions\expect('wp_register_script')->never();

        AssetConfig::replaceJqueryIfConfigured();

        $this->addToAssertionCount(1);
    }
}
