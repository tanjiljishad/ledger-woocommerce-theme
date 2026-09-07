# Responsive token contract

**Roadmap task 2.2.** Rewritten 2026-09-07 for WordPress 7.1 — see
[ADR 0013](decisions/0013-viewport-tokens-supersede-responsive-contract.md),
which supersedes the responsive-contract portion of ADR 0005. The previous
version defined a bespoke `responsiveCategories` / `fixedCategories` split and a
plan for the compiler to emit breakpoint-scoped custom properties. WordPress
7.1 provides this natively; we use that.

## The rule

**Tokens supply values. Core supplies responsiveness.**

- `packages/tokens/tokens.json` holds one value per token. No token is
  "responsive" or "fixed" — that classification is gone.
- Two breakpoints are defined by the `viewport` category
  (`viewport.mobile` = `480px`, `viewport.tablet` = `782px`, WordPress'
  defaults) and emitted by the compiler as `settings.viewport` in
  `themes/flagship/theme.json`.
- A value that should differ by width is authored as a WP 7.1 **`@mobile` /
  `@tablet` style variation** — in `theme.base.json` under
  `styles.blocks[<block>]` / `styles.elements[<element>]`, or in a block
  instance's `style` attribute. Core scopes it to:

  ```css
  @media (width <= 480px)          { /* @mobile */ }
  @media (480px < width <= 782px)  { /* @tablet */ }
  ```

  and adds a generated per-block class. Base (un-prefixed) styles are the
  widest tier.

## What this means for blocks

Every block — core, and every Ledger block — gets `@mobile` / `@tablet`
variations for **free** for any property it exposes through standard block
supports (`typography`, `color`, `background`, `border`, `dimensions`,
`spacing`, `layout`). No opt-in flag, no per-block breakpoint attribute. The
Phase 2 Ledger blocks therefore lean on standard supports wherever possible so
responsiveness comes with them.

`settings.responsiveEditingEnabled` / `settings.blockStatesEditingEnabled`
(both default `true`, set via the `block_editor_settings_all` filter) globally
show/hide the viewport and pseudo/custom-state controls; saved styles keep
working either way.

## Layout width ≠ breakpoint

`layout.content` (`768px`) and `layout.wide` (`1200px`) feed
`settings.layout.contentSize` / `wideSize`. They are content max-widths, not
responsive breakpoints, and have their own token category (previously they
mis-read the retired `breakpoint` tokens).

## Changing a breakpoint

Edit `viewport.mobile` / `viewport.tablet` in `tokens.json`, run
`pnpm run tokens:build`, commit the regenerated artefacts, and add a note to
ADR 0013 with the reason. Do this only with design or benchmark data behind it.
