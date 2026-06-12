<?php
/**
 * Weather forecast helpers for hive visits.
 *
 * @package ApiaryPress
 */

namespace ApiaryPress;

/**
 * A hive visit.
 */
class Visit {
	public const HIVE_POST_TYPE       = 'ap_hive';
	public const HIVE_VISIT_POST_TYPE = 'ap_hive_visit';

	public const VISIT_BOOLEAN_META_KEYS = array(
		'added_super',
		'capped_brood',
		'check_soon',
		'eggs',
		'larvae',
		'queen_cells',
		'saw_queen',
	);

	/**
	 * Get the translated labels for the visit boolean meta keys.
	 */
	public static function get_boolean_meta_labels(): array {
		return array(
			'added_super'  => __( 'Added super', 'apiary-press' ),
			'capped_brood' => __( 'Capped brood', 'apiary-press' ),
			'check_soon'   => __( 'Check soon', 'apiary-press' ),
			'eggs'         => __( 'Eggs', 'apiary-press' ),
			'larvae'       => __( 'Larvae', 'apiary-press' ),
			'queen_cells'  => __( 'Queen cells', 'apiary-press' ),
			'saw_queen'    => __( 'Saw queen', 'apiary-press' ),
		);
	}

	/**
	 * Register the boolean and weather post meta fields for the hive visit post type.
	 */
	public static function register_visit_meta(): void {
		foreach ( self::VISIT_BOOLEAN_META_KEYS as $meta_key ) {
			register_post_meta(
				self::HIVE_VISIT_POST_TYPE,
				$meta_key,
				array(
					'type'              => 'boolean',
					'single'            => true,
					'default'           => false,
					'show_in_rest'      => true,
					'sanitize_callback' => array( __CLASS__, 'sanitize_boolean_meta' ),
					'auth_callback'     => function ( ...$args ) {
						$post_id = isset( $args[2] ) ? absint( $args[2] ) : 0;
						$user_id = isset( $args[3] ) ? absint( $args[3] ) : get_current_user_id();

						if ( $post_id ) {
							return user_can( $user_id, 'edit_post', $post_id );
						}

						return user_can( $user_id, 'edit_posts' );
					},
				)
			);
		}

		foreach ( Weather::FORECAST_UNITS as $meta_key => $type ) {
			$sanitize_callback = 'string' === $type
				? array( __CLASS__, 'sanitize_text_meta' )
				: ( 'integer' === $type ? array( __CLASS__, 'sanitize_integer_meta' ) : array( __CLASS__, 'sanitize_number_meta' ) );

			register_post_meta(
				self::HIVE_VISIT_POST_TYPE,
				$meta_key,
				array(
					'type'              => $type,
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => $sanitize_callback,
					'auth_callback'     => function ( ...$args ) {
						$post_id = isset( $args[2] ) ? absint( $args[2] ) : 0;
						$user_id = isset( $args[3] ) ? absint( $args[3] ) : get_current_user_id();

						if ( $post_id ) {
							return user_can( $user_id, 'edit_post', $post_id );
						}

						return user_can( $user_id, 'edit_posts' );
					},
				)
			);
		}
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
	 * Sanitize a meta value into a float, defaulting to 0.0.
	 *
	 * @param mixed $value The value to sanitize.
	 */
	public static function sanitize_number_meta( $value ): float {
		return is_numeric( $value ) ? (float) $value : 0.0;
	}

	/**
	 * Sanitize a meta value into an integer, defaulting to 0.
	 *
	 * @param mixed $value The value to sanitize.
	 */
	public static function sanitize_integer_meta( $value ): int {
		return is_numeric( $value ) ? (int) $value : 0;
	}

	/**
	 * Sanitize a meta value into a plain text string.
	 *
	 * @param mixed $value The value to sanitize.
	 */
	public static function sanitize_text_meta( $value ): string {
		return sanitize_text_field( (string) $value );
	}
}
