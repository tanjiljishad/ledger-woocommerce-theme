<?php
/**
 * Plugin Name: Ledger Seed
 * Description: Dev-only WP-CLI commands that build a realistic 5,000-product shop for benchmarking. Registers nothing outside WP-CLI.
 * Version:     0.1.0
 * Requires PHP: 8.2
 *
 * @package Ledger\Seed
 *
 * Ledger — Proprietary Commercial License. Copyright (c) 2026 Tanjil.
 */

declare( strict_types=1 );

namespace Ledger\Seed;

defined( 'ABSPATH' ) || exit;

// This tool is inert unless run through WP-CLI.
if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	return;
}

require_once __DIR__ . '/src/Seed_Command.php';
require_once __DIR__ . '/src/Image_Factory.php';

\WP_CLI::add_command( 'ledger seed', Seed_Command::class );
