<?php
/**
 * Hive helpers for apiary press.
 *
 * @package ApiaryPress
 */

namespace ApiaryPress;

/**
 * A hive.
 */
class Hive {
	public const HIVE_POST_TYPE = 'ap_hive';

	public const HIVE_LOCATION_META_KEYS = array(
		'latitude',
		'longitude',
	);

	public const QUEEN_YEAR_META_KEY      = 'queen_year';
	public const QUEEN_COLOR_META_KEY     = 'queen_color';
	public const QUEEN_MARKED_META_KEY    = 'queen_marked';
	public const QUEEN_CLIPPED_META_KEY   = 'queen_clipped';
	public const QUEEN_ORIGIN_META_KEY    = 'queen_origin';
	public const QUEEN_INSTALLED_META_KEY = 'queen_installed_at';

	public const QUEEN_COLOR_VALUES = array(
		'white',
		'yellow',
		'red',
		'green',
		'blue',
	);

	/**
	 * Register the hive custom post types.
	 */
	public static function register_post_types(): void {
		register_post_type(
			self::HIVE_POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Hives', 'apiary-press' ),
					'singular_name' => __( 'Hive', 'apiary-press' ),
					'add_new_item'  => __( 'Add New Hive', 'apiary-press' ),
					'edit_item'     => __( 'Edit Hive', 'apiary-press' ),
					'new_item'      => __( 'New Hive', 'apiary-press' ),
					'view_item'     => __( 'View Hive', 'apiary-press' ),
					'search_items'  => __( 'Search Hives', 'apiary-press' ),
				),
				'description'  => __( 'Bee hives managed in Apiary Press.', 'apiary-press' ),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => true,
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-location-alt',
				'supports'     => array( 'title', 'editor', 'author' ),
				'map_meta_cap' => true,
			)
		);
	}

	/**
	 * Register the location post meta fields for the hive post type.
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

		foreach ( self::HIVE_LOCATION_META_KEYS as $meta_key ) {
			register_post_meta(
				self::HIVE_POST_TYPE,
				$meta_key,
				array(
					'type'              => 'number',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => array( __CLASS__, 'sanitize_number_meta' ),
					'auth_callback'     => $auth_callback,
				)
			);
		}

		register_post_meta(
			self::HIVE_POST_TYPE,
			self::QUEEN_YEAR_META_KEY,
			array(
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_queen_year_meta' ),
				'auth_callback'     => $auth_callback,
			)
		);

		register_post_meta(
			self::HIVE_POST_TYPE,
			self::QUEEN_COLOR_META_KEY,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_queen_color_meta' ),
				'auth_callback'     => $auth_callback,
			)
		);

		foreach ( array( self::QUEEN_MARKED_META_KEY, self::QUEEN_CLIPPED_META_KEY ) as $meta_key ) {
			register_post_meta(
				self::HIVE_POST_TYPE,
				$meta_key,
				array(
					'type'              => 'boolean',
					'single'            => true,
					'default'           => false,
					'show_in_rest'      => true,
					'sanitize_callback' => array( __CLASS__, 'sanitize_boolean_meta' ),
					'auth_callback'     => $auth_callback,
				)
			);
		}

		register_post_meta(
			self::HIVE_POST_TYPE,
			self::QUEEN_ORIGIN_META_KEY,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_text_meta' ),
				'auth_callback'     => $auth_callback,
			)
		);

		register_post_meta(
			self::HIVE_POST_TYPE,
			self::QUEEN_INSTALLED_META_KEY,
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
	 * Sanitize a meta value into a float, defaulting to 0.0.
	 *
	 * @param mixed $value The value to sanitize.
	 */
	public static function sanitize_number_meta( $value ): float {
		return is_numeric( $value ) ? (float) $value : 0.0;
	}

	/**
	 * Sanitize a meta value into a boolean.
	 *
	 * @param mixed $value The value to sanitize.
	 */
	public static function sanitize_boolean_meta( $value ): bool {
		return rest_sanitize_boolean( $value );
	}

	/**
	 * Sanitize a meta value into a trimmed text string.
	 *
	 * @param mixed $value The value to sanitize.
	 */
	public static function sanitize_text_meta( $value ): string {
		return sanitize_text_field( (string) $value );
	}

	/**
	 * Sanitize a queen year value: a four-digit year between 1990 and next year, or 0 to clear.
	 *
	 * @param mixed $value The value to sanitize.
	 */
	public static function sanitize_queen_year_meta( $value ): int {
		if ( ! is_numeric( $value ) ) {
			return 0;
		}

		$year     = (int) $value;
		$max_year = (int) gmdate( 'Y' ) + 1;

		if ( $year < 1990 || $year > $max_year ) {
			return 0;
		}

		return $year;
	}

	/**
	 * Sanitize a queen color value: one of QUEEN_COLOR_VALUES, or '' to clear / fall back to year-based color.
	 *
	 * @param mixed $value The value to sanitize.
	 */
	public static function sanitize_queen_color_meta( $value ): string {
		$value = sanitize_key( (string) $value );

		return in_array( $value, self::QUEEN_COLOR_VALUES, true ) ? $value : '';
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
	 * Return the international queen marking color for a given birth year.
	 *
	 * @param int $year The four-digit year.
	 * @return string One of QUEEN_COLOR_VALUES, or '' if the year is invalid.
	 */
	public static function queen_color_for_year( int $year ): string {
		if ( $year < 1990 ) {
			return '';
		}

		$cycle = array(
			0 => 'blue',
			1 => 'white',
			2 => 'yellow',
			3 => 'red',
			4 => 'green',
			5 => 'blue',
			6 => 'white',
			7 => 'yellow',
			8 => 'red',
			9 => 'green',
		);

		return $cycle[ $year % 10 ] ?? '';
	}

	/**
	 * Translated label for a queen marking color slug.
	 *
	 * @param string $color One of QUEEN_COLOR_VALUES.
	 */
	public static function queen_color_label( string $color ): string {
		switch ( $color ) {
			case 'white':
				return __( 'White', 'apiary-press' );
			case 'yellow':
				return __( 'Yellow', 'apiary-press' );
			case 'red':
				return __( 'Red', 'apiary-press' );
			case 'green':
				return __( 'Green', 'apiary-press' );
			case 'blue':
				return __( 'Blue', 'apiary-press' );
			default:
				return '';
		}
	}

	/**
	 * Hex swatch color for a queen marking color slug, suitable for inline display.
	 *
	 * @param string $color One of QUEEN_COLOR_VALUES.
	 */
	public static function queen_color_swatch( string $color ): string {
		switch ( $color ) {
			case 'white':
				return '#f5f1e6';
			case 'yellow':
				return '#f4c20d';
			case 'red':
				return '#c93434';
			case 'green':
				return '#2f8f3f';
			case 'blue':
				return '#2a6fd1';
			default:
				return '';
		}
	}

	/**
	 * Read all queen meta for a hive into a structured array.
	 *
	 * @param int $hive_id The ID of the hive post.
	 */
	public static function get_queen( int $hive_id ): array {
		$year           = (int) get_post_meta( $hive_id, self::QUEEN_YEAR_META_KEY, true );
		$color_override = (string) get_post_meta( $hive_id, self::QUEEN_COLOR_META_KEY, true );
		$color          = '' !== $color_override ? $color_override : self::queen_color_for_year( $year );

		return array(
			'year'           => $year ? $year : 0,
			'color'          => $color,
			'color_override' => $color_override,
			'marked'         => (bool) get_post_meta( $hive_id, self::QUEEN_MARKED_META_KEY, true ),
			'clipped'        => (bool) get_post_meta( $hive_id, self::QUEEN_CLIPPED_META_KEY, true ),
			'origin'         => (string) get_post_meta( $hive_id, self::QUEEN_ORIGIN_META_KEY, true ),
			'installed_at'   => (string) get_post_meta( $hive_id, self::QUEEN_INSTALLED_META_KEY, true ),
		);
	}

	/**
	 * Get a hive's validated latitude/longitude, or an empty array if missing or out of range.
	 *
	 * @param int $hive_id The ID of the hive post.
	 * @return array The validated coordinates, or an empty array if invalid.
	 */
	public static function get_coordinates( int $hive_id ): array {
		$latitude  = get_post_meta( $hive_id, 'latitude', true );
		$longitude = get_post_meta( $hive_id, 'longitude', true );

		if ( ! is_numeric( $latitude ) || ! is_numeric( $longitude ) ) {
			return array();
		}

		$latitude  = (float) $latitude;
		$longitude = (float) $longitude;

		if ( $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180 ) {
			return array();
		}

		return array(
			'latitude'  => $latitude,
			'longitude' => $longitude,
		);
	}
}
