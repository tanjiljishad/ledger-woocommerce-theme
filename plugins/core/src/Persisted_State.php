<?php
/**
 * Suite-wide registry of persisted option and transient keys.
 *
 * @package Ledger\Core
 */

declare( strict_types=1 );

namespace Ledger\Core;

defined( 'ABSPATH' ) || exit;

/**
 * The one place every Ledger plugin declares the option and transient keys it
 * writes. Each plugin calls `register()` from its `boot()` (and, where it
 * persists on activation, from its activation hook). The keys accumulate in a
 * single non-autoloaded option, `ledger_persisted_state`, so uninstall can
 * remove exactly what the suite created without a `LIKE 'ledger_%'` scan of
 * the options table.
 *
 * From Phase 1 on, a plugin that stores anything registers it here or it does
 * not get cleaned up. That is deliberate: unregistered state is a bug.
 */
final class Persisted_State {

	/**
	 * Option name the registry itself is stored under.
	 */
	public const OPTION = 'ledger_persisted_state';

	/**
	 * Add keys to the registry. Idempotent, and writes only when the stored
	 * set actually changes, so steady-state requests cost one `get_option()`.
	 *
	 * @param string[] $options    Option keys the caller persists.
	 * @param string[] $transients Transient keys the caller persists.
	 */
	public static function register( array $options = array(), array $transients = array() ): void {
		$current = self::keys();

		$next_options    = self::merge( $current['options'], $options );
		$next_transients = self::merge( $current['transients'], $transients );

		if ( $next_options === $current['options'] && $next_transients === $current['transients'] ) {
			return;
		}

		update_option(
			self::OPTION,
			array(
				'options'    => $next_options,
				'transients' => $next_transients,
			),
			false
		);
	}

	/**
	 * The registered keys, always well-formed even if the option is missing or
	 * has been corrupted by hand.
	 *
	 * @return array{options: list<string>, transients: list<string>}
	 */
	public static function keys(): array {
		$raw = get_option( self::OPTION );

		$options    = array();
		$transients = array();

		if ( is_array( $raw ) ) {
			if ( isset( $raw['options'] ) && is_array( $raw['options'] ) ) {
				$options = array_values( array_filter( $raw['options'], 'is_string' ) );
			}
			if ( isset( $raw['transients'] ) && is_array( $raw['transients'] ) ) {
				$transients = array_values( array_filter( $raw['transients'], 'is_string' ) );
			}
		}

		return array(
			'options'    => $options,
			'transients' => $transients,
		);
	}

	/**
	 * Delete every registered key — site and single-site variants — then the
	 * registry option itself. Called from `Ledger Core`'s `uninstall.php`.
	 */
	public static function purge(): void {
		$keys = self::keys();

		foreach ( $keys['options'] as $key ) {
			delete_option( $key );
			delete_site_option( $key );
		}

		foreach ( $keys['transients'] as $key ) {
			delete_transient( $key );
			delete_site_transient( $key );
		}

		delete_option( self::OPTION );
		delete_site_option( self::OPTION );
	}

	/**
	 * Union a validated list with raw additions, keeping the result sorted and
	 * duplicate-free so the change check in `register()` is order-independent.
	 * Non-string and empty additions are dropped rather than trusted.
	 *
	 * @param string[]     $existing  Keys already stored and validated.
	 * @param array<mixed> $additions Raw keys to validate and add.
	 * @return list<string>
	 */
	private static function merge( array $existing, array $additions ): array {
		foreach ( $additions as $key ) {
			if ( is_string( $key ) && '' !== $key && ! in_array( $key, $existing, true ) ) {
				$existing[] = $key;
			}
		}

		sort( $existing );

		return $existing;
	}
}
