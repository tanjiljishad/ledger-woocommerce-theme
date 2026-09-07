<?php
/**
 * Uninstall cleanup for Ledger Core.
 *
 * Runs only when the plugin is deleted from the admin. Deletes every option and
 * transient any Ledger plugin registered through `Ledger\Core\Persisted_State`,
 * then the registry option itself. WooCommerce and user content are untouched.
 *
 * @package Ledger\Core
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/src/Persisted_State.php';

\Ledger\Core\Persisted_State::purge();

wp_cache_flush();
