# ADR 0012 — Retarget to WordPress 7.1

- **Status:** accepted
- **Date:** 2026-09-07
- **Deciders:** Tanjil

## Context

Phase 0 targeted WordPress **6.8** (`.wp-env.json`, plugin `Requires at least`,
theme `Tested up to`, PHPCS `minimum_wp_version`). Since then:

- **WordPress 7.0** shipped, then **7.1 "Mary Lou"** on 2026-08-19. **7.2** is
  due 2026-12-10. The 6.8 target is two majors behind current stable.
- The latest **WooCommerce is 11.1.0**, header `Requires at least: 7.0`. It
  **refuses to activate on WordPress 6.8** — the wp-cli activation step in
  `wp-env start` fails with *"Current WordPress version (6.8) does not meet
  minimum requirements for WooCommerce. The plugin requires WordPress 7.0"*,
  and `wp-env start` aborts. Local Phase 0 dev and the CI `perf` job are both
  blocked at the first `wp-env start`.

`.wp-env.json` pins `woocommerce.zip` (always latest), so there is no version
of "latest WooCommerce" that works with a 6.x core pin any more.

## Decision

We will target **WordPress 7.1** as the minimum supported version, effective
immediately. Concretely:

| Declaration | Was | Now |
| --- | --- | --- |
| `.wp-env.json` `core` | `WordPress/WordPress#6.8` | `WordPress/WordPress#7.1` |
| `plugins/*/…php` `Requires at least` | `6.6` | `7.1` |
| `plugins/*` `Requirements` `'wp'` constraint | `6.6` | `7.1` |
| `themes/flagship/style.css` `Requires at least` | `6.6` | `7.1` |
| `themes/flagship/style.css` `Tested up to` | `6.8` | `7.1` |
| `phpcs.xml.dist` `minimum_wp_version` | `6.6` | `7.1` |
| `composer.json` `php-stubs/woocommerce-stubs` | `^9.3` | `^11.1` |
| `composer.json` `php-stubs/wordpress-stubs` | *(transitive, 6.9.x)* | `^7.1` (explicit) |
| `docs/benchmarks.md`, `global.md` example | `6.8` | `7.1` |

`theme.json` stays at schema `version: 3` for now. Whether 7.1's
`settings.viewport` / responsive block styles warrant a schema bump and changes
to the token compiler is being assessed in a separate impact report **before**
any Phase 2 work — this ADR is the version retarget only.

### Why 7.1, not 7.0

7.0 is already one release behind and WooCommerce 11.1 is `Tested up to: 7.1`.
Targeting a superseded release for a project that has never shipped has no
upside. Track latest stable; re-evaluate cadence at 7.2 (see Notes).

## Consequences

### Positive

- `wp-env start` completes; WooCommerce activates; Phase 0 CI `perf` and local
  dev are unblocked.
- The PHPStan stubs (`wordpress-stubs 7.1`, `woocommerce-stubs 11.1`) match what
  actually runs, so level-8 analysis sees the real API surface.
- Phase 1+ can build against 7.1 APIs (responsive block styles / configurable
  viewports, core Tabs block, SVG Icon API) instead of polyfilling them.

### Negative / accepted costs

- **Drops WordPress 6.x support entirely.** Acceptable: nothing has shipped,
  there are no installs, and the marketplace baseline moves with core.
- Contributors need a WordPress 7.1 environment. `wp-env` handles this.
- The theme/plugin `Requires at least: 7.1` headers will keep the suite off any
  site not yet on 7.1 — intended, but worth stating.
- A `theme.json` v3 → v4 migration is now pending (assessed separately).

### Neutral

- PHP target unchanged (`8.2`). WooCommerce 11.1 needs PHP ≥ 7.4; we exceed it.
- `phpcs` and `phpstan` pass unchanged after the retarget + stub bump (no code
  used an API deprecated by 7.1).

## Alternatives considered

- **Pin WooCommerce to its last WP-6.x line** (e.g. `woocommerce.10.x.zip`):
  rejected — freezes us on a WooCommerce branch that is already in security-only
  maintenance, to keep targeting a WordPress two majors stale. Backwards for a
  greenfield project.
- **Target 7.0:** rejected — superseded, and WooCommerce 11.1 is tested against
  7.1.
- **Stay on 6.8, drop WooCommerce from `.wp-env.json`:** rejected — the whole
  suite is a WooCommerce theme; the shop must run in the dev environment.

## Notes

- Revisit at **7.2** (2026-12-10): decide whether to track latest stable or
  latest-minus-one. Until then, latest.
- `.wp-env.json` still pins `woocommerce.zip` (latest). If a future WooCommerce
  raises its floor past our `core` pin again, bump `core` in step.
- Follow-up: the WordPress 7.1 impact report (token compiler `settings.viewport`,
  Tabs block, SVG Icon API, Navigation font-size propagation) — revise the phase
  plan against it before building.

### Plugin slug mismatch (fixed alongside the retarget)

Retargeting let `wp-env start` reach plugin activation for the first time, which
exposed a **real bug, not an env quirk**: `ledger-blocks` and `ledger-commerce`
declare `Requires Plugins: ledger-core`, and their runtime `Requirements` check
calls `is_plugin_active( 'ledger-core/ledger-core.php' )` — both keyed to the
*shipped* slug `ledger-core`. But the monorepo directory is `plugins/core`, so
`.wp-env.json`'s `./plugins/core` entry mounted and activated it as slug `core`.
The dependency could never resolve in the dev environment, and the runtime check
would have failed there too.

Fix: `.wp-env.json` now `mappings` `./plugins/core` → `wp-content/plugins/ledger-core`
(and `blocks`, `commerce` likewise) so container slugs match shipped slugs, and
those three are removed from the top-level `plugins` array. `mappings` mount but
do not activate, so a `lifecycleScripts.afterStart` runs
`wp plugin activate ledger-core ledger-blocks ledger-commerce` after every
`wp-env start` (idempotent — a re-run just reports "already activated").
`woocommerce` and the dev-only `tools/seed` stay in `plugins`; `tools/seed`
keeps slug `seed` — it never ships and has no plugin dependency.
