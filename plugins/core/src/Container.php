<?php
/**
 * Minimal PSR-11-flavoured service container.
 *
 * @package Ledger\Core
 */

declare( strict_types=1 );

namespace Ledger\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Lazy singleton container. Register factories, resolve on first `get()`.
 *
 * Deliberately tiny — no autowiring, no reflection. If a plugin needs more it
 * can compose its own, but the suite standard is explicit factories.
 */
final class Container {

	/**
	 * Service factories keyed by id.
	 *
	 * @var array<string, callable(Container): mixed>
	 */
	private array $factories = array();

	/**
	 * Resolved instances keyed by id.
	 *
	 * @var array<string, mixed>
	 */
	private array $resolved = array();

	/**
	 * Register a factory for an id. Last registration wins.
	 *
	 * @param string                     $id      Service id.
	 * @param callable(Container): mixed $factory Factory closure.
	 */
	public function set( string $id, callable $factory ): void {
		$this->factories[ $id ] = $factory;
		unset( $this->resolved[ $id ] );
	}

	/**
	 * Whether an id is registered.
	 *
	 * @param string $id Service id.
	 */
	public function has( string $id ): bool {
		return isset( $this->factories[ $id ] );
	}

	/**
	 * Resolve a service, instantiating it once.
	 *
	 * @param string $id Service id.
	 * @return mixed
	 * @throws \OutOfBoundsException When the id is not registered.
	 */
	public function get( string $id ) {
		if ( array_key_exists( $id, $this->resolved ) ) {
			return $this->resolved[ $id ];
		}

		if ( ! isset( $this->factories[ $id ] ) ) {
			throw new \OutOfBoundsException(
				esc_html( sprintf( 'Ledger container: no service registered for "%s".', $id ) )
			);
		}

		$this->resolved[ $id ] = ( $this->factories[ $id ] )( $this );

		return $this->resolved[ $id ];
	}
}
