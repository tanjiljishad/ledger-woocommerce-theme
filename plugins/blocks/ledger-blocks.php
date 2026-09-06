<?php
/**
 * Plugin Name:       Ledger Blocks
 * Description:        Editor blocks for the Ledger suite. Requires Ledger Core.
 * Version:           0.1.0
 * Requires at least: 6.6
 * Requires PHP:      8.2
 * Requires Plugins:  ledger-core
 * Author:            Tanjil
 * Text Domain:       ledger
 * License:           Proprietary
 *
 * @package Ledger\Blocks
 *
 * Ledger — Proprietary Commercial License. Copyright (c) 2026 Tanjil.
 * All rights reserved. See LICENSE at the repository root.
 */

declare( strict_types=1 );

namespace Ledger\Blocks;

defined( 'ABSPATH' ) || exit;

const VERSION     = '0.1.0';
const PLUGIN_FILE = __FILE__;

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
						esc_html__( 'Ledger Blocks', 'ledger' ),
						esc_html__( 'is inactive: it requires Ledger Core to be installed and active.', 'ledger' )
					);
				}
			);
			return;
		}

		$requirements = new \Ledger\Core\Requirements(
			'Ledger Blocks',
			array(
				'php'     => '8.2',
				'wp'      => '6.6',
				'plugins' => array( 'ledger-core/ledger-core.php' => 'Ledger Core' ),
			)
		);

		if ( ! $requirements->met() ) {
			$requirements->render_notice();
			return;
		}

		Plugin::instance()->boot();
	},
	10
);

register_activation_hook( __FILE__, static fn () => flush_rewrite_rules() );
register_deactivation_hook( __FILE__, static fn () => flush_rewrite_rules() );
