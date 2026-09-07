<?php
/**
 * Plugin Name:       Ledger Core
 * Plugin URI:        https://example.com/ledger
 * Description:        Shared service container and infrastructure for the Ledger suite.
 * Version:           0.1.0
 * Requires at least: 6.6
 * Requires PHP:      8.2
 * Author:            Tanjil
 * Text Domain:       ledger
 * License:           Proprietary
 * License URI:       https://example.com/ledger/license
 *
 * @package Ledger\Core
 *
 * Ledger — Proprietary Commercial License. Copyright (c) 2026 Tanjil.
 * All rights reserved. See LICENSE at the repository root.
 */

declare( strict_types=1 );

namespace Ledger\Core;

defined( 'ABSPATH' ) || exit;

const VERSION     = '0.1.0';
const PLUGIN_FILE = __FILE__;

/** Option keys this plugin persists. Declared to Persisted_State on activation and version change. */
const PERSISTED_OPTIONS = array( 'ledger_core_activated_at', 'ledger_core_settings' );

require_once __DIR__ . '/src/Container.php';
require_once __DIR__ . '/src/Persisted_State.php';
require_once __DIR__ . '/src/Requirements.php';
require_once __DIR__ . '/src/Plugin.php';

/**
 * Boot the plugin once all plugins are loaded, but only if requirements pass.
 * A failed check degrades to an admin notice — never a fatal.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		$requirements = new Requirements(
			'Ledger Core',
			array(
				'php' => '8.2',
				'wp'  => '6.6',
			)
		);

		if ( ! $requirements->met() ) {
			$requirements->render_notice();
			return;
		}

		Plugin::instance()->boot();

		/*
		 * Keep the persisted-key registry current when the plugin version
		 * changes, so a release that adds keys needs no manual re-activation.
		 * Admin-only and version-gated (`ledger_core_version` is autoloaded),
		 * so a normal request never touches `ledger_persisted_state`.
		 */
		add_action(
			'admin_init',
			static function (): void {
				if ( VERSION === get_option( 'ledger_core_version' ) ) {
					return;
				}
				Persisted_State::register( PERSISTED_OPTIONS );
				update_option( 'ledger_core_version', VERSION, true );
			}
		);
	},
	5
);

register_activation_hook(
	__FILE__,
	static function (): void {
		require_once __DIR__ . '/src/Requirements.php';
		require_once __DIR__ . '/src/Persisted_State.php';
		$requirements = new Requirements(
			'Ledger Core',
			array(
				'php' => '8.2',
				'wp'  => '6.6',
			)
		);
		$requirements->halt_activation_if_unmet();
		Persisted_State::register( PERSISTED_OPTIONS );
		update_option( 'ledger_core_activated_at', time(), false );
		update_option( 'ledger_core_version', VERSION, true );
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		flush_rewrite_rules();
	}
);
