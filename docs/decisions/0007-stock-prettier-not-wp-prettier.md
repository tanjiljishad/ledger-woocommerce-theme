# ADR 0007 — Format with stock Prettier, not wp-prettier

- **Status:** accepted
- **Date:** 2026-09-07
- **Deciders:** Tanjil

## Context

The repo depends on `@wordpress/prettier-config` and, separately, on
`prettier@3.3.3` — the upstream package, not the `wp-prettier` fork WordPress
core uses. `@wordpress/prettier-config` only enables its `parenSpacing` option
(the `fn( a )` inner-paren spacing seen throughout WP core) when the running
formatter reports itself as `wp-prettier`; under stock Prettier that option is
silently dropped. `wp-prettier` is a fork that trails upstream (its latest is
`3.0.x` against Prettier `3.3`) and it is the only thing that produces the
spaced-paren style.

The Phase 0 scaffold was hand-written in the spaced-paren WP style but had never
been run through the configured formatter. When it first was, 189 of the 195
lint errors were `prettier/prettier` reformatting to the stock style.

## Decision

We stay on stock `prettier`. The configured style is `@wordpress/prettier-config`
as interpreted by upstream Prettier: tabs (width 4), single quotes, `es5`
trailing commas, 80 columns, semicolons, `bracketSpacing`, `arrowParens: always`
— and **no** inner-paren spacing, because `parenSpacing` needs `wp-prettier`.
`eslint --fix` reformatted the scaffold to this style in commit "Make
`pnpm run lint` pass on the Phase 0 scaffold".

We do not adopt `wp-prettier`.

## Consequences

### Positive

- Prettier tracks upstream releases and bug fixes on its normal cadence; no
  waiting on a fork to catch up to a new Prettier minor.
- `eslint-plugin-prettier`, `eslint-config-prettier`, and editor integrations
  all expect stock Prettier — no version-detection surprises.
- One fewer WordPress-specific fork in the toolchain.

### Negative / accepted costs

- Our PHP-less JS/TS does not look like WordPress core's JS (which is
  spaced-paren). Contributors coming from Gutenberg see `fn(a)` where they
  expect `fn( a )`.
- `@wordpress/prettier-config` carries a `parenSpacing` key that does nothing
  here; anyone reading the config has to know why. The `.prettierrc.cjs`
  comment says so.
- If a future dependency ever pulls in `wp-prettier`, the entire tree would
  reformat. `prettier` is pinned exactly to guard against that.

### Neutral

- PHP formatting is unaffected — that is PHPCBF / WPCS, a separate toolchain.

## Alternatives considered

- **Switch to `wp-prettier`** (`"prettier": "npm:wp-prettier@3.0.3"`): rejected.
  It would match WP core's look and honour `parenSpacing`, but it lags upstream
  Prettier and adds fork risk for a purely cosmetic gain.
- **Drop `@wordpress/prettier-config`, write our own `.prettierrc`:** rejected
  for now — the WP config minus `parenSpacing` is exactly what we want, and
  depending on it keeps us aligned if WP changes the other options.

## Notes

Revisit only if `wp-prettier` reaches parity with upstream Prettier and the
team decides matching core's paren style is worth it. Until then, `fn(a)` is
the house style for JS/TS.
