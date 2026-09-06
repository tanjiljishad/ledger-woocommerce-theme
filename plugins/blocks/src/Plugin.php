<?php
/**
 * Blocks plugin bootstrap.
 *
 * @package Ledger\Blocks
 */

declare( strict_types=1 );

namespace Ledger\Blocks;

defined( 'ABSPATH' ) || exit;

use Ledger\Core\Container;

/**
 * Registers block types from the build directory. No blocks ship in Phase 0 —
 * this only proves the wiring, the container hand-off, and asset paths.
 */
final class Plugin {

	private static ?self $instance = null;

	private bool $booted = false;

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		/** @var Container $container */
		$container = apply_filters( 'ledger/container', null );
		unset( $container ); // Reserved for when blocks register services.

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
