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
		foreach ( self::HIVE_LOCATION_META_KEYS as $meta_key ) {
			register_post_meta(
				self::HIVE_POST_TYPE,
				$meta_key,
				array(
					'type'              => 'number',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => array( __CLASS__, 'sanitize_number_meta' ),
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
	 * Sanitize a meta value into a float, defaulting to 0.0.
	 *
	 * @param mixed $value The value to sanitize.
	 */
	public static function sanitize_number_meta( $value ): float {
		return is_numeric( $value ) ? (float) $value : 0.0;
	}
}
