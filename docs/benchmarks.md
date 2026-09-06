# Competitive benchmarks

**Roadmap tasks 1.2–1.4.** Re-run quarterly. This file becomes the comparison
chart on the sales page, so the methodology below must stay precise enough to
reproduce exactly.

> Status: **template — numbers not yet collected.** The rows are populated by
> running the procedure below. Do not quote these figures until a dated run
> fills them in.

## Methodology

- **Tool:** Lighthouse (via `lhci collect` or Chrome DevTools), mobile form
  factor, `throttlingMethod: simulate`, default Moto G Power / slow 4G preset.
- **Runs:** 3 per URL; record the **median**.
- **Network:** measured on a warm cache-busting reload (`?lhci=<timestamp>`),
  first load, no service worker priming.
- **Environment:** competitor **official demo sites**, shop archive + one
  product page. Record the exact URLs used.
- **Floor:** stock Twenty Twenty-Five + WooCommerce, no other plugins, default
  data, served locally with gzip on (roadmap 1.3).
- **Metrics:** LCP (ms), TBT (ms), CLS, CSS transfer (KB), JS transfer (KB),
  total requests, jQuery loaded (Y/N). Transfer = compressed bytes over the
  wire, from the Network panel "transferred" column.
- **Date every run.** Keep old runs below the current one; never overwrite.

## Run: _not yet performed_

Date: `YYYY-MM-DD` · Lighthouse version: `x.y.z` · WordPress: `6.8` · WooCommerce: `x.y`

### Shop archive

| Theme (demo URL) | LCP ms | TBT ms | CLS | CSS KB | JS KB | Requests | jQuery |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | :---: |
| Woodmart | — | — | — | — | — | — | — |
| Porto | — | — | — | — | — | — | — |
| Flatsome | — | — | — | — | — | — | — |
| Blocksy | — | — | — | — | — | — | — |
| Kadence Shop | — | — | — | — | — | — | — |
| Shoptimizer | — | — | — | — | — | — | — |
| **Floor** (TT25 + Woo) | — | — | — | — | — | — | — |
| **Ledger target** | ≤1800 | ≤200 | ≤0.05 | ≤60 | ≤40 | — | N |

### Product page

| Theme (demo URL) | LCP ms | TBT ms | CLS | CSS KB | JS KB | Requests | jQuery |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | :---: |
| Woodmart | — | — | — | — | — | — | — |
| Porto | — | — | — | — | — | — | — |
| Flatsome | — | — | — | — | — | — | — |
| Blocksy | — | — | — | — | — | — | — |
| Kadence Shop | — | — | — | — | — | — | — |
| Shoptimizer | — | — | — | — | — | — | — |
| **Floor** (TT25 + Woo) | — | — | — | — | — | — | — |
| **Ledger target** | ≤1800 | ≤200 | ≤0.05 | ≤60 | ≤40 | — | N |

## Headroom note (fill after the floor run)

The gap between the **Floor** row and the **Ledger target** row is the real
headroom. If the floor already exceeds a target threshold, that threshold is
constrained by WooCommerce/core itself and the budget line needs an ADR, not a
quiet edit — see ADR-0006.
