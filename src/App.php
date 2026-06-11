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

	public const VISIT_WEATHER_META_TYPES = array(
		'weather_temperature_2m'       => 'number',
		'weather_relative_humidity_2m' => 'integer',
		'weather_precipitation'        => 'number',
		'weather_weather_code'         => 'integer',
		'weather_description'          => 'string',
		'weather_cloud_cover'          => 'integer',
		'weather_wind_speed_10m'       => 'number',
		'weather_wind_direction_10m'   => 'integer',
		'weather_wind_gusts_10m'       => 'number',
		'weather_surface_pressure'     => 'number',
		'weather_observed_at'          => 'string',
		'weather_source'               => 'string',
		'weather_latitude'             => 'number',
		'weather_longitude'            => 'number',
		'weather_error'                => 'string',
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

		foreach ( self::VISIT_WEATHER_META_TYPES as $meta_key => $type ) {
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
	 * Get the translated labels for the displayable weather meta keys.
	 */
	public static function get_weather_meta_labels(): array {
		return array(
			'weather_description'          => __( 'Conditions', 'apiary-press' ),
			'weather_temperature_2m'       => __( 'Temperature', 'apiary-press' ),
			'weather_relative_humidity_2m' => __( 'Humidity', 'apiary-press' ),
			'weather_precipitation'        => __( 'Precipitation', 'apiary-press' ),
			'weather_cloud_cover'          => __( 'Cloud cover', 'apiary-press' ),
			'weather_wind_speed_10m'       => __( 'Wind speed', 'apiary-press' ),
			'weather_wind_direction_10m'   => __( 'Wind direction', 'apiary-press' ),
			'weather_wind_gusts_10m'       => __( 'Wind gusts', 'apiary-press' ),
			'weather_surface_pressure'     => __( 'Pressure', 'apiary-press' ),
			'weather_observed_at'          => __( 'Observed at', 'apiary-press' ),
			'weather_source'               => __( 'Source', 'apiary-press' ),
		);
	}

	/**
	 * Format a numeric weather value to one decimal place, trimming trailing zeros.
	 *
	 * @param mixed $value The value to format.
	 */
	public static function format_weather_number( $value ): string {
		$formatted = sprintf( '%.1f', (float) $value );
		return rtrim( rtrim( $formatted, '0' ), '.' );
	}

	/**
	 * Format a stored weather meta value for display, including its unit.
	 *
	 * @param string $meta_key The meta key of the weather value.
	 * @param mixed  $value    The raw meta value to format.
	 */
	public static function format_weather_meta_value( string $meta_key, $value ): string {
		if ( '' === (string) $value ) {
			return '';
		}

		if ( 'weather_observed_at' === $meta_key ) {
			return str_replace( 'T', ' ', sanitize_text_field( (string) $value ) );
		}

		if ( in_array( $meta_key, array( 'weather_description', 'weather_source' ), true ) ) {
			return sanitize_text_field( (string) $value );
		}

		if ( ! is_numeric( $value ) ) {
			return sanitize_text_field( (string) $value );
		}

		$number = self::format_weather_number( $value );

		switch ( $meta_key ) {
			case 'weather_temperature_2m':
				return sprintf( '%s C', $number );
			case 'weather_relative_humidity_2m':
			case 'weather_cloud_cover':
				return sprintf( '%s%%', $number );
			case 'weather_precipitation':
				return sprintf( '%s mm', $number );
			case 'weather_wind_speed_10m':
			case 'weather_wind_gusts_10m':
				return sprintf( '%s km/h', $number );
			case 'weather_wind_direction_10m':
				return sprintf( '%s deg', $number );
			case 'weather_surface_pressure':
				return sprintf( '%s hPa', $number );
		}

		return $number;
	}

	/**
	 * Get the formatted, non-empty weather meta values for a visit, keyed with labels.
	 *
	 * @param int $visit_id The ID of the hive visit post.
	 */
	public static function get_visit_weather_display_values( int $visit_id ): array {
		$weather_values = array();

		foreach ( self::get_weather_meta_labels() as $meta_key => $label ) {
			$formatted_value = self::format_weather_meta_value( $meta_key, get_post_meta( $visit_id, $meta_key, true ) );

			if ( '' !== $formatted_value ) {
				$weather_values[ $meta_key ] = array(
					'label' => $label,
					'value' => $formatted_value,
				);
			}
		}

		return $weather_values;
	}

	/**
	 * Build a short weather summary (description, temperature, humidity, etc.) for a visit.
	 *
	 * @param int $visit_id The ID of the hive visit post.
	 */
	public static function get_visit_weather_summary( int $visit_id ): array {
		$summary = array();

		$description = self::format_weather_meta_value( 'weather_description', get_post_meta( $visit_id, 'weather_description', true ) );

		if ( '' !== $description ) {
			$summary['description'] = $description;
		}

		$temperature = self::format_weather_meta_value( 'weather_temperature_2m', get_post_meta( $visit_id, 'weather_temperature_2m', true ) );

		if ( '' !== $temperature ) {
			$summary['temperature'] = $temperature;
		}

		$humidity = self::format_weather_meta_value( 'weather_relative_humidity_2m', get_post_meta( $visit_id, 'weather_relative_humidity_2m', true ) );

		if ( '' !== $humidity ) {
			$summary['humidity'] = sprintf(
				// translators: %s: humidity value.
				__( '%s humidity', 'apiary-press' ),
				$humidity
			);
		}

		$precipitation = self::format_weather_meta_value( 'weather_precipitation', get_post_meta( $visit_id, 'weather_precipitation', true ) );

		if ( '' !== $precipitation ) {
			$summary['precipitation'] = sprintf(
				// translators: %s: precipitation value.
				__( '%s precipitation', 'apiary-press' ),
				$precipitation
			);
		}

		$wind_speed = self::format_weather_meta_value( 'weather_wind_speed_10m', get_post_meta( $visit_id, 'weather_wind_speed_10m', true ) );

		if ( '' !== $wind_speed ) {
			$summary['wind'] = sprintf(
				// translators: %s: wind speed value.
				__( 'Wind %s', 'apiary-press' ),
				$wind_speed
			);
		}

		return $summary;
	}

	/**
	 * Map an Open-Meteo WMO weather code to a translated description.
	 *
	 * @param int $code The Open-Meteo weather code.
	 */
	public static function get_weather_code_description( int $code ): string {
		$codes = array(
			0  => __( 'Clear sky', 'apiary-press' ),
			1  => __( 'Mainly clear', 'apiary-press' ),
			2  => __( 'Partly cloudy', 'apiary-press' ),
			3  => __( 'Overcast', 'apiary-press' ),
			45 => __( 'Fog', 'apiary-press' ),
			48 => __( 'Depositing rime fog', 'apiary-press' ),
			51 => __( 'Light drizzle', 'apiary-press' ),
			53 => __( 'Moderate drizzle', 'apiary-press' ),
			55 => __( 'Dense drizzle', 'apiary-press' ),
			56 => __( 'Light freezing drizzle', 'apiary-press' ),
			57 => __( 'Dense freezing drizzle', 'apiary-press' ),
			61 => __( 'Slight rain', 'apiary-press' ),
			63 => __( 'Moderate rain', 'apiary-press' ),
			65 => __( 'Heavy rain', 'apiary-press' ),
			66 => __( 'Light freezing rain', 'apiary-press' ),
			67 => __( 'Heavy freezing rain', 'apiary-press' ),
			71 => __( 'Slight snow fall', 'apiary-press' ),
			73 => __( 'Moderate snow fall', 'apiary-press' ),
			75 => __( 'Heavy snow fall', 'apiary-press' ),
			77 => __( 'Snow grains', 'apiary-press' ),
			80 => __( 'Slight rain showers', 'apiary-press' ),
			81 => __( 'Moderate rain showers', 'apiary-press' ),
			82 => __( 'Violent rain showers', 'apiary-press' ),
			85 => __( 'Slight snow showers', 'apiary-press' ),
			86 => __( 'Heavy snow showers', 'apiary-press' ),
			95 => __( 'Thunderstorm', 'apiary-press' ),
			96 => __( 'Thunderstorm with slight hail', 'apiary-press' ),
			99 => __( 'Thunderstorm with heavy hail', 'apiary-press' ),
		);

		// Translators: %d: the numeric weather code from the Open-Meteo API. This is used when no specific description is available for a code.
		return $codes[ $code ] ?? sprintf( __( 'Weather code %d', 'apiary-press' ), $code );
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
	 * Fetch and store a weather snapshot for a visit. Returns an error message, or an empty string on success.
	 *
	 * @param int    $visit_id   The ID of the hive visit post.
	 * @param int    $hive_id    The ID of the hive post.
	 * @param string $visit_date The date of the visit in Y-m-d format.
	 * @param string $visit_time The time of the visit in H:i format (24-hour).
	 * @return string An error message if the snapshot could not be stored, or an empty string on success.
	 */
	public static function store_visit_weather_snapshot( int $visit_id, int $hive_id, string $visit_date, string $visit_time ): string {
		self::clear_visit_weather_snapshot( $visit_id );

		$coordinates = self::get_hive_coordinates( $hive_id );

		if ( empty( $coordinates ) ) {
			$message = __( 'Hive coordinates are missing.', 'apiary-press' );
			update_post_meta( $visit_id, 'weather_error', $message );

			return $message;
		}

		$snapshot = self::fetch_open_meteo_weather_snapshot(
			$coordinates['latitude'],
			$coordinates['longitude'],
			$visit_date,
			$visit_time
		);

		if ( is_wp_error( $snapshot ) ) {
			$message = $snapshot->get_error_message();
			update_post_meta( $visit_id, 'weather_error', $message );

			return $message;
		}

		foreach ( $snapshot as $meta_key => $value ) {
			if ( '' === $value || null === $value ) {
				continue;
			}

			update_post_meta( $visit_id, $meta_key, $value );
		}

		return '';
	}

	/**
	 * Delete all stored weather meta for a visit.
	 *
	 * @param int $visit_id The ID of the hive visit post.
	 */
	public static function clear_visit_weather_snapshot( int $visit_id ): void {
		foreach ( array_keys( self::VISIT_WEATHER_META_TYPES ) as $meta_key ) {
			delete_post_meta( $visit_id, $meta_key );
		}
	}

	/**
	 * Query the Open-Meteo API for the hour nearest a visit and return a weather meta array, or a WP_Error.
	 *
	 * @param float  $latitude   The latitude of the location to query.
	 * @param float  $longitude  The longitude of the location to query.
	 * @param string $visit_date The date of the visit in Y-m-d format.
	 * @param string $visit_time The time of the visit in H:i format (24-hour).
	 * @return array|\WP_Error The weather snapshot as an associative array, or a WP_Error on failure.
	 */
	public static function fetch_open_meteo_weather_snapshot( float $latitude, float $longitude, string $visit_date, string $visit_time ) {
		$current_date      = current_time( 'Y-m-d' );
		$visit_day         = strtotime( $visit_date . ' 00:00:00' );
		$current_day       = strtotime( $current_date . ' 00:00:00' );
		$days_from_current = false !== $visit_day && false !== $current_day ? (int) floor( ( $visit_day - $current_day ) / DAY_IN_SECONDS ) : 0;

		if ( $days_from_current > 15 ) {
			return new \WP_Error( 'apiary_press_weather_future_range', __( 'Weather lookup is only available within Open-Meteo forecast range.', 'apiary-press' ) );
		}

		$use_forecast_api = $days_from_current >= -92;
		$endpoint         = $use_forecast_api
			? 'https://api.open-meteo.com/v1/forecast'
			: 'https://archive-api.open-meteo.com/v1/archive';

		$variables = array(
			'temperature_2m',
			'relative_humidity_2m',
			'precipitation',
			'weather_code',
			'cloud_cover',
			'wind_speed_10m',
			'wind_direction_10m',
			'wind_gusts_10m',
			'surface_pressure',
		);

		$query_args = array(
			'latitude'           => $latitude,
			'longitude'          => $longitude,
			'start_date'         => $visit_date,
			'end_date'           => $visit_date,
			'hourly'             => implode( ',', $variables ),
			'timezone'           => 'auto',
			'temperature_unit'   => 'celsius',
			'wind_speed_unit'    => 'kmh',
			'precipitation_unit' => 'mm',
		);

		if ( $use_forecast_api && $days_from_current < 0 ) {
			$query_args['past_days'] = min( abs( $days_from_current ), 92 );
		} elseif ( $use_forecast_api && $days_from_current >= 7 ) {
			$query_args['forecast_days'] = min( $days_from_current + 1, 16 );
		}

		$url = add_query_arg( $query_args, $endpoint );

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 8,
				'user-agent' => 'Apiary Press; ' . home_url( '/' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			if ( str_starts_with( $response->get_error_message(), 'cURL error 28' ) ) {
				return new \WP_Error( 'apiary_press_weather_timeout', __( 'Weather system offline.', 'apiary-press' ) );
			}

			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code < 200 || $status_code >= 300 ) {
			// translators: %d: the HTTP status code returned by the Open-Meteo API.
			return new \WP_Error( 'apiary_press_weather_http_error', sprintf( __( 'Weather lookup failed with HTTP %d.', 'apiary-press' ), $status_code ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) || empty( $body['hourly']['time'] ) || ! is_array( $body['hourly']['time'] ) ) {
			return new \WP_Error( 'apiary_press_weather_invalid_response', __( 'Weather lookup returned an invalid response.', 'apiary-press' ) );
		}

		$target_timestamp = strtotime( $visit_date . ' ' . $visit_time );

		if ( false === $target_timestamp ) {
			return new \WP_Error( 'apiary_press_weather_invalid_datetime', __( 'Weather lookup needs a valid visit date and time.', 'apiary-press' ) );
		}

		$nearest_index    = null;
		$nearest_distance = null;

		foreach ( $body['hourly']['time'] as $index => $time ) {
			$time_timestamp = strtotime( str_replace( 'T', ' ', $time ) );

			if ( false === $time_timestamp ) {
				continue;
			}

			$distance = abs( $time_timestamp - $target_timestamp );

			if ( null === $nearest_distance || $distance < $nearest_distance ) {
				$nearest_distance = $distance;
				$nearest_index    = $index;
			}
		}

		if ( null === $nearest_index ) {
			return new \WP_Error( 'apiary_press_weather_no_hour', __( 'Weather lookup did not return an hourly record.', 'apiary-press' ) );
		}

		$hourly       = $body['hourly'];
		$weather_code = isset( $hourly['weather_code'][ $nearest_index ] ) ? (int) $hourly['weather_code'][ $nearest_index ] : 0;

		return array(
			'weather_temperature_2m'       => self::get_hourly_value( $hourly, 'temperature_2m', $nearest_index ),
			'weather_relative_humidity_2m' => self::get_hourly_value( $hourly, 'relative_humidity_2m', $nearest_index ),
			'weather_precipitation'        => self::get_hourly_value( $hourly, 'precipitation', $nearest_index ),
			'weather_weather_code'         => $weather_code,
			'weather_description'          => self::get_weather_code_description( $weather_code ),
			'weather_cloud_cover'          => self::get_hourly_value( $hourly, 'cloud_cover', $nearest_index ),
			'weather_wind_speed_10m'       => self::get_hourly_value( $hourly, 'wind_speed_10m', $nearest_index ),
			'weather_wind_direction_10m'   => self::get_hourly_value( $hourly, 'wind_direction_10m', $nearest_index ),
			'weather_wind_gusts_10m'       => self::get_hourly_value( $hourly, 'wind_gusts_10m', $nearest_index ),
			'weather_surface_pressure'     => self::get_hourly_value( $hourly, 'surface_pressure', $nearest_index ),
			'weather_observed_at'          => isset( $hourly['time'][ $nearest_index ] ) ? sanitize_text_field( $hourly['time'][ $nearest_index ] ) : '',
			'weather_source'               => $use_forecast_api ? 'Open-Meteo Forecast' : 'Open-Meteo Historical',
			'weather_latitude'             => $latitude,
			'weather_longitude'            => $longitude,
		);
	}

	/**
	 * Read a single hourly value from an Open-Meteo response, returning '' when absent.
	 *
	 * @param array  $hourly The 'hourly' section of the Open-Meteo response body.
	 * @param string $key    The specific hourly variable key to read.
	 * @param int    $index  The index of the hour to read, as determined by the nearest time match.
	 * @return float|string The numeric value as a float, or a sanitized string, or an empty string if the value is missing or null.
	 */
	private static function get_hourly_value( array $hourly, string $key, int $index ) {
		if ( ! isset( $hourly[ $key ][ $index ] ) || null === $hourly[ $key ][ $index ] ) {
			return '';
		}

		return is_numeric( $hourly[ $key ][ $index ] ) ? (float) $hourly[ $key ][ $index ] : sanitize_text_field( $hourly[ $key ][ $index ] );
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
