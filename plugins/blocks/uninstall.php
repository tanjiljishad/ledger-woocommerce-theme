<?php
/**
 * Uninstall cleanup for Ledger Blocks.
 *
 * @package Ledger\Blocks
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'ledger_blocks_settings' );
delete_site_option( 'ledger_blocks_settings' );

wp_cache_flush();
