# ADR 0004 — PHPStan level 8 from the first commit

- **Status:** accepted
- **Date:** 2026-09-06
- **Deciders:** Tanjil

## Context

Roadmap task 3.6. PHPStan levels are cumulative; climbing from level 5 to level
8 on an established codebase means retrofitting null-safety and type annotations
across every file touched. WordPress core has loose return types
(`get_post()` can return `WP_Post|array|null` depending on args), which fights
static analysis regardless of when you turn it on.

## Decision

`phpstan.neon.dist` runs at `level: 8` over `plugins/` and `themes/` from the
first commit, with `szepeviktor/phpstan-wordpress` and the WooCommerce stubs
loaded. No baseline file. New violations fail CI (static job).

## Consequences

### Positive

- Null-safety and array-shape correctness are enforced while the codebase is
  small enough to fix them immediately.
- The WooCommerce stubs give real types for `WC_Product` and friends, which is
  most of the commerce plugin's surface.

### Negative / accepted costs

- Expect roughly a day of fighting WordPress's loose signatures initially
  (the roadmap budgets for this).
- Occasional `@phpstan-ignore-next-line` with a comment where core's types are
  genuinely unknowable; these are reviewed, not routine.

### Neutral

- We deliberately do **not** create a baseline. A baseline hides existing debt;
  at level 8 from day one there is no debt to hide.

## Alternatives considered

- **Start at level 5, ratchet up:** rejected — the ratchet never gets
  prioritised against features.
- **Level 8 with a baseline:** rejected — same reason; the baseline becomes
  permanent.

## Notes

If a dependency forces unavoidable noise, prefer a narrow `ignoreErrors` regex
in `phpstan.neon.dist` with a comment over a blanket baseline.
