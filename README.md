# Ledger

A performance-gated WooCommerce block theme and plugin suite. From Phase 1
onward, every performance claim is verified in CI, not by eye.

**License:** proprietary — see [`LICENSE`](LICENSE) and
[ADR-0002](docs/decisions/0002-proprietary-license.md). Not GPL, not for
wordpress.org distribution.

## Layout

```
packages/tokens/     design tokens — one tokens.json, generated theme.json / CSS / TS
plugins/core/        shared service container + requirement checks
plugins/blocks/      editor blocks (none ship in Phase 0)
plugins/commerce/    WooCommerce integration layer
themes/flagship/     block theme; renders a browsable shop with plugins off
tools/seed/          dev-only WP-CLI catalogue generator + snapshot tooling
docs/decisions/      numbered ADRs — every architectural decision
docs/                benchmarks, feature audit, performance budget, contracts
```

## Prerequisites

- Node 20+ and **pnpm 9+** (`npm i -g pnpm`)
- PHP **8.2+** and Composer
- Docker (for `wp-env`)

## Setup

```bash
pnpm install
composer install
pnpm run tokens:build      # generate token artefacts
pnpm run build             # tokens + every workspace build
pnpm wp-env start          # WordPress at http://localhost:8889  (wp-admin < 2 min cold)
```

## Everyday commands

| Command | What |
| --- | --- |
| `pnpm run tokens:build` | regenerate token artefacts from `tokens.json` |
| `pnpm run tokens:check` | fail if any generated token file has drifted |
| `pnpm run tokens:test` | token compiler snapshot tests |
| `pnpm run lint` / `pnpm run typecheck` | ESLint (0 warnings) / `tsc -b` |
| `composer run phpcs` / `composer run phpstan` | PHP lint / static analysis (level 8) |
| `pnpm run seed:generate` | build a 5,000-product shop (WP-CLI) |
| `pnpm run reset` | wipe local, restore snapshot, working shop in < 30s |
| `pnpm exec playwright test` | no-jQuery + smoke E2E |
| `pnpm run lhci` | Lighthouse CI against the budget |

## Status

Phase 0 of 8. See [`docs/phase-0-status.md`](docs/phase-0-status.md) for the
task-by-task state and what still needs a live run.
