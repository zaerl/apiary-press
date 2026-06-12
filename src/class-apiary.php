<?php
/**
 * Apiary helpers for apiary press.
 *
 * @package ApiaryPress
 */

namespace ApiaryPress;

/**
 * An apiary.
 */
class Apiary {
	public const APIARY_POST_TYPE = 'ap_apiary';

	/**
	 * Register the apiary custom post types.
	 */
	public static function register_post_types(): void {
		register_post_type(
			self::APIARY_POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Apiaries', 'apiary-press' ),
					'singular_name' => __( 'Apiary', 'apiary-press' ),
					'add_new_item'  => __( 'Add New Apiary', 'apiary-press' ),
					'edit_item'     => __( 'Edit Apiary', 'apiary-press' ),
					'new_item'      => __( 'New Apiary', 'apiary-press' ),
					'view_item'     => __( 'View Apiary', 'apiary-press' ),
					'search_items'  => __( 'Search Apiaries', 'apiary-press' ),
				),
				'description'  => __( 'Apiaries managed in Apiary Press.', 'apiary-press' ),
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
	 * Register the apiary post meta fields for the apiary post type.
	 */
	public static function register_meta(): void {
	}
}
