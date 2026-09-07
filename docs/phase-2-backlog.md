# Phase 2 backlog — settled decisions

Phase 2 is the block + control-API phase. There was no Phase 2 backlog file
before this; `global.md` details Phase 0 only. This records scope decisions
already settled, so the eventual Phase 2 plan is built on them rather than
re-litigating. Driven by [`wordpress-7.1-impact.md`](wordpress-7.1-impact.md)
and [ADR 0013](decisions/0013-viewport-tokens-supersede-responsive-contract.md).

See also [`phase-2-checklist.md`](phase-2-checklist.md) (things deferred *out
of* Phase 0 that must be restored).

---

## Blocks

### Tabs — do **not** build a bespoke block

`core/tabs` is stable in WordPress 7.1 (`core/tabs` + `core/tab-list` +
`core/tab-panels`; hybrid, Interactivity API; ARIA `tablist`/`tab`/`tabpanel`;
supports align/anchor/color/spacing/typography/layout). It covers what a
first-party Tabs block would have.

- **Removed from the backlog:** any `ledger/tabs` block.
- **Instead:** a block style / block variation of `core/tabs` themed with our
  tokens, plus a **product tabs pattern** (description / additional
  information / reviews) that composes `core/tabs`.
- Do not depend on a `core/tabs` data store — the experimental one was removed
  before stabilisation.

### No per-block "responsive attribute schema"

The Phase 2 task to design a responsive-attribute schema for Ledger blocks
(per-block breakpoint attributes + editor controls) is **removed**. WordPress
7.1 gives every block `@mobile` / `@tablet` style variations for free for any
property exposed through standard block supports (`typography`, `color`,
`background`, `border`, `dimensions`, `spacing`, `layout`) — editor UI and
front-end media-query CSS, no opt-in.

- **Rule:** Ledger blocks expose styling through **standard block supports**
  wherever possible, so responsiveness (and pseudo/custom style states) come
  with them. Bespoke breakpoint attributes only where a block genuinely has no
  matching support, and then documented as an exception.
- Breakpoints are `settings.viewport` (`viewport.mobile` / `viewport.tablet`
  tokens → `480px` / `782px`). See ADR 0013 / the responsive token contract.

### Icon Box — re-spec on the WP 7.1 SVG Icon API

Build the Icon Box block on the core **SVG Icon API**, not a custom SVG
embedding / sprite / sanitising pipeline.

- Register a `ledger` collection: `wp_register_icon_collection( 'ledger', [
  'label' => … ] )`.
- Register each icon from a file: `wp_register_icon( 'ledger/<name>', [
  'label' => …, 'file_path' => … ] )`, on `init`.
- Render server-side via `wp_get_icon( 'ledger/<name>', [ 'size' => …,
  'class' => … ] )`; editor via `@wordpress/icons` React components.
- Optional: expose an icon picker using `GET /wp/v2/icons/ledger`.

**Hard constraint — the `wp_kses` sanitiser.** Registered SVGs are filtered to
**only** `<svg>`, `<path>`, `<polygon>` + a fixed attribute subset:

| Allowed | Stripped |
| --- | --- |
| `<path>`, `<polygon>`, `fill` on shapes, `viewBox` | `stroke` and all stroke-* attrs |
| | `<g>`, `<circle>`, `<rect>`, `<line>`, `<use>`, `<defs>` |
| | gradients, filters, masks, `clip-path` |
| | inline `style`, `class` on shapes, event handlers |

So the icon set must be **flat, fill-only, one or a few `<path>`/`<polygon>`
per icon, no groups, no strokes, no gradients**.

**Icon-set audit — no set is committed yet.** There is no `docs/` spec naming
an icon library, so nothing concrete to test. The constraint above *dictates*
the choice:

| Library | Style | Survives `wp_kses`? |
| --- | --- | --- |
| WP `@wordpress/icons` | fill, single path | **yes** (and already in editor) |
| Heroicons **solid** | fill, mostly single path | yes (spot-check multi-path ones) |
| Material Symbols **filled** | fill, single path | yes |
| Phosphor **fill**, Remix **fill**, Bootstrap Icons | fill | yes |
| Lucide, Feather, Tabler, Heroicons **outline** | **stroke** | **no — dead on arrival** |
| Any duotone / multicolour set | `<g>` + multiple fills | no |

**Recommendation:** pick a fill-based set (Heroicons solid or Material Symbols
filled are the safe defaults) and, before committing, run each chosen SVG
through a `wp_kses` check — a handful of "solid" icons still ship as
`<path>` + `<circle>` or with a `<g>`. Decision + the audited list go in the
Icon Box spec when it is written.

---

## Navigation / mega menu

**WordPress 7.1 changed Navigation font-size propagation.** The Navigation
block no longer forces `font-size` onto `core/navigation-link`,
`core/navigation-submenu`, `core/page-list`, `core/home-link` (relative units
were compounding — `1.5em → 2.25em → 3.375em` — down nested dropdowns). It now
relies on standard CSS text inheritance. New default, no opt-in; legacy
behaviour is restorable per child via `render_block_core/navigation-link` (and
sibling) filters using `WP_HTML_Tag_Processor`.

**For the planned mega menu (built on / extending the Navigation block):**

- Set typography **explicitly** on the mega-menu container / columns / links.
  Do not rely on `font-size` set on the Navigation block cascading into the
  panel — it no longer does.
- Any token wiring that assumed "one `font-size` on the nav, children scale"
  must be redesigned. Net positive: mega-menu type is now predictable.
- If the top-level bar specifically wants the old compounding behaviour, that
  is the `render_block_core/navigation-*` filter route — treat as an exception,
  not the default.

---

## Also carried from the 7.1 review (lower priority)

- New block supports **background gradient** and **minimum width** — free for
  Ledger blocks that declare them.
- Block preset-class specificity now uses `:where()` (root-level) — account for
  it when theme CSS competes with block preset classes.
- `@wordpress/blocks` markdown parser is `marked`, not `showdown`.
- `__experimentalCloneSanitizedBlock` / `__experimentalSanitizeBlockAttributes`
  are stabilised (unprefixed); prefixed forms warn.
