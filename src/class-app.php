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

		load_plugin_textdomain( 'apiary-press', false, 'apiary-press/languages' );
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'init', array( $this, 'register_apiary_meta' ) );
		add_action( 'init', array( $this, 'register_hive_meta' ) );
		add_action( 'init', array( $this, 'register_visit_meta' ) );

		add_action( 'wp_app_head', array( $this, 'enqueue_assets' ), 20 );
	}

	/**
	 * Enqueue the shared Apiary Press stylesheet for every app template.
	 */
	public function enqueue_assets(): void {
		$asset_path = dirname( __DIR__ ) . '/assets/apiary-press.css';
		$asset_url  = plugins_url( 'assets/apiary-press.css', dirname( __DIR__ ) . '/apiary-press.php' );
		$version    = file_exists( $asset_path ) ? (string) filemtime( $asset_path ) : '1.0.0';

		wp_app_enqueue_style( 'apiary-press', $asset_url, array(), $version );
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
		$this->app->route( 'apiary/new', 'apiary-form.php' );
		$this->app->route( 'apiary/{id}', 'apiary.php' );
		$this->app->route( 'apiary/{id}/edit', 'apiary-form.php' );
		$this->app->route( 'apiary/{apiary_id}/hive/new', 'hive-form.php' );
		$this->app->route( 'apiary/{apiary_id}/hive/{id}', 'hive.php' );
		$this->app->route( 'apiary/{apiary_id}/hive/{id}/edit', 'hive-form.php' );
		$this->app->route( 'apiary/{apiary_id}/hive/{id}/qr', 'hive-qr.php' );
		$this->app->route( 'apiary/{apiary_id}/hive/{id}/visit/{hive_visit}', 'visit.php' );
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
			'apiaries',
			__( 'Apiaries', 'apiary-press' ),
			home_url( '/' . $this->get_url_path() . '/' )
		);
	}

	/**
	 * Register the hive and hive visit custom post types.
	 */
	public function register_post_types(): void {
		Hive::register_post_types();
		Visit::register_post_types();
	}

	/**
	 * Register the location post meta fields for the apiary post type.
	 */
	public function register_apiary_meta(): void {
		Apiary::register_meta();
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
	 * Activation hook: register post types and meta, then flush rewrite rules.
	 */
	public function activate(): void {
		/*
		 * If using BaseStorage, create/update custom tables here:
		 *
		 * $this->storage->create_tables();
		 */
		$this->register_post_types();
		$this->register_apiary_meta();
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
