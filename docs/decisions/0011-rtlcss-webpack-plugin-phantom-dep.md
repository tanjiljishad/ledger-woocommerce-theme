# ADR 0011 — `rtlcss-webpack-plugin` phantom `@babel/runtime` dependency

- **Status:** accepted
- **Date:** 2026-09-07
- **Deciders:** Tanjil

## Context

The first real CI run of the `perf` job (it had been skipped while `static`
failed) died at `pnpm run build`:

```
plugins/commerce build: [webpack-cli] Error: Cannot find module
  '@babel/runtime/helpers/interopRequireDefault'
Require stack:
- .../rtlcss-webpack-plugin@4.0.7/dist/src/index.js
- .../@wordpress/scripts@30.5.1/config/webpack.config.js
```

`pnpm run build` fans out to `pnpm -r run build`; `@ledger/blocks` and
`@ledger/commerce` run `wp-scripts build`. `@wordpress/scripts@30.5.1` depends on
`rtlcss-webpack-plugin: ^4.0.7` and loads it unconditionally from its webpack
config.

`rtlcss-webpack-plugin@4.0.7`'s shipped `dist/src/index.js` does:

```js
var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
var _defineProperty2 = _interopRequireDefault(require("@babel/runtime/helpers/defineProperty"));
```

— i.e. it requires **`@babel/runtime`** (Babel 7). But its `package.json`
`dependencies` are only:

```json
"dependencies": { "babel-runtime": "~6.25.0", "rtlcss": "^3.5.0" }
```

`babel-runtime` is the **Babel 6** package; `@babel/runtime` is a different,
undeclared package. The `dist/` was transpiled against Babel 7 but the manifest
was never updated. `@babel/runtime` is a **phantom dependency**: the code needs
it, nothing declares it.

Under npm / Yarn, or pnpm with `hoist`/`shamefully-hoist`, `@babel/runtime`
almost always ends up flat in the top-level `node_modules` (it is a transitive
dep of countless Babel packages) and the bad `require` resolves by accident.
Under pnpm's isolated `node_modules` (this repo — see ADR 0003, ADR 0009),
`rtlcss-webpack-plugin` can only see what it declares, `@babel/runtime` is not
there, and the require throws.

This did not surface earlier because:

- `static` never runs `pnpm run build`.
- Local dev on a machine that had once hoisted, or had `@babel/runtime` pulled
  in by another tool, would resolve it.
- Phase 0 has no block or theme JavaScript, so nobody had a reason to build.

## Decision

Record the diagnosis; do not carry a fix in Phase 0.

`pnpm run build` and `pnpm run size` are removed from the `perf` job (there is
nothing to build or weigh yet — `blocks`/`commerce` have no `src/`), so the
breakage is off the critical path. `.size-limit.json` is an explicit empty set.

When the first block ships (Phase 2) and `wp-scripts build` must run, apply the
**smallest** fix that works, in this order of preference:

1. **`pnpm.packageExtensions`** in the root `package.json` to add the missing
   dependency to the offending package without touching our own deps:

   ```json
   "pnpm": {
     "packageExtensions": {
       "rtlcss-webpack-plugin@4": { "dependencies": { "@babel/runtime": "^7" } }
     }
   }
   ```

2. Add `@babel/runtime` as a root `devDependency` — blunter, pollutes our
   manifest with something we do not use directly, but unambiguous.

3. Drop `rtlcss-webpack-plugin` from the webpack config via
   `@wordpress/scripts`' `webpack.config.js` override hook, if RTL stylesheet
   generation is not wanted for the ltr-only themes we ship.

Prefer (1). Revisit (3) if we never want RTL CSS artefacts.

## Consequences

### Positive

- Phase 2 starts with the root cause written down and three ranked fixes, not a
  cold `Cannot find module` and an afternoon of `pnpm why`.
- The `perf` job is honest about what Phase 0 can actually exercise.

### Negative / accepted costs

- `pnpm run build` is broken on a clean pnpm checkout right now. Accepted: it
  produces nothing in Phase 0 and is not in CI. A developer who runs it locally
  hits this error; this ADR is the explanation.
- One more upstream-bug workaround to carry once (1) is applied.

### Neutral

- Unrelated to the PHP toolchain and to `static`.

## Alternatives considered

- **Fix it now (packageExtensions):** rejected for Phase 0 — adds a workaround
  for a build that produces nothing yet; better to land it with the first block
  so it is tested by something real.
- **Re-enable pnpm hoisting so the phantom resolves:** rejected — reverses
  ADR 0009 and re-introduces the `@wordpress/scripts` flat-`node_modules`
  breakage for an unrelated reason.
- **Pin `@wordpress/scripts` to a version without `rtlcss-webpack-plugin`:**
  none of the current line drops it; a downgrade loses other fixes.

## Notes

Upstream: `rtlcss-webpack-plugin` has shipped `dist/` requiring `@babel/runtime`
while declaring `babel-runtime` since 4.0.0. If a 4.0.x / 5.x with a corrected
manifest appears, `@wordpress/scripts` picking it up removes the need for the
workaround.
