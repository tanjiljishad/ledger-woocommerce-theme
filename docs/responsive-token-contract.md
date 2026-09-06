# Responsive token contract

**Roadmap task 2.2.** Decided now, before any component depends on it, so the
Phase 2 control API is consistent.

## The rule

| Category | Responsive? | Rationale |
| --- | :-: | --- |
| `space` | **yes** | Layout rhythm must tighten on small screens. Spacing tokens may resolve to different values per breakpoint. |
| `size` (type scale) | **yes** | Type scale compresses on mobile. Font-size tokens are breakpoint-aware. |
| `color` | no | A colour is the same colour at every width. A responsive colour is a theming bug. |
| `radius` | no | Corner radius is an identity constant, not a layout variable. |
| `shadow` | no | Fixed; elevation semantics do not change with viewport. |
| `motion` | no | Durations/easings are fixed. Reduced-motion is handled by `prefers-reduced-motion`, not by breakpoint. |
| `breakpoint` | n/a | These *define* the responsive boundaries; they are plain constants. |

## How it is enforced

- `packages/tokens/dist/defaults.ts` exports `responsiveCategories`
  (`["space","size"]`) and `fixedCategories`
  (`["color","radius","shadow","motion","breakpoint"]`), generated from the
  compiler's constants.
- Editor controls in Phase 2 read these lists: a responsive category renders a
  per-breakpoint control; a fixed category renders a single control. No
  per-component decisions.
- Attempting to add a per-breakpoint override for a fixed category is a review
  rejection.

## Implementation shape (Phase 2 preview, not built yet)

Responsive tokens emit breakpoint-scoped custom properties:

```css
:root            { --ledger-space-5: 24px; }
@media (min-width: 768px)  { :root { --ledger-space-5: 24px; } }
@media (min-width: 1200px) { :root { --ledger-space-5: 32px; } }
```

Fixed tokens emit exactly once at `:root`. The compiler gains a `responsive`
map in `tokens.json` (per-token, per-breakpoint values) at that point; until
then every token has a single value and the contract above governs what is
*allowed* to become responsive.
