<?php
/**
 * Uninstall cleanup for Ledger Commerce.
 *
 * Removes only Ledger's own options. WooCommerce data, products, and orders
 * are never touched here.
 *
 * @package Ledger\Commerce
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'ledger_commerce_settings' );
delete_site_option( 'ledger_commerce_settings' );

wp_cache_flush();
