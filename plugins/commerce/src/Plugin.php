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

		/** @var Container|null $container */
		$container = apply_filters( 'ledger/container', null );

		if ( $container instanceof Container ) {
			$container->set( 'commerce.version', static fn (): string => VERSION );
		}

		/**
		 * Fires once Ledger Commerce has booted with WooCommerce available.
		 */
		do_action( 'ledger/commerce/booted' );
	}
}
