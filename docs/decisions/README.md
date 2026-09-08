# Architecture decision records

Every architectural decision gets a numbered file here, in the format of
[`0000-template.md`](0000-template.md). This is how the project stays resumable
if the author steps away (roadmap 1.1).

Rules:

- One decision per file. Number sequentially, zero-padded to four digits.
- Never delete or rewrite an accepted ADR. To change a decision, write a new
  ADR that supersedes it and update the old one's status to
  `superseded by ADR-XXXX`.
- Reference ADRs from code comments and other docs by number.

## Index

| # | Title | Status |
| --- | --- | --- |
| [0001](0001-gutenberg-not-a-page-builder.md) | Build on Gutenberg, not a page builder | accepted |
| [0002](0002-proprietary-license.md) | Proprietary license, MIT-incompatible header | accepted |
| [0003](0003-monorepo-pnpm-composer.md) | Single monorepo: pnpm workspace + root Composer | accepted (hoisting superseded by 0009) |
| [0004](0004-phpstan-level-8.md) | PHPStan level 8 from the first commit | accepted |
| [0005](0005-token-compiler-single-source.md) | `tokens.json` is the single source; artefacts generated | accepted (responsive contract superseded by 0013) |
| [0006](0006-performance-budget-is-a-hard-gate.md) | Performance budget is a hard gate, never relaxed | accepted |
| [0007](0007-stock-prettier-not-wp-prettier.md) | Format with stock Prettier, not wp-prettier | accepted |
| [0008](0008-hook-names-underscore-not-slash.md) | Hook names use underscores, not slashes | accepted |
| [0009](0009-drop-hoist-false-from-npmrc.md) | Remove `hoist=false` from `.npmrc` | accepted |
| [0010](0010-phpstan-authoritative-on-types.md) | PHPStan is authoritative on types; drop the PHPCS type-hint sniff | accepted |
| [0011](0011-rtlcss-webpack-plugin-phantom-dep.md) | `rtlcss-webpack-plugin` phantom `@babel/runtime` dependency | accepted |
| [0012](0012-retarget-wordpress-7.1.md) | Retarget to WordPress 7.1 | accepted |
| [0013](0013-viewport-tokens-supersede-responsive-contract.md) | `viewport` tokens + `settings.viewport`; retire the custom responsive contract | accepted |
| [0014](0014-deprecated-api-audit-2026-09.md) | Deprecated-API audit against WordPress 7.1 | accepted |
| [0015](0015-resumable-seed-generate.md) | Resumable `seed generate` | accepted |
