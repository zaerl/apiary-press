<?php

namespace ApiaryPress;

use WpApp\WpApp;
use WpApp\BaseApp;

class App extends BaseApp {
    public const HIVE_POST_TYPE = 'ap_hive';
    public const HIVE_VISIT_POST_TYPE = 'ap_hive_visit';

    public const VISIT_BOOLEAN_META_KEYS = [
        'eggs',
        'larvae',
        'capped_brood',
        'queen_cells',
        'saw_queen',
        'added_super',
        'check_soon',
    ];

    public const HIVE_LOCATION_META_KEYS = [
        'latitude',
        'longitude',
    ];

    public const VISIT_WEATHER_META_TYPES = [
        'weather_temperature_2m'          => 'number',
        'weather_relative_humidity_2m'   => 'integer',
        'weather_precipitation'          => 'number',
        'weather_weather_code'           => 'integer',
        'weather_description'            => 'string',
        'weather_cloud_cover'            => 'integer',
        'weather_wind_speed_10m'         => 'number',
        'weather_wind_direction_10m'     => 'integer',
        'weather_wind_gusts_10m'         => 'number',
        'weather_surface_pressure'       => 'number',
        'weather_observed_at'            => 'string',
        'weather_source'                 => 'string',
        'weather_latitude'               => 'number',
        'weather_longitude'              => 'number',
        'weather_error'                  => 'string',
    ];

    public function __construct() {
        // See https://github.com/akirk/wp-app for documentation.
        $this->app = new WpApp( $this->get_template_dir(), $this->get_url_path(), [
            // Access control
            'require_login'      => true,
            'require_capability' => 'edit_posts',

            // Masterbar
            // 'show_masterbar_for_anonymous' => false,
            // 'show_wp_logo'                 => true,
            // 'show_site_name'               => true,
            // 'show_dark_mode_toggle'        => false,
            // 'clear_admin_bar'              => false,
            // 'add_app_node'                 => false,

            // App identity
            // 'app_name'     => 'Apiary Press',
            // 'my_apps'      => true,
            // 'my_apps_icon' => null,
            'app_name' => 'Apiary Press',
        ] );

        add_action( 'init', [ $this, 'register_post_types' ] );
        add_action( 'init', [ $this, 'register_hive_meta' ] );
        add_action( 'init', [ $this, 'register_visit_meta' ] );

        // Uncomment only when these extension points contain real code.
        // add_action( 'init', [ $this, 'register_taxonomies' ] );
        // add_action( 'wp_dashboard_setup', [ $this, 'register_dashboard_widgets' ] );
        // add_action( 'wp_abilities_api_categories_init', [ $this, 'register_ability_category' ] );
        // add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );
        // add_filter( 'ai_assistant_ability_domains', [ $this, 'register_ai_assistant_ability_domains' ] );
        // add_filter( 'ai_assistant_ability_instructions', [ $this, 'get_ai_assistant_ability_instructions' ], 10, 4 );
    }

    protected function get_url_path(): string {
        return 'apiary-press';
    }

    protected function get_template_dir(): string {
        return dirname( __DIR__ ) . '/templates';
    }

    protected function setup_storage(): void {
        /*
         * Prefer WordPress-native storage before custom tables:
         * - Custom post types and post meta for content-like records.
         * - Taxonomies, terms, and term meta for shared categories or labels.
         * - User meta for per-user settings, preferences, and profile data.
         *
         * Use BaseStorage only when native entities do not fit, such as
         * high-volume rows, relational data, or non-content records.
         *
         * If you do need custom tables:
         *
         * class ApiaryPressStorage extends BaseStorage {
         *     protected function get_schema() {
         *         $charset_collate = $this->wpdb->get_charset_collate();
         *         return [
         *             "CREATE TABLE {$this->wpdb->prefix}apiary_press_items (
         *                 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
         *                 user_id bigint(20) unsigned NOT NULL,
         *                 title varchar(255) NOT NULL,
         *                 created_at datetime DEFAULT CURRENT_TIMESTAMP,
         *                 PRIMARY KEY (id),
         *                 KEY user_id (user_id)
         *             ) $charset_collate;",
         *         ];
         *     }
         * }
         *
         * Then in __construct(): $this->storage = new ApiaryPressStorage();
         * And in activate():     $this->storage->create_tables();
         */
    }

    protected function setup_database(): void {
        $this->setup_storage();
    }

    protected function setup_routes(): void {
        $this->app->route( '' );
        $this->app->route( 'hive/new', 'hive-form.php' );
        $this->app->route( 'hive/{id}', 'hive.php' );
        $this->app->route( 'hive/{id}/edit', 'hive-form.php' );
        $this->app->route( 'hive/{id}/qr', 'hive-qr.php' );
        $this->app->route( 'hive/{id}/visit/{hive_visit}', 'visit.php' );
    }

    protected function setup_menu(): void {
        $this->app->add_menu_item(
            'hives',
            __( 'Hives', 'apiary-press' ),
            home_url( '/' . $this->get_url_path() . '/' )
        );
    }

    public function register_post_types(): void {
        register_post_type( self::HIVE_POST_TYPE, [
            'labels'       => [
                'name'          => __( 'Hives', 'apiary-press' ),
                'singular_name' => __( 'Hive', 'apiary-press' ),
                'add_new_item'  => __( 'Add New Hive', 'apiary-press' ),
                'edit_item'     => __( 'Edit Hive', 'apiary-press' ),
                'new_item'      => __( 'New Hive', 'apiary-press' ),
                'view_item'     => __( 'View Hive', 'apiary-press' ),
                'search_items'  => __( 'Search Hives', 'apiary-press' ),
            ],
            'description'  => __( 'Bee hives managed in Apiary Press.', 'apiary-press' ),
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'menu_icon'    => 'dashicons-location-alt',
            'supports'     => [ 'title', 'editor', 'author' ],
            'map_meta_cap' => true,
        ] );

        register_post_type( self::HIVE_VISIT_POST_TYPE, [
            'labels'       => [
                'name'          => __( 'Hive Visits', 'apiary-press' ),
                'singular_name' => __( 'Hive Visit', 'apiary-press' ),
                'add_new_item'  => __( 'Add New Hive Visit', 'apiary-press' ),
                'edit_item'     => __( 'Edit Hive Visit', 'apiary-press' ),
                'new_item'      => __( 'New Hive Visit', 'apiary-press' ),
                'view_item'     => __( 'View Hive Visit', 'apiary-press' ),
                'search_items'  => __( 'Search Hive Visits', 'apiary-press' ),
            ],
            'description'  => __( 'Inspection visits for Apiary Press hives.', 'apiary-press' ),
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => 'edit.php?post_type=' . self::HIVE_POST_TYPE,
            'show_in_rest' => true,
            'supports'     => [ 'title', 'editor', 'author', 'custom-fields' ],
            'map_meta_cap' => true,
        ] );
    }

    public function register_hive_meta(): void {
        foreach ( self::HIVE_LOCATION_META_KEYS as $meta_key ) {
            register_post_meta( self::HIVE_POST_TYPE, $meta_key, [
                'type'              => 'number',
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => [ $this, 'sanitize_number_meta' ],
                'auth_callback'     => function( ...$args ) {
                    $post_id = isset( $args[2] ) ? absint( $args[2] ) : 0;
                    $user_id = isset( $args[3] ) ? absint( $args[3] ) : get_current_user_id();

                    if ( $post_id ) {
                        return user_can( $user_id, 'edit_post', $post_id );
                    }

                    return user_can( $user_id, 'edit_posts' );
                },
            ] );
        }
    }

    public function register_visit_meta(): void {
        foreach ( self::VISIT_BOOLEAN_META_KEYS as $meta_key ) {
            register_post_meta( self::HIVE_VISIT_POST_TYPE, $meta_key, [
                'type'              => 'boolean',
                'single'            => true,
                'default'           => false,
                'show_in_rest'      => true,
                'sanitize_callback' => [ $this, 'sanitize_boolean_meta' ],
                'auth_callback'     => function( ...$args ) {
                    $post_id = isset( $args[2] ) ? absint( $args[2] ) : 0;
                    $user_id = isset( $args[3] ) ? absint( $args[3] ) : get_current_user_id();

                    if ( $post_id ) {
                        return user_can( $user_id, 'edit_post', $post_id );
                    }

                    return user_can( $user_id, 'edit_posts' );
                },
            ] );
        }

        foreach ( self::VISIT_WEATHER_META_TYPES as $meta_key => $type ) {
            $sanitize_callback = 'string' === $type
                ? [ $this, 'sanitize_text_meta' ]
                : ( 'integer' === $type ? [ $this, 'sanitize_integer_meta' ] : [ $this, 'sanitize_number_meta' ] );

            register_post_meta( self::HIVE_VISIT_POST_TYPE, $meta_key, [
                'type'              => $type,
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => $sanitize_callback,
                'auth_callback'     => function( ...$args ) {
                    $post_id = isset( $args[2] ) ? absint( $args[2] ) : 0;
                    $user_id = isset( $args[3] ) ? absint( $args[3] ) : get_current_user_id();

                    if ( $post_id ) {
                        return user_can( $user_id, 'edit_post', $post_id );
                    }

                    return user_can( $user_id, 'edit_posts' );
                },
            ] );
        }
    }

    public function sanitize_boolean_meta( $value ): bool {
        return rest_sanitize_boolean( $value );
    }

    public function sanitize_number_meta( $value ): float {
        return is_numeric( $value ) ? (float) $value : 0.0;
    }

    public function sanitize_integer_meta( $value ): int {
        return is_numeric( $value ) ? (int) $value : 0;
    }

    public function sanitize_text_meta( $value ): string {
        return sanitize_text_field( (string) $value );
    }

    public static function get_visit_boolean_meta_labels(): array {
        return [
            'eggs'          => __( 'Eggs', 'apiary-press' ),
            'larvae'        => __( 'Larvae', 'apiary-press' ),
            'capped_brood'  => __( 'Capped brood', 'apiary-press' ),
            'queen_cells'   => __( 'Queen cells', 'apiary-press' ),
            'saw_queen'     => __( 'Saw queen', 'apiary-press' ),
            'added_super'   => __( 'Added super', 'apiary-press' ),
            'check_soon'    => __( 'Check soon', 'apiary-press' ),
        ];
    }

    public static function get_weather_meta_labels(): array {
        return [
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
        ];
    }

    public static function format_weather_number( $value ): string {
        $formatted = sprintf( '%.1f', (float) $value );
        return rtrim( rtrim( $formatted, '0' ), '.' );
    }

    public static function format_weather_meta_value( string $meta_key, $value ): string {
        if ( '' === (string) $value ) {
            return '';
        }

        if ( 'weather_observed_at' === $meta_key ) {
            return str_replace( 'T', ' ', sanitize_text_field( (string) $value ) );
        }

        if ( in_array( $meta_key, [ 'weather_description', 'weather_source' ], true ) ) {
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

    public static function get_visit_weather_display_values( int $visit_id ): array {
        $weather_values = [];

        foreach ( self::get_weather_meta_labels() as $meta_key => $label ) {
            $formatted_value = self::format_weather_meta_value( $meta_key, get_post_meta( $visit_id, $meta_key, true ) );

            if ( '' !== $formatted_value ) {
                $weather_values[ $meta_key ] = [
                    'label' => $label,
                    'value' => $formatted_value,
                ];
            }
        }

        return $weather_values;
    }

    public static function get_visit_weather_summary( int $visit_id ): array {
        $summary = [];

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
                /* translators: %s: humidity value */
                __( '%s humidity', 'apiary-press' ),
                $humidity
            );
        }

        $precipitation = self::format_weather_meta_value( 'weather_precipitation', get_post_meta( $visit_id, 'weather_precipitation', true ) );
        if ( '' !== $precipitation ) {
            $summary['precipitation'] = sprintf(
                /* translators: %s: precipitation value */
                __( '%s precipitation', 'apiary-press' ),
                $precipitation
            );
        }

        $wind_speed = self::format_weather_meta_value( 'weather_wind_speed_10m', get_post_meta( $visit_id, 'weather_wind_speed_10m', true ) );
        if ( '' !== $wind_speed ) {
            $summary['wind'] = sprintf(
                /* translators: %s: wind speed value */
                __( 'Wind %s', 'apiary-press' ),
                $wind_speed
            );
        }

        return $summary;
    }

    public static function get_weather_code_description( int $code ): string {
        $codes = [
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
        ];

        return $codes[ $code ] ?? sprintf( __( 'Weather code %d', 'apiary-press' ), $code );
    }

    public static function get_hive_coordinates( int $hive_id ): array {
        $latitude  = get_post_meta( $hive_id, 'latitude', true );
        $longitude = get_post_meta( $hive_id, 'longitude', true );

        if ( ! is_numeric( $latitude ) || ! is_numeric( $longitude ) ) {
            return [];
        }

        $latitude  = (float) $latitude;
        $longitude = (float) $longitude;

        if ( $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180 ) {
            return [];
        }

        return [
            'latitude'  => $latitude,
            'longitude' => $longitude,
        ];
    }

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

    public static function clear_visit_weather_snapshot( int $visit_id ): void {
        foreach ( array_keys( self::VISIT_WEATHER_META_TYPES ) as $meta_key ) {
            delete_post_meta( $visit_id, $meta_key );
        }
    }

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

        $variables = [
            'temperature_2m',
            'relative_humidity_2m',
            'precipitation',
            'weather_code',
            'cloud_cover',
            'wind_speed_10m',
            'wind_direction_10m',
            'wind_gusts_10m',
            'surface_pressure',
        ];

        $query_args = [
            'latitude'           => $latitude,
            'longitude'          => $longitude,
            'start_date'         => $visit_date,
            'end_date'           => $visit_date,
            'hourly'             => implode( ',', $variables ),
            'timezone'           => 'auto',
            'temperature_unit'   => 'celsius',
            'wind_speed_unit'    => 'kmh',
            'precipitation_unit' => 'mm',
        ];

        if ( $use_forecast_api && $days_from_current < 0 ) {
            $query_args['past_days'] = min( abs( $days_from_current ), 92 );
        } elseif ( $use_forecast_api && $days_from_current >= 7 ) {
            $query_args['forecast_days'] = min( $days_from_current + 1, 16 );
        }

        $url = add_query_arg( $query_args, $endpoint );

        $response = wp_remote_get( $url, [
            'timeout'    => 8,
            'user-agent' => 'Apiary Press; ' . home_url( '/' ),
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );

        if ( $status_code < 200 || $status_code >= 300 ) {
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

        return [
            'weather_temperature_2m'        => self::get_hourly_value( $hourly, 'temperature_2m', $nearest_index ),
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
        ];
    }

    private static function get_hourly_value( array $hourly, string $key, int $index ) {
        if ( ! isset( $hourly[ $key ][ $index ] ) || null === $hourly[ $key ][ $index ] ) {
            return '';
        }

        return is_numeric( $hourly[ $key ][ $index ] ) ? (float) $hourly[ $key ][ $index ] : sanitize_text_field( $hourly[ $key ][ $index ] );
    }

    public function register_taxonomies(): void {
        /*
         * Register taxonomies here. This method runs on WordPress init.
         *
         * register_taxonomy( 'apiary_press_category', 'apiary_press_item', [
         *     'label'        => 'Apiary Press Categories',
         *     'hierarchical' => true,
         *     'show_ui'      => true,
         *     'show_in_rest' => true,
         * ] );
         */
    }

    public function register_dashboard_widgets(): void {
        /*
         * Register dashboard widgets here. This method runs on
         * wp_dashboard_setup.
         *
         * wp_add_dashboard_widget(
         *     'apiary_press_dashboard',
         *     'Apiary Press',
         *     [ $this, 'render_dashboard_widget' ]
         * );
         */
    }

    public function render_dashboard_widget(): void {
        /*
         * echo esc_html__( 'Add your dashboard summary here.', 'apiary-press' );
         */
    }

    public function register_ability_category(): void {
        // Register an Abilities API category for this plugin.
        //
        // if ( ! function_exists( 'wp_register_ability_category' ) ) {
        //     return;
        // }
        //
        // wp_register_ability_category( 'apiary-press', [
        //     'label'       => __( 'Apiary Press', 'apiary-press' ),
        //     'description' => __( 'Abilities for Apiary Press.', 'apiary-press' ),
        // ] );
    }

    public function register_abilities(): void {
        // Register focused WordPress Abilities here. AI Assistant can discover
        // and execute these instead of guessing plugin internals.
        // See https://github.com/akirk/ai-assistant/blob/main/docs/plugin-integration.md
        // for AI Assistant-specific guidance.
        //
        // if ( ! function_exists( 'wp_register_ability' ) ) {
        //     return;
        // }
        //
        // wp_register_ability( 'apiary-press/list-items', [
        //     'label'               => __( 'List Apiary Press Items', 'apiary-press' ),
        //     'description'         => 'Returns Apiary Press items with IDs and titles for follow-up ability calls.',
        //     'category'            => 'apiary-press',
        //     'input_schema'        => [
        //         'type'                 => 'object',
        //         'properties'           => [
        //             'search' => [
        //                 'type'        => 'string',
        //                 'description' => 'Optional search term for item titles.',
        //             ],
        //         ],
        //         'additionalProperties' => false,
        //     ],
        //     'output_schema'       => [
        //         'type'       => 'object',
        //         'properties' => [
        //             'items' => [
        //                 'type'  => 'array',
        //                 'items' => [
        //                     'type'       => 'object',
        //                     'properties' => [
        //                         'id'    => [ 'type' => 'integer', 'description' => 'Use with apiary-press/get-item.' ],
        //                         'title' => [ 'type' => 'string' ],
        //                     ],
        //                 ],
        //             ],
        //         ],
        //     ],
        //     'execute_callback'    => [ $this, 'list_ability_items' ],
        //     'permission_callback' => function() {
        //         return current_user_can( 'read' );
        //     },
        //     'meta'                => [
        //         'annotations' => [
        //             'instructions' => 'Use returned item IDs for follow-up detail or edit abilities.',
        //             'readonly'     => true,
        //             'destructive'  => false,
        //             'idempotent'   => true,
        //         ],
        //     ],
        // ] );
    }

    public function list_ability_items( $input ): array {
        // Sanitize ability input and return structured data. Return WP_Error
        // for failures.
        //
        // $input = is_array( $input ) ? $input : [];
        // $search = isset( $input['search'] ) ? sanitize_text_field( $input['search'] ) : '';
        //
        // return [
        //     'items' => [
        //         [
        //             'id'    => 123,
        //             'title' => __( 'Example item', 'apiary-press' ),
        //         ],
        //     ],
        // ];
        return [
            'items' => [],
        ];
    }

    public function register_ai_assistant_ability_domains( array $domains ): array {
        // Tell AI Assistant which user terms belong to this plugin so it
        // considers your abilities for domain-specific requests.
        //
        // $domains['apiary-press'] = 'Apiary Press, items, records, dashboard';
        return $domains;
    }

    public function get_ai_assistant_ability_instructions( string $instructions, string $ability_id, $args, $result ): string {
        // Add presentation or follow-up guidance after a specific ability runs.
        //
        // if ( 'apiary-press/list-items' === $ability_id && ! empty( $result['items'] ) ) {
        //     $instructions = 'Present the items as a compact table. Mention that item IDs can be used for follow-up changes.';
        // }
        return $instructions;
    }

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

    public function deactivate(): void {
        flush_rewrite_rules();
    }
}
