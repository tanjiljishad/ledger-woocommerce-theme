# ADR 0015 — Resumable `seed generate`

- **Status:** accepted
- **Date:** 2026-09-08
- **Deciders:** Tanjil

## Context

Task 5.2 runs `wp ledger seed generate --count=5000 --images` against wp-env.
On this host (Windows 11 + Docker Desktop) a 50-product calibration measured
**~2.4 s/product**, but the full run did not hold that rate — it decayed to tens
of seconds and then ~150 s/product, and it also died twice partway (once when
Docker Desktop stopped as the machine slept, leaving 585/5000 products with no
way to continue). Two separate problems: the decay, and no way to resume.

### Why it slowed down — Action Scheduler queue pile-up

Not the bind mount. A bare `wp option get` mid-run took **40 s**; the cause was
**~38,000 pending Action Scheduler actions**, all
`woocommerce_run_product_attribute_lookup_update_callback` — WooCommerce
schedules one per variation to refresh the product-attributes lookup table, and
nothing drains that queue during a CLI run (no web requests → no AS runner). As
`wp_actionscheduler_actions` grew, every WP-CLI bootstrap (and so every
`WC_Product::save()`) paid a growing scan cost. Cancelling the queue restored
~6 s/product.

The bind-mount theory (~35k–50k small image-file writes crossing wp-env's
`/var/www/html` mount) was investigated and dropped: the mount *is* ~5× slower
for small files in isolation (800 files: 11.8 s vs 2.4 s on the container
overlay), but the `.wp-env.json` change meant to act on it — repointing the
`wp-content/uploads` mapping — was ineffective (wp-env bind-mounts the entire
`/var/www/html` regardless) and reverted, and the real regression was the AS
queue, not file I/O.

There is in any case no way to put `wp-content/uploads` on a fast volume within
wp-env's supported config (a Docker volume / tmpfs is not expressible in
`.wp-env.json`; `upload_path` / `upload_url_path` breaks image URLs and the
Lighthouse image audits), so "make it faster" was never a strong lever — hence
the response is to make the run **resumable** and cheap to restart.

### Why the lookup table is not just noise

`wc_product_attributes_lookup` is the index WooCommerce (and the **Phase 3
Ledger filter engine**) query to answer "which products match these attribute /
price / stock filters" without scanning `postmeta`. It is the single most
important property of the seed for what this project measures — filter
performance against 5,000 products only means something if that table is
complete and correct. So the seed must not leave it half-built, and the
snapshot export (task 5.3) both regenerates it in one clean pass and
**asserts** its row count is consistent with the product and variation counts
before dumping — a silent partial regeneration would otherwise stay invisible
until Phase 3 profiling, where we would be tuning the engine against a
half-indexed store.

### Why re-running from zero wasn't an option

`generate` seeded the whole catalogue from a single `mt_srand( 424242 )` stream,
so product *N*'s data depended on every random draw made for products `0..N-1`.
A crash meant starting over, and a plain re-run also fataled on the first
duplicate SKU (`WC_Product::set_sku()` throws on a SKU already in use).

## Decision

Make `generate` **resumable** so an interrupted run is continued, not restarted.
Do not try to make it faster — accept ~5 h and run it unattended.

- **Per-product RNG.** `mt_srand( 424242 + $i )` at the top of each iteration.
  Every product is now a pure function of its index — identical distributions to
  before (same `mt_rand` ranges), different concrete values. Nothing has
  snapshotted the old output, so byte-for-byte parity has no value to keep.
- **Skip finished products.** Without `--fresh`, an iteration whose SKU
  (`LDG-%05d`) already exists and is complete is skipped — "complete" = featured
  image present when `--images`, and ≥ 12 children when variable. A half-written
  row (crash mid-product) fails that check and is deleted and rebuilt.
- **`--fresh`** still wipes and rebuilds from scratch.

Recovery procedure after a Docker/machine interruption: `docker start` the
containers (the DB volume and the host-backed WordPress tree both persist),
re-run the **same** `generate` command, and it fills in only the missing tail.

## Alternatives considered

- **Move `wp-content/uploads` off the bind mount** (volume / tmpfs / mapping /
  `upload_path`). Tried (`.wp-env.json` mapping change) and reverted — not
  possible within wp-env, and it rested on the unproven bind-mount diagnosis
  above. See Context.
- **Generate without `--images`, add images in a second pass.** Rejected — the
  task needs image handling measured at full scale (global.md 5.2), and a second
  pass writes the same ~50k files anyway.
- **Fewer products (e.g. 2,000).** Rejected — 5,000 is the spec; filter
  performance against a smaller set means less.
- **Seed on a native-Docker (Linux) host or in CI, import the snapshot here.**
  Viable and faster; kept as the option if the local run proves impractical.
  Task 5.3 produces the portable snapshot that makes this a one-time cost.

## Consequences

### Positive

- A run survives any number of Docker/machine restarts; the operator relaunches
  the same command and walks away.
- Quick partial runs (`--count=50`) during development are trivially topped up to
  the full set later without `--fresh`.

### Negative / accepted costs

- The full run is still ~5 hours of wall-clock on this host. Unchanged.
- The old single-stream `mt_srand( 424242 )` catalogue is not reproducible. No
  consumer of it exists.
- `is_complete()` treats "SKU exists + image + ≥12 children" as done; it does not
  re-validate prices, reviews, or category assignment on a resumed row. A row
  that saved its parent and image but crashed before reviews keeps 0 reviews.
  Acceptable for benchmark data; `--fresh` is the clean rebuild.

## Notes

- `tools/seed` is outside the `phpcs` / `phpstan` scope (`plugins` + `themes`
  only), so this change is not covered by the static gate; it was
  `php -l`-checked and exercised by the 5.2 run.
- **Mitigations added to `generate()`** for the Action Scheduler pile-up (see
  Context): `wp_defer_term_counting()` + `wp_defer_comment_counting()` around the
  loop, and `as_unschedule_all_actions( 'woocommerce_run_product_attribute_lookup_update_callback' )`
  every 200 products. After the run, rebuild the lookup table in one pass with
  `wp wc tool run regenerate_product_attributes_lookup_table` (the success
  message says so).
- **`export-snapshot.mjs` (task 5.3) also cleans up** before the dump: it
  regenerates the attribute-lookup table, drains the remaining queue,
  **asserts** the `wc_product_attributes_lookup` row count is consistent with
  the product / variation counts (aborts the export loudly if not — see "Why the
  lookup table is not just noise"), then `TRUNCATE`s
  `wp_actionscheduler_actions` / `_logs` / `_claims`. The committed snapshot
  must restore a healthy store, not one mid-bulk-import — otherwise every CI
  restore re-inherits this degradation.
- The residual ~6 s/product (vs the 2.4 s calibration) is unexplained and not
  worth chasing — probably `postmeta` growth plus MySQL on the bind-mounted
  volume. Viable unattended; re-evaluate only if seeding moves to a
  native-filesystem Docker host or CI.
