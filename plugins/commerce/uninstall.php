<?php
/**
 * Uninstall cleanup for Ledger Commerce.
 *
 * Keys here are also declared to `Ledger\Core\Persisted_State` on activation and
 * on version change. Uninstall runs with no other plugin loaded, so this file
 * repeats its own keys and deletes only those. WooCommerce data, products, and
 * orders are never touched here.
 *
 * @package Ledger\Commerce
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'ledger_commerce_settings' );
delete_site_option( 'ledger_commerce_settings' );

wp_cache_flush();
