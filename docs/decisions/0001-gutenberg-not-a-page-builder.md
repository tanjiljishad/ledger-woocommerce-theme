# ADR 0001 — Build on Gutenberg, not a page builder

- **Status:** accepted
- **Date:** 2026-09-06
- **Deciders:** Tanjil

## Context

The product is a performance-gated WooCommerce theme and plugin suite. The
central promise (roadmap Phase 0) is that every performance claim is verified by
a machine. The editing layer is the single biggest determinant of front-end
weight: a page builder (Elementor, WPBakery, Bricks, Divi, Oxygen) ships its own
rendering runtime, its own CSS framework, and — in most cases — jQuery.

The six competitors we benchmark against (Woodmart, Porto, Flatsome, Blocksy,
Kadence Shop, Shoptimizer) all lean on either a bundled builder or a large
options framework. Their floor is set by that choice.

## Decision

We will build exclusively on the WordPress block editor (Gutenberg) and the
block/full-site-editing APIs. We will not bundle, integrate with, or optimise
for any third-party page builder. Layout is expressed as block templates,
block patterns, and `theme.json`; dynamic behaviour is expressed as blocks with
view scripts using the Interactivity API or vanilla ES modules.

## Consequences

### Positive

- No second rendering runtime. The front end ships our CSS plus core block
  library CSS, nothing else by default.
- `theme.json` gives us a typed, diff-able control surface that the token
  compiler can target directly (see ADR-0005).
- jQuery can be dequeued outright; the no-jQuery Playwright test (roadmap 6.2)
  is a realistic gate, not an aspiration.
- Editing UX, block patterns, and global styles are maintained upstream.

### Negative / accepted costs

- Some commerce UI that builders make trivial (mega menus, complex mobile
  filters) we must design and build as blocks.
- Gutenberg's release cadence is fast; we pin the WordPress core version in
  `.wp-env.json` and bump deliberately.
- Users migrating from a builder-based theme get no automatic content
  conversion. This is a documented limitation, not a bug.

### Neutral

- Marketplace positioning becomes "the fast one", explicitly opposed to the
  builder ecosystem.

## Alternatives considered

- **Bundle a lightweight builder (e.g. build on top of Bricks):** rejected —
  still a second runtime, still an external dependency for our core value prop,
  and licensing/redistribution friction.
- **Custom editor:** rejected — enormous cost, throws away upstream work and
  the block ecosystem.
- **Classic theme + shortcodes + Customizer:** rejected — no typed control
  surface, worse editing UX, and the Customizer is on a deprecation path.

## Notes

Revisit only if the block editor's front-end payload regresses substantially or
if WordPress ships a first-party builder-style runtime.
