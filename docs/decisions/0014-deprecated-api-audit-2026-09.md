# ADR 0014 — Deprecated-API audit against WordPress 7.1

- **Status:** accepted
- **Date:** 2026-09-07
- **Deciders:** Tanjil

## Context

The flagship theme's `wp_global_styles_render_svg_filters` re-hook was written
against pre-7.0 WordPress and turned out to call a chain deprecated since 6.3.0
(removed in the preceding commit, "Remove the deprecated SVG-filter hook block
from the flagship theme"). Since our code predates the 7.0 / 7.1 retarget
(ADR 0012), we swept the rest of it for the same class of problem before
building Phase 2 on top.

## Scope

- `themes/flagship/` (PHP: `functions.php`; templates/parts are block markup,
  no PHP; `theme.json` is declarative).
- `plugins/core/`, `plugins/blocks/`, `plugins/commerce/` — every `.php` file
  including `uninstall.php`.
- **Out of scope:** `tools/seed/` (dev-only WP-CLI tooling, never shipped) and
  `bin`/build scripts.

## Method

Run against a live `wp-env` on **WordPress 7.1 / WooCommerce 11.1.0 / PHP 8.2.33**:

1. **Static intersection.** Extracted every function name from WordPress 7.1's
   `wp-includes/*deprecated*.php` and `wp-admin/includes/deprecated.php` (378
   symbols) and intersected with every function identifier called in theme +
   plugin source. Also checked our `add_action` / `add_filter` hook-name
   strings against core's `_deprecated_hook()` list.
2. **Runtime capture.** `WP_DEBUG` + `WP_DEBUG_LOG` on. Truncated `debug.log`,
   then with all three Ledger plugins and the flagship theme active exercised:
   the eight front-end routes (`/`, `/shop/`, simple + variable product, cart,
   checkout, my-account, `wp-login.php`), an authenticated `/wp-admin/`,
   `plugins.php`, `post-new.php` (block editor), `site-editor.php`, the
   `wp ledger seed` WP-CLI commands, `do_blocks()` rendering, and a full
   plugin deactivate → reactivate cycle. Collected every `PHP Deprecated`,
   `PHP Notice` ("called incorrectly" = `_doing_it_wrong`), and `PHP Warning`
   line and attributed each to a file.

## Findings

**Nothing. Our theme and the three plugins call no API deprecated in
WordPress 6.x or 7.x** (after the SVG-filter removal).

- Static intersection: no real hit (only `__construct`, a magic method false
  positive).
- Runtime: `debug.log` was **empty** across the entire front-end + CLI +
  activation-lifecycle exercise, and empty again across the authenticated
  admin + block-editor + site-editor walk. No line named
  `themes/flagship/` or `plugins/ledger-*`.
- `load_plugin_textdomain( 'ledger', … )` in `Ledger\Core\Plugin::boot()` is
  **not** deprecated on 7.1 and produced no "called incorrectly" notice — it
  runs on `plugins_loaded` on every request, including all the pages walked,
  and was silent. (The `_load_textdomain_just_in_time` notice seen during the
  very first `wp-env start` is WooCommerce's own, not ours.)
- `FeaturesUtil::declare_compatibility()` (commerce HPOS opt-in) is current
  WooCommerce API.

## Decision

No code changes beyond the SVG-filter block already removed. Record the audit
and its date; re-run it at each WordPress **major** bump.

## Consequences

### Positive

- Phase 2 starts on a theme + plugin base with a clean bill against 7.1.
- The method (this ADR) is repeatable in ~15 minutes at the next bump.

### Negative / accepted costs

- The runtime pass depends on actually exercising a code path; a deprecated
  call on a branch not hit here (e.g. `Requirements::render_notice()`, which
  only fires when a requirement is unmet) would be missed. Those branches were
  read by eye and use only current APIs (`esc_html*`, `printf`, `wp_die`,
  `wp_kses_post`).

## Notes

- Re-run trigger: WordPress 7.2 (due 2026-12-10), and every major after.
- If a future audit finds more than a trivial fix, that fix gets its own
  commit and this ADR a dated addendum.
