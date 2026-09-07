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
 * Blocks and Commerce declare their keys through the `ledger_register_persisted_state`
 * action (see `register_from_hook()`), so they carry no compile-time dependency
 * on this class. Core wires the listener in `ledger-core.php`.
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
	 * Input is treated as untrusted: `merge()` keeps only non-empty strings.
	 *
	 * @param array<mixed> $options    Option keys the caller persists.
	 * @param array<mixed> $transients Transient keys the caller persists.
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
	 * Handler for the `ledger_register_persisted_state` action. Lets a plugin
	 * declare its keys without referencing this class directly.
	 *
	 * @param mixed $keys Expected shape: array{options?: mixed[], transients?: mixed[]}.
	 */
	public static function register_from_hook( mixed $keys ): void {
		if ( ! is_array( $keys ) ) {
			return;
		}

		$options    = isset( $keys['options'] ) && is_array( $keys['options'] ) ? $keys['options'] : array();
		$transients = isset( $keys['transients'] ) && is_array( $keys['transients'] ) ? $keys['transients'] : array();

		self::register( $options, $transients );
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
	 * Whether the current request is one where touching persisted state is
	 * expected: uninstall, WP-CLI, a core install/upgrade pass, the
	 * `admin_init` version check, or a plugin activation. Deliberately *not*
	 * `is_admin()` — that is also true for `admin-ajax.php`, a hot path on a
	 * WooCommerce store, and every other admin screen.
	 *
	 * @return bool
	 */
	private static function write_context(): bool {
		if ( defined( 'WP_UNINSTALL_PLUGIN' ) || defined( 'WP_CLI' ) ) {
			return true;
		}

		if ( wp_installing() ) {
			return true;
		}

		// `activate_plugin` fires before any `activate_{$plugin}` callback, so
		// this is true for the whole activation request, including keys routed
		// through the `ledger_register_persisted_state` action.
		return doing_action( 'admin_init' ) || did_action( 'activate_plugin' ) > 0;
	}

	/**
	 * Combine two key lists into one: non-empty strings only, de-duplicated,
	 * and sorted so the equality check in `register()` is order-independent.
	 * Both inputs are treated as untrusted.
	 *
	 * @param array<mixed> $a First key list.
	 * @param array<mixed> $b Second key list.
	 * @return list<string>
	 */
	private static function merge( array $a, array $b ): array {
		$unique = array();

		foreach ( array_merge( array_values( $a ), array_values( $b ) ) as $key ) {
			if ( is_string( $key ) && '' !== $key ) {
				$unique[ $key ] = $key;
			}
		}

		$unique = array_values( $unique );
		sort( $unique );

		return $unique;
	}
}
