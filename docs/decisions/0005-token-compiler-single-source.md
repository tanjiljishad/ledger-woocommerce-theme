# ADR 0005 — `tokens.json` is the single source; three artefacts are generated

- **Status:** accepted
- **Date:** 2026-09-06
- **Deciders:** Tanjil

## Context

Roadmap 0.2. Design values (colour, spacing, type scale, radius, shadow,
motion, breakpoints) are consumed in three places: `theme.json` settings (editor
+ global styles), a runtime CSS custom-property sheet, and editor control
defaults in TypeScript. Hand-maintaining three copies guarantees drift.

## Decision

`packages/tokens/tokens.json` is the only place a design value is authored. A
Node build step (`packages/tokens/src/compile.mjs`) emits, and commits:

| Artefact | Path | Consumer |
| --- | --- | --- |
| theme.json settings partial | `packages/tokens/dist/theme.settings.json` | reference / review |
| full theme.json | `themes/flagship/theme.json` (base merged in) | WordPress |
| CSS custom properties | `packages/tokens/dist/tokens.css` → theme `assets/` | front end |
| TS control defaults | `packages/tokens/dist/defaults.ts` | block editor |

The generated files are committed. `compile.mjs --check` (CI static job) fails if
any committed artefact has drifted from `tokens.json`. A token change therefore
shows up as a reviewable diff across all outputs (roadmap 2.4), and the compiler
is never run by hand in anger — only as `pnpm run tokens:build`.

Responsive contract (roadmap 2.2): `space` and `size` are responsive
categories; `color`, `radius`, `shadow`, `motion`, `breakpoint` are fixed. This
split is encoded in `defaults.ts` (`responsiveCategories` / `fixedCategories`)
and documented in `docs/responsive-token-contract.md`.

## Consequences

### Positive

- Impossible to change a colour in CSS without the editor and `theme.json`
  following, or CI going red.
- Snapshot test (`packages/tokens/test/`) turns an accidental palette edit into
  a failing assertion with a readable diff.
- Adding an output format later (native app, email) is one more emitter.

### Negative / accepted costs

- Contributors must run `pnpm run tokens:build` and commit generated files;
  forgetting is caught by CI but costs a round-trip.
- `defaults.ts` ships `/* eslint-disable */` — it is generated, not authored.

## Alternatives considered

- **Style Dictionary:** rejected for now — heavier dependency than a ~200-line
  script needs; revisit if output formats multiply.
- **Generate at build time, don't commit:** rejected — then `--check` has
  nothing to diff and review loses the signal.

## Notes

Keep `tokens.json` flat and boring. Every token becomes three (now four)
outputs; an unused token is dead weight everywhere.
