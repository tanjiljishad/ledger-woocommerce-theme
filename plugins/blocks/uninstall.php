<?php
/**
 * Uninstall cleanup for Ledger Blocks.
 *
 * Keys here are also declared to `Ledger\Core\Persisted_State` at boot. Uninstall
 * runs with no other plugin loaded, so this file repeats its own keys and deletes
 * only those — removing Blocks alone must not touch the rest of the suite.
 *
 * @package Ledger\Blocks
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'ledger_blocks_settings' );
delete_site_option( 'ledger_blocks_settings' );

wp_cache_flush();
