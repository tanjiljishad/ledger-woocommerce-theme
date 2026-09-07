# Phase 0 · 4.3 — Graceful degradation acceptance record

**Roadmap task 4.3. v1.0 acceptance criterion / marketplace requirement:** the
flagship theme must render a browsable, checkout-capable shop using only core +
WooCommerce blocks, with every Ledger plugin deactivated.

## Verified against

| | |
| --- | --- |
| Date | 2026-09-07 |
| WordPress | **7.1** |
| WooCommerce | **11.1.0** |
| PHP | **8.2.33** |
| Environment | `wp-env` (Docker), dev instance `http://localhost:8888` |
| Theme | `flagship` (Ledger Flagship 0.1.0) — active |
| Ledger plugins | `ledger-core`, `ledger-blocks`, `ledger-commerce` — **deactivated** |
| Other active | `woocommerce`, `seed` (dev-only) |

Fixtures: 3 simple products (Ledger Mug, Ledger Notebook, Ledger Sticker Pack)
and 1 variable product (Ledger Tee, Size S/M/L). Pretty permalinks; WooCommerce
shop/cart/checkout/my-account pages installed.

## Result — PASS

**Zero fatal errors on any route.** The shop is fully browsable and the
add-to-cart → checkout path is navigable with the Ledger plugins off.

| Route | HTTP | Renders | Navigable | Notes |
| --- | :-: | :-: | :-: | --- |
| `/` (home) | 200 | yes | yes | flagship theme, WooCommerce assets present |
| `/shop/` (archive) | 200 | yes | yes | all 4 products listed, "Add to cart", `wp-block-woocommerce` / `wc-block-*` markup |
| `/product/ledger-mug/` (simple) | 200 | yes | yes | price, "Add to cart" |
| `/product/ledger-tee/` (variable) | 200 | yes | yes | variation selector, "Add to cart" |
| `/?add-to-cart=11`, `=12` | 200 | yes | yes | items added to session cart |
| `/cart/` | 200 | yes | yes | block cart, added items shown |
| `/checkout/` | 200 | yes | yes | block checkout, order review + place-order form |
| `/my-account/` | 200 | yes | yes | login / register form |

Add-to-cart → checkout: products added via `?add-to-cart=`, `/cart/` shows a
populated cart, `/checkout/` renders the block checkout with the line items and
a place-order form. Order completion is not exercised (no payment gateway
configured — out of scope for graceful degradation).

## `debug.log` — 0 fatals, notices reported

`WP_DEBUG` + `WP_DEBUG_LOG` on. Over the full walk:

| Count | Message | Source | Assessment |
| --: | --- | --- | --- |
| **0** | Fatal error / critical error / parse error | — | — |
| ~97 | `PHP Deprecated: Function get_filter_svg_from_preset is deprecated since 6.3.0` | cascade from the theme hook below | **ours — fix before v1.0** |
| ~97 | `PHP Deprecated: Function get_filter_id_from_preset is deprecated since 6.3.0` | same | ours |
| ~13 | `PHP Deprecated: Function wp_global_styles_render_svg_filters is deprecated since 6.3.0` | `themes/flagship/functions.php` | ours |
| ~13 | `PHP Deprecated: Function wp_get_global_styles_svg_filters is deprecated since 6.3.0` | same | ours |
| 2 | `PHP Notice: _load_textdomain_just_in_time … woocommerce domain … triggered too early` | WooCommerce internal, at plugin-activation time | not ours; known WooCommerce behaviour |

### Action item — theme SVG-filter deprecations

`themes/flagship/functions.php` does:

```php
add_action( 'after_setup_theme', static function (): void {
    remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );
    add_action( 'wp_footer', 'wp_global_styles_render_svg_filters' );
} );
```

to move core's global-styles SVG duotone filters out of the render-blocking
path. On WordPress 7.1 `wp_global_styles_render_svg_filters` (and the
`wp_get_global_styles_svg_filters` → `get_filter_svg_from_preset` →
`get_filter_id_from_preset` chain it calls) are all **deprecated since 6.3.0**,
so this emits ~4 `PHP Deprecated` lines per front-end request. Not fatal and the
functions still run, but it is our code leaning on an API core has been
removing for four minors. Revisit the optimisation: on 7.1 the theme most
likely just needs `remove_action( 'wp_body_open',
'wp_global_styles_render_svg_filters' )` (duotone SVG filters are opt-in via
`filter` block support and this theme ships none), or the current no-longer-
needed re-hook dropped entirely. Tracked as a Phase 0 theme cleanup.

## How to reproduce

```sh
pnpm wp-env start
wp-env run cli wp plugin deactivate ledger-core ledger-blocks ledger-commerce
wp-env run cli wp plugin activate woocommerce
wp-env run cli wp theme activate flagship
wp-env run cli wp rewrite structure '/%postname%/' --hard && wp-env run cli wp rewrite flush --hard
# create a few products, then walk /shop/ /product/<slug>/ /cart/ /checkout/ /my-account/
wp-env run cli wp eval 'error_log("check debug.log");'
```
