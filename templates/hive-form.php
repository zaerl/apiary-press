<?php
/**
 * Hive form template for creating and editing hives in the Apiary Press app.
 *
 * @package ApiaryPress
 */

namespace ApiaryPress;

use ApiaryPress\App;
use ApiaryPress\Visit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'appr_read_coordinate_input' ) ) {
	/**
	 * Helper function to read and validate coordinate input from the form.
	 *
	 * @param string $field_name The name of the form field.
	 * @param string $label The human-readable label for the field (used in error messages).
	 * @param float  $minimum The minimum valid value for the coordinate.
	 * @param float  $maximum The maximum valid value for the coordinate.
	 * @return array An array containing 'value' and 'error' keys.
	 */
	function appr_read_coordinate_input( string $field_name, string $label, float $minimum, float $maximum ): array {
		$raw_value = isset( $_POST[ $field_name ] ) ? trim( sanitize_text_field( wp_unslash( $_POST[ $field_name ] ) ) ) : '';

		if ( '' === $raw_value ) {
			return array(
				'value' => '',
				'error' => '',
			);
		}

		if ( ! is_numeric( $raw_value ) ) {
			return array(
				'value' => '',
				'error' => sprintf(
					/* translators: %s: coordinate field label */
					__( '%s must be a number.', 'apiary-press' ),
					$label
				),
			);
		}

		$value = (float) $raw_value;

		if ( $value < $minimum || $value > $maximum ) {
			return array(
				'value' => '',
				'error' => sprintf(
					/* translators: 1: coordinate field label, 2: minimum value, 3: maximum value */
					__( '%1$s must be between %2$s and %3$s.', 'apiary-press' ),
					$label,
					(string) $minimum,
					(string) $maximum
				),
			);
		}

		return array(
			'value' => (string) $value,
			'error' => '',
		);
	}
}

if ( ! function_exists( 'appr_update_coordinate_meta' ) ) {
	/**
	 * Helper function to update or delete coordinate meta based on the input value.
	 *
	 * @param int    $appr_hive_id The ID of the hive.
	 * @param string $appr_meta_key The meta key to update.
	 * @param string $appr_value The value to set. If empty, the meta will be deleted.
	 */
	function appr_update_coordinate_meta( int $appr_hive_id, string $appr_meta_key, string $appr_value ): void {
		if ( '' === $appr_value ) {
			delete_post_meta( $appr_hive_id, $appr_meta_key );
			return;
		}

		update_post_meta( $appr_hive_id, $appr_meta_key, (float) $appr_value );
	}
}

if ( ! function_exists( 'appr_read_queen_inputs' ) ) {
	/**
	 * Pull the queen-related fields off the current POST request, lightly sanitized.
	 * Returns a structured array; validation happens in the caller so errors can short-circuit save.
	 */
	function appr_read_queen_inputs(): array {
		$year_raw     = isset( $_POST['ap_queen_year'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['ap_queen_year'] ) ) ) : '';
		$color_raw    = isset( $_POST['ap_queen_color'] ) ? sanitize_key( wp_unslash( $_POST['ap_queen_color'] ) ) : '';
		$origin_raw   = isset( $_POST['ap_queen_origin'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_queen_origin'] ) ) : '';
		$installed_at = isset( $_POST['ap_queen_installed_at'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_queen_installed_at'] ) ) : '';
		$marked       = isset( $_POST['ap_queen_marked'] );
		$clipped      = isset( $_POST['ap_queen_clipped'] );

		return array(
			'year'         => $year_raw,
			'color'        => in_array( $color_raw, Hive::QUEEN_COLOR_VALUES, true ) ? $color_raw : '',
			'origin'       => $origin_raw,
			'installed_at' => $installed_at,
			'marked'       => $marked,
			'clipped'      => $clipped,
		);
	}
}

if ( ! function_exists( 'appr_validate_queen_inputs' ) ) {
	/**
	 * Validate queen inputs and return ['' or error message, normalized values].
	 *
	 * @param array $appr_queen_inputs Raw queen field values from the POST request.
	 */
	function appr_validate_queen_inputs( array $appr_queen_inputs ): array {
		$error           = '';
		$year_normalized = 0;
		$installed_at    = '';

		if ( '' !== $appr_queen_inputs['year'] ) {
			if ( ! ctype_digit( $appr_queen_inputs['year'] ) ) {
				$error = __( 'Queen year must be a four-digit year.', 'apiary-press' );
			} else {
				$year     = (int) $appr_queen_inputs['year'];
				$max_year = (int) gmdate( 'Y' ) + 1;

				if ( $year < 1990 || $year > $max_year ) {
					$error = sprintf(
						/* translators: 1: minimum year, 2: maximum year */
						__( 'Queen year must be between %1$d and %2$d.', 'apiary-press' ),
						1990,
						$max_year
					);
				} else {
					$year_normalized = $year;
				}
			}
		}

		if ( '' === $error && '' !== $appr_queen_inputs['installed_at'] ) {
			$timestamp = strtotime( $appr_queen_inputs['installed_at'] );

			if ( false === $timestamp ) {
				$error = __( 'Queen install date is not valid.', 'apiary-press' );
			} else {
				$installed_at = gmdate( 'Y-m-d', $timestamp );
			}
		}

		return array(
			'error'        => $error,
			'year'         => $year_normalized,
			'color'        => $appr_queen_inputs['color'],
			'origin'       => $appr_queen_inputs['origin'],
			'installed_at' => $installed_at,
			'marked'       => (bool) $appr_queen_inputs['marked'],
			'clipped'      => (bool) $appr_queen_inputs['clipped'],
		);
	}
}

if ( ! function_exists( 'appr_save_queen_meta' ) ) {
	/**
	 * Persist the validated queen fields onto a hive post.
	 *
	 * @param int   $appr_hive_id The hive post ID.
	 * @param array $appr_queen   The validated queen values from appr_validate_queen_inputs().
	 */
	function appr_save_queen_meta( int $appr_hive_id, array $appr_queen ): void {
		if ( $appr_queen['year'] > 0 ) {
			update_post_meta( $appr_hive_id, Hive::QUEEN_YEAR_META_KEY, $appr_queen['year'] );
		} else {
			delete_post_meta( $appr_hive_id, Hive::QUEEN_YEAR_META_KEY );
		}

		if ( '' !== $appr_queen['color'] ) {
			update_post_meta( $appr_hive_id, Hive::QUEEN_COLOR_META_KEY, $appr_queen['color'] );
		} else {
			delete_post_meta( $appr_hive_id, Hive::QUEEN_COLOR_META_KEY );
		}

		if ( '' !== $appr_queen['origin'] ) {
			update_post_meta( $appr_hive_id, Hive::QUEEN_ORIGIN_META_KEY, $appr_queen['origin'] );
		} else {
			delete_post_meta( $appr_hive_id, Hive::QUEEN_ORIGIN_META_KEY );
		}

		if ( '' !== $appr_queen['installed_at'] ) {
			update_post_meta( $appr_hive_id, Hive::QUEEN_INSTALLED_META_KEY, $appr_queen['installed_at'] );
		} else {
			delete_post_meta( $appr_hive_id, Hive::QUEEN_INSTALLED_META_KEY );
		}

		update_post_meta( $appr_hive_id, Hive::QUEEN_MARKED_META_KEY, $appr_queen['marked'] ? 1 : 0 );
		update_post_meta( $appr_hive_id, Hive::QUEEN_CLIPPED_META_KEY, $appr_queen['clipped'] ? 1 : 0 );
	}
}

global $wp_app_route;

$appr_route_params = isset( $wp_app_route['params'] ) && is_array( $wp_app_route['params'] ) ? $wp_app_route['params'] : array();
$appr_apiary_id    = isset( $appr_route_params['apiary_id'] ) ? absint( $appr_route_params['apiary_id'] ) : absint( get_query_var( 'apiary_id' ) );
$appr_hive_id      = isset( $appr_route_params['id'] ) ? absint( $appr_route_params['id'] ) : absint( get_query_var( 'id' ) );
$appr_is_new_hive  = 0 === $appr_hive_id;
$appr_apiary       = $appr_apiary_id ? get_post( $appr_apiary_id ) : null;
$appr_hive         = $appr_hive_id ? get_post( $appr_hive_id ) : null;
$appr_form_error   = '';

$appr_not_found = ! $appr_apiary
	|| Apiary::APIARY_POST_TYPE !== $appr_apiary->post_type
	|| ( ! $appr_is_new_hive && ( ! $appr_hive || Hive::HIVE_POST_TYPE !== $appr_hive->post_type || absint( $appr_hive->post_parent ) !== $appr_apiary_id ) );
$appr_forbidden = ! $appr_not_found && ( $appr_is_new_hive ? ! current_user_can( 'edit_post', $appr_apiary_id ) : ! current_user_can( 'edit_post', $appr_hive_id ) );

if ( $appr_not_found ) {
	status_header( 404 );
} elseif ( $appr_forbidden ) {
	status_header( 403 );
}

$appr_action = isset( $_POST['ap_action'] ) ? sanitize_key( wp_unslash( $_POST['ap_action'] ) ) : '';

if ( ! $appr_not_found && ! $appr_forbidden && $appr_is_new_hive && 'create_hive' === $appr_action ) {
	$appr_nonce           = isset( $_POST['ap_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_nonce'] ) ) : '';
	$appr_title           = isset( $_POST['ap_hive_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_hive_name'] ) ) : '';
	$appr_notes           = isset( $_POST['ap_hive_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ap_hive_notes'] ) ) : '';
	$appr_latitude_input  = appr_read_coordinate_input( 'ap_hive_latitude', __( 'Latitude', 'apiary-press' ), -90, 90 );
	$appr_longitude_input = appr_read_coordinate_input( 'ap_hive_longitude', __( 'Longitude', 'apiary-press' ), -180, 180 );
	$appr_queen_inputs    = appr_read_queen_inputs();
	$appr_queen_validated = appr_validate_queen_inputs( $appr_queen_inputs );

	if ( ! wp_verify_nonce( $appr_nonce, 'ap_create_hive' ) ) {
		$appr_form_error = __( 'The hive could not be saved. Reload and try again.', 'apiary-press' );
	} elseif ( '' === $appr_title ) {
		$appr_form_error = __( 'Hive name is required.', 'apiary-press' );
	} elseif ( $appr_latitude_input['error'] ) {
		$appr_form_error = $appr_latitude_input['error'];
	} elseif ( $appr_longitude_input['error'] ) {
		$appr_form_error = $appr_longitude_input['error'];
	} elseif ( ( '' === $appr_latitude_input['value'] ) !== ( '' === $appr_longitude_input['value'] ) ) {
		$appr_form_error = __( 'Add both latitude and longitude, or leave both blank.', 'apiary-press' );
	} elseif ( $appr_queen_validated['error'] ) {
		$appr_form_error = $appr_queen_validated['error'];
	} else {
		$appr_new_hive_id = wp_insert_post(
			array(
				'post_type'    => Hive::HIVE_POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => $appr_title,
				'post_content' => $appr_notes,
				'post_parent'  => $appr_apiary_id,
				'post_author'  => get_current_user_id(),
			),
			true
		);

		if ( is_wp_error( $appr_new_hive_id ) ) {
			$appr_form_error = $appr_new_hive_id->get_error_message();
		} else {
			appr_update_coordinate_meta( $appr_new_hive_id, 'latitude', $appr_latitude_input['value'] );
			appr_update_coordinate_meta( $appr_new_hive_id, 'longitude', $appr_longitude_input['value'] );
			appr_save_queen_meta( $appr_new_hive_id, $appr_queen_validated );

			wp_safe_redirect( add_query_arg( 'created', '1', App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . absint( $appr_new_hive_id ) ) ) );
			exit;
		}
	}
}

if ( ! $appr_not_found && ! $appr_forbidden && ! $appr_is_new_hive && 'update_hive' === $appr_action ) {
	$appr_nonce           = isset( $_POST['ap_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_nonce'] ) ) : '';
	$appr_title           = isset( $_POST['ap_hive_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_hive_name'] ) ) : '';
	$appr_notes           = isset( $_POST['ap_hive_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ap_hive_notes'] ) ) : '';
	$appr_latitude_input  = appr_read_coordinate_input( 'ap_hive_latitude', __( 'Latitude', 'apiary-press' ), -90, 90 );
	$appr_longitude_input = appr_read_coordinate_input( 'ap_hive_longitude', __( 'Longitude', 'apiary-press' ), -180, 180 );
	$appr_queen_inputs    = appr_read_queen_inputs();
	$appr_queen_validated = appr_validate_queen_inputs( $appr_queen_inputs );

	if ( ! wp_verify_nonce( $appr_nonce, 'ap_update_hive_' . $appr_hive_id ) ) {
		$appr_form_error = __( 'The hive could not be saved. Reload and try again.', 'apiary-press' );
	} elseif ( '' === $appr_title ) {
		$appr_form_error = __( 'Hive name is required.', 'apiary-press' );
	} elseif ( $appr_latitude_input['error'] ) {
		$appr_form_error = $appr_latitude_input['error'];
	} elseif ( $appr_longitude_input['error'] ) {
		$appr_form_error = $appr_longitude_input['error'];
	} elseif ( ( '' === $appr_latitude_input['value'] ) !== ( '' === $appr_longitude_input['value'] ) ) {
		$appr_form_error = __( 'Add both latitude and longitude, or leave both blank.', 'apiary-press' );
	} elseif ( $appr_queen_validated['error'] ) {
		$appr_form_error = $appr_queen_validated['error'];
	} else {
		$appr_updated_id = wp_update_post(
			array(
				'ID'           => $appr_hive_id,
				'post_title'   => $appr_title,
				'post_content' => $appr_notes,
				'post_parent'  => $appr_apiary_id,
			),
			true
		);

		if ( is_wp_error( $appr_updated_id ) ) {
			$appr_form_error = $appr_updated_id->get_error_message();
		} else {
			appr_update_coordinate_meta( $appr_hive_id, 'latitude', $appr_latitude_input['value'] );
			appr_update_coordinate_meta( $appr_hive_id, 'longitude', $appr_longitude_input['value'] );
			appr_save_queen_meta( $appr_hive_id, $appr_queen_validated );

			wp_safe_redirect( add_query_arg( 'updated', '1', App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id ) ) );
			exit;
		}
	}
}

if ( ! $appr_not_found && ! $appr_forbidden && ! $appr_is_new_hive && 'delete_hive' === $appr_action ) {
	$appr_nonce = isset( $_POST['ap_delete_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_delete_nonce'] ) ) : '';

	if ( ! wp_verify_nonce( $appr_nonce, 'ap_delete_hive_' . $appr_hive_id ) ) {
		$appr_form_error = __( 'The hive could not be removed. Reload and try again.', 'apiary-press' );
	} elseif ( ! current_user_can( 'delete_post', $appr_hive_id ) ) {
		$appr_form_error = __( 'You do not have permission to remove this hive.', 'apiary-press' );
	} else {
		$appr_deleted = wp_delete_post( $appr_hive_id, true );

		if ( ! $appr_deleted ) {
			$appr_form_error = __( 'The hive could not be removed.', 'apiary-press' );
		} else {
			wp_safe_redirect( add_query_arg( 'hive_deleted', '1', App::get_url( 'apiary/' . $appr_apiary_id ) ) );
			exit;
		}
	}
}

if ( ! $appr_not_found && ! $appr_is_new_hive ) {
	$appr_hive = get_post( $appr_hive_id );
}

$appr_hive_title     = ! $appr_not_found && ! $appr_is_new_hive ? get_the_title( $appr_hive ) : '';
$appr_hive_notes     = ! $appr_not_found && ! $appr_is_new_hive ? $appr_hive->post_content : '';
$appr_hive_latitude  = ! $appr_not_found && ! $appr_is_new_hive ? get_post_meta( $appr_hive_id, 'latitude', true ) : '';
$appr_hive_longitude = ! $appr_not_found && ! $appr_is_new_hive ? get_post_meta( $appr_hive_id, 'longitude', true ) : '';

$appr_queen           = ! $appr_not_found && ! $appr_is_new_hive ? Hive::get_queen( $appr_hive_id ) : array(
	'year'           => 0,
	'color'          => '',
	'color_override' => '',
	'marked'         => false,
	'clipped'        => false,
	'origin'         => '',
	'installed_at'   => '',
);
$appr_queen_year      = $appr_queen['year'] ? (string) $appr_queen['year'] : '';
$appr_queen_color     = $appr_queen['color_override'];
$appr_queen_origin    = $appr_queen['origin'];
$appr_queen_installed = $appr_queen['installed_at'];
$appr_queen_marked    = (bool) $appr_queen['marked'];
$appr_queen_clipped   = (bool) $appr_queen['clipped'];

$appr_page_title  = $appr_is_new_hive ? __( 'New Hive', 'apiary-press' ) : __( 'Edit Hive', 'apiary-press' );
$appr_form_action = $appr_is_new_hive ? 'create_hive' : 'update_hive';
$appr_form_nonce  = $appr_is_new_hive ? 'ap_create_hive' : 'ap_update_hive_' . $appr_hive_id;
$appr_form_url    = $appr_is_new_hive ? App::get_url( 'apiary/' . $appr_apiary_id . '/hive/new' ) : App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id . '/edit' );
$appr_button_text = $appr_is_new_hive ? __( 'Save Hive', 'apiary-press' ) : __( 'Update Hive', 'apiary-press' );

if ( $appr_form_error ) {
	$appr_hive_title      = isset( $_POST['ap_hive_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_hive_name'] ) ) : $appr_hive_title;
	$appr_hive_notes      = isset( $_POST['ap_hive_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ap_hive_notes'] ) ) : $appr_hive_notes;
	$appr_hive_latitude   = isset( $_POST['ap_hive_latitude'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_hive_latitude'] ) ) : $appr_hive_latitude;
	$appr_hive_longitude  = isset( $_POST['ap_hive_longitude'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_hive_longitude'] ) ) : $appr_hive_longitude;
	$appr_queen_year      = isset( $_POST['ap_queen_year'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_queen_year'] ) ) : $appr_queen_year;
	$appr_queen_color     = isset( $_POST['ap_queen_color'] ) ? sanitize_key( wp_unslash( $_POST['ap_queen_color'] ) ) : $appr_queen_color;
	$appr_queen_origin    = isset( $_POST['ap_queen_origin'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_queen_origin'] ) ) : $appr_queen_origin;
	$appr_queen_installed = isset( $_POST['ap_queen_installed_at'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_queen_installed_at'] ) ) : $appr_queen_installed;
	$appr_queen_marked    = isset( $_POST['ap_queen_marked'] );
	$appr_queen_clipped   = isset( $_POST['ap_queen_clipped'] );
}

$appr_queen_color_options = array(
	''       => __( 'Auto (from year)', 'apiary-press' ),
	'white'  => __( 'White (years ending 1, 6)', 'apiary-press' ),
	'yellow' => __( 'Yellow (years ending 2, 7)', 'apiary-press' ),
	'red'    => __( 'Red (years ending 3, 8)', 'apiary-press' ),
	'green'  => __( 'Green (years ending 4, 9)', 'apiary-press' ),
	'blue'   => __( 'Blue (years ending 5, 0)', 'apiary-press' ),
);

$appr_queen_max_year = (int) gmdate( 'Y' ) + 1;
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php wp_app_title( $appr_page_title ); ?></title>
	<?php wp_app_head(); ?>
</head>
<body>
	<?php wp_app_body_open(); ?>

	<main class="shell shell-narrow">
		<?php if ( $appr_not_found ) : ?>
			<section class="message">
				<h1><?php echo esc_html__( 'Hive Not Found', 'apiary-press' ); ?></h1>
				<p class="hive-notes"><?php echo esc_html__( 'The requested hive is not available.', 'apiary-press' ); ?></p>
				<p><a class="admin-link" href="<?php echo esc_url( App::get_url() ); ?>"><?php echo esc_html__( 'Back to Apiaries', 'apiary-press' ); ?></a></p>
			</section>
		<?php elseif ( $appr_forbidden ) : ?>
			<section class="message">
				<h1><?php echo esc_html__( 'Access Denied', 'apiary-press' ); ?></h1>
				<p class="hive-notes">
					<?php
					echo esc_html(
						$appr_is_new_hive
							? __( 'You do not have permission to add hives.', 'apiary-press' )
							: __( 'You do not have permission to edit this hive.', 'apiary-press' )
					);
					?>
				</p>
				<p><a class="admin-link" href="<?php echo esc_url( App::get_url() ); ?>"><?php echo esc_html__( 'Back to Apiaries', 'apiary-press' ); ?></a></p>
			</section>
		<?php else : ?>
			<header class="topbar">
				<div>
					<a class="crumb" href="<?php echo esc_url( $appr_is_new_hive ? App::get_url( 'apiary/' . $appr_apiary_id ) : App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id ) ); ?>">
						<?php echo esc_html( $appr_is_new_hive ? get_the_title( $appr_apiary ) : get_the_title( $appr_hive ) ); ?>
					</a>
					<h1><?php echo esc_html( $appr_page_title ); ?></h1>
				</div>
			</header>

			<?php if ( $appr_form_error ) : ?>
				<div class="error"><?php echo esc_html( $appr_form_error ); ?></div>
			<?php endif; ?>

			<section class="panel" aria-labelledby="hive-form-heading">
				<h2 id="hive-form-heading"><?php echo esc_html( $appr_page_title ); ?></h2>
				<form method="post" action="<?php echo esc_url( $appr_form_url ); ?>">
					<input type="hidden" name="ap_action" value="<?php echo esc_attr( $appr_form_action ); ?>">
					<?php wp_nonce_field( $appr_form_nonce, 'ap_nonce' ); ?>

					<div class="field">
						<label for="ap_hive_name"><?php echo esc_html__( 'Name', 'apiary-press' ); ?></label>
						<input id="ap_hive_name" name="ap_hive_name" type="text" value="<?php echo esc_attr( $appr_hive_title ); ?>" required>
					</div>

					<div class="coordinate-grid">
						<div class="field">
							<label for="ap_hive_latitude"><?php echo esc_html__( 'Latitude', 'apiary-press' ); ?></label>
							<input id="ap_hive_latitude" name="ap_hive_latitude" type="number" inputmode="decimal" min="-90" max="90" step="any" value="<?php echo esc_attr( $appr_hive_latitude ); ?>">
						</div>

						<div class="field">
							<label for="ap_hive_longitude"><?php echo esc_html__( 'Longitude', 'apiary-press' ); ?></label>
							<input id="ap_hive_longitude" name="ap_hive_longitude" type="number" inputmode="decimal" min="-180" max="180" step="any" value="<?php echo esc_attr( $appr_hive_longitude ); ?>">
						</div>
					</div>

					<div class="coordinate-actions">
						<button id="ap_use_current_location" class="button button-secondary" type="button">
							<?php echo esc_html__( 'Set Current Location', 'apiary-press' ); ?>
						</button>
						<span id="ap_location_status" class="location-status" role="status" aria-live="polite"></span>
					</div>

					<div class="field">
						<label for="ap_hive_notes"><?php echo esc_html__( 'Notes', 'apiary-press' ); ?></label>
						<textarea id="ap_hive_notes" name="ap_hive_notes"><?php echo esc_textarea( $appr_hive_notes ); ?></textarea>
					</div>

					<fieldset class="queen-fieldset">
						<legend><?php echo esc_html__( 'Queen', 'apiary-press' ); ?></legend>

						<div class="queen-grid">
							<div class="field">
								<label for="ap_queen_year"><?php echo esc_html__( 'Year', 'apiary-press' ); ?></label>
								<input
									id="ap_queen_year"
									name="ap_queen_year"
									type="number"
									inputmode="numeric"
									min="1990"
									max="<?php echo esc_attr( (string) $appr_queen_max_year ); ?>"
									step="1"
									value="<?php echo esc_attr( $appr_queen_year ); ?>"
								>
							</div>

							<div class="field">
								<label for="ap_queen_color"><?php echo esc_html__( 'Marking color', 'apiary-press' ); ?></label>
								<select id="ap_queen_color" name="ap_queen_color">
									<?php foreach ( $appr_queen_color_options as $appr_color_value => $appr_color_label ) : ?>
										<option value="<?php echo esc_attr( $appr_color_value ); ?>" <?php selected( $appr_queen_color, $appr_color_value ); ?>>
											<?php echo esc_html( $appr_color_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p id="ap_queen_color_hint" class="muted queen-color-hint" aria-live="polite"></p>
							</div>

							<div class="field">
								<label for="ap_queen_installed_at"><?php echo esc_html__( 'Installed on', 'apiary-press' ); ?></label>
								<input
									id="ap_queen_installed_at"
									name="ap_queen_installed_at"
									type="date"
									value="<?php echo esc_attr( $appr_queen_installed ); ?>"
								>
							</div>

							<div class="field">
								<label for="ap_queen_origin"><?php echo esc_html__( 'Origin / breeder', 'apiary-press' ); ?></label>
								<input
									id="ap_queen_origin"
									name="ap_queen_origin"
									type="text"
									value="<?php echo esc_attr( $appr_queen_origin ); ?>"
								>
							</div>
						</div>

						<div class="queen-flags">
							<label class="checkbox">
								<input type="checkbox" name="ap_queen_marked" value="1" <?php checked( $appr_queen_marked ); ?>>
								<?php echo esc_html__( 'Marked', 'apiary-press' ); ?>
							</label>
							<label class="checkbox">
								<input type="checkbox" name="ap_queen_clipped" value="1" <?php checked( $appr_queen_clipped ); ?>>
								<?php echo esc_html__( 'Clipped', 'apiary-press' ); ?>
							</label>
						</div>
					</fieldset>

					<button class="button" type="submit"><?php echo esc_html( $appr_button_text ); ?></button>
				</form>

				<?php if ( ! $appr_is_new_hive && current_user_can( 'delete_post', $appr_hive_id ) ) : ?>
					<form class="delete-form" method="post" action="<?php echo esc_url( $appr_form_url ); ?>">
						<input type="hidden" name="ap_action" value="delete_hive">
						<?php wp_nonce_field( 'ap_delete_hive_' . $appr_hive_id, 'ap_delete_nonce' ); ?>
						<p class="danger-text"><?php echo esc_html__( 'Delete this hive and all visits, treatments, feedings, harvests, and visit media.', 'apiary-press' ); ?></p>
						<button
							class="button button-danger"
							type="submit"
							onclick="return confirm('<?php echo esc_js( __( 'Delete this hive and all related records?', 'apiary-press' ) ); ?>');"
						>
							<?php echo esc_html__( 'Delete Hive', 'apiary-press' ); ?>
						</button>
					</form>
				<?php endif; ?>
			</section>
		<?php endif; ?>
	</main>

	<?php wp_app_body_close(); ?>
	<script>
		(function() {
			const locationButton = document.getElementById('ap_use_current_location');
			const latitudeInput = document.getElementById('ap_hive_latitude');
			const longitudeInput = document.getElementById('ap_hive_longitude');
			const status = document.getElementById('ap_location_status');
			const messages =
			<?php
			echo wp_json_encode(
				array(
					'idle'        => '',
					'locating'    => __( 'Getting current location...', 'apiary-press' ),
					'ready'       => __( 'Current location set.', 'apiary-press' ),
					'unavailable' => __( 'Location access is unavailable in this browser.', 'apiary-press' ),
					'insecure'    => __( 'Location access requires HTTPS or localhost.', 'apiary-press' ),
					'denied'      => __( 'Allow location access and try again.', 'apiary-press' ),
					'failed'      => __( 'Current location could not be read.', 'apiary-press' ),
				)
			);
			?>
			;

			if (!locationButton || !latitudeInput || !longitudeInput || !status) {
				return;
			}

			const setStatus = function(message) {
				status.textContent = message;
			};

			const queenYearInput = document.getElementById('ap_queen_year');
			const queenColorSelect = document.getElementById('ap_queen_color');
			const queenColorHint = document.getElementById('ap_queen_color_hint');
			const queenColorLabels =
			<?php
			echo wp_json_encode(
				array(
					'white'  => __( 'White', 'apiary-press' ),
					'yellow' => __( 'Yellow', 'apiary-press' ),
					'red'    => __( 'Red', 'apiary-press' ),
					'green'  => __( 'Green', 'apiary-press' ),
					'blue'   => __( 'Blue', 'apiary-press' ),
				)
			);
			?>
			;
			const queenHintTemplate =
			<?php
				/* translators: 1: four-digit queen birth year, 2: name of the international marking color (e.g. White, Yellow). */
				echo wp_json_encode( __( 'Standard color for %1$s: %2$s.', 'apiary-press' ) );
			?>
			;

			const queenColorForYear = function(year) {
				const cycle = ['blue', 'white', 'yellow', 'red', 'green', 'blue', 'white', 'yellow', 'red', 'green'];
				return cycle[year % 10];
			};

			const refreshQueenHint = function() {
				if (!queenYearInput || !queenColorSelect || !queenColorHint) {
					return;
				}

				const rawYear = parseInt(queenYearInput.value, 10);

				if (!rawYear || rawYear < 1990) {
					queenColorHint.textContent = '';
					return;
				}

				const colorSlug = queenColorForYear(rawYear);
				const colorLabel = queenColorLabels[colorSlug] || '';

				if (!colorLabel) {
					queenColorHint.textContent = '';
					return;
				}

				queenColorHint.textContent = queenHintTemplate
					.replace('%1$s', String(rawYear))
					.replace('%2$s', colorLabel);
			};

			if (queenYearInput) {
				queenYearInput.addEventListener('input', refreshQueenHint);
				refreshQueenHint();
			}

			locationButton.addEventListener('click', function() {
				if (!('geolocation' in navigator)) {
					setStatus(messages.unavailable);
					return;
				}

				if (window.isSecureContext === false) {
					setStatus(messages.insecure);
					return;
				}

				locationButton.disabled = true;
				setStatus(messages.locating);

				navigator.geolocation.getCurrentPosition(
					function(position) {
						latitudeInput.value = position.coords.latitude.toFixed(6);
						longitudeInput.value = position.coords.longitude.toFixed(6);
						setStatus(messages.ready);
						locationButton.disabled = false;
					},
					function(error) {
						setStatus(error && error.code === 1 ? messages.denied : messages.failed);
						locationButton.disabled = false;
					},
					{
						enableHighAccuracy: true,
						maximumAge: 60000,
						timeout: 15000
					}
				);
			});
		})();
	</script>
</body>
</html>
