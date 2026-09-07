# ADR 0008 — Hook names use underscores, not slashes

- **Status:** accepted
- **Date:** 2026-09-07
- **Deciders:** Tanjil

## Context

The Phase 0 plugins defined three cross-plugin hooks in slash-namespaced style:
`ledger/container` (filter), `ledger/core/booted` and `ledger/commerce/booted`
(actions). Slash namespacing is a real pattern in the wider PHP ecosystem, but
`WordPress.NamingConventions.ValidHookName.UseUnderscores` in our PHPCS ruleset
flags it, and `phpcs.xml.dist` exists precisely so "naming and compat
violations fail here, not in marketplace review".

Hook names are public API. The moment a customer site, a companion plugin, or a
snippet calls `add_action( 'ledger/core/booted', ... )`, the name is frozen:
changing it later silently breaks their integration unless we ship and maintain
a `do_action_deprecated()` / `apply_filters_deprecated()` shim for every rename.
Right now, in Phase 0, nothing outside this repo consumes these hooks.

## Decision

All Ledger hook names use lowercase words separated by underscores, prefixed
with the owning unit:

| Old | New |
| --- | --- |
| `ledger/container` | `ledger_container` |
| `ledger/core/booted` | `ledger_core_booted` |
| `ledger/commerce/booted` | `ledger_commerce_booted` |

Future hooks follow the same shape: `ledger_<unit>_<event>` for actions,
`ledger_<noun>` or `ledger_<unit>_<noun>` for filters. The rename landed in the
commit "Make `composer run phpcs` pass on the Phase 0 scaffold", updating every
`apply_filters` / `add_filter` / `do_action` call site and the docs.

We lock this now, before Phase 1, so no deprecation shim is ever needed for
these three.

## Consequences

### Positive

- PHPCS passes with no per-line exceptions for hook naming.
- Hook names match the `PrefixAllGlobals` prefix (`ledger`) and read
  consistently with WordPress core's own `_`-style hooks.
- The public hook surface is deliberately small and named to a rule from the
  first release, so `docs/` can document it as stable.

### Negative / accepted costs

- Slash namespacing groups a plugin's hooks more visibly (`ledger/commerce/*`).
  We lose that grouping; the `ledger_<unit>_` prefix is the substitute.

### Neutral

- No runtime behaviour change — a hook is a string key either way.

## Alternatives considered

- **Keep slashes, suppress the sniff** (`phpcs:ignore` or a ruleset exclusion):
  rejected. It weakens the naming gate for cosmetic reasons and the exclusion
  would invite the next slash hook.
- **Keep slashes, rename later with deprecation shims:** rejected. Pure cost —
  shim code, tests, and a support-window obligation — for a rename that is free
  today.

## Notes

Any new hook is part of the public API. Add it to the hook reference in `docs/`
when Phase 1 starts shipping them, and never rename a shipped hook without a
superseding ADR and a `*_deprecated()` shim.
