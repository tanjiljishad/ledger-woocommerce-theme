<?php
/**
 * Commerce plugin bootstrap.
 *
 * @package Ledger\Commerce
 */

declare( strict_types=1 );

namespace Ledger\Commerce;

defined( 'ABSPATH' ) || exit;

use Ledger\Core\Container;

/**
 * Holds WooCommerce-facing services. Phase 0 registers nothing functional;
 * it only confirms the plugin boots after Core and WooCommerce and can reach
 * the shared container.
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

		// Core shares its container through this filter; null when Core is inactive.
		$container = apply_filters( 'ledger_container', null );

		if ( $container instanceof Container ) {
			$container->set( 'commerce.version', static fn (): string => VERSION );
		}

		/**
		 * Fires once Ledger Commerce has booted with WooCommerce available.
		 */
		do_action( 'ledger_commerce_booted' );
	}
}
