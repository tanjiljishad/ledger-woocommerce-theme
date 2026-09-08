# ADR 0015 — Resumable `seed generate`

- **Status:** accepted
- **Date:** 2026-09-08
- **Deciders:** Tanjil

## Context

Task 5.2 runs `wp ledger seed generate --count=5000 --images` against wp-env.
On this host (Windows 11 + Docker Desktop) the run measured **~2.4 s/product** on
a 50-product calibration and drifted toward **~3.5 s/product** on longer runs —
i.e. **~4–5 hours** for the full 5,000 — and it died twice partway, once when
Docker Desktop stopped as the machine slept, leaving 585/5000 products with no
way to continue.

### Why it is slow — cause not established

The first diagnosis was that the bottleneck is the ~35,000–50,000 small
image-file writes crossing the wp-env bind mount: every `--images` product
writes ~9–11 files (the source JPEG plus every registered sub-size — WordPress
`thumbnail` / `medium` / `medium_large` / `large` / `1536x1536` / `2048x2048`,
and WooCommerce's `woocommerce_thumbnail` / `woocommerce_single` /
`woocommerce_gallery_thumbnail`), and an isolated test does show the mount is
~5× slower for small files (800 files: 11.8 s bind-mounted vs 2.4 s on the
container overlay, ~15 ms vs ~3 ms each).

**That diagnosis is wrong as stated — or at least unproven — and is recorded
here as unsettled, not as the cause.** The `.wp-env.json` change meant to act on
it (repointing the `wp-content/uploads` mapping to move uploads "off the bind
mount") was reverted as ineffective: wp-env bind-mounts the *entire*
`/var/www/html` from the host, so uploads sits on a bind mount wherever the
mapping points, and there was never a clean with/against measurement of the seed
rate itself. The per-product cost also includes GD rendering, WordPress
sub-size generation, `WC_Product::save()` (dozens of meta rows per product),
variation and review inserts, and periodic object-cache clears — any of which
could dominate. **The real driver of the ~2.4 s/product rate is currently
unknown.** Not investigated here — the run is in flight; see Notes.

What *is* settled: there is no way to put `wp-content/uploads` on a fast volume
within wp-env's supported config (a Docker volume / tmpfs is not expressible in
`.wp-env.json`; `upload_path` / `upload_url_path` breaks image URLs and the
Lighthouse image audits). So whatever the cause, "make generation faster" is not
a lever available here — the response is to make the run **resumable** instead.

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
- **Open question:** what actually makes generation ~2.4 s/product? Profile a
  short `--images` run (e.g. Xdebug / `microtime` around GD render, sub-size
  generation, `WC_Product::save()`, the file writes) once 5.2 is no longer in
  flight. Until then the number stands unexplained and "it's the bind mount" is
  not the answer of record.
- Re-evaluate the throughput question if the project moves local/CI seeding to a
  native-filesystem Docker host, or wp-env gains a faster sharing backend.
