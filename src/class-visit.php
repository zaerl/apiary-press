<?php
/**
 * Hive visit helpers for apiary press.
 *
 * @package ApiaryPress
 */

namespace ApiaryPress;

/**
 * A hive visit.
 */
class Visit {
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

	public const MEDIA_UPLOAD_FIELD = 'ap_visit_media';

	public const REASON_META_KEY = 'reason';

	public const REASON_DEFAULT = 'routine_inspection';

	public const REASON_VALUES = array(
		'add_remove_super',
		'brood_inspection',
		'deadout_inspection',
		'disease_check_treatment',
		'equipment_maintenance',
		'feeding',
		'food_stores_check',
		'honey_harvest',
		'pest_check',
		'queen_check',
		'requeening',
		'routine_inspection',
		'seasonal_preparation',
		'split_or_combine_colony',
		'swarm_check',
		'varroa_monitoring_treatment',
		'weather_damage_check',
	);

	/**
	 * Get the translated labels for the visit reason meta keys.
	 */
	public static function get_visit_meta_labels(): array {
		return array(
			'add_remove_super'            => __( 'Add / remove super', 'apiary-press' ),
			'brood_inspection'            => __( 'Brood inspection', 'apiary-press' ),
			'deadout_inspection'          => __( 'Deadout inspection', 'apiary-press' ),
			'disease_check_treatment'     => __( 'Disease check / treatment', 'apiary-press' ),
			'equipment_maintenance'       => __( 'Equipment maintenance', 'apiary-press' ),
			'feeding'                     => __( 'Feeding', 'apiary-press' ),
			'food_stores_check'           => __( 'Food stores check', 'apiary-press' ),
			'honey_harvest'               => __( 'Honey harvest', 'apiary-press' ),
			'pest_check'                  => __( 'Pest check', 'apiary-press' ),
			'queen_check'                 => __( 'Queen check', 'apiary-press' ),
			'requeening'                  => __( 'Requeening', 'apiary-press' ),
			'routine_inspection'          => __( 'Routine inspection', 'apiary-press' ),
			'seasonal_preparation'        => __( 'Seasonal preparation', 'apiary-press' ),
			'split_or_combine_colony'     => __( 'Split or combine colony', 'apiary-press' ),
			'swarm_check'                 => __( 'Swarm check', 'apiary-press' ),
			'varroa_monitoring_treatment' => __( 'Varroa monitoring / treatment', 'apiary-press' ),
			'weather_damage_check'        => __( 'Weather damage check', 'apiary-press' ),
		);
	}

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
	 * Register the hive visit custom post types.
	 */
	public static function register_post_types(): void {
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
				'show_in_menu' => 'edit.php?post_type=' . Hive::HIVE_POST_TYPE,
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor', 'author', 'custom-fields' ),
				'map_meta_cap' => true,
			)
		);
	}

	/**
	 * Register the boolean and weather post meta fields for the hive visit post type.
	 */
	public static function register_meta(): void {
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

		register_post_meta(
			self::HIVE_VISIT_POST_TYPE,
			self::REASON_META_KEY,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => self::REASON_DEFAULT,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_reason_meta' ),
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

	/**
	 * Sanitize a visit reason value: returns the slug if it is one of REASON_VALUES, REASON_DEFAULT otherwise.
	 *
	 * @param mixed $value The value to sanitize.
	 */
	public static function sanitize_reason_meta( $value ): string {
		$value = sanitize_key( (string) $value );

		return in_array( $value, self::REASON_VALUES, true ) ? $value : self::REASON_DEFAULT;
	}

	/**
	 * Get the attachments parented to a visit, ordered by upload time.
	 *
	 * @param int $visit_id The visit (post) ID.
	 * @return \WP_Post[]
	 */
	public static function get_visit_media( int $visit_id ): array {
		if ( ! $visit_id ) {
			return array();
		}

		$attachments = get_children(
			array(
				'post_parent'    => $visit_id,
				'post_type'      => 'attachment',
				'posts_per_page' => -1,
				'orderby'        => array(
					'menu_order' => 'ASC',
					'date'       => 'ASC',
				),
			)
		);

		return is_array( $attachments ) ? array_values( $attachments ) : array();
	}

	/**
	 * Process files submitted under MEDIA_UPLOAD_FIELD and attach them to the visit.
	 *
	 * Nonce verification is expected to have happened already on the request.
	 *
	 * @param int $visit_id The visit (post) ID files will be attached to.
	 * @return string[] Error messages from failed uploads (the visit itself is unaffected).
	 */
	public static function handle_media_uploads( int $visit_id ): array {
		$field = self::MEDIA_UPLOAD_FIELD;

		if ( ! $visit_id || empty( $_FILES[ $field ]['name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return array();
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			return array( __( 'You do not have permission to upload files.', 'apiary-press' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Nonce verification is the caller's responsibility; media_handle_upload validates each file.
		$raw    = $_FILES[ $field ]; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$errors = array();
		$names  = is_array( $raw['name'] ) ? $raw['name'] : array( $raw['name'] );
		$slot   = $field . '_single';

		foreach ( $names as $index => $name ) {
			$file = is_array( $raw['name'] )
				? array(
					'name'     => $raw['name'][ $index ],
					'type'     => $raw['type'][ $index ],
					'tmp_name' => $raw['tmp_name'][ $index ],
					'error'    => $raw['error'][ $index ],
					'size'     => $raw['size'][ $index ],
				)
				: $raw;

			if ( UPLOAD_ERR_NO_FILE === (int) $file['error'] || '' === (string) $file['name'] ) {
				continue;
			}

			$_FILES[ $slot ] = $file;
			$attachment_id   = media_handle_upload( $slot, $visit_id );
			unset( $_FILES[ $slot ] );

			if ( is_wp_error( $attachment_id ) ) {
				$errors[] = sprintf(
					/* translators: 1: file name, 2: error message. */
					__( '%1$s: %2$s', 'apiary-press' ),
					sanitize_file_name( (string) $file['name'] ),
					$attachment_id->get_error_message()
				);
			}
		}

		return $errors;
	}

	/**
	 * Delete an attachment if it actually belongs to the given visit and the user can delete it.
	 *
	 * @param int $attachment_id The attachment to remove.
	 * @param int $visit_id      The visit the attachment must be parented to.
	 */
	public static function delete_visit_media( int $attachment_id, int $visit_id ): bool {
		if ( ! $attachment_id || ! $visit_id ) {
			return false;
		}

		$attachment = get_post( $attachment_id );

		if ( ! $attachment
			|| 'attachment' !== $attachment->post_type
			|| absint( $attachment->post_parent ) !== $visit_id ) {
			return false;
		}

		if ( ! current_user_can( 'delete_post', $attachment_id ) ) {
			return false;
		}

		return (bool) wp_delete_attachment( $attachment_id, true );
	}
}
