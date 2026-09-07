# Phase 2 checklist

Items deferred out of Phase 0 that must be picked up when the first block
ships. Not the Phase 2 roadmap (`global.md`) — a carry list of things
intentionally left undone with a reason, so they are restored rather than
forgotten.

## Restore the bundle-size gate

Phase 0 has no block or theme JS/CSS, so `size-limit`, `.size-limit.json`, the
root `size` script, `@size-limit/file` / `size-limit` devDependencies, and the
`perf` job's `size` step were **removed** — an empty config that errors on
invocation is worse than an absent tool, because the Phase 2 path of least
resistance would then be deleting the check rather than fixing it.

When the first block's build produces real artefacts, bring all of it back:

- [ ] Re-add `size-limit` + `@size-limit/file` devDependencies and the
      `"size": "size-limit"` script.
- [ ] Recreate `.size-limit.json` with the per-entry gzipped budgets from
      `docs/performance-budget.md` → "Per-entry bundle budgets":

      | Entry | Budget |
      | --- | ---: |
      | theme critical CSS | 18 KB |
      | tokens CSS | 4 KB |
      | blocks editor + view CSS | 24 KB |
      | commerce CSS | 14 KB |
      | theme JS | 8 KB |
      | blocks view JS | 20 KB |
      | commerce JS | 12 KB |

      **Sums: 60 KB CSS, 40 KB JS** — the Lighthouse `resource-summary`
      transfer caps (ADR-0006, never relaxed).
- [ ] Re-add `pnpm run build` + `pnpm run size` to the `perf` job in
      `.github/workflows/ci.yml`, before `wp-env start`.
- [ ] Fix the `rtlcss-webpack-plugin` phantom `@babel/runtime` dependency that
      breaks `wp-scripts build` under pnpm — see
      [ADR-0011](decisions/0011-rtlcss-webpack-plugin-phantom-dep.md)
      (prefer `pnpm.packageExtensions`).
- [ ] Update `docs/performance-budget.md` and `docs/phase-0-status.md` (task
      6.4) to drop the "not enforced in Phase 0" notes.
