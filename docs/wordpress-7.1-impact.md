# WordPress 7.1 impact report

**Purpose:** assess what shipped in WordPress 7.1 ("Mary Lou", 2026-08-19)
against the Ledger phase plan, so the plan can be revised *before* building
Phase 2. Companion to [ADR 0012](decisions/0012-retarget-wordpress-7.1.md).
Nothing here is implemented yet.

Sources:

- [Responsive block styles and configurable viewports in WordPress 7.1](https://make.wordpress.org/core/2026/08/05/responsive-block-styles-and-configurable-viewports-in-wordpress-7-1/)
- [Pseudo and custom style states in WordPress 7.1](https://make.wordpress.org/core/2026/08/05/pseudo-and-custom-style-states-in-wordpress-7-1/)
- [WordPress 7.1 Field Guide](https://make.wordpress.org/core/2026/08/05/wordpress-7-1-field-guide/)
- [Miscellaneous Editor Changes in WordPress 7.1](https://make.wordpress.org/core/2026/08/04/miscellaneous-block-editor-changes-in-wordpress-7-1/)
- [Registering and rendering SVG icons in WordPress 7.1](https://make.wordpress.org/core/2026/07/24/registering-and-rendering-svg-icons-in-wordpress-7-1/)
- [Core Tabs block — Block Editor Handbook](https://developer.wordpress.org/block-editor/reference-guides/core-blocks/core-blocks-design/core-block-tabs/)

---

## 1. `settings.viewport` schema

Exact shape in `theme.json`:

```json
{
  "settings": {
    "viewport": {
      "mobile": "30rem",
      "tablet": "45rem"
    }
  }
}
```

- Two keys only: **`mobile`** and **`tablet`**. There is no `desktop` key —
  the un-prefixed (base) styles *are* the desktop tier.
- Values are non-negative lengths in **`px`, `em`, or `rem` only**. "CSS
  functions, percentages, unitless values, and other units are ignored."
- Defaults if unset: `mobile` = `480px`, `tablet` = `782px`.
- Additive to `theme.json` schema **version 3** — 7.1 does **not** introduce a
  v4 (not mentioned anywhere in the Field Guide; the responsive note places
  `viewport` under `settings` with no version gate).

---

## 2. How responsive style states are stored and how core builds the media queries

**Storage — two places, same syntax:**

- **Global (`theme.json`):** under `styles.blocks[<blockType>]`, keys `@mobile`
  and `@tablet`. These nest: pseudo-states (`":hover"`, `":focus"`,
  `":focus-visible"`, `":active"` — colon prefix) and custom states (`-`
  prefix, e.g. `"-current"`) can sit inside `@mobile` / `@tablet` and vice
  versa. `text-shadow` is a new supported property.
- **Per block instance:** in the block's `style` attribute, same `@mobile` /
  `@tablet` keys.

**CSS generation:** "On the frontend, WordPress generates media-query-scoped
CSS for the responsive values and adds a stable, generated class to the
rendered block." Per-instance state declarations are emitted `!important` to
override the block's own inline styles. Responsive layout values and
`blockGap` reuse the existing layout support, scoped by the block's generated
container class.

**Breakpoint ranges** are derived from `settings.viewport` using CSS range
syntax:

```css
@media (width <= 30rem)            { /* mobile — value of viewport.mobile */ }
@media (30rem < width <= 45rem)    { /* tablet — between mobile and viewport.tablet */ }
```

i.e. mobile is `max-width: viewport.mobile`; tablet is the band between
`viewport.mobile` and `viewport.tablet`; base is everything wider.

**Editor toggles** (`block_editor_settings_all` filter):
`responsiveEditingEnabled` (default `true`) hides the viewport UI;
`blockStatesEditingEnabled` (default `true`) hides the pseudo/custom-state UI.
Saved styles keep working either way.

---

## 3. Do our custom dynamic blocks get responsive styles for free?

**Yes — for free, no opt-in flag.** Quote: responsive styles "can be applied
to all block types (and their block style variations) that use core block
supports such as typography, color, background, border, dimensions, spacing,
and layout." A block registered via `block.json` + `render_callback` that
declares those supports gets the `@mobile` / `@tablet` editor controls and the
front-end media-query CSS automatically. Declaring the supports **is** the
opt-in. (Confirmed by the core Tabs block, which declares standard supports
and picks them up.)

Implication: our Phase 2 "controls API" for Ledger blocks should lean on
standard block supports wherever possible so responsiveness comes with them,
rather than inventing per-block breakpoint attributes.

---

## 4. Token compiler: emit `settings.viewport`, do not feed a custom schema

Today `packages/tokens/tokens.json` has `breakpoint.tablet = 768px` and
`breakpoint.desktop = 1200px`, and the compiler treats `space` / `size` as
`RESPONSIVE_CATEGORIES` under a bespoke scheme (see
`docs/responsive-token-contract.md`, ADR 0005).

7.1 gives us a native target. The compiler should emit `settings.viewport`
into the generated `theme.json` settings partial. **The mapping is not a
passthrough** — core's tiers are `mobile` / `tablet` (both narrower than
base), ours are named `tablet` / `desktop`:

| Ledger token | px | → core `settings.viewport` key | rationale |
| --- | --- | --- | --- |
| `breakpoint.tablet` | 768 | `mobile` | "tablet and below" band in core terms |
| `breakpoint.desktop` | 1200 | `tablet` | mid band; base = wider than this |

(Or introduce explicit `viewport.mobile` / `viewport.tablet` tokens and retire
the `tablet` / `desktop` names to avoid the semantic clash.)

Open questions for the plan revision:

- Does `RESPONSIVE_CATEGORIES` (space/size re-scaling) survive, or is it
  replaced by authoring `@mobile` / `@tablet` style variations per block /
  in global styles? Leaning: keep tokens as the *source of the values*, stop
  hand-rolling the responsive application, let core's `@mobile`/`@tablet` do it.
- `docs/responsive-token-contract.md` and ADR 0005 both need revising.
- Verify `settings.viewport` validates under `theme.json` `version: 3` in a
  live 7.1 (schema URL is `trunk`).

---

## 5. Core Tabs block — replaces a from-scratch build

`core/tabs` is **stable in 7.1** (promoted from Gutenberg 23.6).

- **Hybrid block:** saves static markup, server may enhance; uses the
  **Interactivity API** for tab switching.
- **Inner blocks:** `core/tab-list` and `core/tab-panels` (exactly these two).
- **Attributes:** `activeTabIndex` (number, default `0`),
  `editorActiveTabIndex` (number, local/editor-only).
- **Supports:** align, anchor, color (text + background), layout (editing
  disabled), Interactivity API, spacing (`blockGap`, margin, padding),
  typography + `fontSize`; `html: false`.
- **A11y:** semantic `role="tablist"` / `role="tab"` / `role="tabpanel"`,
  keyboard-tested; toolbar buttons for reordering tabs.
- The experimental `core/tabs` data store was **removed** before
  stabilization — do not build against a `core/tabs` store.

**Plan impact:** if a bespoke Tabs block is on the Phase 1/2 block list,
**drop it.** Ship instead a block style / block variation of `core/tabs`
themed with our tokens, plus any commerce-specific pattern (e.g.
description / additional-information / reviews tabs on the product page) as a
pattern that *uses* `core/tabs`.

---

## 6. SVG Icon API — build the Icon Box block on it, not a custom pipeline

New in 7.1. PHP API (register on `init`):

| Function | Args |
| --- | --- |
| `wp_register_icon_collection( $name, $args )` | `label` (req), `description` |
| `wp_unregister_icon_collection( $name )` | — |
| `wp_register_icon( $name, $args )` | `label` (req), and `content` (inline SVG string) **or** `file_path` (abs path to `.svg`) |
| `wp_unregister_icon( $name )` | — |
| `wp_get_icon( $name, $options )` | `size` (px, default `24`), `class`, `label` → returns SVG markup string |

- Names are `collection/icon-name`; must start/end lowercase alphanumeric.
- SVG is sanitized through `wp_kses` against a strict allowlist — only
  `<svg>`, `<path>`, `<polygon>`, a fixed attribute subset; `fill` on shapes
  survives, `stroke` does not, inline styles / event handlers stripped.
- REST (auth, `edit_posts`): `GET /wp/v2/icon-collections`,
  `/wp/v2/icon-collections/<collection>`, `/wp/v2/icons`,
  `/wp/v2/icons/<collection>` (new in 7.1), `/wp/v2/icons/<collection>/<name>`;
  `search` + `collection` query params.
- Editor React icons: `@wordpress/icons` (v15+, outer `<svg fill="currentColor">`).
- Core ships a `core` collection; the Icon block picker groups by collection.

**Plan impact:** register a `ledger` icon collection + our icon set via
`wp_register_icon` (from `file_path`), and have the Icon Box block render
server-side through `wp_get_icon()`. No custom SVG embedding / sanitising /
sprite pipeline. Constraint to design around: **no `<g>`, no `stroke`,
no gradients** in registered icons — our icon set must be single-path /
polygon, `fill`-only. Audit the intended icon set against that now.

---

## 7. Navigation block font-size propagation — mega-menu impact

7.1 change (new default, no opt-in): the Navigation block **no longer forces
`font-size` onto its children** (`core/navigation-link`,
`core/navigation-submenu`, `core/page-list`, `core/home-link`). Previously
relative units compounded down nested dropdowns (`1.5em → 2.25em → 3.375em`)
and broke layout; it now "safely relies on standard CSS text inheritance."
Legacy behaviour is restorable per-child via a
`render_block_core/navigation-link` filter (and siblings) applying font-size
classes with `WP_HTML_Tag_Processor`.

**Plan impact on the planned mega menu:** a mega menu built on / extending the
Navigation block **must set typography explicitly** on its own
container/columns/links. Anything that assumed "set `font-size` on the Nav
block, children scale from it" is now wrong — children inherit from the
document/context, not from the Nav block. Net: *better* for predictable
mega-menu type, but the design and any token wiring must not depend on the
old cascade. If we ever want the old behaviour for the top-level bar only,
it's the `render_block_core/navigation-*` filter route.

---

## Other 7.1 items worth noting for the plan

- **New block supports:** background **gradient**, **minimum width**
  ([dev note](https://make.wordpress.org/core/2026/07/26/new-block-support-in-wordpress-7-1-minimum-width/)).
  Free for our blocks if declared.
- **Block-level preset class specificity** now uses `:where()` to sit at
  root-level specificity — relevant when our theme CSS competes with block
  preset classes.
- `@wordpress/blocks` swapped its Markdown parser `showdown` → `marked`.
- `__experimentalCloneSanitizedBlock` / `__experimentalSanitizeBlockAttributes`
  are stabilised (unprefixed); prefixed forms warn.

## Recommended plan revisions (for discussion — not actioned)

1. **Token compiler / ADR 0005 / `responsive-token-contract.md`:** emit
   `settings.viewport`; remap `tablet`/`desktop` → core `mobile`/`tablet`;
   decide the fate of `RESPONSIVE_CATEGORIES`.
2. **Block backlog:** remove "Tabs block"; add "`core/tabs` style + product
   tabs pattern".
3. **Icon Box block:** re-spec on the SVG Icon API; audit icon set for the
   `<path>`/`<polygon>` + `fill`-only sanitiser limit.
4. **Mega menu:** typography set explicitly; drop any reliance on Nav
   font-size propagation.
5. **Controls API (Phase 2):** prefer standard block supports so
   responsive + state variations come for free.
6. Confirm `theme.json` stays v3; no v4 migration needed for 7.1.
