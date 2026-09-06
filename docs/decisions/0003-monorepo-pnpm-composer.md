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

pnpm's `shamefully-hoist` stays off (`.npmrc`) because `@wordpress/*` packages
and `@wordpress/scripts` misbehave when flattened into the project-root
`node_modules`. pnpm's default per-package hoisting is left on, so the plugins
that `@wordpress/eslint-plugin`'s shared config pulls in stay resolvable for
ESLint 8's `.eslintrc` resolver without being redeclared at the root. See the
Notes below for the 2026-09-06 correction.

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

**2026-09-06 correction.** The Decision paragraph above originally read "pnpm
hoisting is disabled (`.npmrc` `hoist=false`)". That was wrong in effect:
`hoist=false` also switches off pnpm's default `public-hoist-pattern`
(`*eslint*`, `*prettier*`), which is what lets ESLint find the plugins bundled
by `@wordpress/eslint-plugin`. To compensate, 12 of those plugins had been
copied into root `devDependencies`, pinned by hand. `hoist=false` was removed
and those 12 entries deleted; `shamefully-hoist=false` (the setting that
actually protects `@wordpress/scripts`) is unchanged. All four checks
(`pnpm run lint`, `pnpm run typecheck`, `composer run phpcs`,
`composer run phpstan`) pass after the change.
