<?php
/**
 * Weather forecast helpers for hive visits.
 *
 * @package ApiaryPress
 */

namespace ApiaryPress;

/**
 * Fetch, store, and format MET Weather API weather snapshots for hive visits.
 */
class Weather {
	// MET Weather API units.
	public const FORECAST_UNITS = array(
		'air_pressure_at_sea_level'       => 'string',
		'air_temperature'                 => 'string',
		'air_temperature_max'             => 'string',
		'air_temperature_min'             => 'string',
		'cloud_area_fraction'             => 'string',
		'cloud_area_fraction_high'        => 'string',
		'cloud_area_fraction_low'         => 'string',
		'cloud_area_fraction_medium'      => 'string',
		'dew_point_temperature'           => 'string',
		'fog_area_fraction'               => 'string',
		'precipitation_amount'            => 'string',
		'precipitation_amount_max'        => 'string',
		'precipitation_amount_min'        => 'string',
		'probability_of_precipitation'    => 'string',
		'probability_of_thunder'          => 'string',
		'relative_humidity'               => 'string',
		'ultraviolet_index_clear_sky_max' => 'string',
		'wind_from_direction'             => 'string',
		'wind_speed'                      => 'string',
		'wind_speed_of_gust'              => 'string',
		'symbol_code'                     => 'string', // Icon.
	);

	/**
	 * Get list of human-readable forecast details for a specific hour in a MET Weather API response, keyed with translated labels.
	 *
	 * @param string $name The specific forecast detail name.
	 * @param string $value The specific forecast detail value.
	 *
	 * @return string|null A human-readable forecast detail with a translated label, or null if not available.
	 */
	public static function get_forecast_display_value( string $name, string $value ): string|null {
		if ( ! isset( self::FORECAST_UNITS[ $name ] ) ) {
			null;
		}

		$value = self::format_weather_number( $value );

		switch ( $name ) {
			case 'air_pressure_at_sea_level':
				return sprintf(
					// translators: %s: the air pressure value with unit, e.g. "42 hPa".
					__( 'Air pressure at sea level %s hPa', 'apiary-press' ),
					esc_html( $value )
				);
			case 'air_temperature':
				return sprintf(
					// translators: %s: the air temperature value with unit, e.g. "15 °C".
					__( 'Air temperature %s °C', 'apiary-press' ),
					esc_html( $value )
				);
			case 'cloud_area_fraction':
				return sprintf(
					// translators: %s: the cloud area fraction value with unit, e.g. "15 %".
					__( 'Cloud area fraction %s%%', 'apiary-press' ),
					esc_html( $value )
				);
			case 'precipitation_amount':
				return sprintf(
					// translators: %s: the precipitation amount value with unit, e.g. "15 mm".
					__( 'Precipitation amount %s mm', 'apiary-press' ),
					esc_html( $value )
				);
			case 'relative_humidity':
				return sprintf(
					// translators: %s: the relative humidity value with unit, e.g. "15 %".
					__( 'Relative humidity %s%%', 'apiary-press' ),
					esc_html( $value )
				);
		}

		return null;
	}

	/**
	 * Get the forecast icon code for a specific hour in a MET Weather API response, or null if not available.
	 *
	 * @param array  $forecast_step The specific forecast step data (ForecastTimeStep) from the MET Weather API.
	 * @param string $data_type The specific data type to retrieve from the timeseries, e.g., 'instant', 'next_1_hours'.
	 *
	 * @return string|null The forecast icon code, or null if not available.
	 */
	public static function get_forecast_icon_url( array $forecast_step, string $data_type = 'instant' ): ?string {
		if (
			empty( $forecast_step[ $data_type ] ) ||
			! array_key_exists( 'summary', $forecast_step[ $data_type ] ) ||
			! is_array( $forecast_step[ $data_type ]['summary'] ) ) {
			return null;
		}

		return $forecast_step[ $data_type ]['summary']['symbol_code'] ?? null;
	}

	/**
	 * Render an <img> tag for a MET Weather symbol code, or '' if the code is invalid or the SVG is missing.
	 *
	 * @param string $symbol_code The MET Weather symbol code, e.g. 'clearsky_day'.
	 * @param int    $size        Pixel size for the rendered icon (width and height).
	 */
	public static function render_symbol_icon_html( string $symbol_code, int $size = 64 ): string {
		if ( ! preg_match( '/^[a-z_]+$/', $symbol_code ) ) {
			return '';
		}

		$plugin_root = dirname( __DIR__ );

		if ( ! file_exists( $plugin_root . '/assets/weather/' . $symbol_code . '.svg' ) ) {
			return '';
		}

		$icon_url = plugins_url( 'assets/weather/' . $symbol_code . '.svg', $plugin_root . '/apiary-press.php' );

		return sprintf(
			'<img class="weather-icon" src="%1$s" alt="%2$s" width="%3$d" height="%3$d">',
			esc_url( $icon_url ),
			esc_attr( str_replace( '_', ' ', $symbol_code ) ),
			(int) $size
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
	 * Fetch and store a weather snapshot for a visit. Returns an error message, or an empty string on success.
	 *
	 * @param int $visit_id The ID of the hive visit post.
	 * @param int $hive_id  The ID of the hive post.
	 * @return string An error message if the snapshot could not be stored, or an empty string on success.
	 */
	public static function store_visit_weather_snapshot( int $visit_id, int $hive_id ): string {
		self::clear_visit_weather_snapshot( $visit_id );

		$coordinates = Hive::get_coordinates( $hive_id );

		if ( empty( $coordinates ) ) {
			$message = __( 'Hive coordinates are missing.', 'apiary-press' );
			update_post_meta( $visit_id, 'weather_error', $message );

			return $message;
		}

		$snapshot = self::fetch_met_weather_snapshot(
			$coordinates['latitude'],
			$coordinates['longitude'],
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
		foreach ( array_keys( self::FORECAST_UNITS ) as $meta_key ) {
			delete_post_meta( $visit_id, $meta_key );
		}
	}

	/**
	 * Query the Open-Meteo API for the hour nearest a visit and return a weather meta array, or a WP_Error.
	 *
	 * @param float $latitude   The latitude of the location to query.
	 * @param float $longitude  The longitude of the location to query.
	 * @return array|\WP_Error The weather snapshot as an associative array, or a WP_Error on failure.
	 */
	public static function fetch_met_weather_snapshot( float $latitude, float $longitude ) {
		$endpoint = 'https://api.met.no/weatherapi/locationforecast/2.0/compact';

		$query_args = array(
			'lat' => $latitude,
			'lon' => $longitude,
		);

		$url = add_query_arg( $query_args, $endpoint );

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 8,
				'user-agent' => 'ApiaryPress/1.0 admin@w.org',
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

		if ( ! is_array( $body ) || empty( $body['properties'] ) || ! is_array( $body['properties']['timeseries'] ) ) {
			return new \WP_Error( 'apiary_press_weather_invalid_response', __( 'Weather lookup returned an invalid response.', 'apiary-press' ) );
		}

		$timeseries = $body['properties']['timeseries'];

		if (
			isset( $timeseries[0] ) &&
			isset( $timeseries[0]['data'] ) &&
			isset( $timeseries[0]['data']['instant'] )
		) {
			$ret  = $timeseries[0]['data']['instant']['details'] ?? array();
			$icon = self::get_forecast_icon_url( $timeseries[0]['data'], 'instant' );

			if ( empty( $icon ) ) {
				// If no symbol code is available for current instant, try to get it from the 'next_1_hours' data as a fallback.
				$icon = self::get_forecast_icon_url( $timeseries[0]['data'], 'next_1_hours' );
			}

			if ( ! empty( $icon ) ) {
				$ret['symbol_code'] = $icon;
			}

			return $ret;
		}

		return new \WP_Error( 'apiary_press_weather_no_data', __( 'Weather lookup did not return any forecast data.', 'apiary-press' ) );
	}
}
