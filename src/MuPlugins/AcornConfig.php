<?php

namespace Threespot\Wp\MuPlugins;

/**
 * Acorn storage path on Pantheon.
 *
 * Acorn (the framework under Sage) keeps its cache, compiled Blade views and
 * log in wp-content/cache/acorn by default. Pantheon's code directory is
 * read-only, so on Pantheon this module moves that storage into the private
 * files directory, which is writable on every environment and refused to web
 * requests. The folders are created on the first request, so new environments
 * need no manual setup.
 *
 * This replaces the old convention of a committed wp-content/cache ->
 * uploads/cache symlink plus a cache folder created over SFTP, which left
 * Acorn's log at a public URL (uploads/cache/acorn/logs/laravel.log).
 *
 * Runs only on Pantheon (not Lando), and only when the site hasn't defined
 * ACORN_STORAGE_PATH itself. On a non-Sage site nothing reads the constant.
 *
 * Filters: none. To opt out, define ACORN_STORAGE_PATH in config/application.php.
 */
class AcornConfig
{
    /**
     * Acorn's storage path, relative to WP_CONTENT_DIR. On Pantheon,
     * wp-content/uploads is the files directory and private/ is blocked
     * from the web.
     */
    public const STORAGE_PATH = 'uploads/private/acorn';

    /**
     * Folders Acorn expects under its storage path. Acorn creates these only
     * for its default path (Paths::fallbackStoragePath()), so a custom path
     * has to create them itself.
     */
    public const STORAGE_DIRECTORIES = [
        'framework/cache/data',
        'framework/views',
        'framework/sessions',
        'logs',
    ];

    /**
     * Wire the hook only on Pantheon. Locally Acorn's default path is
     * writable, so Lando and non-Pantheon environments pay nothing.
     */
    public static function register(): void
    {
        $env = $_ENV['PANTHEON_ENVIRONMENT'] ?? null;

        if ($env === null || $env === 'lando') {
            return;
        }

        // setup_theme fires just before WordPress loads the theme's
        // functions.php, where Sage calls Application::configure() and
        // Acorn reads its paths.
        add_action('setup_theme', [self::class, 'setStoragePath']);
    }

    /**
     * Create the storage folders if needed, then point Acorn at them.
     */
    public static function setStoragePath(): void
    {
        if (defined('ACORN_STORAGE_PATH')) {
            return;
        }

        $path = WP_CONTENT_DIR . '/' . self::STORAGE_PATH;

        // framework/cache is the folder Acorn refuses to boot without, so
        // it's the one checked on every request.
        $required = "{$path}/framework/cache";

        if (!is_dir($required)) {
            foreach (self::STORAGE_DIRECTORIES as $directory) {
                wp_mkdir_p("{$path}/{$directory}");
            }
        }

        // If the folders couldn't be created, leave Acorn on its default
        // path so a site that still has the old symlink keeps working.
        if (!is_dir($required)) {
            return;
        }

        define('ACORN_STORAGE_PATH', $path);
    }
}
