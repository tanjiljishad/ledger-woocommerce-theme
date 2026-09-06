# ADR 0006 — The performance budget is a hard gate, never relaxed for a feature

- **Status:** accepted
- **Date:** 2026-09-06
- **Deciders:** Tanjil

## Context

Roadmap Phase 0 exists so that "every claim the product makes about performance
is verified by a machine". A budget that can be bumped when a feature does not
fit is not a budget — it is a suggestion, and the marketing claim built on it
becomes a lie over ten months of feature work.

## Decision

The numbers in `docs/performance-budget.md` are enforced in CI at three layers
and are **never** raised to accommodate a feature:

1. **Lighthouse CI** (`lighthouserc.js`) — performance score, LCP, CLS, TBT,
   per-type transfer bytes, render-blocking resources. `error` level.
2. **size-limit** (`.size-limit.json`) — per-entry gzipped JS and CSS budgets
   that sum to the Lighthouse transfer thresholds.
3. **Playwright** (`tests/e2e/`) — no jQuery on any rendered route; no console
   errors on shop/product/cart/account.

Branch protection on `main` requires every check. No direct pushes, including
the author's.

When a feature does not fit the budget, the options are: make it smaller, load
it on demand (dynamic import / conditional enqueue), or do not ship it. Raising
the ceiling is not on the list. Changing a threshold requires its own ADR
superseding this one, with data showing the new number is still competitive
against `docs/benchmarks.md`.

## Consequences

### Positive

- The sales-page comparison chart stays true by construction.
- "Does it fit the budget?" is answered by CI, not by argument.

### Negative / accepted costs

- Some features are slower to land because they need a lazy-loading design.
- Rare genuine platform shifts (a core block library size jump) require an ADR
  and a benchmark re-run rather than a one-line config edit.

## Alternatives considered

- **Soft budget with warnings:** rejected — warnings are ignored.
- **Per-feature budget exceptions:** rejected — exceptions accrete.

## Notes

Roadmap task 6.6 ("prove the gate fails") is the acceptance test for this ADR:
commit an oversized stylesheet and a main-thread blocker, watch CI go red on the
byte and TBT assertions, screenshot, revert.
