# Phase 5 checklist

Items to pick up in Phase 5. There was no Phase 5 checklist before this;
`global.md` details Phase 0 only. Same idea as
[`phase-2-checklist.md`](phase-2-checklist.md) / [`phase-2-backlog.md`](phase-2-backlog.md).

## Revisit `viewport.tablet`

`packages/tokens/tokens.json` currently sets `viewport.tablet = 782px` — this
is WordPress' default, and `782` is derived from the admin bar breakpoint, not
from anything about a storefront. `viewport.mobile = 480px` is likewise the
core default. See [ADR 0013](decisions/0013-viewport-tokens-supersede-responsive-contract.md).

- [ ] Once the **product grid** and **filter sheet / off-canvas filters**
      designs exist, decide the real `@tablet` (and `@mobile`) boundary from
      where those layouts actually need to change — column-count drops, filter
      sidebar → drawer, sticky add-to-cart appears — measured against the seed
      data and mobile Lighthouse, not inherited from core.
- [ ] If it changes: edit `viewport.mobile` / `viewport.tablet`, run
      `pnpm run tokens:build`, commit the regenerated artefacts, and add a
      dated note to ADR 0013 with the reasoning.
