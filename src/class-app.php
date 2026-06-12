<?php
/**
 * Main plugin class and bootstrap.
 *
 * @package ApiaryPress
 */

namespace ApiaryPress;

use WpApp\WpApp;
use WpApp\BaseApp;

/**
 * Main plugin class. Initializes the app, registers routes, and sets up storage.
 */
class App extends BaseApp {
	/**
	 * Initialize the app, register routes, and set up storage.
	 */
	public function __construct() {
		// See https://github.com/akirk/wp-app for documentation.
		$this->app = new WpApp(
			$this->get_template_dir(),
			$this->get_url_path(),
			array(
				'require_login'      => true,
				'require_capability' => 'edit_posts',
				'app_name'           => 'Apiary Press',
			)
		);

		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'init', array( $this, 'register_hive_meta' ) );
		add_action( 'init', array( $this, 'register_visit_meta' ) );
	}

	/**
	 * Get the base URL path for the app. This is used to route requests and generate links.
	 */
	protected function get_url_path(): string {
		return 'apiary-press';
	}

	/**
	 * Get the directory path for the app's templates. This is used by the routing system to locate template files.
	 */
	protected function get_template_dir(): string {
		return dirname( __DIR__ ) . '/templates';
	}

	/**
	 * Initialize all the routes.
	 */
	protected function setup_routes(): void {
		$this->app->route( '' );
		$this->app->route( 'hive/new', 'hive-form.php' );
		$this->app->route( 'hive/{id}', 'hive.php' );
		$this->app->route( 'hive/{id}/edit', 'hive-form.php' );
		$this->app->route( 'hive/{id}/qr', 'hive-qr.php' );
		$this->app->route( 'hive/{id}/visit/{hive_visit}', 'visit.php' );
	}

	/**
	 * Generate the storage.
	 */
	protected function setup_database(): void {

	}

	/**
	 * Add the app's menu items.
	 */
	protected function setup_menu(): void {
		$this->app->add_menu_item(
			'hives',
			__( 'Hives', 'apiary-press' ),
			home_url( '/' . $this->get_url_path() . '/' )
		);
	}

	/**
	 * Register the hive and hive visit custom post types.
	 */
	public function register_post_types(): void {
		register_post_type(
			Hive::HIVE_POST_TYPE,
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

		register_post_type(
			Visit::HIVE_VISIT_POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Hive Visits', 'apiary-press' ),
					'singular_name' => __( 'Hive Visit', 'apiary-press' ),
					'add_new_item'  => __( 'Add New Hive Visit', 'apiary-press' ),
					'edit_item'     => __( 'Edit Hive Visit', 'apiary-press' ),
					'new_item'      => __( 'New Hive Visit', 'apiary-press' ),
					'view_item'     => __( 'View Hive Visit', 'apiary-press' ),
					'search_items'  => __( 'Search Hive Visits', 'apiary-press' ),
				),
				'description'  => __( 'Inspection visits for Apiary Press hives.', 'apiary-press' ),
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
	 * Register the location post meta fields for the hive post type.
	 */
	public function register_hive_meta(): void {
		Hive::register_meta();
	}

	/**
	 * Register the boolean and weather post meta fields for the hive visit post type.
	 */
	public function register_visit_meta(): void {
		Visit::register_meta();
	}

	/**
	 * Sanitize a meta value into a boolean.
	 *
	 * @param mixed $value The value to sanitize.
	 */
	public function sanitize_boolean_meta( $value ): bool {
		return rest_sanitize_boolean( $value );
	}

	/**
	 * Sanitize a meta value into a float, defaulting to 0.0.
	 *
	 * @param mixed $value The value to sanitize.
	 */
	public function sanitize_number_meta( $value ): float {
		return is_numeric( $value ) ? (float) $value : 0.0;
	}

	/**
	 * Get a hive's validated latitude/longitude, or an empty array if missing or out of range.
	 *
	 * @param int $hive_id The ID of the hive post.
	 * @return array The validated coordinates, or an empty array if invalid.
	 */
	public static function get_hive_coordinates( int $hive_id ): array {
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

	/**
	 * Activation hook: register post types and meta, then flush rewrite rules.
	 */
	public function activate(): void {
		/*
		 * If using BaseStorage, create/update custom tables here:
		 *
		 * $this->storage->create_tables();
		 */
		$this->register_post_types();
		$this->register_hive_meta();
		$this->register_visit_meta();

		flush_rewrite_rules();
	}

	/**
	 * Deactivation hook: flush rewrite rules.
	 */
	public function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Helper function to generate a URL for the app, given a path relative to the app's base URL.
	 *
	 * @param string $path The path relative to the app's base URL.
	 * @return string The full URL.
	 */
	public static function get_url( string $path = '' ): string {
		return trailingslashit( home_url( '/apiary-press/' . ltrim( $path, '/' ) ) );
	}
}
