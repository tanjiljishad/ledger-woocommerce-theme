<?php
/**
 * `wp ledger seed` — build and tear down benchmark data.
 *
 * @package Ledger\Seed
 */

declare( strict_types=1 );

namespace Ledger\Seed;

defined( 'ABSPATH' ) || exit;

use WP_CLI;
use WP_CLI\Utils;

/**
 * Generates a realistic WooCommerce catalogue for performance benchmarking.
 */
final class Seed_Command {

	private const CATEGORIES = array(
		'Outerwear', 'Footwear', 'Bags', 'Accessories', 'Home', 'Kitchen',
		'Lighting', 'Stationery', 'Fitness', 'Audio', 'Outdoor', 'Grooming',
	);

	private const MATERIALS = array( 'Merino', 'Oak', 'Canvas', 'Ceramic', 'Brushed Steel', 'Linen', 'Walnut', 'Recycled Nylon' );
	private const QUALIFIERS = array( 'Everyday', 'Studio', 'Trail', 'Heritage', 'Compact', 'Weatherproof', 'Featherweight', 'Modular' );
	private const NOUNS = array( 'Jacket', 'Sneaker', 'Tote', 'Mug', 'Lamp', 'Notebook', 'Kettle', 'Backpack', 'Headphones', 'Bottle', 'Chair', 'Razor' );

	/**
	 * Generate products, categories, images, and reviews.
	 *
	 * ## OPTIONS
	 *
	 * [--count=<n>]
	 * : How many products to create. Default 5000.
	 *
	 * [--variable-ratio=<f>]
	 * : Fraction that are variable products. Default 0.4.
	 *
	 * [--images]
	 * : Generate and attach a real image per product. Slow; off by default so
	 *   catalogue and image cost can be measured separately.
	 *
	 * [--reviews]
	 * : Attach 0–8 approved reviews per product. Default on.
	 *
	 * [--fresh]
	 * : Delete every existing product (and Ledger-seeded media) first.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ledger seed generate --count=5000 --images
	 *     wp ledger seed generate --count=200 --fresh   # quick local run
	 *
	 * @param array<int, string>    $args       Positional args (unused).
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function generate( array $args, array $assoc_args ): void {
		if ( ! class_exists( \WooCommerce::class ) ) {
			WP_CLI::error( 'WooCommerce must be active.' );
		}

		$count      = max( 1, (int) ( $assoc_args['count'] ?? 5000 ) );
		$var_ratio  = (float) ( $assoc_args['variable-ratio'] ?? 0.4 );
		$do_images  = isset( $assoc_args['images'] );
		$do_reviews = ! isset( $assoc_args['reviews'] ) || Utils\get_flag_value( $assoc_args, 'reviews', true );

		if ( isset( $assoc_args['fresh'] ) ) {
			$this->wipe();
		}

		$term_ids = $this->ensure_categories();
		$images   = new Image_Factory();

		// Deterministic run: same seed => same catalogue.
		mt_srand( 424242 );

		$progress    = Utils\make_progress_bar( 'Seeding products', $count );
		$variable_no = (int) round( $count * $var_ratio );

		for ( $i = 0; $i < $count; $i++ ) {
			$is_variable = $i < $variable_no;
			$title       = $this->product_title( $i );
			$price       = $this->price();

			$product = $is_variable
				? new \WC_Product_Variable()
				: new \WC_Product_Simple();

			$product->set_name( $title );
			$product->set_status( 'publish' );
			$product->set_catalog_visibility( 'visible' );
			$product->set_description( $this->paragraph( $title ) );
			$product->set_short_description( $this->sentence( $title ) );
			$product->set_sku( sprintf( 'LDG-%05d', $i + 1 ) );
			$product->set_category_ids( $this->pick_terms( $term_ids ) );
			$product->set_reviews_allowed( true );

			if ( ! $is_variable ) {
				$product->set_regular_price( (string) $price );
				if ( 0 === $i % 5 ) {
					$product->set_sale_price( (string) round( $price * 0.8, 2 ) );
				}
				$product->set_manage_stock( true );
				$product->set_stock_quantity( (int) mt_rand( 0, 240 ) );
				$product->set_stock_status( 0 === mt_rand( 0, 11 ) ? 'outofstock' : 'instock' );
			}

			$product_id = $product->save();

			if ( $is_variable ) {
				$this->add_variations( $product, $price );
			}

			if ( $do_images ) {
				$attachment_id = $images->attach( (int) $product_id, $title, $i + 1 );
				if ( $attachment_id > 0 ) {
					set_post_thumbnail( (int) $product_id, $attachment_id );
				}
			}

			if ( $do_reviews ) {
				$this->add_reviews( (int) $product_id, $title );
			}

			if ( 0 === $i % 200 ) {
				Utils\wp_clear_object_cache();
			}
			$progress->tick();
		}

		$progress->finish();
		wc_delete_product_transients();
		WP_CLI::success( sprintf( 'Created %d products (%d variable, %d simple).', $count, $variable_no, $count - $variable_no ) );
	}

	/**
	 * Delete all products and Ledger-seeded attachments.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip confirmation.
	 *
	 * @param array<int, string>    $args       Unused.
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function wipe( array $args = array(), array $assoc_args = array() ): void {
		WP_CLI::confirm( 'Delete every product and seeded image?', $assoc_args );

		$ids = get_posts(
			array(
				'post_type'      => array( 'product', 'product_variation' ),
				'post_status'    => 'any',
				'numberposts'    => -1,
				'fields'         => 'ids',
				'suppress_filters' => true,
			)
		);

		foreach ( $ids as $id ) {
			wp_delete_post( (int) $id, true );
		}

		$media = get_posts(
			array(
				'post_type'   => 'attachment',
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_key'    => '_ledger_seed',
				'meta_value'  => '1',
			)
		);
		foreach ( $media as $id ) {
			wp_delete_attachment( (int) $id, true );
		}

		WP_CLI::success( sprintf( 'Removed %d product posts.', count( $ids ) ) );
	}

	/**
	 * @return list<int> Category term IDs.
	 */
	private function ensure_categories(): array {
		$ids = array();
		foreach ( self::CATEGORIES as $name ) {
			$existing = term_exists( $name, 'product_cat' );
			if ( is_array( $existing ) ) {
				$ids[] = (int) $existing['term_id'];
				continue;
			}
			$created = wp_insert_term( $name, 'product_cat' );
			if ( ! is_wp_error( $created ) ) {
				$ids[] = (int) $created['term_id'];
			}
		}
		return $ids;
	}

	/**
	 * @param list<int> $term_ids All category IDs.
	 * @return list<int> One or two IDs.
	 */
	private function pick_terms( array $term_ids ): array {
		if ( array() === $term_ids ) {
			return array();
		}
		shuffle( $term_ids );
		return array_slice( $term_ids, 0, mt_rand( 1, 2 ) );
	}

	private function add_variations( \WC_Product_Variable $product, float $base ): void {
		$attributes = array(
			'Size'     => array( 'XS', 'S', 'M', 'L', 'XL' ),
			'Colour'   => array( 'Black', 'Sand', 'Forest', 'Slate' ),
			'Material' => array( self::MATERIALS[ array_rand( self::MATERIALS ) ], self::MATERIALS[ array_rand( self::MATERIALS ) ] ),
		);

		$product_attributes = array();
		foreach ( $attributes as $label => $values ) {
			$values      = array_values( array_unique( $values ) );
			$attribute   = new \WC_Product_Attribute();
			$attribute->set_name( $label );
			$attribute->set_options( $values );
			$attribute->set_visible( true );
			$attribute->set_variation( true );
			$product_attributes[] = $attribute;
		}
		$product->set_attributes( $product_attributes );
		$product->save();

		// ~12 variations: a spread across Size x Colour.
		$made = 0;
		foreach ( $attributes['Size'] as $size ) {
			foreach ( array_slice( $attributes['Colour'], 0, 3 ) as $colour ) {
				if ( $made >= 12 ) {
					break 2;
				}
				$variation = new \WC_Product_Variation();
				$variation->set_parent_id( $product->get_id() );
				$variation->set_attributes(
					array(
						'size'   => $size,
						'colour' => $colour,
					)
				);
				$variation->set_regular_price( (string) round( $base + mt_rand( 0, 1500 ) / 100, 2 ) );
				$variation->set_manage_stock( true );
				$variation->set_stock_quantity( (int) mt_rand( 0, 60 ) );
				$variation->save();
				$made++;
			}
		}
	}

	private function add_reviews( int $product_id, string $title ): void {
		$n = mt_rand( 0, 8 );
		for ( $i = 0; $i < $n; $i++ ) {
			$comment_id = wp_insert_comment(
				array(
					'comment_post_ID'      => $product_id,
					'comment_author'       => 'Buyer ' . mt_rand( 1000, 9999 ),
					'comment_author_email' => 'buyer' . mt_rand( 1000, 9999 ) . '@example.test',
					'comment_content'      => $this->sentence( $title ),
					'comment_approved'     => 1,
					'comment_type'         => 'review',
				)
			);
			if ( $comment_id ) {
				add_comment_meta( (int) $comment_id, 'rating', (string) mt_rand( 3, 5 ) );
			}
		}
		if ( $n > 0 ) {
			\WC_Comments::clear_transients( $product_id );
		}
	}

	private function product_title( int $i ): string {
		$q = self::QUALIFIERS[ $i % count( self::QUALIFIERS ) ];
		$m = self::MATERIALS[ ( $i * 7 ) % count( self::MATERIALS ) ];
		$n = self::NOUNS[ ( $i * 3 ) % count( self::NOUNS ) ];
		return trim( "$q $m $n" );
	}

	private function price(): float {
		return (float) ( mt_rand( 900, 24000 ) / 100 );
	}

	private function sentence( string $subject ): string {
		$templates = array(
			'The %s has held up through daily use without complaint.',
			'Exactly the %s I was after — well made and honest about its size.',
			'Solid %s. A little heavier than expected, still recommended.',
			'Second %s I have bought. Consistent quality.',
		);
		return sprintf( $templates[ array_rand( $templates ) ], strtolower( $subject ) );
	}

	private function paragraph( string $subject ): string {
		return implode(
			' ',
			array(
				$this->sentence( $subject ),
				$this->sentence( $subject ),
				'Ships flat-packed where it makes sense. Covered by the standard two-year guarantee.',
			)
		);
	}
}
