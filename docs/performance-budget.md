# Performance budget

**Roadmap task 6.7.** These numbers are a hard gate. They are **never relaxed to
accommodate a feature** — see ADR-0006. A feature that does not fit is made
smaller, loaded on demand, or not shipped. Changing a number requires an ADR
superseding 0006 with benchmark data showing the new value is still competitive.

## The numbers

| Metric | Budget | Where enforced | Config key |
| --- | ---: | --- | --- |
| Lighthouse performance score (mobile) | ≥ 0.95 | Lighthouse CI | `categories:performance` |
| Largest Contentful Paint | ≤ 1800 ms | Lighthouse CI | `largest-contentful-paint` |
| Cumulative Layout Shift | ≤ 0.05 | Lighthouse CI | `cumulative-layout-shift` |
| Total Blocking Time (INP proxy) | ≤ 200 ms | Lighthouse CI | `total-blocking-time` |
| CSS transfer (per page, gzipped) | ≤ 60 KB (61440 B) | Lighthouse CI | `resource-summary:stylesheet:size` |
| JS transfer (per page, gzipped) | ≤ 40 KB (40960 B) | Lighthouse CI | `resource-summary:script:size` |
| Render-blocking resources | 0 | Lighthouse CI | `render-blocking-resources` |
| Responsive images | 0 offenders (warn) | Lighthouse CI | `uses-responsive-images` |
| jQuery on any rendered route | absent | Playwright | `tests/e2e/no-jquery.spec.ts` |
| Console errors on shop/product/cart/account | 0 | Playwright | `tests/e2e/smoke.spec.ts` |

### Per-entry bundle budgets (gzipped)

> **Not enforced in Phase 0.** There is no block or theme JS/CSS to weigh yet,
> so `size-limit`, `.size-limit.json`, and the `perf` job's `size` step were
> removed rather than left as an empty config that errors on invocation. They
> return with the first block — see [`phase-2-checklist.md`](phase-2-checklist.md).
> The Lighthouse `resource-summary` transfer caps (60 KB CSS / 40 KB JS above)
> stay enforced throughout.

They sum to the Lighthouse transfer thresholds with headroom:

| Entry | Budget |
| --- | ---: |
| theme critical CSS | 18 KB |
| tokens CSS | 4 KB |
| blocks editor + view CSS | 24 KB |
| commerce CSS | 14 KB |
| **CSS total** | **60 KB** |
| theme JS | 8 KB |
| blocks view JS | 20 KB |
| commerce JS | 12 KB |
| **JS total** | **40 KB** |

## Enforcement layers

1. **Static job** (`.github/workflows/ci.yml` → `static`): PHPCS, PHPStan,
   ESLint, `tsc`, token snapshot + `tokens:check`. Target < 3 min.
2. **Perf job** (`perf`, needs `static`): wp-env up → restore seed snapshot →
   Lighthouse CI (3 runs/URL) → Playwright. `build` and `size-limit` rejoin
   this chain in Phase 2 (see `phase-2-checklist.md`).
3. **Branch protection** on `main`: every check required, no direct pushes.

## Notes

- Byte thresholds are **transfer** size and assume gzip is on in the test
  server.
- `total-blocking-time` stands in for INP, which has no lab equivalent.
- Lighthouse variance is smoothed with 3 runs per URL; verify stability before
  trusting a threshold (roadmap 6.3).
- The gate must be seen to fail once (roadmap 6.6) before it is trusted.
