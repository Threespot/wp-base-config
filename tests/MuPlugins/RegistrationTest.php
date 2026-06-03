<?php

namespace Threespot\Wp\Tests\MuPlugins;

use Brain\Monkey\Functions;
use Threespot\Wp\Tests\BrainMonkeyTestCase;

use Threespot\Wp\MuPlugins\AcfConfig;
use Threespot\Wp\MuPlugins\AdminConfig;
use Threespot\Wp\MuPlugins\AssetConfig;
use Threespot\Wp\MuPlugins\BlockConfig;
use Threespot\Wp\MuPlugins\CriticalConfig;
use Threespot\Wp\MuPlugins\LoginConfig;
use Threespot\Wp\MuPlugins\SmtpConfig;
use Threespot\Wp\MuPlugins\ThemeConfig;

/**
 * Verifies each *Config::register() wires the expected hooks.
 *
 * These are intentionally smoke tests, not behavior tests: we assert the
 * hook name and the target callable, not what the callable does. The point
 * is to catch typos in hook names and "I forgot to register this method"
 * bugs at refactor time. The callable's behavior is tested separately
 * (or, for now, verified end-to-end on the Lando-local pilot site).
 */
class RegistrationTest extends BrainMonkeyTestCase
{
    /**
     * @var array<string, list<array{callable: callable|string|array, priority: int}>>
     */
    private array $actions = [];

    /**
     * @var array<string, list<array{callable: callable|string|array, priority: int}>>
     */
    private array $filters = [];

    /**
     * @var list<array{hook: string, callable: callable|string|array}>
     */
    private array $removedActions = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->actions = [];
        $this->filters = [];
        $this->removedActions = [];

        Functions\when('add_action')->alias(function ($hook, $cb, $priority = 10, $args = 1) {
            $this->actions[$hook][] = ['callable' => $cb, 'priority' => $priority];
            return true;
        });

        Functions\when('add_filter')->alias(function ($hook, $cb, $priority = 10, $args = 1) {
            $this->filters[$hook][] = ['callable' => $cb, 'priority' => $priority];
            return true;
        });

        Functions\when('remove_action')->alias(function ($hook, $cb, $priority = 10) {
            $this->removedActions[] = ['hook' => $hook, 'callable' => $cb];
            return true;
        });
    }

    /**
     * Assert any callback registered against $hook matches $expectedCallable.
     */
    private function assertActionRegistered(string $hook, $expectedCallable = null): void
    {
        $this->assertArrayHasKey($hook, $this->actions, "No action registered for $hook");

        if ($expectedCallable !== null) {
            $match = false;
            foreach ($this->actions[$hook] as $registered) {
                if ($registered['callable'] === $expectedCallable) {
                    $match = true;
                    break;
                }
            }
            $this->assertTrue($match, "Expected callable not registered for action $hook");
        }
    }

    private function assertFilterRegistered(string $hook, $expectedCallable = null): void
    {
        $this->assertArrayHasKey($hook, $this->filters, "No filter registered for $hook");

        if ($expectedCallable !== null) {
            $match = false;
            foreach ($this->filters[$hook] as $registered) {
                if ($registered['callable'] === $expectedCallable) {
                    $match = true;
                    break;
                }
            }
            $this->assertTrue($match, "Expected callable not registered for filter $hook");
        }
    }

    public function test_login_config_registers_expected_hooks(): void
    {
        LoginConfig::register();

        $this->assertActionRegistered('login_enqueue_scripts', [LoginConfig::class, 'enqueueLoginStyles']);
        $this->assertFilterRegistered('login_headerurl', [LoginConfig::class, 'filterHeaderUrl']);
        $this->assertFilterRegistered('login_headertext', [LoginConfig::class, 'filterHeaderText']);
    }

    public function test_smtp_config_registers_phpmailer_only_in_lando(): void
    {
        // Without PANTHEON_ENVIRONMENT, register() should bail.
        unset($_ENV['PANTHEON_ENVIRONMENT']);
        Functions\when('apply_filters')->returnArg(2);

        SmtpConfig::register();

        $this->assertArrayNotHasKey('phpmailer_init', $this->actions);
    }

    public function test_smtp_config_registers_when_lando(): void
    {
        $_ENV['PANTHEON_ENVIRONMENT'] = 'lando';
        Functions\when('apply_filters')->returnArg(2);

        try {
            SmtpConfig::register();
            $this->assertActionRegistered('phpmailer_init', [SmtpConfig::class, 'configurePhpMailer']);
        } finally {
            unset($_ENV['PANTHEON_ENVIRONMENT']);
        }
    }

    public function test_acf_config_registers_expected_hooks(): void
    {
        AcfConfig::register();

        $this->assertActionRegistered('acf/init', [AcfConfig::class, 'addOptionsPage']);
        $this->assertFilterRegistered('acf/fields/wysiwyg/toolbars', [AcfConfig::class, 'customizeWysiwygToolbars']);
        $this->assertActionRegistered('acf/render_field_settings', [AcfConfig::class, 'addHideLabelSetting']);
        $this->assertFilterRegistered('acf/prepare_field', [AcfConfig::class, 'maybeHideFieldLabel']);
    }

    public function test_theme_config_registers_expected_hooks(): void
    {
        ThemeConfig::register();

        $this->assertActionRegistered('after_setup_theme', [ThemeConfig::class, 'addThemeSupport']);
        $this->assertFilterRegistered('get_the_archive_title', [ThemeConfig::class, 'filterArchiveTitle']);
        $this->assertFilterRegistered('excerpt_length', [ThemeConfig::class, 'filterExcerptLength']);
        $this->assertFilterRegistered('excerpt_more', [ThemeConfig::class, 'filterExcerptMore']);
        $this->assertFilterRegistered('protected_title_format', [ThemeConfig::class, 'filterProtectedTitleFormat']);
        $this->assertFilterRegistered('wpseo_title', [ThemeConfig::class, 'filterYoastTitle']);
        $this->assertActionRegistered('save_post', [ThemeConfig::class, 'removeDefaultCategoryWhenOtherPresent']);
    }

    public function test_block_config_registers_expected_hooks(): void
    {
        BlockConfig::register();

        $this->assertFilterRegistered('style_loader_src', [BlockConfig::class, 'forceAbsoluteStyleUrls']);
        $this->assertFilterRegistered('block_categories_all', [BlockConfig::class, 'addBlockCategory']);
        $this->assertActionRegistered('init', [BlockConfig::class, 'addPatternCategory']);
        $this->assertActionRegistered('init', [BlockConfig::class, 'unregisterBlockBindingsSources']);
        $this->assertFilterRegistered('the_content', [BlockConfig::class, 'stripEmptyParagraphs']);
        $this->assertFilterRegistered('wp_content_img_tag', [BlockConfig::class, 'stripAutoSizesFromContent']);
        $this->assertFilterRegistered('wp_get_attachment_image_attributes', [BlockConfig::class, 'stripAutoSizesFromAttachment']);
        $this->assertFilterRegistered('register_block_type_args', [BlockConfig::class, 'disableHeadingLevels']);
        $this->assertFilterRegistered('render_block', [BlockConfig::class, 'customizeBlockMarkup']);
        $this->assertActionRegistered('enqueue_block_editor_assets', [BlockConfig::class, 'enqueueEditorAssets']);
        $this->assertFilterRegistered('block_editor_settings_all', [BlockConfig::class, 'addEditorStyles']);
    }

    public function test_admin_config_registers_expected_hooks(): void
    {
        AdminConfig::register();

        // Spot-check a representative sample across the module's surface
        $this->assertActionRegistered('admin_enqueue_scripts', [AdminConfig::class, 'enqueueAdminStyles']);
        $this->assertActionRegistered('wp_dashboard_setup', [AdminConfig::class, 'removeDashboardWidgets']);
        $this->assertActionRegistered('admin_menu', [AdminConfig::class, 'removeMenuPages']);
        $this->assertActionRegistered('wp_before_admin_bar_render', [AdminConfig::class, 'customizeAdminBar']);
        $this->assertActionRegistered('customize_register', [AdminConfig::class, 'removeCustomizerSections']);
        $this->assertActionRegistered('init', [AdminConfig::class, 'removeUserRoles']);
        $this->assertFilterRegistered('site_status_tests', [AdminConfig::class, 'removeSiteStatusTests']);
        $this->assertActionRegistered('user_register', [AdminConfig::class, 'setDefaultScreenOptions']);
        $this->assertFilterRegistered('robots_txt', [AdminConfig::class, 'customizeRobotsTxt']);
        $this->assertFilterRegistered('register_taxonomy_args', [AdminConfig::class, 'hideTaxonomiesFromNavMenus']);
        $this->assertFilterRegistered('upload_mimes', [AdminConfig::class, 'allowSvgUploads']);

        // Top-level side effects
        $this->assertFilterRegistered('admin_footer_text', '__return_null');
        $this->assertFilterRegistered('xmlrpc_enabled', '__return_false');

        // remove_action('wp_head', 'wp_generator') is a top-level side effect of register()
        $removed = false;
        foreach ($this->removedActions as $r) {
            if ($r['hook'] === 'wp_head' && $r['callable'] === 'wp_generator') {
                $removed = true;
                break;
            }
        }
        $this->assertTrue($removed, 'register() should remove wp_generator from wp_head');
    }

    public function test_asset_config_registers_expected_hooks(): void
    {
        AssetConfig::register();

        $this->assertActionRegistered('wp_enqueue_scripts', [AssetConfig::class, 'replaceJqueryIfConfigured']);
        $this->assertActionRegistered('wp_enqueue_scripts', [AssetConfig::class, 'disableOembedDiscovery']);
        $this->assertFilterRegistered('script_loader_tag', [AssetConfig::class, 'deferScripts']);
    }

    public function test_critical_config_registers_expected_hooks(): void
    {
        CriticalConfig::register();

        $this->assertActionRegistered('threespot/critical/inline_script', [CriticalConfig::class, 'printInlineScript']);
    }
}
