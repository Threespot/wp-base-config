# threespot/wp-base-config

Shared WordPress configuration and helper functions for Threespot sites.

## What this is

Threespot WordPress sites historically copy-pasted the same ~12 PHP files
(`admin-config.php`, `asset-config.php`, `image-helpers.php`, etc.) into every
new project's Sage theme. Improvements made on one site didn't flow back to
the others, and every new build started by porting bug fixes from whichever
site had the most recent version.

This repo extracts that shared layer into one private Composer package. A site
adds it as a dependency, points it at its compiled asset URLs via a few
filters, and replaces hundreds of lines of `app/*-config.php` boilerplate with
~15 lines of helper calls.

## What's in the package

Two kinds of code:

**MU-plugin modules** (`src/MuPlugins/*Config.php`) register WordPress hooks
and ship as a single mu-plugin. They survive theme swaps, exactly like the
legacy `app/*-config.php` files did. The nine modules cover:

| Module | Responsibility |
|---|---|
| `AcfConfig`      | ACF options page, custom WYSIWYG toolbars, "Hide Label" field setting |
| `AdminConfig`    | Dashboard widgets, admin bar, customizer sections, robots.txt, SVG uploads, ~20 other behaviors |
| `AssetConfig`    | Script `defer` policy, optional local jQuery, oEmbed cleanup |
| `BlockConfig`    | Block editor polish: custom category, h1 → h2, lazy iframes, editor CSS/JS |
| `ContentTypesConfig` | Auto-loads site-level CPT/taxonomy definitions from a fixed mu-plugins convention; auto-applies taxonomy `query_var` filters to archive queries |
| `CriticalConfig` | Inline critical `<script>` in `<head>` — `no-js`/`js` swap, iOS / old-Safari UA classes |
| `LoginConfig`    | `/wp-login.php` logo URL/text and custom stylesheet enqueue |
| `SmtpConfig`     | PHPMailer override (Mailhog on Lando by default) |
| `ThemeConfig`    | `add_theme_support` calls, archive-title cleanup, excerpt format |

**Helper functions** (`src/Helpers/*.php`, namespace `Threespot\Wp\Helpers`)
are pure PHP utilities autoloaded via Composer's `autoload.files`. Templates
call them directly. The four files mirror the legacy `app/` layout:
`general.php`, `image.php`, `svg.php`, `taxonomy.php`.

A small **public API** (`src/PublicApi/functions.php`) exposes global
`threespot_*` helpers — `threespot_remove_dashboard_widget(...)`,
`threespot_no_defer_script(...)`, etc. — so per-site overrides stay
declarative instead of fiddling with raw filters.

## Installing in a WordPress project

Two steps. Assumes a Bedrock-based site (i.e. `vendor/autoload.php` is loaded
by `config/application.php`).

### 1. Add the package to the site's composer.json

```jsonc
{
  "require": {
    "threespot/wp-base-config": "^0.1"
  },
  "repositories": [
    { "type": "vcs", "url": "https://github.com/threespot/wp-base-config" }
  ]
}
```

Then `composer update threespot/wp-base-config`. The helper functions and
public API are now autoloaded on every request — they work immediately.

### 2. Symlink the mu-plugin loader

WordPress only auto-loads files directly inside `web/app/mu-plugins/`, not
files nested in vendor packages. Symlink the loader from the site root:

```bash
ln -s ../../vendor/threespot/wp-base-config/mu-plugins/threespot-wp-base-config.php \
      web/app/mu-plugins/threespot-wp-base-config.php
```

(Or copy it, if symlinks don't fit the deploy target. The loader is a 25-line
file that does nothing except `require` the package's `bootstrap.php`.)

After `composer install`, verify:

```bash
ls -la web/app/mu-plugins/threespot-wp-base-config.php
lando wp eval 'echo class_exists("Threespot\\Wp\\MuPlugins\\AdminConfig") ? "OK\n" : "missing\n";'
```

## Using it in a theme

Once installed, a theme's `functions.php` typically does three things:

### Wire up the theme's compiled asset URLs

The package never calls `Vite::asset()` directly — it can't, because it has to
work for non-Sage themes too. Instead it fires filters at every enqueue point
and the theme supplies the URL:

```php
use Illuminate\Support\Facades\Vite;

add_filter('threespot/login/css_url',          fn() => Vite::asset('resources/styles/login.scss'));
add_filter('threespot/admin/all_css_url',      fn() => Vite::asset('resources/styles/admin-all.scss'));
add_filter('threespot/admin/fields_css_url',   fn() => Vite::asset('resources/styles/admin-fields.scss'));
add_filter('threespot/blocks/editor_css_urls', fn() => [
    Vite::asset('resources/styles/gutenberg.scss'),
    Vite::asset('resources/styles/main.scss'),
]);
add_filter('threespot/blocks/editor_js_urls',  fn() => [
    Vite::asset('resources/scripts/gutenberg.js'),
]);
```

A non-Sage theme would substitute `get_template_directory_uri() . '/path'`.

### Inline the critical `<script>` in `<head>`

`CriticalConfig` ships a small inline script (`no-js` → `js`, iOS / old-Safari
UA classes). The theme decides *where* it gets inlined by firing an action:

```blade
{{-- in head.blade.php, before wp_head() --}}
@php(do_action('threespot/critical/inline_script'))
```

Plain PHP equivalent:

```php
<?php do_action('threespot/critical/inline_script'); ?>
```

The package strips comments and blank lines at render time and caches the
result for the request. Output is bigger than a real JS minifier would
produce (no whitespace collapsing inside statements) — see
`src/MuPlugins/CriticalConfig.php` for the tradeoff.

### Supply the `roots/acorn-prettify` config

`Threespot\Wp\Sage\prettify_config()` returns Threespot's default config for
the [`roots/acorn-prettify`](https://github.com/roots/acorn-prettify) package
(HTML clean-up, nice-search, relative URLs). Acorn only auto-loads config from
the *theme's* `config/` directory, so keep a thin `config/prettify.php` that
pulls the defaults in and layers per-site overrides on top:

```php
<?php
return array_replace_recursive(
    Threespot\Wp\Sage\prettify_config(),
    [
        // Per-site overrides, e.g.:
        // 'clean-up' => ['disable-gutenberg-block-css' => true],
    ]
);
```

`array_replace_recursive` merges the numerically-indexed `relative-urls.hooks`
list *by index*, so to change that list assign it wholesale in the override
array rather than relying on the recursive merge.

### Tweak defaults via the public API

The defaults reflect what the legacy theme files did. Site-specific deltas go
through the `threespot_*` helpers:

```php
// The Yoast dashboard widget is removed by default — put it back on this site.
threespot_keep_dashboard_widget('wpseo-dashboard-overview');

// Add a custom admin-bar node to the removal list.
threespot_remove_admin_bar_node('my-plugin-node');

// jQuery deferral is blocked by default; this site has a plugin that also breaks under defer.
threespot_no_defer_script('my-plugin-js');

// This site does NOT want the customizer "colors" section removed.
threespot_keep_customizer_section('colors');
```

Each helper is variadic — `threespot_remove_admin_bar_node('a', 'b', 'c')`.

### Override single-value settings via raw filters

For things that aren't subtractive lists, just `add_filter` directly:

```php
add_filter('threespot/blocks/vimeo_color',         fn() => '0066ff');
add_filter('threespot/acf/options_page_title',     fn() => 'Site Settings');
add_filter('threespot/admin/is_internal_user', function ($is_internal, $user) {
    return str_contains($user->user_email, '@threespot.com');
}, 10, 2);
```

### Comments

The package's stance is **comments off site-wide**, re-enabled per post type.
`AdminConfig::disableCommentSupport()` strips `comments` and `trackbacks`
support from every post type returned by `get_custom_post_types()` (which
includes `post` and `page`), the Comments menu page and admin-bar node are
removed, and `edit-comments.php` redirects to the dashboard.

> **⚠️ This writes to the database.** Removing `comments` support changes what
> WordPress *saves*, not just what the admin shows. `get_default_comment_status()`
> returns `'closed'` for any post type without comment support — ignoring your
> `default_comment_status` option — and that value is stored in
> `wp_posts.comment_status` on every post as it is created.
>
> **The value persists.** Re-enabling comments later does **not** reopen posts
> that were created while comments were off. Those rows need a database repair.

To re-enable comments for one post type:

```php
threespot_enable_comments('post');
```

To re-enable comments site-wide — this empties the disable list, stops the
`edit-comments.php` redirect, and restores the Comments menu page and admin-bar
node in one call:

```php
threespot_enable_comments();
```

Both are variadic and both only affect posts created from that point on.

One core quirk to know about: `get_default_comment_status()` hardcodes
`'closed'` for the `page` post type *before* it checks support, so pages are
saved closed on stock WordPress too. `threespot_enable_comments('page')` restores
the comment metabox but does not change what gets written at creation — you also
need `add_filter('get_default_comment_status', ...)` for that.

To
reopen content that was already saved closed, repair the rows directly. Check
the damage first:

```bash
wp post list --post_type=post --comment_status=closed --format=count
```

Then, once you're satisfied that's the set you want reopened:

```bash
wp db query "UPDATE wp_posts SET comment_status = 'open' \
  WHERE post_type = 'post' AND post_status = 'publish' AND comment_status = 'closed';"
```

Scope the `WHERE` clause deliberately — it cannot distinguish posts closed by
this package from posts an editor closed on purpose. Back up before running it
on production, and note that Pantheon's table prefix may not be `wp_`.

### Calling helper functions from templates

Helpers live in the `Threespot\Wp\Helpers` namespace. Import once and use
short names:

```php
use function Threespot\Wp\Helpers\{img_tag, svg, get_primary_term};

echo img_tag($image_id, ['ratio' => 'sixteen_nine', 'class' => 'hero']);
echo svg(['file' => 'icons/search', 'class' => 'icon', 'width' => 18]);
```

Or fully-qualify inline (handy in Blade):

```blade
{!! \Threespot\Wp\Helpers\img_tag($image_id, ['ratio' => 'sixteen_nine']) !!}
```

**Migrating a theme that already uses `App\img_tag(...)`** — drop a thin shim
in the theme's `app/helpers.php` so existing templates keep working:

```php
namespace App;
function img_tag(...$args) { return \Threespot\Wp\Helpers\img_tag(...$args); }
function svg(...$args)     { return \Threespot\Wp\Helpers\svg(...$args); }
```

## Registering custom post types and taxonomies

`ContentTypesConfig` auto-loads site-level CPT and taxonomy definitions from a
fixed convention inside the site's mu-plugins directory:

```
mu-plugins/
├── custom-post-types/
│   └── post-types/
│       └── <name>.php          # one file per CPT
└── custom-taxonomies/
    └── taxonomies/
        └── <name>.php          # one file per taxonomy
```

Sites do not need to wire anything up — drop a file in the expected folder
and it's loaded on `plugins_loaded`. Each file registers one CPT or one
taxonomy via [extended-cpts](https://github.com/johnbillion/extended-cpts):

```php
// mu-plugins/custom-taxonomies/taxonomies/news-type.php
add_action('init', function () {
  register_extended_taxonomy('news_type', ['news'], [
    'query_var' => true,        // ← auto-enables ?news_type=… on archives
    'show_in_rest' => true,
    'show_admin_column' => true,
    // ...
  ], [
    'singular' => 'News Type',
    'plural' => 'News Types',
    'slug' => 'news-type',
  ]);
});
```

### Automatic taxonomy query-var filtering

Setting `'query_var' => true` is the **one and only opt-in** for URL filtering.
WordPress already registers the public query var as part of `register_taxonomy()`,
and `ContentTypesConfig` translates it into a `tax_query` clause on the main
search/archive query. Multiple taxonomies combine with `AND` — visiting
`/news/?news_type=press-release&region=northeast` returns only items
tagged with both.

The dispatcher introspects registered taxonomies at query time, so any
non-core taxonomy with `query_var => true` opts in automatically — including
taxonomies registered by other plugins. If that ever becomes a problem,
add a skip-list inside `ContentTypesConfig::applyTaxonomyQueryVars()` rather
than special-casing per site.

### Archive-specific query tweaks

Custom `orderby`, `posts_per_archive_page`, ACF meta sorts — anything keyed by
post type — belongs in the same file as `register_extended_post_type`. One
file = one concern.

```php
// mu-plugins/custom-post-types/post-types/event.php
add_action('init', function () {
  register_extended_post_type('event', [/* ... */], [/* ... */]);
});

add_action('pre_get_posts', function ($query) {
  if (is_admin() || !$query->is_main_query() || !$query->is_post_type_archive('event')) {
    return;
  }
  $query->set('meta_key', 'start_time');
  $query->set('meta_type', 'DATE');
  $query->set('orderby', ['meta_value' => 'DESC']);
});
```

Requires `johnbillion/extended-cpts` (any version). Sites without it will
silently skip loading — no fatal errors.

## Frontend tooling configs (`dist/`)

The package ships Threespot's standard ESLint, Stylelint, PostCSS, and
`postcss-pxtorem` configs in `dist/`. Themes consume them via symlinks so
every site picks up rule updates with `composer update`.

Files:

```
dist/
├── eslint.config.js       # ESLint v9 flat config (browser + Gutenberg globals)
├── .stylelintrc.cjs       # Stylelint w/ stylelint-scss
├── postcss.config.cjs     # postcss-pxtorem + postcss-preset-env
├── .pxtorem-config.cjs    # px → rem conversion rules (loaded by postcss.config.cjs)
└── vite-base.js           # Shared Vite config (consumed via mergeConfig — see below)
```

### Symlink them from the theme

From inside the theme directory (e.g. `web/wp-content/themes/sage/`):

```bash
ln -sf ../../../../vendor/threespot/wp-base-config/dist/eslint.config.js   eslint.config.js
ln -sf ../../../../vendor/threespot/wp-base-config/dist/.stylelintrc.cjs   .stylelintrc.cjs
ln -sf ../../../../vendor/threespot/wp-base-config/dist/postcss.config.cjs postcss.config.cjs
ln -sf ../../../../vendor/threespot/wp-base-config/dist/.pxtorem-config.cjs .pxtorem-config.cjs
```

The four-level `../../../../` walks from the theme dir up to the Bedrock
project root, then back down into `vendor/`. Commit the symlinks; `vendor/`
is recreated on every `composer install`, so the link targets are always
populated.

### Heads-up: symlinked configs and `node_modules`

ESLint v9 flat config uses ESM `import` for plugins (`@eslint/js`,
`eslint-plugin-react`, etc.). By default Node resolves symlinks before
walking up to find `node_modules` — which means a symlinked
`eslint.config.js` would look for plugins under
`vendor/threespot/wp-base-config/node_modules`, not the theme's.

If ESLint or Stylelint can't find their plugins, run them with
`--preserve-symlinks`:

```jsonc
// package.json
"scripts": {
  "lint:js":  "NODE_OPTIONS=--preserve-symlinks eslint \"resources/scripts/**/*.{js,jsx}\"",
  "lint:css": "NODE_OPTIONS=--preserve-symlinks stylelint \"resources/**/*.{css,scss}\""
}
```

PostCSS and `postcss-pxtorem` resolve their plugin lists themselves and
aren't affected. The sibling `require('./.pxtorem-config.cjs')` inside
`postcss.config.cjs` also works through the symlink, since both files live
in `dist/`.

### Vite: shared base + per-project wrapper

`vite.config.js` doesn't fit a pure-symlink pattern because each project
needs its own `laravel-vite-plugin` inputs and Lando hostname. The package
ships `dist/vite-base.js` exporting `threespotViteBase({plugins, ...options})` — a
function that returns the stable Vite config (plugin options, aliases,
esbuild, SVG sprite, server defaults). It also accepts optional per-theme
options: `jqueryPath` (host a local jQuery) and `staticCopyTargets` (extra
files to copy verbatim into the build).

The base intentionally has **no npm-package imports**. Vite's config-loader
bundler (Rolldown in Vite 8+) follows the symlink to `vendor/` before any
of Vite's resolve options apply, so any bare imports inside `vite-base.js`
would emit `UNRESOLVED_IMPORT` warnings on every build. Instead, the
wrapper in each theme imports the plugin packages itself (they resolve
through the theme's own `node_modules`) and passes the constructors in.
The base still owns the plugin options.

Symlink the base file from inside the theme dir:

```bash
ln -sf ../../../../vendor/threespot/wp-base-config/dist/vite-base.js threespot-vite-base.js
```

Theme's `vite.config.js`:

```js
import { createLogger, defineConfig, mergeConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import mkcert from 'vite-plugin-mkcert';
import { wordpressPlugin, wordpressThemeJson } from '@roots/vite-plugin';
import { viteStaticCopy } from 'vite-plugin-static-copy';
import { createSvgIconsPlugin } from 'vite-plugin-svg-icons';
import eslint from 'vite-plugin-eslint';
import stylelint from 'vite-plugin-stylelint';
import browserslist from 'browserslist';
import { browserslistToTargets, Features } from 'lightningcss';

import { threespotViteBase } from './threespot-vite-base.js';

const HOST = 'my-site.lndo.site';

export default mergeConfig(
  threespotViteBase({
    createLogger,
    wordpressPlugin,
    wordpressThemeJson,
    viteStaticCopy,
    createSvgIconsPlugin,
    eslint,
    stylelint,
    browserslist,
    browserslistToTargets,
    Features,
    // Optional: host a local jQuery. Path is relative to the theme's resources/
    // dir; the file is copied to public/build/assets/resources/<path>. Pair it
    // with a matching `threespot/assets/jquery_path` filter (see Asset URLs).
    jqueryPath: 'scripts/lib/jquery-4.0.0.min.js',
    // Optional: extra vite-plugin-static-copy targets, appended to the base set.
    // staticCopyTargets: [{ src: 'resources/scripts/lib/widget.min.js', dest: 'assets' }],
  }),
  defineConfig({
    plugins: [
      laravel({
        input: [
          'resources/scripts/main.js',
          'resources/styles/main.scss',
          // ...per project
        ],
        refresh: ['resources/views/**/*.blade.php'],
        url: process.env.APP_URL,
      }),
      mkcert({ hosts: [HOST] }),
    ],
    server: {
      hmr: { host: HOST },
    },
  }),
);
```

`mergeConfig` deep-merges, so anything the wrapper sets layers on top of
the base — including arrays of plugins (which concatenate). To override a
specific plugin's options, pass replacement instances into
`threespotViteBase()` and the base re-instantiates them with its defaults;
or override post-merge by spreading the result and tweaking. The plugin
imports must stay in the theme: every theme already lists these as
devDependencies in `package.json` (they ship with Sage), so the boilerplate
is one block at the top.

### Overriding per project

The configs are intentionally minimal — they cover the rules every
Threespot site wants. If a single project needs to add or override a rule,
replace the symlink with a small wrapper:

```js
// theme/eslint.config.js (real file, not a symlink)
import threespotConfig from '../../../../vendor/threespot/wp-base-config/dist/eslint.config.js';

export default [
  ...threespotConfig,
  {
    files: ['resources/scripts/checkout/**/*.js'],
    rules: { 'no-console': 'error' },
  },
];
```

## What stays per-site

Some things vary too much to live in the package:

- **`IMAGE_SIZES` constant** — image dimensions are project-specific. Keep
  `app/image-sizes.php` in the site.
- **`register_nav_menus()`** — menu slugs differ per project. The theme's
  `setup.php` keeps owning this.
- **Theme assets (`main.scss`, `main.js`, `critical.scss`)** — the package
  owns the *deferral policy* but not the actual enqueue.
- **Project-specific overrides** — anything driven by the filters above.

## Filter reference

### Asset URLs

The package never calls `Vite::asset()` directly — it fires a filter at the
point of enqueue and the site supplies the URL.

| Filter | Used by | Default |
|---|---|---|
| `threespot/admin/all_css_url`      | `AdminConfig::enqueueAdminStyles` | `null` (no stylesheet) — loaded on every admin page |
| `threespot/admin/fields_css_url`   | `AdminConfig::enqueueAdminStyles` | `null` (no stylesheet) — loaded only on hooks in `fields_css_hooks` |
| `threespot/assets/jquery_path`     | `AssetConfig::replaceJqueryIfConfigured` | `null` (use WP core jQuery) — resources/-relative path; the URL is derived from the Vite static-copy output (pair with the `jqueryPath` Vite option) |
| `threespot/blocks/editor_css_urls` | `BlockConfig::addEditorStyles`    | `[]` (no stylesheets) — array of URLs loaded into the editor canvas in order |
| `threespot/blocks/editor_js_urls`  | `BlockConfig::enqueueEditorAssets` | `[]` (no JS bundles) — array of URLs output as `<script type="module">` tags in order |
| `threespot/login/css_url`          | `LoginConfig`                | `null` (no stylesheet) |

### Scalar configuration

| Filter | Default | Notes |
|---|---|---|
| `threespot/acf/options_page_capability`   | `'edit_posts'`              | Required capability |
| `threespot/acf/options_page_slug`         | `'theme-settings'`          | ACF options page slug |
| `threespot/acf/options_page_title`        | `'Theme Settings'`          | ACF options page title |
| `threespot/acf/wysiwyg_toolbars`          | `[]`                        | Additional custom toolbars merged on top of defaults |
| `threespot/admin/allow_svg_uploads`       | `true`                      | Adds `image/svg+xml` to `upload_mimes` |
| `threespot/admin/fields_css_hooks`        | (dashboard, edit, post hooks) | Admin hooks where `fields_css_url` is enqueued |
| `threespot/admin/is_internal_user`        | `false`                     | Predicate `fn($_, $user) => bool`. Receives current `WP_User`. |
| `threespot/admin/redirect_comments_screen` | `true`                     | Redirects `edit-comments.php` to the dashboard. See [Comments](#comments). |
| `threespot/admin/screen_options_per_page` | `50`                        | Default screen-options page size for new users |
| `threespot/admin/tinymce_body_class`      | `'u-richtext'`              | TinyMCE iframe body class |
| `threespot/assets/disable_oembed_discovery` | `true`                    | Strips `<link rel="alternate" type="application/json+oembed">` |
| `threespot/assets/jquery_version`         | `null`                      | Version passed to `wp_register_script('jquery-core')` |
| `threespot/assets/module_script_handles`  | `[]`                        | Script handles to mark `type="module"` |
| `threespot/blocks/category`               | `['slug'=>'threespotblock', 'title'=>'Custom Blocks', 'icon'=>null]` | Custom block category |
| `threespot/blocks/cover_default_overlay_class` | `'has-dark-background-color'`        | Class copied onto the `core/cover` wrapper when the cover has no overlay color (filter to `''` to disable) |
| `threespot/blocks/disabled_heading_levels` | `[1]`                      | Heading levels stripped from `core/heading` |
| `threespot/blocks/pattern_category`       | `['slug'=>'threespotblock', 'label'=>'Custom Patterns']` | Custom pattern category |
| `threespot/blocks/vimeo_color`            | `'ff5100'`                  | Read by `format_video_iframe()` helper |
| `threespot/helpers/excluded_post_types`   | `['ifso_triggers']`         | Post types stripped from `get_custom_post_types()` |
| `threespot/login/header_text`             | `get_option('blogname')`    | Login logo alt text |
| `threespot/login/header_url`              | `home_url()`                | Login logo link |
| `threespot/smtp/config`                   | Mailhog defaults            | `host`, `port`, `auth`, `username`, `password` |
| `threespot/smtp/should_configure`         | `PANTHEON_ENVIRONMENT === 'lando'` | Whether to override PHPMailer |
| `threespot/theme/excerpt_length`          | `25`                        | Words |
| `threespot/theme/excerpt_more`            | `'…'`                       | Excerpt suffix |

### Subtractive lists

These back the `threespot_keep_*` / `threespot_remove_*` helper pairs. The
defaults match the legacy theme's behavior.

| Filter | Public API pair | Default contents (abridged) |
|---|---|---|
| `threespot/admin/admin_bar_nodes_removed_frontend` | — (use raw filter) | `search`, `updates`, `duplicate-post` |
| `threespot/admin/admin_bar_nodes_removed_non_internal` | — (use raw filter) | `query-monitor` |
| `threespot/admin/admin_bar_nodes_removed_production` | — (use raw filter) | `pantheon-hud` |
| `threespot/admin/admin_bar_nodes_removed` | `threespot_keep_admin_bar_node` / `threespot_remove_admin_bar_node` | `comments`, `customize`, `fwp-cache`, `gform-forms`, `searchwp`, `updates`, `wp-logo`, `wpseo-menu` |
| `threespot/admin/closed_metaboxes` | `threespot_collapse_metabox` / `threespot_uncollapse_metabox` | `ame-cpe-content-permissions`, `wpseo_meta` |
| `threespot/admin/customizer_sections_removed` | `threespot_keep_customizer_section` / `threespot_remove_customizer_section` | `colors`, `custom_css`, `static_front_page` |
| `threespot/admin/dashboard_widgets_removed` | `threespot_keep_dashboard_widget` / `threespot_remove_dashboard_widget` | `welcome_panel`, `dashboard_primary`, `dashboard_quick_press`, `dashboard_secondary`, `wpseo-dashboard-overview` |
| `threespot/admin/disable_comments_post_types` | `threespot_enable_comments` / `threespot_disable_comments` | `get_custom_post_types()` — includes `post` and `page`. **Has a persistent `comment_status` side effect — see [Comments](#comments).** |
| `threespot/admin/menu_pages_removed` | `threespot_keep_menu_page` / `threespot_remove_menu_page` | `edit-comments.php` |
| `threespot/admin/screen_options_hidden_columns` | `threespot_hide_screen_options_column` / `threespot_show_screen_options_column` | Yoast columns (`wpseo-focuskw`, etc.) |
| `threespot/admin/site_status_tests_removed` | — (use raw filter) | `async.background_updates`, `direct.available_updates_disk_space`, `direct.theme_version`, `direct.update_temp_backup_writable` |
| `threespot/admin/taxonomies_hidden_from_nav_menus` | `threespot_hide_taxonomy_from_nav_menus` / `threespot_show_taxonomy_in_nav_menus` | `category` |
| `threespot/admin/user_roles_removed` | `threespot_keep_user_role` / `threespot_remove_user_role` | `wpseo_manager`, `wpseo_editor` |
| `threespot/assets/do_not_defer_scripts` | `threespot_defer_script` / `threespot_no_defer_script` | jQuery / WP / Gravity Forms / WooCommerce / WPForms-reCAPTCHA handles |
| `threespot/blocks/disabled_block_bindings_sources` | `threespot_keep_block_bindings_source` / `threespot_remove_block_bindings_source` | `core/post-data`, `core/post-meta` |
| `threespot/helpers/excluded_post_types` | `threespot_include_post_type` / `threespot_exclude_post_type` | `ifso_triggers` |

`keep_*` removes an item from the subtractive list (so the underlying thing
is NOT stripped). `remove_*` adds an item to the list. `threespot_defer_script` /
`threespot_no_defer_script` follow the same shape: the list is "scripts NOT to
defer", so `no_defer_script` adds to it and `defer_script` removes from it.
`threespot_enable_comments` / `threespot_disable_comments` likewise: the list is
"post types with comments stripped", so `disable_comments` adds to it and
`enable_comments` removes from it. `threespot_enable_comments()` with no
arguments is a special case — it clears the list *and* restores the rest of the
comments UI.

## Helper function reference

All live in the `Threespot\Wp\Helpers` namespace, autoloaded via Composer.

| File | Functions |
|---|---|
| `general.php` | `bytes_to_human_size`, `strip_p_tags`, `is_external`, `nowrap`, `trim_excerpt`, `clean_wysiwyg_markup`, `strip_quotes`, `obfuscate`, `get_custom_post_types`, `format_video_iframe` |
| `image.php`   | `calculate_height`, `get_registered_image_sizes`, `blank_gif`, `get_image_file_type`, `bis_get_sizes`, `bis_srcset`, `buildAttributes`, `img_tag`, `get_img_alt`, `get_img_focal_point`, `resolve_bis_crop_param`, `set_img_alt` (plus `fly_*` deprecated aliases) |
| `svg.php`     | `svg`, `append_icon` |
| `taxonomy.php` | `get_primary_term` |

`image.php` reads a site-defined `IMAGE_SIZES` constant — kept per-site
because image dimensions vary too much project to project. The image-resizing
helpers expect the [Better Image Sizes](https://wordpress.org/plugins/better-image-sizes/)
plugin to be active; `fly_*` aliases remain for sites still on the legacy Fly
Dynamic Image Resizer plugin.

## Running tests

```bash
composer install
composer test
```

The test suite (PHPUnit + [Brain Monkey](https://brain-wp.github.io/BrainMonkey/))
covers the pure helper functions, the `threespot_*` public API, and verifies
each `*Config::register()` wires up the right WordPress hooks. ~90 tests,
runs in under a second, no database needed.

Out of scope for v0.1:
- `Helpers/svg.php` (DOMDocument + file I/O — needs fixtures)
- Full `img_tag` / `bis_srcset` coverage (requires mocking the Better Image
  Sizes plugin)
- Integration tests against a real WP install (use a local Lando test
  site for end-to-end verification)

GitHub Actions runs the suite on PHP 8.1 / 8.2 / 8.3 for every push and PR
against `main` (see `.github/workflows/test.yml`).

## Secret scanning

[gitleaks](https://github.com/gitleaks/gitleaks) guards against committing
secrets (keys, tokens, passwords, private keys). It runs in two places:

- **Locally**, as a pre-commit hook on staged changes. Opt in once per machine:

  ```bash
  brew install pre-commit gitleaks
  pre-commit install            # wires .git/hooks/pre-commit
  pre-commit run --all-files    # optional: scan everything already tracked
  ```

  Config lives in `.pre-commit-config.yaml` and `.gitleaks.toml`. The local
  hook is opt-in and unenforced — it is a convenience, not the safety net.

- **In CI**, as the authoritative backstop. `.github/workflows/gitleaks.yml`
  scans the full commit history on every push and PR to `main` and fails the
  build on any finding. Pair it with GitHub's **Secret scanning + push
  protection** repo setting (free on public repos) for server-side blocking.

False positives go in the `[allowlist]` block of `.gitleaks.toml` — keep
entries narrow so real secrets are never masked.

## Layout

```
threespot/wp-base-config/
├── composer.json                                # type: library
├── UPGRADING.md                                 # breaking changes to filters / public names / unhookable callbacks
├── bootstrap.php                                # registers all MU-plugin modules
├── mu-plugins/
│   └── threespot-wp-base-config.php             # WP loader; symlink into web/app/mu-plugins/
├── dist/                                        # frontend tooling configs; symlinked from each theme
│   ├── eslint.config.js
│   ├── .stylelintrc.cjs
│   ├── postcss.config.cjs
│   ├── .pxtorem-config.cjs
│   └── vite-base.js                             # consumed via mergeConfig — see "Vite: shared base + per-project wrapper"
└── src/
    ├── MuPlugins/                               # named-callback hook modules
    │   ├── AcfConfig.php
    │   ├── AdminConfig.php
    │   ├── AssetConfig.php
    │   ├── BlockConfig.php
    │   ├── ContentTypesConfig.php
    │   ├── CriticalConfig.php
    │   ├── LoginConfig.php
    │   ├── SmtpConfig.php
    │   └── ThemeConfig.php
    ├── Helpers/                                 # autoloaded namespaced functions
    │   ├── author.php
    │   ├── general.php
    │   ├── image.php
    │   ├── menu.php
    │   ├── svg.php
    │   └── taxonomy.php
    ├── Sage/                                    # Acorn/Vite-aware adapter helpers
    │   ├── assets.php                           # vite_asset_url, inline_critical_css
    │   ├── blade.php                            # register_blade_directives
    │   └── prettify.php                         # prettify_config (roots/acorn-prettify defaults)
    ├── Assets/                                  # bundled JS imported by themes
    │   ├── block-defaults.jsx
    │   └── gutenberg-defaults.js
    └── PublicApi/
        └── functions.php                        # threespot_keep_* / threespot_remove_* etc.
```

Each `MuPlugins/*Config::register()` registers named static-method callbacks
so a site can fully disable any individual behavior with `remove_action`:

```php
remove_action('wp_loaded', ['Threespot\Wp\MuPlugins\AdminConfig', 'disableCommentSupport']);
```

Prefer the public API where a pair exists, though — for this example,
`threespot_enable_comments()` is the supported route (see [Comments](#comments)).
Unhooking is the escape hatch for behaviors that have no helper.

Renames to these callback names are breaking changes for any site that unhooks
them. See [UPGRADING.md](UPGRADING.md).
