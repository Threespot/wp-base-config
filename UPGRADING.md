# Upgrading

Breaking changes and deprecations in the package's public contract — filter names, public
`threespot_*` function names, and the named static callbacks sites unhook with
`remove_action()`. Version numbers refer to git tags.

Each entry is written to be actionable without further context, so a coding
agent can be pointed at this file and asked to bring a site up to date.

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
