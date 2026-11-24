# Upgrading

Breaking changes and deprecations in the package's public contract — filter names, public
`threespot_*` function names, and the named static callbacks sites unhook with
`remove_action()`. Version numbers refer to git tags.

Each entry is written to be actionable without further context, so a coding
agent can be pointed at this file and asked to bring a site up to date.

---

## 0.2.15 → 0.2.16

Not breaking: a method is renamed with a deprecated alias, and empty headings
are now stripped too.

### `stripEmptyParagraphs()` is now `stripEmptyElements()`

**What changed.** `BlockConfig::stripEmptyParagraphs()` is renamed
`BlockConfig::stripEmptyElements()`, and it removes empty `<h1>`-`<h6>` as well
as empty `<p>`. "Empty" means the same as before: only whitespace, `&nbsp;`,
U+00A0 or `<br>`, whatever the attributes (an empty heading with an `id` is
removed too). CSS `:empty` rules could hide empty headings, but a hidden element
still counts for `:first-child` / `:last-child`, and `:empty` doesn't match an
element holding only a space or `&nbsp;`.

`stripEmptyParagraphs()` still works as a deprecated alias of the new method
(it strips headings too) and calls `_deprecated_function()`, which logs a notice
when `WP_DEBUG` is on.

**Action.**
1. Search the site for `stripEmptyParagraphs` and change each call to
   `stripEmptyElements`.
2. A `remove_filter('the_content', [BlockConfig::class, 'stripEmptyParagraphs'])`
   no longer removes anything, because the package now registers
   `stripEmptyElements`. Change the name in the `remove_filter()` call (and the
   `acf_the_content` one, priority 20).
3. If the theme has its own code that removes empty headings, it can go.

**Removal.** Remove the `stripEmptyParagraphs()` alias (and
`tests/MuPlugins/BlockConfigDeprecationTest.php`) in the next major version of
wp-base-config. Sites must use `stripEmptyElements()` by then.

---

## 0.2.14 → 0.2.15

Not breaking: term and attachment URLs become absolute again, with no code
change needed on most sites.

### Term and attachment URLs are no longer made relative

**What changed.** `prettify_config()` no longer lists `term_link` or
`wp_get_attachment_url` among the `relative-urls` hooks.

- With `term_link` listed, `get_term_link()` returned a relative path, and
  Yoast uses that function for term archives: their XML sitemap `<loc>`
  values, canonical link, `og:url` and schema `@id` all came out relative.
  Sitemap URLs must be absolute, so search engines reject those entries. Seen
  on GIH, where all 73 term archive URLs in the taxonomy sitemaps were
  relative.
- With `wp_get_attachment_url` listed, Yoast's schema gave images (the site
  logo included) a relative `contentUrl` and `thumbnailUrl`. Google expects
  absolute image URLs there.

Image `src` and `srcset` values that WordPress builds for attachments also
become absolute. Browsers treat both forms the same.

**Action.**
1. If the theme's `config/prettify.php` assigns
   `$config['relative-urls']['hooks']` wholesale and that list includes
   `'term_link'` or `'wp_get_attachment_url'`, remove them. Sites that only
   call `prettify_config()` need no change.
2. Fix the URLs Yoast already saved. Updating the package does not change
   them, and they keep coming out relative on the front end (seen on GIH
   dev). Yoast saves URLs when it builds a page's SEO data (its "indexables")
   during a page view, so only environments that served pages with the old
   config are affected, usually live. On each environment, after deploying:
   1. Count the affected rows:
      `wp db query "SELECT COUNT(*) FROM wp_yoast_indexable WHERE permalink LIKE '/%' OR open_graph_image LIKE '/%' OR twitter_image LIKE '/%'"`
   2. If the count is above 0, save the script below as a file and run
      `wp eval-file <file> run`. It rebuilds only those rows (seconds, where
      `wp yoast index --reindex` rebuilds everything and took over 10
      minutes on GIH dev), and prints the count before and after, which
      should end at 0.
   3. Clear the logo URL Yoast saved in its settings. Yoast rebuilds it on
      the next page view:
      `wp eval 'WPSEO_Options::set("company_logo_meta", false);'`
   4. Clear the page cache.

   ```php
   <?php
   use Yoast\WP\SEO\Builders\Indexable_Builder;
   use Yoast\WP\SEO\Repositories\Indexable_Repository;

   global $wpdb;
   $table = $wpdb->prefix . 'yoast_indexable';
   $where = "permalink LIKE '/%' OR open_graph_image LIKE '/%' OR twitter_image LIKE '/%'";
   echo 'Before: ' . $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$where}") . "\n";
   if (!in_array('run', (array) ($args ?? []), true)) {
       return;
   }
   $repo = YoastSEO()->classes->get(Indexable_Repository::class);
   $builder = YoastSEO()->classes->get(Indexable_Builder::class);
   foreach ($wpdb->get_results("SELECT object_id, object_type FROM {$table} WHERE {$where}") as $row) {
       $indexable = $repo->find_by_id_and_type((int) $row->object_id, $row->object_type, false);
       if ($indexable) {
           $builder->build_for_id_and_type((int) $row->object_id, $row->object_type, $indexable);
       }
   }
   echo 'After: ' . $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$where}") . "\n";
   ```
3. Check the result: every `<loc>` in a taxonomy sitemap (for example
   `/category-sitemap.xml`) starts with `https://`, and so do a term
   archive's `og:url` and the home page's schema `logo.contentUrl`.

---

## 0.2.13 → 0.2.14

Not breaking: no action needed.

### "No vertical margin" toggle shows on WordPress 7.0

**What changed.** The group block's "No vertical margin" toggle goes in the
Layout panel on WordPress 7.1+ and in a "Group Options" panel on older
versions. The package chose by checking for a block-editor export it took to
be new in 7.1, but that export is already in 7.0, so on 7.0 sites the toggle
was placed in a sidebar group 7.0 doesn't have and didn't show at all. It now
checks the WordPress version itself (the `version-X-Y-Z` class WordPress puts
on the admin `<body>`), and falls back to the "Group Options" panel when it
can't tell.

**Action.** None. Update the package and rebuild the theme's assets (the
toggle is bundled into the theme's `block-defaults.jsx` entry).

---

## 0.2.12 → 0.2.13

Not breaking: nothing changes until a site edits its own code.

### `is_production()` is deprecated

**What changed.** `is_production()` is true on every Pantheon environment, dev
and test included, so code gated with it (usually GTM in the `App` composer)
runs outside live. Two helpers replace it:

- `is_pantheon()`: the same check under a clearer name.
- `is_live_env()`: true only when `PANTHEON_ENVIRONMENT` is `live`.

`is_production()` still works: it wraps `is_pantheon()` and also applies its
own `threespot/helpers/is_production` filter.

**Action.** Gate live-only code (analytics, cookie banners) with
`is_live_env()`:

```php
use function Threespot\Wp\Helpers\is_live_env;

'is_live' => is_live_env(),
```

Replace other calls with `is_pantheon()`.

**If the site hooks `threespot/helpers/is_production`,** check what the hook
does before switching, since `is_pantheon()` and `is_live_env()` don't apply
it:

- A hook that returns false on `test` (the "staging exclusion" the old
  docblock suggested) was keeping live-only code off test. Delete it and use
  `is_live_env()` for that code. Don't move it to
  `threespot/helpers/is_pantheon`: `is_pantheon()` would then be false on
  Pantheon's test environment.
- Move any other hook to `threespot/helpers/is_pantheon`.

The two filters are linked one way: `is_production()` starts from the
filtered `is_pantheon()` result, so a hook on `threespot/helpers/is_pantheon`
also changes `is_production()`, but a hook on
`threespot/helpers/is_production` doesn't change `is_pantheon()`.

---

## 0.1.10 → 0.2.0

**Why a minor bump and not a patch.** Composer's caret operator is
special-cased for `0.y.z` releases: `^0.1.6` resolves to `>=0.1.6 <0.2.0`, so a
`0.1.11` tag would install on the next `composer update` for every site pinning
`^0.1`. This release renames a public callback in a way that fails *silently*
(see below), and the package has no way to warn — `remove_action()` on a
callback that no longer exists is a no-op, so there is no deprecation shim
possible. The version bump is the only enforcement mechanism available: `0.2.0`
falls outside `^0.1`, so nothing moves until someone deliberately changes the
constraint to `^0.2`, which is the point at which this file gets read.

**Bump the constraint deliberately, not reflexively.** Read the action-required
steps below *first*, then:

```jsonc
"threespot/wp-base-config": "^0.2"
```

At least one site in the fleet uses the old `remove_action()` opt-out, so this
migration is required in practice, not hypothetical.

### `AdminConfig::disableCommentsAndRedirect()` was split in two

**What changed.** The method did two unrelated things: it stripped comment
support from post types, and it redirected `edit-comments.php` to the dashboard.
A site could only opt out of both at once. It is now two callbacks on two
different hooks:

| Before | After |
|---|---|
| `add_action('admin_init', [AdminConfig::class, 'disableCommentsAndRedirect'], 11)` | `add_action('wp_loaded', [AdminConfig::class, 'disableCommentSupport'])` |
| (same method) | `add_action('admin_init', [AdminConfig::class, 'redirectCommentsScreen'], 11)` |

The redirect is now also gated behind a new
`threespot/admin/redirect_comments_screen` filter (default `true`).

Comment support is stripped on `wp_loaded` rather than `admin_init` so the
behavior is consistent across contexts. `admin_init` never fires under WP-CLI,
REST, or on the front end, so `post_type_supports($type, 'comments')` used to
report `true` there while the editor was quietly saving posts with
`comment_status = 'closed'`. `wp_loaded` fires after all of `init` (so every CPT
is registered) and before any wp-admin file runs, so **the admin outcome is
unchanged** — only the CLI/REST/front-end reporting is now honest.

**Who is affected.** Only sites that unhook the old callback. Default behavior
is unchanged: comments are still disabled site-wide by default, on the same post
types, with the same result in the editor.

### Action required

**1. Fix any `remove_action()` call naming the old method.** This is the part
that fails silently — `remove_action()` on a hook/callback that no longer exists
is a no-op, so a site that had deliberately re-enabled comments will find them
disabled again with no error anywhere.

Search the site for the old name:

```bash
grep -rn "disableCommentsAndRedirect" --include="*.php" .
```

If you find the documented opt-out block, replace the whole thing:

```php
// BEFORE — no longer does anything
remove_action('admin_init', ['Threespot\Wp\MuPlugins\AdminConfig', 'disableCommentsAndRedirect'], 11);
threespot_keep_menu_page('edit-comments.php');
threespot_keep_admin_bar_node('comments');

// AFTER — one call, same effect
threespot_enable_comments();
```

If the site only wanted comments on specific post types, name them instead:

```php
threespot_enable_comments('post');
```

**2. Nothing to do if the site does not mention comments.** The defaults did not
change.

### While you are here: the `comment_status` side effect

Not a change in this release, but newly documented and worth checking on any
site that expects comments to work.

Stripping `comments` support makes `get_default_comment_status()` return
`'closed'` regardless of the `default_comment_status` option, and that value is
written to `wp_posts.comment_status` for each post as it is created. It persists
— `threespot_enable_comments()` does **not** reopen posts created while comments
were off.

To check whether a site has affected content:

```bash
wp post list --post_type=post --comment_status=closed --format=count
```

Repairing it is a deliberate, site-specific database edit, not something to
apply blindly — the query cannot tell posts closed by this package apart from
posts an editor closed on purpose. See the
[Comments section of the README](README.md#comments) for the query and caveats.
