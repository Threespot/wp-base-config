# Upgrading

Breaking changes to the package's public contract — filter names, public
`threespot_*` function names, and the named static callbacks sites unhook with
`remove_action()`. Version numbers refer to git tags.

Each entry is written to be actionable without further context, so a coding
agent can be pointed at this file and asked to bring a site up to date.

---

## 0.1.10 → 0.1.11

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
