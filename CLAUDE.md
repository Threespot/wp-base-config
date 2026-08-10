# CLAUDE.md

Working notes for editing this package. README.md covers *how a consumer uses
the package*; this file covers *how to extend it without breaking the
contract*. If something is already in README.md it is not repeated here.

## General Behavior

When answering WordPress/PHP questions, provide a direct answer first before exploring the codebase. Only investigate files if the user asks for project-specific context or if the question genuinely requires it.

## Code Style / Conventions

Never introduce Unicode smart quotes or curly quotes in any code file (PHP, JS, SCSS, Blade, JSON, etc.). Always use straight quotes (' and "). Do not alter existing comments solely to replace curly quotes with straight quotes — only avoid introducing new ones. A PostToolUse hook in `.claude/settings.json` scans added lines after every Edit and will block if any contain curly quotes.

## Repo layout reminders

- `bootstrap.php` is the single registration entrypoint. Every new
  `*Config` module must be added to its `register()` list.
- `mu-plugins/threespot-wp-base-config.php` is the WP loader stub copied/
  symlinked into the consuming site's `web/app/mu-plugins/`. It does
  nothing but `require` the package's `bootstrap.php`. Do not put logic in it.
- Every other file in `mu-plugins/` is a **standalone drop-in** (currently
  `acf-local-json-autosync.php`, `site-health-auth-guard.php`,
  `suppress-admin-notices.php`): self-contained, never registered in
  `bootstrap.php`, copied — not symlinked — into a site's
  `web/app/mu-plugins/` only when that site needs it. The `src/MuPlugins`
  conventions below (named static methods, `threespot/*` filters, docblock
  filter lists, RegistrationTest assertions) do NOT apply to drop-ins,
  because sites own and edit their copies directly. Each drop-in must be
  self-documenting via its plugin header and listed in the README's
  "Standalone drop-in mu-plugins" table.
- `src/Sage/` holds Acorn/Vite-aware adapters. These are the ONLY files in
  the package that may `use Illuminate\Support\Facades\*`. Everything in
  `src/MuPlugins/` and `src/Helpers/` must run on plain WP without Acorn.
- `src/Assets/critical.js`, `src/Assets/gutenberg-defaults.js`, and
  `src/Assets/block-defaults.jsx` are bundled JS. `critical.js` is loaded
  by `CriticalConfig` at runtime and pseudo-minified in PHP. The other
  two are imported by themes through their own build via the
  `@threespot-base-config` Vite alias. `block-defaults.jsx` uses JSX,
  which the package's `vite-base.js` transpiles via oxc with the
  `wp.element.createElement` / `wp.element.Fragment` pragmas — keep that
  runtime in mind if adding more JSX assets.

## Conventions for `src/MuPlugins/*Config.php`

1. **Named static methods, never closures.** Hooks must be `[self::class,
   'methodName']` so sites can `remove_action(...)` individual behaviors.
   Closures are unhookable and break the override contract.
2. **One `public static function register(): void`** that wires every
   hook. No constructor, no instance state.
3. **Filter naming:** `threespot/<area>/<key>` (forward slashes).
   `<area>` matches the module slug: `login`, `admin`, `assets`, `blocks`,
   `acf`, `theme`, `smtp`, `taxonomy`, `critical`, `helpers`.
4. **Defaults are class constants** (`DEFAULT_FOO = [...]`) passed as the
   second arg to `apply_filters(...)`. This lets tests assert on the
   default list without instantiating WP.
5. **Subtractive list pattern:** when the module strips things by default
   (dashboard widgets, admin-bar nodes, etc.), pair it with a
   `threespot_keep_*` / `threespot_remove_*` helper in `src/PublicApi/
   functions.php`. The list filter's default lives in the module; both
   helpers route through `threespot_remove_from_list` / `threespot_keep_in_list`.
6. **Module docblock must list every filter** the module fires. See
   `AdminConfig.php` for the format. The README's filter reference table
   is hand-maintained from these docblocks — update both when adding a filter.
7. **No `Vite::asset()` calls.** Modules fire `threespot/<area>/<key>_url`
   filters; the consuming theme supplies URLs. This is what lets a non-Sage
   site adopt the package.

## Conventions for `src/Helpers/*.php` and `src/PublicApi/functions.php`

- Helpers live in namespace `Threespot\Wp\Helpers`. They are autoloaded
  via `composer.json` `autoload.files` — every new helper file must be
  added there, or it won't load.
- Public API functions are global and prefixed `threespot_`. Wrap every
  declaration in `if (!function_exists('...'))` — the file is loaded on
  every request and double-defining a function is fatal.
- Public API helpers are variadic (`string ...$ids`). Keep that shape
  when adding new pairs.
- `image.php` reads a site-defined `IMAGE_SIZES` constant. Do not move
  that constant into the package — image dimensions are per-project.

## Tests

- Run with `composer test`. PHPUnit 10/11 + Brain Monkey. ~90 tests,
  sub-second, no DB.
- Bootstrap (`tests/bootstrap.php`) defines a fixture `IMAGE_SIZES`
  constant so `image.php` helpers load.
- New test classes extend `Threespot\Wp\Tests\BrainMonkeyTestCase` —
  it sets up/tears down the Mockery container per-test.
- `tests/MuPlugins/RegistrationTest.php` is a smoke harness: it stubs
  `add_action` / `add_filter` / `remove_action` and asserts each
  `*Config::register()` wires the expected hook → callable pairs.
  When you add a hook to a module, add a matching assertion here.
- Behavior tests for hook callbacks are out of scope for v0.1 (the
  pilot site is the end-to-end verification). Stick to: pure helpers,
  the public API, and registration smoke tests.
- `phpunit.xml.dist` sets `failOnWarning="true"` and
  `failOnNotice="true"` — a deprecated stub or undefined-index warning
  fails the suite. Don't suppress; fix.
- CI runs the same suite on PHP 8.1 / 8.2 / 8.3.

## End-to-end verification

There is no headless integration harness in the repo. After a non-trivial
change, a test Lando site is used as the verification surface.

## Hard constraints to respect

- **`dist/vite-base.js` must have no bare npm imports.** Vite's config
  loader follows symlinks to `vendor/` before resolve options apply, so
  any `import x from 'some-plugin'` inside the base would emit
  `UNRESOLVED_IMPORT` on every consuming build. The base receives
  plugin constructors as arguments (`threespotViteBase({eslint,
  stylelint, ...})`) and instantiates them itself. Keep that shape.
- **`dist/package.json` is load-bearing, not a stray npm manifest.** Its
  single key (`{"type": "module"}`) is what makes Node and Vite treat
  `dist/vite-base.js` and `dist/eslint.config.js` as ESM. Both are loaded
  through a symlink into `vendor/`, so the nearest `package.json` is resolved
  from the real path — the consuming theme's own `"type": "module"` never
  applies, and without this file the search reaches the Bedrock project root
  and comes back CommonJS. Delete it and every consuming build warns
  (`ESM syntax in a file loaded as CommonJS`), and the config fails outright
  under Vite's `configLoader: 'native'`. Consequence for new files: everything
  in `dist/` is ESM by default — use a `.cjs` extension if it needs `require`.
- **`src/Assets/critical.js` is pseudo-minified by string replacement
  at runtime** in `CriticalConfig::loadMinifiedSource()`. The header
  comment on `CriticalConfig` lists the patterns that get tightened —
  most importantly, **a literal `, ` inside a string is silently
  mangled**. If a critical script needs a human-readable string, pass
  it in via a filter rather than baking it into `critical.js`.
- **Pantheon env check** (`$_ENV['PANTHEON_ENVIRONMENT']`) is hardcoded
  in `AdminConfig` and `SmtpConfig`. v0.1 assumes every consumer is on
  Pantheon. Don't generalize this until a non-Pantheon site enters
  the fleet — the planning doc explicitly defers that.

## When adding a new mu-plugin module

1. Create `src/MuPlugins/FooConfig.php` following the conventions above.
2. Add `FooConfig::register();` to `bootstrap.php`.
3. Add a `test_foo_config_registers_expected_hooks()` to
   `tests/MuPlugins/RegistrationTest.php`.
4. Document every filter in the class docblock and add a row to the
   filter reference tables in `README.md`.
5. If the module has any subtractive lists, ship matching
   `threespot_keep_*` / `threespot_remove_*` pairs in
   `src/PublicApi/functions.php` and document the pairing in the
   "Subtractive lists" table.

## When changing a public name

Filter names, helper function names, and `threespot_*` API names are
the user-facing contract. Even pre-v1.0, grep every consuming site
before renaming (currently: the Lando-local pilot site). Renames are
a tag-worthy event; mention them in the commit message.
