<?php
/**
 * Ledger Flagship — theme setup.
 *
 * Does nothing but enqueue and register. No business logic, no shortcodes,
 * no template hacks. Everything else is theme.json and block templates.
 *
 * @package Ledger\Theme
 *
 * Ledger — Proprietary Commercial License. Copyright (c) 2026 Tanjil.
 * All rights reserved. See LICENSE at the repository root.
 */

declare( strict_types=1 );

namespace Ledger\Theme;

defined( 'ABSPATH' ) || exit;

const VERSION = '0.1.0';

/**
 * Enqueue the design-token custom properties and the compiled theme stylesheet.
 * Tokens load first so the theme sheet can reference `--ledger-*`.
 */
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		$dir = get_template_directory();
		$uri = get_template_directory_uri();

		$tokens = $dir . '/assets/tokens.css';
		if ( is_readable( $tokens ) ) {
			wp_enqueue_style(
				'ledger-tokens',
				$uri . '/assets/tokens.css',
				array(),
				(string) filemtime( $tokens )
			);
		}

		$style = $dir . '/build/style.css';
		if ( is_readable( $style ) ) {
			wp_enqueue_style(
				'ledger-flagship',
				$uri . '/build/style.css',
				array( 'ledger-tokens' ),
				(string) filemtime( $style )
			);
		}
	}
);

/**
 * Register the block pattern category the templates reference.
 */
add_action(
	'init',
	static function (): void {
		register_block_pattern_category(
			'ledger',
			array( 'label' => __( 'Ledger', 'ledger' ) )
		);
	}
);
