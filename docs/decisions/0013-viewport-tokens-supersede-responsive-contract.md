# ADR 0013 — `viewport` tokens + `settings.viewport`; retire the custom responsive contract

- **Status:** accepted
- **Date:** 2026-09-07
- **Deciders:** Tanjil
- **Supersedes:** the responsive-contract portion of
  [ADR 0005](0005-token-compiler-single-source.md) (roadmap 2.2). ADR 0005's
  core decision — `tokens.json` is the single source, artefacts are generated
  and committed, `--check` guards drift — stands unchanged.

## Context

ADR 0005 / [`docs/responsive-token-contract.md`](../responsive-token-contract.md)
defined a bespoke responsive scheme:

- `space` and `size` are "responsive categories"; `color`, `radius`, `shadow`,
  `motion`, `breakpoint` are "fixed" — encoded as `responsiveCategories` /
  `fixedCategories` exports in `defaults.ts`.
- `breakpoint.tablet` (768px) and `breakpoint.desktop` (1200px) were the
  boundaries.
- Planned (never built): the compiler would emit breakpoint-scoped custom
  properties, e.g. `@media (min-width: 768px) { :root { --ledger-space-5: … } }`,
  and Phase 2 editor controls would render per-breakpoint inputs for responsive
  categories and single inputs for fixed ones.

WordPress 7.1 ships this natively (see
[`docs/wordpress-7.1-impact.md`](../wordpress-7.1-impact.md)):

- `settings.viewport` (`mobile` / `tablet`, lengths in px/em/rem) defines two
  breakpoints; base styles are the widest tier.
- `@mobile` / `@tablet` style variations in `theme.json` `styles.blocks[…]` and
  in a block instance's `style` attribute are turned by core into
  media-query-scoped CSS plus a generated per-block class.
- **Any** block that declares standard block supports (typography, color,
  spacing, dimensions, layout, …) gets these variations for free — editor UI
  and front-end CSS — with no opt-in.

Building and maintaining our own version of this now would be redundant and
would diverge from what the editor's device-preview and third-party blocks use.

## Decision

1. **`tokens.json` gains a `viewport` category** — `{ "mobile": "480px",
   "tablet": "782px" }` — and the compiler emits it as `settings.viewport` in
   the generated `theme.json`.
2. **`breakpoint.tablet` / `breakpoint.desktop` are retired.** They are *not*
   renamed to `viewport.*`: core's `mobile` / `tablet` are both narrower than
   base, our `tablet` / `desktop` were a mid width and a wide width — the
   semantics differ and reusing the names would mislead.
3. **`layout` category added** — `{ "content": "768px", "wide": "1200px" }` —
   to feed `settings.layout.contentSize` / `wideSize`, which previously (mis)read
   the `breakpoint` tokens. Content max-width is not a responsive breakpoint;
   it now has its own tokens. Values carried over from the old `breakpoint`
   tokens; a Phase 2 design review may widen `content` for shop archives.
4. **`RESPONSIVE_CATEGORIES` / `FIXED_CATEGORIES` and the
   `responsiveCategories` / `fixedCategories` exports are deleted**, along with
   the never-built per-breakpoint custom-property plan. There is no
   per-category responsive/fixed split. Tokens supply *values*; core's
   `@mobile` / `@tablet` applied to block supports supplies *responsiveness*.
5. `theme.json` stays schema **version 3** — 7.1 adds `viewport` additively.

### Value choice

`480px` / `782px` are WordPress's own defaults. Reasoning: we have no
competitive teardown or mobile-Lighthouse data yet that would justify custom
breakpoints, and matching core means our `@mobile` / `@tablet` line up with the
editor's device preview and with any core- or third-party-authored responsive
styles. Deviating without data just fragments the breakpoint story. Revisit in
Phase 2 with real design + benchmark data.

## Consequences

### Positive

- One breakpoint system, core's, shared by our theme, our blocks, and every
  third-party block.
- The compiler shrinks: no category classification, no responsive-CSS emitter
  to build.
- Phase 2 block work authors `@mobile` / `@tablet` in `theme.json` / block
  attributes and gets the media queries for free.

### Negative / accepted costs

- `defaults.ts` no longer tells the editor which categories are "responsive" —
  correct, because that concept is gone, but any Phase 2 code that assumed it
  needs rethinking against block supports.
- `--ledger-breakpoint-*` custom properties are removed. Nothing consumed them
  (`grep` clean), but a hand-written `@media` in future theme CSS should use
  `--ledger-viewport-*` or, better, author an `@mobile`/`@tablet` variation.
- We inherit core's breakpoint values; if design later wants different numbers,
  it is a `viewport` token change + a note here.

### Neutral

- `layout.contentSize` / `wideSize` output is unchanged (768 / 1200); only its
  token source moved.

## Alternatives considered

- **Rename `breakpoint.tablet`→`viewport.mobile`, `breakpoint.desktop`→`viewport.tablet`:**
  rejected — 768 as a *mobile* ceiling and 1200 as a *tablet* ceiling both
  misrepresent core's model; the names would actively mislead.
- **Keep the custom scheme, feed `settings.viewport` alongside it:** rejected —
  two responsive systems, and the custom one still has no editor UI and would
  have to be built.
- **Bump `theme.json` to a hypothetical v4:** not applicable — 7.1 introduces
  none.

## Notes

- Follow-ups tracked in [`docs/phase-2-backlog.md`](../phase-2-backlog.md):
  drop the bespoke Tabs block, drop the Phase 2 "responsive attribute schema"
  task, re-spec Icon Box on the SVG Icon API, note the Navigation font-size
  propagation change for the mega menu.
- `docs/responsive-token-contract.md` rewritten to match.
