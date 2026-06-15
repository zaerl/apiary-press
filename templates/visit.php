<?php
/**
 * Visit template for displaying and managing individual hive visits in the Apiary Press app.
 *
 * @package ApiaryPress
 */

namespace ApiaryPress;

use ApiaryPress\App;
use ApiaryPress\Visit;
use ApiaryPress\Weather;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wp_app_route;

$appr_route_params    = isset( $wp_app_route['params'] ) && is_array( $wp_app_route['params'] ) ? $wp_app_route['params'] : array();
$appr_apiary_id       = isset( $appr_route_params['apiary_id'] ) ? absint( $appr_route_params['apiary_id'] ) : absint( get_query_var( 'apiary_id' ) );
$appr_hive_id         = isset( $appr_route_params['id'] ) ? absint( $appr_route_params['id'] ) : absint( get_query_var( 'id' ) );
$appr_hive_visit_slug = isset( $appr_route_params['hive_visit'] ) ? sanitize_key( $appr_route_params['hive_visit'] ) : sanitize_key( get_query_var( 'hive_visit' ) );
$appr_is_new_visit    = 'new' === $appr_hive_visit_slug;
$appr_hive_visit_id   = $appr_is_new_visit ? 0 : absint( $appr_hive_visit_slug );
$appr_apiary          = $appr_apiary_id ? get_post( $appr_apiary_id ) : null;
$appr_hive            = $appr_hive_id ? get_post( $appr_hive_id ) : null;
$appr_visit           = $appr_hive_visit_id ? get_post( $appr_hive_visit_id ) : null;
$appr_meta_labels     = Visit::get_boolean_meta_labels();
$appr_form_error      = '';

$appr_not_found = ! $appr_apiary
	|| Apiary::APIARY_POST_TYPE !== $appr_apiary->post_type
	|| ! $appr_hive
	|| Hive::HIVE_POST_TYPE !== $appr_hive->post_type
	|| absint( $appr_hive->post_parent ) !== $appr_apiary_id
	|| ( ! $appr_is_new_visit && ( ! $appr_visit || Visit::HIVE_VISIT_POST_TYPE !== $appr_visit->post_type || absint( $appr_visit->post_parent ) !== $appr_hive_id ) );

$appr_forbidden = ! $appr_not_found && ( $appr_is_new_visit ? ! current_user_can( 'edit_post', $appr_hive_id ) : ! current_user_can( 'edit_post', $appr_hive_visit_id ) );

if ( $appr_not_found ) {
	status_header( 404 );
} elseif ( $appr_forbidden ) {
	status_header( 403 );
}

$appr_action = isset( $_POST['ap_action'] ) ? sanitize_key( wp_unslash( $_POST['ap_action'] ) ) : '';

if ( ! $appr_not_found && ! $appr_forbidden && $appr_is_new_visit && 'create_visit' === $appr_action ) {
	$appr_nonce          = isset( $_POST['ap_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_nonce'] ) ) : '';
	$appr_visit_date_raw = isset( $_POST['ap_visit_date'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_visit_date'] ) ) : current_time( 'Y-m-d' );
	$appr_visit_time_raw = isset( $_POST['ap_visit_time'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_visit_time'] ) ) : current_time( 'H:i' );
	$appr_notes          = isset( $_POST['ap_visit_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ap_visit_notes'] ) ) : '';
	$appr_selected_meta  = isset( $_POST['ap_visit_meta'] ) && is_array( $_POST['ap_visit_meta'] )
		? array_map( 'sanitize_key', wp_unslash( $_POST['ap_visit_meta'] ) )
		: array();
	$appr_reason_value   = isset( $_POST['ap_visit_reason'] )
		? Visit::sanitize_reason_meta( wp_unslash( $_POST['ap_visit_reason'] ) )
		: '';

	if ( ! wp_verify_nonce( $appr_nonce, 'ap_create_visit_' . $appr_hive_id ) ) {
		$appr_form_error = __( 'The visit could not be saved. Reload and try again.', 'apiary-press' );
	} elseif ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $appr_visit_date_raw ) ) {
		$appr_form_error = __( 'Visit date is invalid.', 'apiary-press' );
	} elseif ( ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $appr_visit_time_raw ) ) {
		$appr_form_error = __( 'Visit time is invalid.', 'apiary-press' );
	} else {
		$appr_visit_timestamp = strtotime( $appr_visit_date_raw . ' ' . $appr_visit_time_raw . ':00' );

		if ( false === $appr_visit_timestamp ) {
			$appr_form_error = __( 'Visit date is invalid.', 'apiary-press' );
		} else {
			$appr_visit_title = sprintf(
				/* translators: 1: hive title, 2: visit date */
				__( '%1$s visit on %2$s', 'apiary-press' ),
				get_the_title( $appr_hive ),
				date_i18n( get_option( 'date_format' ), $appr_visit_timestamp )
			);

			$appr_visit_id = wp_insert_post(
				array(
					'post_type'    => Visit::HIVE_VISIT_POST_TYPE,
					'post_status'  => 'publish',
					'post_title'   => $appr_visit_title,
					'post_content' => $appr_notes,
					'post_parent'  => $appr_hive_id,
					'post_author'  => get_current_user_id(),
					'post_date'    => $appr_visit_date_raw . ' ' . $appr_visit_time_raw . ':00',
				),
				true
			);

			if ( is_wp_error( $appr_visit_id ) ) {
				$appr_form_error = $appr_visit_id->get_error_message();
			} else {
				foreach ( Visit::VISIT_BOOLEAN_META_KEYS as $appr_meta_key ) {
					update_post_meta( $appr_visit_id, $appr_meta_key, in_array( $appr_meta_key, $appr_selected_meta, true ) ? '1' : '0' );
				}

				update_post_meta( $appr_visit_id, Visit::REASON_META_KEY, $appr_reason_value );

				Weather::store_visit_weather_snapshot( $appr_visit_id, $appr_hive_id, $appr_visit_date_raw, $appr_visit_time_raw );

				$appr_media_errors  = Visit::handle_media_uploads( (int) $appr_visit_id );
				$appr_redirect_args = array( 'created' => '1' );

				if ( ! empty( $appr_media_errors ) ) {
					$appr_redirect_args['media_errors'] = count( $appr_media_errors );
				}

				wp_safe_redirect( add_query_arg( $appr_redirect_args, App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id . '/visit/' . absint( $appr_visit_id ) ) ) );
				exit;
			}
		}
	}
}

if ( ! $appr_not_found && ! $appr_forbidden && ! $appr_is_new_visit && 'delete_visit' === $appr_action ) {
	$appr_nonce = isset( $_POST['ap_delete_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_delete_nonce'] ) ) : '';

	if ( ! wp_verify_nonce( $appr_nonce, 'ap_delete_visit_' . $appr_hive_visit_id ) ) {
		$appr_form_error = __( 'The visit could not be removed. Reload and try again.', 'apiary-press' );
	} elseif ( ! current_user_can( 'delete_post', $appr_hive_visit_id ) ) {
		$appr_form_error = __( 'You do not have permission to remove this visit.', 'apiary-press' );
	} else {
		$appr_deleted = wp_delete_post( $appr_hive_visit_id, true );

		if ( ! $appr_deleted ) {
			$appr_form_error = __( 'The visit could not be removed.', 'apiary-press' );
		} else {
			wp_safe_redirect( add_query_arg( 'visit_deleted', '1', App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id ) ) );
			exit;
		}
	}
}

if ( ! $appr_not_found && ! $appr_forbidden && ! $appr_is_new_visit && 'delete_visit_media' === $appr_action ) {
	$appr_nonce         = isset( $_POST['ap_media_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_media_nonce'] ) ) : '';
	$appr_attachment_id = isset( $_POST['ap_attachment_id'] ) ? absint( $_POST['ap_attachment_id'] ) : 0;

	if ( ! wp_verify_nonce( $appr_nonce, 'ap_delete_visit_media_' . $appr_hive_visit_id . '_' . $appr_attachment_id ) ) {
		$appr_form_error = __( 'The media could not be removed. Reload and try again.', 'apiary-press' );
	} elseif ( ! Visit::delete_visit_media( $appr_attachment_id, $appr_hive_visit_id ) ) {
		$appr_form_error = __( 'The media could not be removed.', 'apiary-press' );
	} else {
		wp_safe_redirect( add_query_arg( 'media_removed', '1', App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id . '/visit/' . $appr_hive_visit_id ) ) );
		exit;
	}
}

if ( ! $appr_not_found && ! $appr_forbidden && ! $appr_is_new_visit && 'update_visit' === $appr_action ) {
	$appr_nonce          = isset( $_POST['ap_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_nonce'] ) ) : '';
	$appr_visit_date_raw = isset( $_POST['ap_visit_date'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_visit_date'] ) ) : '';
	$appr_visit_time_raw = isset( $_POST['ap_visit_time'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_visit_time'] ) ) : mysql2date( 'H:i', $appr_visit->post_date, false );
	$appr_notes          = isset( $_POST['ap_visit_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ap_visit_notes'] ) ) : '';
	$appr_selected_meta  = isset( $_POST['ap_visit_meta'] ) && is_array( $_POST['ap_visit_meta'] )
		? array_map( 'sanitize_key', wp_unslash( $_POST['ap_visit_meta'] ) )
		: array();
	$appr_reason_value   = isset( $_POST['ap_visit_reason'] )
		? Visit::sanitize_reason_meta( wp_unslash( $_POST['ap_visit_reason'] ) )
		: '';

	if ( ! wp_verify_nonce( $appr_nonce, 'ap_update_visit_' . $appr_hive_visit_id ) ) {
		$appr_form_error = __( 'The visit could not be saved. Reload and try again.', 'apiary-press' );
	} elseif ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $appr_visit_date_raw ) ) {
		$appr_form_error = __( 'Visit date is invalid.', 'apiary-press' );
	} elseif ( ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $appr_visit_time_raw ) ) {
		$appr_form_error = __( 'Visit time is invalid.', 'apiary-press' );
	} else {
		$appr_visit_timestamp = strtotime( $appr_visit_date_raw . ' ' . $appr_visit_time_raw . ':00' );

		if ( false === $appr_visit_timestamp ) {
			$appr_form_error = __( 'Visit date is invalid.', 'apiary-press' );
		} else {
			$appr_visit_title = sprintf(
				/* translators: 1: hive title, 2: visit date */
				__( '%1$s visit on %2$s', 'apiary-press' ),
				get_the_title( $appr_hive ),
				date_i18n( get_option( 'date_format' ), $appr_visit_timestamp )
			);

			$appr_updated_id = wp_update_post(
				array(
					'ID'           => $appr_hive_visit_id,
					'post_title'   => $appr_visit_title,
					'post_content' => $appr_notes,
					'post_date'    => $appr_visit_date_raw . ' ' . $appr_visit_time_raw . ':00',
					'post_parent'  => $appr_hive_id,
				),
				true
			);

			if ( is_wp_error( $appr_updated_id ) ) {
				$appr_form_error = $appr_updated_id->get_error_message();
			} else {
				foreach ( Visit::VISIT_BOOLEAN_META_KEYS as $appr_meta_key ) {
					update_post_meta( $appr_hive_visit_id, $appr_meta_key, in_array( $appr_meta_key, $appr_selected_meta, true ) ? '1' : '0' );
				}

				update_post_meta( $appr_hive_visit_id, Visit::REASON_META_KEY, $appr_reason_value );

				$appr_media_errors  = Visit::handle_media_uploads( $appr_hive_visit_id );
				$appr_redirect_args = array( 'updated' => '1' );

				if ( ! empty( $appr_media_errors ) ) {
					$appr_redirect_args['media_errors'] = count( $appr_media_errors );
				}

				wp_safe_redirect( add_query_arg( $appr_redirect_args, App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id . '/visit/' . $appr_hive_visit_id ) ) );
				exit;
			}
		}
	}
}

if ( ! $appr_not_found && ! $appr_is_new_visit ) {
	$appr_visit = get_post( $appr_hive_visit_id );
}

$appr_visit_date    = ! $appr_not_found && ! $appr_is_new_visit ? mysql2date( 'Y-m-d', $appr_visit->post_date, false ) : current_time( 'Y-m-d' );
$appr_visit_time    = ! $appr_not_found && ! $appr_is_new_visit ? mysql2date( 'H:i', $appr_visit->post_date, false ) : current_time( 'H:i' );
$appr_visit_notes   = ! $appr_not_found && ! $appr_is_new_visit ? $appr_visit->post_content : '';
$appr_visit_reason  = ! $appr_not_found && ! $appr_is_new_visit ? (string) get_post_meta( $appr_hive_visit_id, Visit::REASON_META_KEY, true ) : Visit::REASON_DEFAULT;
$appr_reason_labels = Visit::get_visit_meta_labels();

if ( ! isset( $appr_reason_labels[ $appr_visit_reason ] ) ) {
	$appr_visit_reason = Visit::REASON_DEFAULT;
}
$appr_active_flags      = array();
$appr_form_checked_meta = array();
$appr_weather_values    = array();
$appr_weather_error     = '';
$appr_page_title        = $appr_is_new_visit ? __( 'New Visit', 'apiary-press' ) : ( $appr_visit ? get_the_title( $appr_visit ) : __( 'Hive Visit', 'apiary-press' ) );
$appr_form_action       = $appr_is_new_visit ? 'create_visit' : 'update_visit';
$appr_form_nonce        = $appr_is_new_visit ? 'ap_create_visit_' . $appr_hive_id : 'ap_update_visit_' . $appr_hive_visit_id;
$appr_form_heading      = $appr_is_new_visit ? __( 'New Visit', 'apiary-press' ) : __( 'Edit Visit', 'apiary-press' );
$appr_form_button       = $appr_is_new_visit ? __( 'Save Visit', 'apiary-press' ) : __( 'Update Visit', 'apiary-press' );
$appr_form_url_visit    = $appr_is_new_visit ? 'new' : (string) $appr_hive_visit_id;

if ( ! $appr_not_found && ! $appr_is_new_visit ) {
	foreach ( $appr_meta_labels as $appr_meta_key => $appr_label ) {
		if ( rest_sanitize_boolean( get_post_meta( $appr_hive_visit_id, $appr_meta_key, true ) ) ) {
			$appr_active_flags[ $appr_meta_key ] = $appr_label;
			$appr_form_checked_meta[]            = $appr_meta_key;
		}
	}

	$appr_weather_error = get_post_meta( $appr_hive_visit_id, 'weather_error', true );

	foreach ( Weather::FORECAST_UNITS as $appr_meta_key => $appr_label ) {
		$appr_value = get_post_meta( $appr_hive_visit_id, $appr_meta_key, true );

		if ( '' === $appr_value || null === $appr_value ) {
			continue;
		}

		if ( 'symbol_code' === $appr_meta_key ) {
			$appr_weather_values[ $appr_meta_key ] = $appr_value;
			continue;
		}

		$appr_value = Weather::get_forecast_display_value( $appr_meta_key, $appr_value );

		if ( $appr_value ) {
			$appr_weather_values[ $appr_meta_key ] = $appr_value;
		}
	}
}

if ( $appr_form_error ) {
	$appr_visit_date        = isset( $_POST['ap_visit_date'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_visit_date'] ) ) : $appr_visit_date;
	$appr_visit_time        = isset( $_POST['ap_visit_time'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_visit_time'] ) ) : $appr_visit_time;
	$appr_visit_notes       = isset( $_POST['ap_visit_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ap_visit_notes'] ) ) : $appr_visit_notes;
	$appr_visit_reason      = isset( $_POST['ap_visit_reason'] ) ? Visit::sanitize_reason_meta( wp_unslash( $_POST['ap_visit_reason'] ) ) : $appr_visit_reason;
	$appr_form_checked_meta = isset( $_POST['ap_visit_meta'] ) && is_array( $_POST['ap_visit_meta'] )
		? array_map( 'sanitize_key', wp_unslash( $_POST['ap_visit_meta'] ) )
		: array();
}
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

	<main class="shell shell-mid">
		<?php if ( $appr_not_found ) : ?>
			<section class="message">
				<h1><?php echo esc_html__( 'Visit Not Found', 'apiary-press' ); ?></h1>
				<p class="visit-notes"><?php echo esc_html__( 'The requested visit is not available for this hive.', 'apiary-press' ); ?></p>
				<p><a class="admin-link" href="<?php echo esc_url( App::get_url() ); ?>"><?php echo esc_html__( 'Back to Apiaries', 'apiary-press' ); ?></a></p>
			</section>
		<?php elseif ( $appr_forbidden ) : ?>
			<section class="message">
				<h1><?php echo esc_html__( 'Access Denied', 'apiary-press' ); ?></h1>
				<p class="visit-notes">
					<?php
					echo esc_html(
						$appr_is_new_visit
							? __( 'You do not have permission to add a visit to this hive.', 'apiary-press' )
							: __( 'You do not have permission to edit this visit.', 'apiary-press' )
					);
					?>
				</p>
				<p><a class="admin-link" href="<?php echo esc_url( App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id ) ); ?>"><?php echo esc_html__( 'Back to Hive', 'apiary-press' ); ?></a></p>
			</section>
		<?php else : ?>
			<header class="topbar">
				<div>
					<a class="crumb" href="<?php echo esc_url( App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id ) ); ?>"><?php echo esc_html( get_the_title( $appr_hive ) ); ?></a>
					<h1>
						<?php
						echo esc_html(
							$appr_is_new_visit
								? __( 'New Visit', 'apiary-press' )
								: mysql2date( get_option( 'date_format' ), $appr_visit->post_date )
						);
						?>
					</h1>
					<?php if ( ! $appr_is_new_visit ) : ?>
						<?php $appr_author_name = get_the_author_meta( 'display_name', (int) $appr_visit->post_author ); ?>
						<div class="muted">
							<?php
							printf(
								/* translators: %s: the display name of the user who recorded the visit. */
								esc_html__( 'by %s', 'apiary-press' ),
								esc_html( $appr_author_name ? $appr_author_name : __( 'Unknown', 'apiary-press' ) )
							);
							?>
						</div>
					<?php endif; ?>
				</div>
				<?php if ( ! $appr_is_new_visit ) : ?>
					<a class="admin-link" href="<?php echo esc_url( get_edit_post_link( $appr_hive_visit_id, '' ) ); ?>">
						<?php echo esc_html__( 'WordPress Admin', 'apiary-press' ); ?>
					</a>
				<?php endif; ?>
			</header>

			<?php if ( isset( $_GET['created'] ) ) : ?>
				<div class="notice"><?php echo esc_html__( 'Visit saved.', 'apiary-press' ); ?></div>
			<?php endif; ?>

			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice"><?php echo esc_html__( 'Visit updated.', 'apiary-press' ); ?></div>
			<?php endif; ?>

			<?php if ( isset( $_GET['media_removed'] ) ) : ?>
				<div class="notice"><?php echo esc_html__( 'Media removed.', 'apiary-press' ); ?></div>
			<?php endif; ?>

			<?php if ( isset( $_GET['media_errors'] ) ) : ?>
				<div class="error">
					<?php
					$appr_media_error_count = absint( $_GET['media_errors'] );
					printf(
						/* translators: %d: number of media files that failed to upload. */
						esc_html( _n( '%d media file could not be uploaded.', '%d media files could not be uploaded.', $appr_media_error_count, 'apiary-press' ) ),
						(int) $appr_media_error_count
					);
					?>
				</div>
			<?php endif; ?>

			<?php if ( $appr_form_error ) : ?>
				<div class="error"><?php echo esc_html( $appr_form_error ); ?></div>
			<?php endif; ?>

			<div class="layout <?php echo $appr_is_new_visit ? 'layout-single' : ''; ?>">
				<section class="panel" aria-labelledby="edit-visit-heading">
					<h2 id="edit-visit-heading"><?php echo esc_html( $appr_form_heading ); ?></h2>
					<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id . '/visit/' . $appr_form_url_visit ) ); ?>">
						<input type="hidden" name="ap_action" value="<?php echo esc_attr( $appr_form_action ); ?>">
						<?php wp_nonce_field( $appr_form_nonce, 'ap_nonce' ); ?>

						<div class="date-time-grid">
							<div class="field">
								<label for="ap_visit_date"><?php echo esc_html__( 'Date', 'apiary-press' ); ?></label>
								<input id="ap_visit_date" name="ap_visit_date" type="date" value="<?php echo esc_attr( $appr_visit_date ); ?>" required>
							</div>

							<div class="field">
								<label for="ap_visit_time"><?php echo esc_html__( 'Time', 'apiary-press' ); ?></label>
								<input id="ap_visit_time" name="ap_visit_time" type="time" value="<?php echo esc_attr( $appr_visit_time ); ?>" required>
							</div>
						</div>

						<div class="field">
							<label for="ap_visit_reason"><?php echo esc_html__( 'Reason', 'apiary-press' ); ?></label>
							<select id="ap_visit_reason" name="ap_visit_reason">
								<?php foreach ( $appr_reason_labels as $appr_reason_slug => $appr_reason_label ) : ?>
									<option value="<?php echo esc_attr( $appr_reason_slug ); ?>" <?php selected( $appr_visit_reason, $appr_reason_slug ); ?>>
										<?php echo esc_html( $appr_reason_label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="check-grid">
							<?php foreach ( $appr_meta_labels as $appr_meta_key => $appr_label ) : ?>
								<div class="check-option">
									<input
										id="ap_visit_meta_<?php echo esc_attr( $appr_meta_key ); ?>"
										name="ap_visit_meta[]"
										type="checkbox"
										value="<?php echo esc_attr( $appr_meta_key ); ?>"
										<?php checked( in_array( $appr_meta_key, $appr_form_checked_meta, true ) ); ?>
									>
									<label for="ap_visit_meta_<?php echo esc_attr( $appr_meta_key ); ?>"><?php echo esc_html( $appr_label ); ?></label>
								</div>
							<?php endforeach; ?>
						</div>

						<div class="field">
							<label for="ap_visit_notes"><?php echo esc_html__( 'Notes', 'apiary-press' ); ?></label>
							<textarea id="ap_visit_notes" name="ap_visit_notes"><?php echo esc_textarea( $appr_visit_notes ); ?></textarea>
						</div>

						<?php if ( current_user_can( 'upload_files' ) ) : ?>
							<div class="field media-upload">
								<label for="ap_visit_media"><?php echo esc_html__( 'Add media', 'apiary-press' ); ?></label>
								<input id="ap_visit_media" name="ap_visit_media[]" type="file" accept="image/*,video/*" multiple>
								<p class="media-help"><?php echo esc_html__( 'Photos or videos are saved to the media library and linked to this visit.', 'apiary-press' ); ?></p>
							</div>
						<?php endif; ?>

						<button class="button" type="submit"><?php echo esc_html( $appr_form_button ); ?></button>
					</form>
				</section>

				<?php if ( ! $appr_is_new_visit ) : ?>
					<aside class="summary" aria-labelledby="visit-summary-heading">
						<h2 id="visit-summary-heading"><?php echo esc_html__( 'Visit Summary', 'apiary-press' ); ?></h2>

						<?php if ( trim( $appr_visit->post_content ) ) : ?>
							<p class="visit-notes"><?php echo esc_html( wp_strip_all_tags( $appr_visit->post_content ) ); ?></p>
						<?php else : ?>
							<p class="visit-notes"><?php echo esc_html__( 'No notes recorded.', 'apiary-press' ); ?></p>
						<?php endif; ?>

						<?php if ( isset( $appr_reason_labels[ $appr_visit_reason ] ) ) : ?>
							<p class="visit-reason">
								<strong><?php echo esc_html__( 'Reason:', 'apiary-press' ); ?></strong>
								<?php echo esc_html( $appr_reason_labels[ $appr_visit_reason ] ); ?>
							</p>
						<?php endif; ?>

						<?php if ( empty( $appr_active_flags ) ) : ?>
							<div class="muted"><?php echo esc_html__( 'No observations marked.', 'apiary-press' ); ?></div>
						<?php else : ?>
							<div class="badge-list">
								<?php foreach ( $appr_active_flags as $appr_meta_key => $appr_label ) : ?>
									<span class="badge <?php echo 'check_soon' === $appr_meta_key ? 'badge-attention' : ''; ?>">
										<?php echo esc_html( $appr_label ); ?>
									</span>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

						<div class="summary-section" aria-labelledby="visit-weather-heading">
							<h3 id="visit-weather-heading"><?php echo esc_html__( 'Registered Weather', 'apiary-press' ); ?></h3>

							<?php if ( $appr_weather_error ) : ?>
								<div class="muted"><?php echo esc_html( $appr_weather_error ); ?></div>
							<?php elseif ( empty( $appr_weather_values ) ) : ?>
								<div class="muted"><?php echo esc_html__( 'No weather snapshot recorded.', 'apiary-press' ); ?></div>
							<?php else : ?>
								<?php
								echo Weather::render_symbol_icon_html( $appr_weather_values['symbol_code'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								?>
								<dl class="weather-list">
									<?php foreach ( $appr_weather_values as $name => $weather_value ) : ?>
										<?php if ( 'symbol_code' === $name ) : ?>
											<?php continue; ?>
										<?php endif; ?>
										<div>
											<?php echo esc_html( $weather_value ); ?>
										</div>
									<?php endforeach; ?>
								</dl>
							<?php endif; ?>
						</div>

						<?php
						$appr_visit_media = Visit::get_visit_media( $appr_hive_visit_id );
						$appr_visit_url   = App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id . '/visit/' . $appr_hive_visit_id );
						?>
						<div class="summary-section" aria-labelledby="visit-media-heading">
							<h3 id="visit-media-heading"><?php echo esc_html__( 'Media', 'apiary-press' ); ?></h3>

							<?php if ( empty( $appr_visit_media ) ) : ?>
								<div class="muted"><?php echo esc_html__( 'No media attached.', 'apiary-press' ); ?></div>
							<?php else : ?>
								<div class="media-grid">
									<?php foreach ( $appr_visit_media as $appr_attachment ) : ?>
										<?php
										$appr_attachment_id  = (int) $appr_attachment->ID;
										$appr_attachment_url = wp_get_attachment_url( $appr_attachment_id );
										$appr_attachment_alt = trim( (string) get_post_meta( $appr_attachment_id, '_wp_attachment_image_alt', true ) );

										if ( '' === $appr_attachment_alt ) {
											$appr_attachment_alt = get_the_title( $appr_attachment_id );
										}

										$appr_is_image = wp_attachment_is_image( $appr_attachment_id );
										$appr_is_video = wp_attachment_is( 'video', $appr_attachment_id );
										?>
										<div class="media-item">
											<?php if ( $appr_is_image ) : ?>
												<a class="media-link" href="<?php echo esc_url( $appr_attachment_url ); ?>" target="_blank" rel="noopener">
													<?php echo wp_get_attachment_image( $appr_attachment_id, 'thumbnail', false, array( 'alt' => $appr_attachment_alt ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
												</a>
											<?php elseif ( $appr_is_video ) : ?>
												<video class="media-link" controls preload="metadata" src="<?php echo esc_url( $appr_attachment_url ); ?>"></video>
											<?php else : ?>
												<a class="media-link media-file" href="<?php echo esc_url( $appr_attachment_url ); ?>" target="_blank" rel="noopener">
													<?php echo esc_html( get_the_title( $appr_attachment_id ) ); ?>
												</a>
											<?php endif; ?>

											<?php if ( current_user_can( 'delete_post', $appr_attachment_id ) ) : ?>
												<form method="post" action="<?php echo esc_url( $appr_visit_url ); ?>" class="media-remove-form">
													<input type="hidden" name="ap_action" value="delete_visit_media">
													<input type="hidden" name="ap_attachment_id" value="<?php echo esc_attr( (string) $appr_attachment_id ); ?>">
													<?php wp_nonce_field( 'ap_delete_visit_media_' . $appr_hive_visit_id . '_' . $appr_attachment_id, 'ap_media_nonce' ); ?>
													<button
														type="submit"
														class="media-remove"
														aria-label="<?php echo esc_attr__( 'Remove media', 'apiary-press' ); ?>"
														onclick="return confirm('<?php echo esc_js( __( 'Remove this media from the visit?', 'apiary-press' ) ); ?>');"
													>&times;</button>
												</form>
											<?php endif; ?>
										</div>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>

						<form class="delete-form" method="post" action="<?php echo esc_url( App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id . '/visit/' . $appr_hive_visit_id ) ); ?>">
							<input type="hidden" name="ap_action" value="delete_visit">
							<?php wp_nonce_field( 'ap_delete_visit_' . $appr_hive_visit_id, 'ap_delete_nonce' ); ?>
							<p class="danger-text"><?php echo esc_html__( 'Remove this visit from the hive record.', 'apiary-press' ); ?></p>
							<button class="button button-danger" type="submit">
								<?php echo esc_html__( 'Remove Visit', 'apiary-press' ); ?>
							</button>
						</form>
					</aside>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</main>

	<?php wp_app_body_close(); ?>
</body>
</html>
