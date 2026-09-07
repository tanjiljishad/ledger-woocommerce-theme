# ADR 0009 — Remove `hoist=false` from `.npmrc`

- **Status:** accepted
- **Date:** 2026-09-07
- **Deciders:** Tanjil
- **Supersedes:** the hoisting paragraph of [ADR-0003](0003-monorepo-pnpm-composer.md)

## Context

ADR 0003's Decision paragraph read:

> pnpm hoisting is disabled (`.npmrc` `hoist=false`) because `@wordpress/*`
> packages and `@wordpress/scripts` misbehave under a hoisted `node_modules`.

Two problems surfaced when the Phase 0 scaffold was first installed and linted:

1. **The stated mechanism is wrong.** `@wordpress/scripts` breaks under a
   *flattened project-root* `node_modules` — the thing `shamefully-hoist=true`
   (i.e. `public-hoist-pattern=*`) produces. It does not care about pnpm's
   default per-package hoist into `node_modules/.pnpm/node_modules`.
   `shamefully-hoist=false` is what protects it, and that was already set.

2. **`hoist=false` is a bigger hammer than intended.** It disables pnpm's
   entire hoisting layer, including the default `public-hoist-pattern`
   (`*eslint*`, `*prettier*`). `@wordpress/eslint-plugin` bundles its plugins
   (`eslint-plugin-jsx-a11y`, `-react`, `-import`, `@typescript-eslint/*`, …)
   as real dependencies; with no hoisting, ESLint 8's `.eslintrc` resolver —
   which searches from the project root — cannot find them, and `pnpm run lint`
   fails to load. The scaffold had worked around this by copying 12 of those
   plugins into root `devDependencies`, pinned by hand, permanently coupled to
   whatever `@wordpress/eslint-plugin` happens to bundle.

## Decision

We will remove `hoist=false` from `.npmrc` and keep `shamefully-hoist=false`.

pnpm's defaults then apply: dependencies hoist into
`node_modules/.pnpm/node_modules` (private, not the project root), and the
default `public-hoist-pattern` lifts `*eslint*` / `*prettier*` to the root
`node_modules` where the ESLint resolver expects them. The `@wordpress/*`
runtime packages are **not** flattened to the root, so `@wordpress/scripts` is
unaffected.

The 12 hand-pinned ESLint plugin `devDependencies` are deleted. `eslint`,
`prettier`, `@wordpress/eslint-plugin`, `@wordpress/prettier-config`,
`typescript`, and the non-ESLint tooling remain as the only root
`devDependencies`.

`.npmrc` carries a comment explaining that `shamefully-hoist=false` is the
`@wordpress/scripts` guard and the default `public-hoist-pattern` is what keeps
ESLint plugins resolvable.

## Consequences

### Positive

- `pnpm run lint` works from a clean `pnpm install` with no root ESLint-plugin
  declarations to keep in sync.
- The `.npmrc` matches how the wider pnpm + `@wordpress/eslint-plugin`
  ecosystem is documented to work.
- `pnpm-lock.yaml` shrinks by the 12 removed direct dependencies (they remain
  as transitive deps of `@wordpress/eslint-plugin`).

### Negative / accepted costs

- ESLint now resolves its plugins from `node_modules/.pnpm/node_modules`, a
  path pnpm owns. If a future pnpm major changes that layout, lint resolution
  could break and would need revisiting (a `public-hoist-pattern` entry, or
  re-declaring the plugins).
- One more ADR to read to understand the `.npmrc`.

### Neutral

- No change to `composer` / PHP tooling.
- `shamefully-hoist=false` and the rest of `.npmrc` are unchanged.

## Alternatives considered

- **Keep `hoist=false`, keep the 12 pinned devDeps:** rejected — the pin list
  silently drifts from `@wordpress/eslint-plugin`'s bundled versions on every
  bump, and the `.npmrc` comment misdescribes why the setting exists.
- **Keep `hoist=false`, add `public-hoist-pattern[]=*eslint*` back explicitly:**
  rejected — `hoist=false` disables `public-hoist-pattern` outright, so this
  does nothing.
- **`node-linker=hoisted`:** rejected — reintroduces the flat `node_modules`
  that breaks `@wordpress/scripts`, the exact thing ADR 0003 set out to avoid.

## Notes

Verified after `rm -rf node_modules && pnpm install --frozen-lockfile`:
`pnpm run lint`, `pnpm run typecheck`, `composer run phpcs`,
`composer run phpstan` all pass.
