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
    }

    public function sanitize_boolean_meta( $value ): bool {
        return rest_sanitize_boolean( $value );
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
        $this->register_visit_meta();
        flush_rewrite_rules();
    }

    public function deactivate(): void {
        flush_rewrite_rules();
    }
}
