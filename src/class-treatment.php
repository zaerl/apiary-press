<?php
/**
 * Treatment and feeding helpers for apiary press.
 *
 * @package ApiaryPress
 */

namespace ApiaryPress;

/**
 * A treatment or feeding intervention applied to a hive.
 *
 * Both kinds share the same shape (date + product + quantity + unit + notes),
 * so they are stored under a single post type with a `kind` discriminator
 * rather than two separate types.
 */
class Treatment {
	public const HIVE_TREATMENT_POST_TYPE = 'ap_hive_treatment';

	public const KIND_META_KEY     = 'kind';
	public const PRODUCT_META_KEY  = 'product';
	public const TARGET_META_KEY   = 'target';
	public const QUANTITY_META_KEY = 'quantity';
	public const UNIT_META_KEY     = 'unit';
	public const END_DATE_META_KEY = 'end_date';

	public const KIND_TREATMENT = 'treatment';
	public const KIND_FEEDING   = 'feeding';

	public const KIND_VALUES = array(
		self::KIND_TREATMENT,
		self::KIND_FEEDING,
	);

	public const TARGET_VALUES = array(
		'varroa',
		'nosema',
		'foulbrood',
		'chalkbrood',
		'small_hive_beetle',
		'wax_moth',
		'other',
	);

	public const UNIT_VALUES = array(
		'g',
		'kg',
		'ml',
		'l',
		'strips',
		'tablets',
		'doses',
	);

	/**
	 * Units typically used per kind, in display order.
	 */
	public const UNITS_BY_KIND = array(
		self::KIND_TREATMENT => array( 'g', 'ml', 'strips', 'tablets', 'doses' ),
		self::KIND_FEEDING   => array( 'kg', 'g', 'l', 'ml' ),
	);

	/**
	 * Translated labels for the kind discriminator.
	 */
	public static function get_kind_labels(): array {
		return array(
			self::KIND_TREATMENT => __( 'Treatment', 'apiary-press' ),
			self::KIND_FEEDING   => __( 'Feeding', 'apiary-press' ),
		);
	}

	/**
	 * Translated labels for the treatment target.
	 */
	public static function get_target_labels(): array {
		return array(
			'varroa'            => __( 'Varroa', 'apiary-press' ),
			'nosema'            => __( 'Nosema', 'apiary-press' ),
			'foulbrood'         => __( 'Foulbrood', 'apiary-press' ),
			'chalkbrood'        => __( 'Chalkbrood', 'apiary-press' ),
			'small_hive_beetle' => __( 'Small hive beetle', 'apiary-press' ),
			'wax_moth'          => __( 'Wax moth', 'apiary-press' ),
			'other'             => __( 'Other', 'apiary-press' ),
		);
	}

	/**
	 * Translated labels for the unit values.
	 */
	public static function get_unit_labels(): array {
		return array(
			// translators: abbreviated form of "grams".
			'g'       => __( 'g', 'apiary-press' ),
			// translators: abbreviated form of "kilograms".
			'kg'      => __( 'kg', 'apiary-press' ),
			// translators: abbreviated form of "milliliters".
			'ml'      => __( 'ml', 'apiary-press' ),
			// translators: abbreviated form of "liters".
			'l'       => __( 'L', 'apiary-press' ),
			'strips'  => __( 'strips', 'apiary-press' ),
			'tablets' => __( 'tablets', 'apiary-press' ),
			'doses'   => __( 'doses', 'apiary-press' ),
		);
	}

	/**
	 * Register the treatment custom post type.
	 */
	public static function register_post_types(): void {
		register_post_type(
			self::HIVE_TREATMENT_POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Treatments & Feedings', 'apiary-press' ),
					'singular_name' => __( 'Treatment / Feeding', 'apiary-press' ),
					'add_new_item'  => __( 'Add New Treatment', 'apiary-press' ),
					'edit_item'     => __( 'Edit Treatment', 'apiary-press' ),
					'new_item'      => __( 'New Treatment', 'apiary-press' ),
					'view_item'     => __( 'View Treatment', 'apiary-press' ),
					'search_items'  => __( 'Search Treatments', 'apiary-press' ),
				),
				'description'  => __( 'Treatments and feedings applied to Apiary Press hives.', 'apiary-press' ),
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
	 * Register the post meta fields for treatments.
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
			self::HIVE_TREATMENT_POST_TYPE,
			self::KIND_META_KEY,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => self::KIND_TREATMENT,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_kind_meta' ),
				'auth_callback'     => $auth_callback,
			)
		);

		register_post_meta(
			self::HIVE_TREATMENT_POST_TYPE,
			self::PRODUCT_META_KEY,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_text_meta' ),
				'auth_callback'     => $auth_callback,
			)
		);

		register_post_meta(
			self::HIVE_TREATMENT_POST_TYPE,
			self::TARGET_META_KEY,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_target_meta' ),
				'auth_callback'     => $auth_callback,
			)
		);

		register_post_meta(
			self::HIVE_TREATMENT_POST_TYPE,
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
			self::HIVE_TREATMENT_POST_TYPE,
			self::UNIT_META_KEY,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_unit_meta' ),
				'auth_callback'     => $auth_callback,
			)
		);

		register_post_meta(
			self::HIVE_TREATMENT_POST_TYPE,
			self::END_DATE_META_KEY,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_date_meta' ),
				'auth_callback'     => $auth_callback,
			)
		);
	}

	/**
	 * Sanitize a kind value: returns the slug if valid, KIND_TREATMENT otherwise.
	 *
	 * @param mixed $value The value to sanitize.
	 */
	public static function sanitize_kind_meta( $value ): string {
		$value = sanitize_key( (string) $value );

		return in_array( $value, self::KIND_VALUES, true ) ? $value : self::KIND_TREATMENT;
	}

	/**
	 * Sanitize a target value: returns the slug if valid, '' otherwise.
	 *
	 * @param mixed $value The value to sanitize.
	 */
	public static function sanitize_target_meta( $value ): string {
		$value = sanitize_key( (string) $value );

		return in_array( $value, self::TARGET_VALUES, true ) ? $value : '';
	}

	/**
	 * Sanitize a unit value: returns the slug if valid, '' otherwise.
	 *
	 * @param mixed $value The value to sanitize.
	 */
	public static function sanitize_unit_meta( $value ): string {
		$value = sanitize_key( (string) $value );

		return in_array( $value, self::UNIT_VALUES, true ) ? $value : '';
	}

	/**
	 * Sanitize a quantity value into a non-negative float, defaulting to 0.0.
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
	 * Sanitize a text meta value.
	 *
	 * @param mixed $value The value to sanitize.
	 */
	public static function sanitize_text_meta( $value ): string {
		return sanitize_text_field( (string) $value );
	}

	/**
	 * Sanitize a date meta value: returns YYYY-MM-DD if parseable, '' otherwise.
	 *
	 * @param mixed $value The value to sanitize.
	 */
	public static function sanitize_date_meta( $value ): string {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		$timestamp = strtotime( $value );

		if ( false === $timestamp ) {
			return '';
		}

		return gmdate( 'Y-m-d', $timestamp );
	}

	/**
	 * Read the structured treatment payload for a post into an array.
	 *
	 * @param int $treatment_id The treatment post ID.
	 */
	public static function get_treatment( int $treatment_id ): array {
		$kind = (string) get_post_meta( $treatment_id, self::KIND_META_KEY, true );

		if ( ! in_array( $kind, self::KIND_VALUES, true ) ) {
			$kind = self::KIND_TREATMENT;
		}

		$quantity_raw = get_post_meta( $treatment_id, self::QUANTITY_META_KEY, true );

		return array(
			'kind'     => $kind,
			'product'  => (string) get_post_meta( $treatment_id, self::PRODUCT_META_KEY, true ),
			'target'   => (string) get_post_meta( $treatment_id, self::TARGET_META_KEY, true ),
			'quantity' => is_numeric( $quantity_raw ) ? (float) $quantity_raw : 0.0,
			'unit'     => (string) get_post_meta( $treatment_id, self::UNIT_META_KEY, true ),
			'end_date' => (string) get_post_meta( $treatment_id, self::END_DATE_META_KEY, true ),
		);
	}

	/**
	 * Fetch all treatment posts for a list of hives in a single query, bucketed by hive ID.
	 *
	 * @param int[] $hive_ids Hive post IDs to include. Empty list returns an empty array.
	 * @return array<int, \WP_Post[]> Map of hive_id → array of treatment posts, sorted by date DESC within each bucket.
	 */
	public static function get_for_hives( array $hive_ids ): array {
		$hive_ids = array_values( array_filter( array_map( 'absint', $hive_ids ) ) );
		$bucket   = array_fill_keys( $hive_ids, array() );

		if ( empty( $hive_ids ) ) {
			return $bucket;
		}

		$posts = get_posts(
			array(
				'post_type'        => self::HIVE_TREATMENT_POST_TYPE,
				'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
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
	 * True when a treatment has a start date in the past and either no end date
	 * or an end date that has not yet passed.
	 *
	 * @param \WP_Post $treatment The treatment post.
	 */
	public static function is_ongoing( \WP_Post $treatment ): bool {
		$kind = (string) get_post_meta( $treatment->ID, self::KIND_META_KEY, true );

		if ( self::KIND_TREATMENT !== $kind ) {
			return false;
		}

		$end_date = (string) get_post_meta( $treatment->ID, self::END_DATE_META_KEY, true );

		if ( '' === $end_date ) {
			return false;
		}

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end_date ) ) {
			return false;
		}

		return current_time( 'Y-m-d' ) <= $end_date;
	}
}
