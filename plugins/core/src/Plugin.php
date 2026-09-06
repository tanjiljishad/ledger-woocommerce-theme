<?php
/**
 * Core plugin bootstrap.
 *
 * @package Ledger\Core
 */

declare( strict_types=1 );

namespace Ledger\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the shared container and exposes it to the rest of the suite through
 * the `ledger/container` filter, so blocks and commerce never `new` it.
 */
final class Plugin {

	private static ?self $instance = null;

	private Container $container;

	private bool $booted = false;

	private function __construct() {
		$this->container = new Container();
	}

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	public function container(): Container {
		return $this->container;
	}

	/**
	 * Register services and wire hooks. Idempotent.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		$this->container->set( 'version', static fn (): string => VERSION );

		/**
		 * Share the container with dependent plugins.
		 *
		 * @param Container $container The shared service container.
		 */
		add_filter( 'ledger/container', fn (): Container => $this->container, 0 );

		load_plugin_textdomain( 'ledger', false, dirname( plugin_basename( PLUGIN_FILE ) ) . '/languages' );

		/**
		 * Fires once Ledger Core is ready and its container is resolvable.
		 *
		 * @param Container $container The shared service container.
		 */
		do_action( 'ledger/core/booted', $this->container );
	}
}
