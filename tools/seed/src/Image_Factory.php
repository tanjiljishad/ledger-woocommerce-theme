<?php
/**
 * Procedural product image generator.
 *
 * Draws real JPEGs with GD — deterministic gradients plus a label — so image
 * handling (srcset, sizes, lazy-loading, format) is measured honestly. No
 * external placeholder service, per roadmap task 5.2.
 *
 * @package Ledger\Seed
 */

declare( strict_types=1 );

namespace Ledger\Seed;

defined( 'ABSPATH' ) || exit;

final class Image_Factory {

	/** Master dimensions — realistic for a shop hero/product image. */
	private const WIDTH  = 1200;
	private const HEIGHT = 1200;

	/**
	 * Generate an attachment for a product and return its ID (0 on failure).
	 *
	 * @param int    $product_id Parent product post ID.
	 * @param string $label      Text drawn on the image.
	 * @param int    $seed       Deterministic colour seed.
	 */
	public function attach( int $product_id, string $label, int $seed ): int {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return 0;
		}

		$image = imagecreatetruecolor( self::WIDTH, self::HEIGHT );

		mt_srand( $seed );
		$r1 = mt_rand( 40, 210 );
		$g1 = mt_rand( 40, 210 );
		$b1 = mt_rand( 40, 210 );
		$r2 = (int) max( 0, $r1 - 60 );
		$g2 = (int) max( 0, $g1 - 60 );
		$b2 = (int) max( 0, $b1 - 60 );

		for ( $y = 0; $y < self::HEIGHT; $y++ ) {
			$t     = $y / self::HEIGHT;
			$color = imagecolorallocate(
				$image,
				(int) round( $r1 + ( $r2 - $r1 ) * $t ),
				(int) round( $g1 + ( $g2 - $g1 ) * $t ),
				(int) round( $b1 + ( $b2 - $b1 ) * $t )
			);
			imageline( $image, 0, $y, self::WIDTH, $y, $color );
		}

		$ink = imagecolorallocate( $image, 255, 255, 255 );
		imagestring( $image, 5, 40, 40, substr( $label, 0, 48 ), $ink );

		$tmp = wp_tempnam( 'ledger-seed-' . $seed . '.jpg' );
		imagejpeg( $image, $tmp, 82 );
		imagedestroy( $image );

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$file_array = array(
			'name'     => sanitize_title( $label ) . '-' . $seed . '.jpg',
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload( $file_array, $product_id, $label );

		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp );
			return 0;
		}

		// Force every registered size so measurements reflect real srcset output.
		$meta = wp_generate_attachment_metadata( $attachment_id, get_attached_file( $attachment_id ) );
		wp_update_attachment_metadata( $attachment_id, $meta );

		// Marker so `wp ledger seed wipe` can find and remove only seeded media.
		update_post_meta( (int) $attachment_id, '_ledger_seed', '1' );

		return (int) $attachment_id;
	}
}
