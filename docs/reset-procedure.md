# Local reset procedure

**Roadmap task 5.4.** One command wipes local, restores the snapshot, and
leaves a working shop. You will run this hundreds of times.

```bash
pnpm run reset
```

That runs `tools/seed/reset.mjs`:

1. `pnpm wp-env start` — ensure the container is up.
2. `tools/seed/restore-snapshot.mjs`:
   - copies `tools/seed/snapshot/shop.sql` into the mapped uploads dir,
   - `wp db reset --yes` then `wp db import`,
   - untars `tools/seed/snapshot/uploads.tar.gz` into `wp-content/uploads`,
   - `wp rewrite flush`, regenerate thumbnails.
3. `wp cache flush` + `wp transient delete --all`.

Target: **under 30 seconds** once the snapshot exists.

## First-time setup (creating the snapshot)

```bash
pnpm wp-env start
pnpm run seed:generate            # wp ledger seed generate --count=5000
pnpm wp-env run cli wp ledger seed generate --count=5000 --images   # optional, slow
pnpm run seed:export             # writes tools/seed/snapshot/{shop.sql,uploads.tar.gz}
```

Commit `shop.sql` via **Git LFS** (or rely on CI cache — see
`.github/workflows/ci.yml`). Regenerating on every CI run is far too slow.

## Quick partial reset (no snapshot)

For a fast local iteration without a full restore:

```bash
pnpm wp-env run cli wp ledger seed generate --count=200 --fresh
```

## When the snapshot schema goes stale

If a WordPress/WooCommerce major bump changes the schema, re-run first-time
setup to regenerate the snapshot, then commit the new `shop.sql`.
