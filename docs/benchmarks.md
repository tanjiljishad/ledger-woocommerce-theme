# Competitive benchmarks

**Roadmap tasks 1.2–1.4.** Re-run quarterly. This file becomes the comparison
chart on the sales page, so the methodology below must stay precise enough to
reproduce exactly, and every number must be traceable to a raw report in
`tools/bench/results/`.

> Status: **first run recorded — 2026-09-08.** Competitor rows are filled. The
> **Floor** (task 1.3) and **Kadence Shop** rows are pending a throwaway
> environment and are measured next, together, on identical hosting.

## Methodology

### Tool

- **PageSpeed Insights API**, `strategy=mobile` — this is Lighthouse mobile
  (Moto G-class device emulation, ~Slow-4G simulated throttling, 4× CPU
  slowdown) executed on Google's own infrastructure. **PSI Lighthouse 13.4.1**
  for this run.
- Chosen deliberately over a local Lighthouse: **a third party can reproduce any
  row** by calling the same public endpoint —
  `https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=<page>&strategy=mobile` —
  no shared machine state, no "trust our laptop". It also runs on Google's
  network rather than ours, and it clears the Cloudflare bot blocks that stop a
  local headless browser reaching some demos.
- Runner: [`tools/bench/psi.mjs`](../tools/bench/psi.mjs). Key from
  `PSI_API_KEY` (env or a gitignored `.env`) — never committed, never in this
  file.

### Runs

- **3 runs per URL**, each with a throwaway `?ledgerbench=<date>-<n>` query
  param. PSI caches a result per URL for ~15 minutes, so without the param three
  back-to-back calls return one identical run; the param forces three genuinely
  independent Lighthouse executions (WordPress ignores unknown query args). This
  matches the `?lhci=<timestamp>` cache-busting the CI gate uses.
- The reported row is the **median run by performance score**; the `LCP ms`
  column shows `median (run1/run2/run3)` so the spread is visible.
- Consolidated by [`tools/bench/report.mjs`](../tools/bench/report.mjs) from the
  raw JSON. `Src` = `PSI`; `LH*` = local Lighthouse (see exceptions).

### How the bytes are measured

- From Lighthouse's `resource-summary` audit, field `transferSize` =
  **compressed bytes over the wire** (verified against `network-requests`: e.g.
  Woodmart's `jquery.min.js` is 30.5 KB `transferSize` vs 87.6 KB decoded).
  This is the same basis as the Ledger budget (**60 KB CSS / 40 KB JS
  transfer**) and the DevTools "Transferred" column.
- **`CSS KB` is external stylesheet transfer only.** A theme that inlines its
  CSS into the HTML shows ~0 KB here while carrying the weight in the Document.
  Shoptimizer does exactly this — its `CSS KB` is 0–2 while its HTML document is
  67 KB (shop) / 102 KB (product) vs 29–61 KB for the others; the difference is
  inlined CSS. Noted in the table.
- `Requests` is the total request count from `resource-summary`.
- `jQuery` = a request whose URL matches `jquery-*` or `/wp-includes/js/jquery/`.

### On LCP and demo hosting

**Byte counts, request counts, and jQuery presence are the defensible numbers.**
They are a property of the theme's build and barely move between runs (CSS/JS
KB were identical to ±1 KB across all three runs for every PSI row).

**LCP is soft** on a shared vendor demo host and should be read as an order of
magnitude, not a figure. Evidence from this run:

- Woodmart product LCP ranged **2.4 s → 6.0 s** across three runs on the same URL
  minutes apart.
- Origin response time (TTFB) varies wildly by vendor: Flatsome **4–6 ms**
  (edge-cached), Woodmart ~120 ms, Shoptimizer ~230 ms, Porto 50–220 ms. Flatsome's
  fast origin but 5–6 s LCP shows its LCP is bound by page weight and
  render-blocking assets, not hosting; Porto's ~8.5 s LCP with 183 KB CSS +
  522 KB JS render-blocking is likewise mostly its own doing. Woodmart's wide
  spread is hosting noise.
- TBT and CLS are less network-sensitive and travel better between environments.

### Exceptions to "PSI, third-party reproducible"

- **Blocksy** — `startersites.io` (the Modern Shop demo host) serves Google's
  PSI fetcher a **Cloudflare 403 error page**; PSI's "measurement" of it was
  `cf.errors.css` + `cf-no-screenshot-error.png`, 6 requests, not the shop. A
  real headless Chromium with a normal UA passes, so Blocksy is measured with
  **local Lighthouse 12.8.2** ([`tools/bench/lh-local.mjs`](../tools/bench/lh-local.mjs),
  default mobile config). These rows are marked `LH*`. Consequence: **not
  third-party reproducible the way the PSI rows are**, and measured over a home
  connection — the local run saw a ~2.4 s TTFB to Cloudflare, so **Blocksy's LCP
  is not comparable to the PSI rows** (its CSS/JS/request counts still are).
- **Porto** — the primary demo `https://www.portotheme.com/wordpress/porto/shop/`
  returns Lighthouse `NO_FCP` ("the page did not paint any content"): its
  product grid renders client-side. *This is a finding, not just a measurement
  obstacle* — a shop archive that paints nothing without JavaScript fails
  no-JS/slow-JS users outright. Porto is therefore measured on its **Full Site
  Editing "Shop 1" demo**
  (`https://www.portotheme.com/wordpress/porto/shop1/shop/` +
  `…/shop1/product/new-balance-fresh-foam/`), which is server-rendered, same
  vendor host.
- **Kadence Shop** — no stable public demo exists (Kadence moved to Liquid Web;
  its starter-template previews are ephemeral InstaWP instances). Kadence is
  measured in **task 1.3** on the same throwaway environment as the Floor, with
  identical hosting. That makes Floor ↔ Kadence directly comparable to each
  other but **neither is directly comparable to the vendor-hosted demo rows** —
  different origin, different network. Marked when added.

### Reproduce

```
PSI_API_KEY=<key>  node tools/bench/psi.mjs --runs=3      # PSI rows
node tools/bench/lh-local.mjs --runs=3                    # Blocksy (local)
node tools/bench/report.mjs --date=2026-09-08             # rebuild the tables
```

Raw per-run Lighthouse JSON is committed under `tools/bench/results/` — the
demos change, so a given day's run is not reproducible later and the raw JSON is
the evidence behind any claim made from this file. Date every run; keep old
runs below the current one; never overwrite.

## Run: 2026-09-08

PSI Lighthouse `13.4.1` · local Lighthouse `12.8.2` (rows marked `LH*`) ·
WordPress target `7.1` · WooCommerce `11.1.0` · demos as hosted by each vendor
on the test date.

### Shop archive

| Theme | Src | Runs | LCP ms (med · run1/run2/run3) | TBT ms | CLS | CSS KB | JS KB | Requests | jQuery | TTFB ms |
| --- | :-: | :-: | --- | ---: | ---: | ---: | ---: | ---: | :-: | ---: |
| Woodmart | PSI | 3 | 4201 · 5540/3751/4201 | 114 | 0.001 | 113 | 425 | 222 | Y | ~115 |
| Porto | PSI | 3 | 8262 · 8262/8412/8291 | 145 | 0.089 | 183 | 522 | 116 | Y | ~51 |
| Flatsome | PSI | 3 | 6226 · 7992/6238/6226 | 64 | 0.005 | 44 | 320 | 63 | Y | ~5 |
| Blocksy | LH\* | 3 | 3769 · 5773/3769/3061 | 62 | 0.001 | 28 | 48 | 45 | Y | ~2400 (local) |
| Kadence Shop | — | — | task 1.3 | — | — | — | — | — | — | — |
| Shoptimizer | PSI | 3 | 1869 · 1869/2043/2191 | 0 | 0 | 0 † | 21 | 29 | N | ~232 |
| **Floor** (TT25 + Woo) | — | — | task 1.3 | — | — | — | — | — | — | — |
| **Ledger target** | — | — | ≤ 1800 | ≤ 200 | ≤ 0.05 | ≤ 60 | ≤ 40 | — | N | — |

### Product page

| Theme | Src | Runs | LCP ms (med · run1/run2/run3) | TBT ms | CLS | CSS KB | JS KB | Requests | jQuery | TTFB ms |
| --- | :-: | :-: | --- | ---: | ---: | ---: | ---: | ---: | :-: | ---: |
| Woodmart | PSI | 3 | 6001 · 2401/6001/4351 | 135 | 0 | 133 | 431 | 221 | Y | ~138 |
| Porto | PSI | 3 | 8893 · 8893/8893/8864 | 17 | 0.089 | 180 | 404 | 118 | Y | ~221 |
| Flatsome | PSI | 3 | 5176 · 5176/5101/5026 | 24 | 0 | 61 | 374 | 72 | Y | ~6 |
| Blocksy | LH\* | 3 | 4070 · 4070/4262/4256 | 10 | 0 | 34 | 68 | 49 | Y | ~2460 (local) |
| Kadence Shop | — | — | task 1.3 | — | — | — | — | — | — | — |
| Shoptimizer | PSI | 3 | 3151 · 2851/3151/3155 | 0 | 0 | 2 † | 87 | 29 | Y | ~225 |
| **Floor** (TT25 + Woo) | — | — | task 1.3 | — | — | — | — | — | — | — |
| **Ledger target** | — | — | ≤ 1800 | ≤ 200 | ≤ 0.05 | ≤ 60 | ≤ 40 | — | N | — |

† Shoptimizer inlines its CSS into the HTML document (67 KB shop / 102 KB
product, vs 29–61 KB elsewhere). The `CSS KB` column counts external stylesheet
transfer only, so it reads ~0 for Shoptimizer; the real CSS payload is ~50–90 KB
inside the Document.

## Observations

- **jQuery is near-universal.** Five of six load it on the product page
  (all but nothing); on the shop archive only Shoptimizer avoids it. Ledger
  shipping zero jQuery is a real, checkable differentiator, not a marketing
  line — the Playwright `no-jquery` spec enforces it every build.
- **Two clusters.** Woodmart and Porto are 400–520 KB JS / 110–185 KB external
  CSS / 120–220 requests — the "feature-maximal page-builder" profile. Blocksy
  and Shoptimizer are 20–90 KB JS / 0–34 KB CSS / 29–49 requests — the "lean"
  profile Ledger competes in. Flatsome sits between (320–375 KB JS, 44–61 KB CSS,
  63–72 requests).
- **Shoptimizer is the bar to beat on payload:** ~21 KB JS and no external CSS on
  the shop archive, 29 requests, TBT 0. Its cost is a heavier HTML document
  (inlined CSS) and it still posts LCP ~1.9–3.2 s on a mid-tier demo host.
- **Porto's client-rendered `/shop/`** paints nothing to Lighthouse — recorded
  as a graceful-degradation finding against Porto, and a reminder that Ledger's
  archive must server-render (it does; task 4.3).
- **Nobody in this set is close to the Ledger targets** on the feature-maximal
  pages; Shoptimizer already beats the JS target and Blocksy is within ~10–30 KB
  of it. The targets are aggressive but not unprecedented.

## Headroom note (fill after the floor run)

The gap between the **Floor** row and the **Ledger target** row is the real
headroom. If the floor already exceeds a target threshold, that threshold is
constrained by WooCommerce/core itself and the budget line needs an ADR, not a
quiet edit — see ADR 0006.
