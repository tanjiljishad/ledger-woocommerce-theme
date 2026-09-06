# ADR 0003 — Single monorepo: pnpm workspace + root Composer

- **Status:** accepted
- **Date:** 2026-09-06
- **Deciders:** Tanjil

## Context

The suite is four shippable units (theme + three plugins) plus shared packages
(tokens, config) and dev tooling (seed). They version together, share a token
system, and must be tested against one wp-env instance in CI (roadmap 0.3).

## Decision

One Git repository. JavaScript is a pnpm workspace
(`pnpm-workspace.yaml` → `themes/*`, `plugins/*`, `packages/*`, `tools/*`). PHP
is one root `composer.json` with PSR-4 autoloading, one namespace per unit
(`Ledger\Core\`, `Ledger\Blocks\`, `Ledger\Commerce\`, `Ledger\Theme\`).

pnpm hoisting is disabled (`.npmrc` `hoist=false`) because `@wordpress/*`
packages and `@wordpress/scripts` misbehave under a hoisted `node_modules`.

## Consequences

### Positive

- One `pnpm install`, one `composer install`, one CI checkout.
- Shared tooling config (ESLint, Prettier, tsconfig, PHPCS, PHPStan) lives once
  at the root.
- Cross-unit refactors are atomic commits.

### Negative / accepted costs

- Publishing a single unit for distribution needs a build/export step that
  prunes the rest (deferred to a later phase).
- `pnpm -r` topological ordering only works if workspace deps are declared;
  we add `workspace:*` deps where a real build edge exists (theme → tokens).
- Contributors need both Node and PHP toolchains even to touch one side.

## Alternatives considered

- **Repo per unit:** rejected — token changes would fan out across four PRs,
  CI could not test the integrated shop cheaply.
- **npm/yarn workspaces:** rejected — pnpm's disk model and strict
  `node_modules` catch accidental phantom-dependency use, which matters for a
  bundle-size-gated project.

## Notes

If distribution tooling gets heavy, consider Nx or Turborepo for task caching —
not before there is a measured need.
