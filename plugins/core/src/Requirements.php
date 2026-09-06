<?php
/**
 * Environment requirement checks with graceful degradation.
 *
 * @package Ledger\Core
 */

declare( strict_types=1 );

namespace Ledger\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Checks PHP version, WordPress version, and named plugin dependencies.
 * Never fatals at runtime: unmet requirements surface as an admin notice.
 * Activation is a different matter — there we halt with a readable message.
 */
final class Requirements {

	/**
	 * Human-readable label for the plugin being checked.
	 */
	private string $label;

	/**
	 * Constraints: keys `php`, `wp`, and `plugins` (array<string,string> path => label).
	 *
	 * @var array{php?: string, wp?: string, plugins?: array<string, string>}
	 */
	private array $constraints;

	/**
	 * Collected failure messages.
	 *
	 * @var list<string>
	 */
	private array $failures = array();

	/**
	 * @param string                                                                 $label       Plugin label.
	 * @param array{php?: string, wp?: string, plugins?: array<string, string>}       $constraints Constraints.
	 */
	public function __construct( string $label, array $constraints ) {
		$this->label       = $label;
		$this->constraints = $constraints;
	}

	/**
	 * Evaluate all constraints. Populates the failure list as a side effect.
	 */
	public function met(): bool {
		$this->failures = array();

		if ( isset( $this->constraints['php'] ) && version_compare( PHP_VERSION, $this->constraints['php'], '<' ) ) {
			$this->failures[] = sprintf(
				/* translators: 1: required PHP version, 2: current PHP version. */
				__( 'requires PHP %1$s or newer (running %2$s)', 'ledger' ),
				$this->constraints['php'],
				PHP_VERSION
			);
		}

		if ( isset( $this->constraints['wp'] ) && version_compare( get_bloginfo( 'version' ), $this->constraints['wp'], '<' ) ) {
			$this->failures[] = sprintf(
				/* translators: 1: required WP version, 2: current WP version. */
				__( 'requires WordPress %1$s or newer (running %2$s)', 'ledger' ),
				$this->constraints['wp'],
				get_bloginfo( 'version' )
			);
		}

		foreach ( $this->constraints['plugins'] ?? array() as $plugin_path => $plugin_label ) {
			if ( ! $this->plugin_active( $plugin_path ) ) {
				$this->failures[] = sprintf(
					/* translators: %s: dependency plugin name. */
					__( 'requires %s to be installed and active', 'ledger' ),
					$plugin_label
				);
			}
		}

		return array() === $this->failures;
	}

	/**
	 * Whether a plugin (by `dir/file.php`) is active, network included.
	 */
	private function plugin_active( string $plugin_path ): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( $plugin_path );
	}

	/**
	 * Render a dismissible admin notice listing every failure.
	 */
	public function render_notice(): void {
		if ( array() === $this->failures ) {
			return;
		}

		$failures = $this->failures;
		$label    = $this->label;

		add_action(
			'admin_notices',
			static function () use ( $failures, $label ): void {
				printf(
					'<div class="notice notice-error"><p><strong>%s</strong> %s:</p><ul style="list-style:disc;margin-left:1.5em">%s</ul></div>',
					esc_html( $label ),
					esc_html__( 'is inactive', 'ledger' ),
					implode(
						'',
						array_map(
							static fn ( string $msg ): string => '<li>' . esc_html( $msg ) . '</li>',
							$failures
						)
					)
				);
			}
		);
	}

	/**
	 * Abort activation with a formatted message when requirements are unmet.
	 */
	public function halt_activation_if_unmet(): void {
		if ( $this->met() ) {
			return;
		}

		$message = sprintf(
			'<h1>%s</h1><p>%s</p><ul>%s</ul>',
			esc_html( $this->label . ' ' . __( 'cannot be activated', 'ledger' ) ),
			esc_html__( 'The environment does not meet these requirements:', 'ledger' ),
			implode(
				'',
				array_map(
					static fn ( string $msg ): string => '<li>' . esc_html( $msg ) . '</li>',
					$this->failures
				)
			)
		);

		wp_die(
			wp_kses_post( $message ),
			esc_html__( 'Plugin activation failed', 'ledger' ),
			array( 'back_link' => true )
		);
	}
}
