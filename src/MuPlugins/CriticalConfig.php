<?php

namespace Threespot\Wp\MuPlugins;

/**
 * Inline critical JS for the page <head>.
 *
 * The bundled script (src/Assets/critical.js) swaps html.no-js -> html.js
 * and tags the html element with ua-safari / ua-old-safari classes that
 * the theme CSS hooks into. It is intentionally written
 * without ES module syntax so it runs everywhere, including legacy
 * browsers that would otherwise skip type="module" scripts.
 *
 * Themes fire this action wherever they want the <script> to appear:
 *
 *     <?php do_action('threespot/critical/inline_script'); ?>
 *
 * Runtime pass at render time:
 *   - strips /* ... *\/ block comments
 *   - drops blank lines and lines whose first non-whitespace chars are //
 *   - trims each remaining line and joins them with a single space, so
 *     the inline <script> is one line in the page source
 *   - tightens whitespace around: { } = += === !== == != <= >= < > || &&
 *   - drops the space after , and after ;
 *   - drops the space in `if (` → `if(`
 *
 * Output is larger than a real JS minifier would produce (no variable
 * renaming, no whitespace collapsing inside expressions, no keyword-
 * adjacent boundary detection); that tradeoff avoids a build step in
 * the package. Constraints on the source file (str_replace tightening
 * is not JS-aware):
 *   - statements must end with explicit ; (no ASI is available — the
 *     joiner does not insert line terminators)
 *   - string and regex literals must not contain the patterns we tighten:
 *     ` { `, ` } `, ` = `, ` += `, ` === `, ` !== `, ` == `, ` != `,
 *     ` <= `, ` >= `, ` < `, ` > `, ` || `, ` && `, `; `, `, `, `if (`
 *
 *     `, ` is the worst foot-gun — any embedded human-readable string like
 *     'Hello, world' will be silently mangled. Add such strings via a
 *     filter / external file instead of inlining them in critical.js.
 */
class CriticalConfig
{
    /**
     * Wire the custom inline-script action. Called once from bootstrap.php.
     *
     * Note this is a CUSTOM action — `threespot/critical/inline_script` is
     * not a WP-core hook. Themes fire it themselves in their head template
     * (see the class docblock for the call site).
     */
    public static function register(): void
    {
        add_action('threespot/critical/inline_script', [self::class, 'printInlineScript']);
    }

    /**
     * Print the minified critical script as an inline `<script>` tag.
     * No-op if the source file couldn't be loaded.
     */
    public static function printInlineScript(): void
    {
        $script = self::loadMinifiedSource();

        if ($script === '') {
            return;
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo "<script>" . $script . "</script>\n";
    }

    /**
     * Read src/Assets/critical.js, pseudo-minify it, and cache the result
     * for the rest of the request.
     *
     * See the class docblock for the rationale and the list of source-file
     * constraints this minifier imposes.
     */
    private static function loadMinifiedSource(): string
    {
        // Per-request cache: this runs at most once per page even if the action
        // somehow fires multiple times.
        static $cached = null;

        if ($cached !== null) {
            return $cached;
        }

        $path = __DIR__ . '/../Assets/critical.js';

        if (!is_readable($path)) {
            return $cached = '';
        }

        $source = (string) file_get_contents($path);

        // Strip /* ... */ block comments (single-line and multi-line).
        $source = (string) preg_replace('#/\*.*?\*/#s', '', $source);

        // Drop blank lines and `// ...` line comments, trimming each kept line.
        $out = [];
        foreach (preg_split('/\R/', $source) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '//')) {
                continue;
            }
            $out[] = $trimmed;
        }

        // Join lines with a single space — the source must use explicit ; because
        // ASI doesn't apply when statements are concatenated this way.
        $joined = implode(' ', $out);

        return $cached = str_replace(
            [
                ' || ', ' && ',
                ' += ',
                ' === ', ' !== ', ' == ', ' != ',
                ' <= ', ' >= ', ' < ', ' > ',
                ' = ',
                '; ', ', ',
                ' {', '{ ', ' }', '} ',
                'if (',
            ],
            [
                '||', '&&',
                '+=',
                '===', '!==', '==', '!=',
                '<=', '>=', '<', '>',
                '=',
                ';', ',',
                '{', '{', '}', '}',
                'if(',
            ],
            $joined
        );
    }
}
