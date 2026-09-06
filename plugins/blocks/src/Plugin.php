<?php
/**
 * Blocks plugin bootstrap.
 *
 * @package Ledger\Blocks
 */

declare( strict_types=1 );

namespace Ledger\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * Registers block types from the build directory. No blocks ship in Phase 0 —
 * this only proves the wiring, the container hand-off, and asset paths.
 */
final class Plugin {

	/**
	 * Sole instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Whether boot() has already run.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Retrieve the singleton instance.
	 */
	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * Wire the plugin's hooks. Safe to call more than once.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		/*
		 * Core shares its container through the `ledger_container` filter.
		 * Nothing consumes it yet — this call only proves the hand-off is
		 * reachable from the Blocks plugin.
		 */
		$container = apply_filters( 'ledger_container', null );
		unset( $container );

		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Register every block found in build/*, each expected to carry a block.json.
	 */
	public function register_blocks(): void {
		$build = __DIR__ . '/../build';

		if ( ! is_dir( $build ) ) {
			return;
		}

		foreach ( (array) glob( $build . '/*', GLOB_ONLYDIR ) as $dir ) {
			if ( is_string( $dir ) && is_file( $dir . '/block.json' ) ) {
				register_block_type( $dir );
			}
		}
	}
}
