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
	public const HIVE_POST_TYPE       = 'ap_hive';
	public const HIVE_VISIT_POST_TYPE = 'ap_hive_visit';

	public const VISIT_BOOLEAN_META_KEYS = array(
		'eggs',
		'larvae',
		'capped_brood',
		'queen_cells',
		'saw_queen',
		'added_super',
		'check_soon',
	);

	public const HIVE_LOCATION_META_KEYS = array(
		'latitude',
		'longitude',
	);

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

		register_post_type(
			self::HIVE_VISIT_POST_TYPE,
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
				'show_in_menu' => 'edit.php?post_type=' . self::HIVE_POST_TYPE,
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
		foreach ( self::HIVE_LOCATION_META_KEYS as $meta_key ) {
			register_post_meta(
				self::HIVE_POST_TYPE,
				$meta_key,
				array(
					'type'              => 'number',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => array( $this, 'sanitize_number_meta' ),
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
	 * Register the boolean and weather post meta fields for the hive visit post type.
	 */
	public function register_visit_meta(): void {
		foreach ( self::VISIT_BOOLEAN_META_KEYS as $meta_key ) {
			register_post_meta(
				self::HIVE_VISIT_POST_TYPE,
				$meta_key,
				array(
					'type'              => 'boolean',
					'single'            => true,
					'default'           => false,
					'show_in_rest'      => true,
					'sanitize_callback' => array( $this, 'sanitize_boolean_meta' ),
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

		foreach ( Weather::VISIT_WEATHER_META_TYPES as $meta_key => $type ) {
			$sanitize_callback = 'string' === $type
				? array( $this, 'sanitize_text_meta' )
				: ( 'integer' === $type ? array( $this, 'sanitize_integer_meta' ) : array( $this, 'sanitize_number_meta' ) );

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
	 * Sanitize a meta value into an integer, defaulting to 0.
	 *
	 * @param mixed $value The value to sanitize.
	 */
	public function sanitize_integer_meta( $value ): int {
		return is_numeric( $value ) ? (int) $value : 0;
	}

	/**
	 * Sanitize a meta value into a plain text string.
	 *
	 * @param mixed $value The value to sanitize.
	 */
	public function sanitize_text_meta( $value ): string {
		return sanitize_text_field( (string) $value );
	}

	/**
	 * Get the translated labels for the visit boolean meta keys.
	 */
	public static function get_visit_boolean_meta_labels(): array {
		return array(
			'eggs'         => __( 'Eggs', 'apiary-press' ),
			'larvae'       => __( 'Larvae', 'apiary-press' ),
			'capped_brood' => __( 'Capped brood', 'apiary-press' ),
			'queen_cells'  => __( 'Queen cells', 'apiary-press' ),
			'saw_queen'    => __( 'Saw queen', 'apiary-press' ),
			'added_super'  => __( 'Added super', 'apiary-press' ),
			'check_soon'   => __( 'Check soon', 'apiary-press' ),
		);
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
}
