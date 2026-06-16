<?php
/**
 * Harvest helpers for apiary press.
 *
 * @package ApiaryPress
 */

namespace ApiaryPress;

/**
 * A honey harvest entry on a hive.
 */
class Harvest {
	public const HIVE_HARVEST_POST_TYPE = 'ap_hive_harvest';

	public const QUANTITY_META_KEY   = 'quantity_kg';
	public const HONEY_TYPE_META_KEY = 'honey_type';
	public const FRAMES_META_KEY     = 'frames_extracted';
	public const METHOD_META_KEY     = 'extraction_method';

	public const METHOD_EXTRACTOR        = 'extractor';
	public const METHOD_CRUSH_AND_STRAIN = 'crush_and_strain';
	public const METHOD_PRESSED          = 'pressed';
	public const METHOD_COMB_HONEY       = 'comb_honey';
	public const METHOD_OTHER            = 'other';

	public const METHOD_VALUES = array(
		self::METHOD_EXTRACTOR,
		self::METHOD_CRUSH_AND_STRAIN,
		self::METHOD_PRESSED,
		self::METHOD_COMB_HONEY,
		self::METHOD_OTHER,
	);

	/**
	 * Translated labels for extraction methods.
	 */
	public static function get_method_labels(): array {
		return array(
			self::METHOD_EXTRACTOR        => __( 'Centrifugal extractor', 'apiary-press' ),
			self::METHOD_CRUSH_AND_STRAIN => __( 'Crush and strain', 'apiary-press' ),
			self::METHOD_PRESSED          => __( 'Pressed', 'apiary-press' ),
			self::METHOD_COMB_HONEY       => __( 'Comb honey', 'apiary-press' ),
			self::METHOD_OTHER            => __( 'Other', 'apiary-press' ),
		);
	}

	/**
	 * Register the harvest custom post type.
	 */
	public static function register_post_types(): void {
		register_post_type(
			self::HIVE_HARVEST_POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Harvests', 'apiary-press' ),
					'singular_name' => __( 'Harvest', 'apiary-press' ),
					'add_new_item'  => __( 'Add New Harvest', 'apiary-press' ),
					'edit_item'     => __( 'Edit Harvest', 'apiary-press' ),
					'new_item'      => __( 'New Harvest', 'apiary-press' ),
					'view_item'     => __( 'View Harvest', 'apiary-press' ),
					'search_items'  => __( 'Search Harvests', 'apiary-press' ),
				),
				'description'  => __( 'Honey harvest entries for Apiary Press hives.', 'apiary-press' ),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'edit.php?post_type=' . Hive::HIVE_POST_TYPE,
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor', 'author', 'custom-fields' ),
				'map_meta_cap' => true,
			)
		);
	}

	/**
	 * Register the post meta fields for harvests.
	 */
	public static function register_meta(): void {
		$auth_callback = function ( ...$args ) {
			$post_id = isset( $args[2] ) ? absint( $args[2] ) : 0;
			$user_id = isset( $args[3] ) ? absint( $args[3] ) : get_current_user_id();

			if ( $post_id ) {
				return user_can( $user_id, 'edit_post', $post_id );
			}

			return user_can( $user_id, 'edit_posts' );
		};

		register_post_meta(
			self::HIVE_HARVEST_POST_TYPE,
			self::QUANTITY_META_KEY,
			array(
				'type'              => 'number',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_quantity_meta' ),
				'auth_callback'     => $auth_callback,
			)
		);

		register_post_meta(
			self::HIVE_HARVEST_POST_TYPE,
			self::HONEY_TYPE_META_KEY,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_text_meta' ),
				'auth_callback'     => $auth_callback,
			)
		);

		register_post_meta(
			self::HIVE_HARVEST_POST_TYPE,
			self::FRAMES_META_KEY,
			array(
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_frames_meta' ),
				'auth_callback'     => $auth_callback,
			)
		);

		register_post_meta(
			self::HIVE_HARVEST_POST_TYPE,
			self::METHOD_META_KEY,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_method_meta' ),
				'auth_callback'     => $auth_callback,
			)
		);
	}

	/**
	 * Sanitize a quantity value into a non-negative float.
	 *
	 * @param mixed $value The value to sanitize.
	 */
	public static function sanitize_quantity_meta( $value ): float {
		if ( ! is_numeric( $value ) ) {
			return 0.0;
		}

		$value = (float) $value;

		return $value < 0 ? 0.0 : $value;
	}

	/**
	 * Sanitize a frames-extracted value into a non-negative integer.
	 *
	 * @param mixed $value The value to sanitize.
	 */
	public static function sanitize_frames_meta( $value ): int {
		if ( ! is_numeric( $value ) ) {
			return 0;
		}

		$value = (int) $value;

		return $value < 0 ? 0 : $value;
	}

	/**
	 * Sanitize a method value: returns the slug if valid, '' otherwise.
	 *
	 * @param mixed $value The value to sanitize.
	 */
	public static function sanitize_method_meta( $value ): string {
		$value = sanitize_key( (string) $value );

		return in_array( $value, self::METHOD_VALUES, true ) ? $value : '';
	}

	/**
	 * Sanitize a text meta value.
	 *
	 * @param mixed $value The value to sanitize.
	 */
	public static function sanitize_text_meta( $value ): string {
		return sanitize_text_field( (string) $value );
	}

	/**
	 * Read the structured harvest payload for a post into an array.
	 *
	 * @param int $harvest_id The harvest post ID.
	 */
	public static function get_harvest( int $harvest_id ): array {
		$quantity_raw = get_post_meta( $harvest_id, self::QUANTITY_META_KEY, true );
		$frames_raw   = get_post_meta( $harvest_id, self::FRAMES_META_KEY, true );

		return array(
			'quantity_kg' => is_numeric( $quantity_raw ) ? (float) $quantity_raw : 0.0,
			'honey_type'  => (string) get_post_meta( $harvest_id, self::HONEY_TYPE_META_KEY, true ),
			'frames'      => is_numeric( $frames_raw ) ? (int) $frames_raw : 0,
			'method'      => (string) get_post_meta( $harvest_id, self::METHOD_META_KEY, true ),
		);
	}

	/**
	 * Total harvested kilograms across the given harvest posts.
	 *
	 * @param \WP_Post[] $harvests Harvest posts to sum.
	 */
	public static function total_kg( array $harvests ): float {
		$total = 0.0;

		foreach ( $harvests as $harvest ) {
			if ( ! ( $harvest instanceof \WP_Post ) ) {
				continue;
			}

			$value = get_post_meta( $harvest->ID, self::QUANTITY_META_KEY, true );

			if ( is_numeric( $value ) ) {
				$total += (float) $value;
			}
		}

		return $total;
	}

	/**
	 * Fetch all harvest posts for a list of hives in a single query, bucketed by hive ID.
	 *
	 * @param int[] $hive_ids Hive post IDs to include. Empty list returns an empty array.
	 * @return array<int, \WP_Post[]> Map of hive_id → array of harvest posts, sorted by date DESC within each bucket.
	 */
	public static function get_for_hives( array $hive_ids ): array {
		$hive_ids = array_values( array_filter( array_map( 'absint', $hive_ids ) ) );
		$bucket   = array_fill_keys( $hive_ids, array() );

		if ( empty( $hive_ids ) ) {
			return $bucket;
		}

		$posts = get_posts(
			array(
				'post_type'        => self::HIVE_HARVEST_POST_TYPE,
				'post_status'      => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'post_parent__in'  => $hive_ids,
				'numberposts'      => -1,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => false,
			)
		);

		foreach ( $posts as $post ) {
			$parent = absint( $post->post_parent );

			if ( isset( $bucket[ $parent ] ) ) {
				$bucket[ $parent ][] = $post;
			}
		}

		return $bucket;
	}

	/**
	 * Format a kg value for display, trimming trailing zeros (e.g. 12.5 → "12.5", 4 → "4").
	 *
	 * @param float $value The kilogram value.
	 */
	public static function format_kg( float $value ): string {
		if ( $value <= 0 ) {
			return '0';
		}

		$formatted = number_format( $value, 3, '.', '' );

		return rtrim( rtrim( $formatted, '0' ), '.' );
	}
}
