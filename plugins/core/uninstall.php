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

$options = array(
	'ledger_core_activated_at',
	'ledger_core_settings',
);

foreach ( $options as $option ) {
	delete_option( $option );
	delete_site_option( $option );
}

// Transients created under the `ledger_` namespace.
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '\_transient\_ledger\_%'
	    OR option_name LIKE '\_transient\_timeout\_ledger\_%'"
);

if ( is_multisite() ) {
	$wpdb->query(
		"DELETE FROM {$wpdb->sitemeta}
		 WHERE meta_key LIKE '\_site\_transient\_ledger\_%'
		    OR meta_key LIKE '\_site\_transient\_timeout\_ledger\_%'"
	);
}

wp_cache_flush();
