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
 * the `ledger_container` filter, so blocks and commerce never `new` it.
 */
final class Plugin {

	/**
	 * Sole instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * The shared service container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Whether boot() has already run.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Build the plugin with a fresh container.
	 */
	private function __construct() {
		$this->container = new Container();
	}

	/**
	 * Retrieve the singleton instance.
	 */
	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * The shared service container.
	 */
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
		add_filter( 'ledger_container', fn (): Container => $this->container, 0 );

		load_plugin_textdomain( 'ledger', false, dirname( plugin_basename( PLUGIN_FILE ) ) . '/languages' );

		/**
		 * Fires once Ledger Core is ready and its container is resolvable.
		 *
		 * @param Container $container The shared service container.
		 */
		do_action( 'ledger_core_booted', $this->container );
	}
}
