<?php
/**
 * Uninstall cleanup for Ledger Core.
 *
 * Runs only when the plugin is deleted from the admin. Removes every option,
 * transient, and user-meta key the plugin created. Leaves WooCommerce and
 * user content untouched.
 *
 * @package Ledger\Core
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$ledger_options = array(
	'ledger_core_activated_at',
	'ledger_core_settings',
);

foreach ( $ledger_options as $ledger_option ) {
	delete_option( $ledger_option );
	delete_site_option( $ledger_option );
}

// Transients created under the `ledger_` namespace. A direct query is the only
// way to bulk-delete by name prefix; object-cache helpers do not apply during
// uninstall.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '\_transient\_ledger\_%'
	    OR option_name LIKE '\_transient\_timeout\_ledger\_%'"
);

if ( is_multisite() ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		"DELETE FROM {$wpdb->sitemeta}
		 WHERE meta_key LIKE '\_site\_transient\_ledger\_%'
		    OR meta_key LIKE '\_site\_transient\_timeout\_ledger\_%'"
	);
}

wp_cache_flush();
