<?php

namespace Threespot\Wp\Cli;

use InvalidArgumentException;

/**
 * Copies SearchWP engine settings between environments.
 *
 * `wp threespot searchwp export-engines` and `apply-engines`. SearchWP keeps its engines in the database (the searchwp_engines option),
 * so each environment otherwise has to be set up by hand in the admin.
 * Export the engines where they're right, commit the JSON, then apply it on
 * each environment and rebuild the index.
 *
 * Registered in bootstrap.php only when WP-CLI is running. It sits beside
 * threespot/wp-fixtures' `wp threespot fixtures` under the same `threespot`
 * parent command.
 */
class SearchwpCommand
{
    /**
     * Prints SearchWP's engine settings as JSON, or saves them to a file.
     *
     * ## OPTIONS
     *
     * [<file>]
     * : File to write. Prints to the screen when left out.
     *
     * ## EXAMPLES
     *
     *     wp threespot searchwp export-engines
     *     wp threespot searchwp export-engines config/searchwp-engines.json
     *
     * @subcommand export-engines
     * @when after_wp_load
     *
     * @param array<int, string> $args
     * @param array<string, string> $assoc_args
     */
    public function exportEngines(array $args, array $assoc_args): void
    {
        self::requireSearchwp();

        $engines = \SearchWP\Settings::_get_engines_settings(true);

        if ($engines === []) {
            \WP_CLI::warning('SearchWP has no engines saved yet.');
        }

        $json = self::encodeEngines($engines);
        $file = $args[0] ?? '';

        if ($file === '') {
            echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON for the terminal or a redirect, not HTML
            return;
        }

        if (file_put_contents($file, $json) === false) {
            \WP_CLI::error("Could not write $file.");
        }

        \WP_CLI::success(sprintf('Saved %d SearchWP engine(s) to %s.', count($engines), $file));
    }

    /**
     * Replaces SearchWP's engine settings with the ones in a JSON file.
     *
     * Every engine is replaced, and an engine missing from the file is
     * removed. Rebuild the index afterwards.
     *
     * ## OPTIONS
     *
     * <file>
     * : JSON file from `wp threespot searchwp export-engines`.
     *
     * ## EXAMPLES
     *
     *     wp threespot searchwp apply-engines config/searchwp-engines.json
     *     wp searchwp index --rebuild
     *
     * @subcommand apply-engines
     * @when after_wp_load
     *
     * @param array<int, string> $args
     * @param array<string, string> $assoc_args
     */
    public function applyEngines(array $args, array $assoc_args): void
    {
        self::requireSearchwp();

        $file = $args[0] ?? '';

        if ($file === '' || !is_readable($file)) {
            \WP_CLI::error("Can't read $file.");
        }

        try {
            $engines = self::decodeEngines((string) file_get_contents($file));
        } catch (InvalidArgumentException $e) {
            \WP_CLI::error($file . ': ' . $e->getMessage());
        }

        // SearchWP's own import path checks each engine and flags the index as
        // outdated when it needs a rebuild. Older versions lack it.
        if (class_exists('\SearchWP\Admin\Views\EnginesView') && method_exists('\SearchWP\Admin\Views\EnginesView', 'update_engines')) {
            \SearchWP\Admin\Views\EnginesView::update_engines($engines);
        } else {
            \SearchWP\Settings::update_engines_config($engines);
        }

        \WP_CLI::success(sprintf('Applied %d SearchWP engine(s): %s.', count($engines), implode(', ', array_keys($engines))));
        \WP_CLI::log('Now rebuild the index: wp searchwp index --rebuild');
    }

    /**
     * Engine settings as readable JSON, ending in a newline.
     *
     * @param array<string, mixed> $engines
     */
    public static function encodeEngines(array $engines): string
    {
        return json_encode($engines, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }

    /**
     * Engine settings from JSON, checked enough to catch the wrong file.
     *
     * @return array<string, array<string, mixed>>
     * @throws InvalidArgumentException When the JSON isn't a set of named engines with sources
     */
    public static function decodeEngines(string $json): array
    {
        $engines = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('not valid JSON (' . json_last_error_msg() . ').');
        }

        if (!is_array($engines) || $engines === []) {
            throw new InvalidArgumentException('no engines found.');
        }

        foreach ($engines as $name => $engine) {
            if (!is_string($name)) {
                throw new InvalidArgumentException('expected engine names as keys, e.g. {"default": {...}}.');
            }

            if (!is_array($engine) || !isset($engine['sources']) || !is_array($engine['sources'])) {
                throw new InvalidArgumentException("engine \"$name\" has no sources.");
            }
        }

        return $engines;
    }

    /**
     * Stop with a clear message when SearchWP isn't active.
     */
    private static function requireSearchwp(): void
    {
        if (!class_exists('\SearchWP\Settings')) {
            \WP_CLI::error('SearchWP is not active on this site, so there are no engines to export or apply.');
        }
    }
}
