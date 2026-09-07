<?php
/**
 * Plugin Name:       Ledger Commerce
 * Description:        WooCommerce integration layer for the Ledger suite. Requires Ledger Core and WooCommerce.
 * Version:           0.1.0
 * Requires at least: 7.1
 * Requires PHP:      8.2
 * Requires Plugins:  ledger-core, woocommerce
 * Author:            Tanjil
 * Text Domain:       ledger
 * License:           Proprietary
 *
 * @package Ledger\Commerce
 *
 * Ledger — Proprietary Commercial License. Copyright (c) 2026 Tanjil.
 * All rights reserved. See LICENSE at the repository root.
 */

declare( strict_types=1 );

namespace Ledger\Commerce;

defined( 'ABSPATH' ) || exit;

const VERSION     = '0.1.0';
const PLUGIN_FILE = __FILE__;

/** Option keys this plugin persists. Declared to Persisted_State on activation and version change. */
const PERSISTED_OPTIONS = array( 'ledger_commerce_settings' );

require_once __DIR__ . '/src/Plugin.php';

add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! class_exists( \Ledger\Core\Requirements::class ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					printf(
						'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
						esc_html__( 'Ledger Commerce', 'ledger' ),
						esc_html__( 'is inactive: it requires Ledger Core to be installed and active.', 'ledger' )
					);
				}
			);
			return;
		}

		$requirements = new \Ledger\Core\Requirements(
			'Ledger Commerce',
			array(
				'php'     => '8.2',
				'wp'      => '7.1',
				'plugins' => array(
					'ledger-core/ledger-core.php' => 'Ledger Core',
					'woocommerce/woocommerce.php' => 'WooCommerce',
				),
			)
		);

		if ( ! $requirements->met() ) {
			$requirements->render_notice();
			return;
		}

		// Declare HPOS / custom order tables compatibility before WooCommerce inits.
		add_action(
			'before_woocommerce_init',
			static function (): void {
				if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
						'custom_order_tables',
						__FILE__,
						true
					);
				}
			}
		);

		Plugin::instance()->boot();

		// Sync persisted keys on version change. Admin-only, version-gated.
		add_action(
			'admin_init',
			static function (): void {
				if ( VERSION === get_option( 'ledger_commerce_version' ) ) {
					return;
				}
				do_action( 'ledger_register_persisted_state', array( 'options' => PERSISTED_OPTIONS ) );
				update_option( 'ledger_commerce_version', VERSION, true );
			}
		);
	},
	20
);

register_activation_hook(
	__FILE__,
	static function (): void {
		do_action( 'ledger_register_persisted_state', array( 'options' => PERSISTED_OPTIONS ) );
		update_option( 'ledger_commerce_version', VERSION, true );
		flush_rewrite_rules();
	}
);
register_deactivation_hook( __FILE__, static fn () => flush_rewrite_rules() );
