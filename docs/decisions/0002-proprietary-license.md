# ADR 0002 — Proprietary license, MIT-incompatible header

- **Status:** accepted
- **Date:** 2026-09-06
- **Deciders:** Tanjil

## Context

Roadmap task 1.1 requires an "MIT-incompatible license header decided" before
any code is written, so every source file carries consistent terms from commit
one. The suite is a commercial product sold on its performance guarantees; the
plan is a ten-month build toward a paid release.

WordPress themes and plugins distributed on wordpress.org must be GPL. We do not
intend to distribute there for the commercial suite; distribution is direct and
licensed. PHP that calls the WordPress API can still be proprietary when
distributed outside the .org repository — the GPL's reach over such code is
contested and we do not rely on being inside it.

## Decision

First-party code in this repository is under a proprietary commercial license
(`/LICENSE`). Every PHP file carries the short header:

```
Ledger — Proprietary Commercial License. Copyright (c) 2026 Tanjil.
All rights reserved. See LICENSE at the repository root.
```

JS/TS packages set `"license": "SEE LICENSE IN <path>"`. The license text is
written to be explicitly incompatible with MIT and GPL: no permissive grant, no
copyleft, use only by separate signed agreement.

## Consequences

### Positive

- No ambiguity for contractors or a future acquirer about ownership and terms.
- We are free to offer paid tiers, per-seat terms, and source escrow without a
  license conflict.

### Negative / accepted costs

- We cannot publish the commercial suite on wordpress.org.
- Third-party GPL code cannot be copied into the tree; it is consumed as a
  dependency (WooCommerce, `@wordpress/*`) and its licenses are respected.
- If we later want a free .org "lite" edition it must be a separate,
  independently-licensed codebase or a clean GPL carve-out.

## Alternatives considered

- **GPLv2+ (the WordPress default):** rejected for the commercial suite — makes
  redistribution terms unenforceable and undercuts paid licensing.
- **Dual license (GPL + commercial):** deferred — viable later for a lite
  edition, unnecessary complexity now.

## Notes

Have a lawyer review `/LICENSE` before the first external sale.
