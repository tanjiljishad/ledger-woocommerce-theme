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
 * writes. Keys accumulate in a single non-autoloaded option,
 * `ledger_persisted_state`, so uninstall can remove exactly what the suite
 * created without a `LIKE 'ledger_%'` scan of the options table.
 *
 * `ledger_persisted_state` is touched only on activation, on a version-gated
 * `admin_init` upgrade check, and on uninstall — never on a normal request.
 * `read()` asserts that with `_doing_it_wrong()` if it is ever called outside
 * one of those contexts.
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
	 * Add keys to the registry. Idempotent; writes only when the stored set
	 * actually changes. Call from an activation hook or a version-gated
	 * `admin_init` upgrade check — never from a normal request path.
	 *
	 * @param string[] $options    Option keys the caller persists.
	 * @param string[] $transients Transient keys the caller persists.
	 */
	public static function register( array $options = array(), array $transients = array() ): void {
		$current = self::read();

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
	 * Read and normalise the registry. Always returns a well-formed shape even
	 * if the option is missing or has been corrupted by hand.
	 *
	 * Asserts it is not being called on a normal request: the registry is for
	 * activation, the `admin_init` upgrade check, and uninstall only.
	 *
	 * @return array{options: list<string>, transients: list<string>}
	 */
	private static function read(): array {
		if ( ! self::write_context() ) {
			_doing_it_wrong(
				__METHOD__,
				'ledger_persisted_state is read on activation, upgrade, and uninstall only, never on a normal request.',
				'0.1.0'
			);
		}

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
		$keys = self::read();

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
	 * Whether the current request is one where writing persisted state is
	 * expected: uninstall, WP-CLI, an install/activation pass, or any
	 * admin-side request (where the activation hook and `admin_init` upgrade
	 * check run). A plain front-end request is none of these.
	 *
	 * @return bool
	 */
	private static function write_context(): bool {
		if ( defined( 'WP_UNINSTALL_PLUGIN' ) || defined( 'WP_CLI' ) ) {
			return true;
		}

		return wp_installing() || is_admin();
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
