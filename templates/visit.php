<?php
/**
 * Visit template for displaying and managing individual hive visits in the Apiary Press app.
 *
 * @package ApiaryPress
 */

namespace ApiaryPress;

use ApiaryPress\App;
use ApiaryPress\Weather;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wp_app_route;

$route_params    = isset( $wp_app_route['params'] ) && is_array( $wp_app_route['params'] ) ? $wp_app_route['params'] : array();
$hive_id         = isset( $route_params['id'] ) ? absint( $route_params['id'] ) : absint( get_query_var( 'id' ) );
$hive_visit_slug = isset( $route_params['hive_visit'] ) ? sanitize_key( $route_params['hive_visit'] ) : sanitize_key( get_query_var( 'hive_visit' ) );
$is_new_visit    = 'new' === $hive_visit_slug;
$hive_visit_id   = $is_new_visit ? 0 : absint( $hive_visit_slug );
$hive            = $hive_id ? get_post( $hive_id ) : null;
$visit           = $hive_visit_id ? get_post( $hive_visit_id ) : null;
$meta_labels     = App::get_visit_boolean_meta_labels();
$form_error      = '';

$not_found = ! $hive
	|| App::HIVE_POST_TYPE !== $hive->post_type
	|| ( ! $is_new_visit && ( ! $visit || App::HIVE_VISIT_POST_TYPE !== $visit->post_type || absint( $visit->post_parent ) !== $hive_id ) );

$forbidden = ! $not_found && ( $is_new_visit ? ! current_user_can( 'edit_post', $hive_id ) : ! current_user_can( 'edit_post', $hive_visit_id ) );

if ( $not_found ) {
	status_header( 404 );
} elseif ( $forbidden ) {
	status_header( 403 );
}

$action = isset( $_POST['ap_action'] ) ? sanitize_key( wp_unslash( $_POST['ap_action'] ) ) : '';

if ( ! $not_found && ! $forbidden && $is_new_visit && 'create_visit' === $action ) {
	$nonce          = isset( $_POST['ap_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_nonce'] ) ) : '';
	$visit_date_raw = isset( $_POST['ap_visit_date'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_visit_date'] ) ) : current_time( 'Y-m-d' );
	$visit_time_raw = isset( $_POST['ap_visit_time'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_visit_time'] ) ) : current_time( 'H:i' );
	$notes          = isset( $_POST['ap_visit_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ap_visit_notes'] ) ) : '';
	$selected_meta  = isset( $_POST['ap_visit_meta'] ) && is_array( $_POST['ap_visit_meta'] )
		? array_map( 'sanitize_key', wp_unslash( $_POST['ap_visit_meta'] ) )
		: array();

	if ( ! wp_verify_nonce( $nonce, 'ap_create_visit_' . $hive_id ) ) {
		$form_error = __( 'The visit could not be saved. Reload and try again.', 'apiary-press' );
	} elseif ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $visit_date_raw ) ) {
		$form_error = __( 'Visit date is invalid.', 'apiary-press' );
	} elseif ( ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $visit_time_raw ) ) {
		$form_error = __( 'Visit time is invalid.', 'apiary-press' );
	} else {
		$visit_timestamp = strtotime( $visit_date_raw . ' ' . $visit_time_raw . ':00' );

		if ( false === $visit_timestamp ) {
			$form_error = __( 'Visit date is invalid.', 'apiary-press' );
		} else {
			$visit_title = sprintf(
				/* translators: 1: hive title, 2: visit date */
				__( '%1$s visit on %2$s', 'apiary-press' ),
				get_the_title( $hive ),
				date_i18n( get_option( 'date_format' ), $visit_timestamp )
			);

			$visit_id = wp_insert_post(
				array(
					'post_type'    => App::HIVE_VISIT_POST_TYPE,
					'post_status'  => 'publish',
					'post_title'   => $visit_title,
					'post_content' => $notes,
					'post_parent'  => $hive_id,
					'post_author'  => get_current_user_id(),
					'post_date'    => $visit_date_raw . ' ' . $visit_time_raw . ':00',
				),
				true
			);

			if ( is_wp_error( $visit_id ) ) {
				$form_error = $visit_id->get_error_message();
			} else {
				foreach ( App::VISIT_BOOLEAN_META_KEYS as $meta_key ) {
					update_post_meta( $visit_id, $meta_key, in_array( $meta_key, $selected_meta, true ) ? '1' : '0' );
				}

				Weather::store_visit_weather_snapshot( $visit_id, $hive_id, $visit_date_raw, $visit_time_raw );

				wp_safe_redirect( add_query_arg( 'created', '1', App::get_url( 'hive/' . $hive_id . '/visit/' . absint( $visit_id ) ) ) );
				exit;
			}
		}
	}
}

if ( ! $not_found && ! $forbidden && ! $is_new_visit && 'delete_visit' === $action ) {
	$nonce = isset( $_POST['ap_delete_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_delete_nonce'] ) ) : '';

	if ( ! wp_verify_nonce( $nonce, 'ap_delete_visit_' . $hive_visit_id ) ) {
		$form_error = __( 'The visit could not be removed. Reload and try again.', 'apiary-press' );
	} elseif ( ! current_user_can( 'delete_post', $hive_visit_id ) ) {
		$form_error = __( 'You do not have permission to remove this visit.', 'apiary-press' );
	} else {
		$deleted = wp_delete_post( $hive_visit_id, true );

		if ( ! $deleted ) {
			$form_error = __( 'The visit could not be removed.', 'apiary-press' );
		} else {
			wp_safe_redirect( add_query_arg( 'visit_deleted', '1', App::get_url( 'hive/' . $hive_id ) ) );
			exit;
		}
	}
}

if ( ! $not_found && ! $forbidden && ! $is_new_visit && 'update_visit' === $action ) {
	$nonce          = isset( $_POST['ap_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_nonce'] ) ) : '';
	$visit_date_raw = isset( $_POST['ap_visit_date'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_visit_date'] ) ) : '';
	$visit_time_raw = isset( $_POST['ap_visit_time'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_visit_time'] ) ) : mysql2date( 'H:i', $visit->post_date, false );
	$notes          = isset( $_POST['ap_visit_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ap_visit_notes'] ) ) : '';
	$selected_meta  = isset( $_POST['ap_visit_meta'] ) && is_array( $_POST['ap_visit_meta'] )
		? array_map( 'sanitize_key', wp_unslash( $_POST['ap_visit_meta'] ) )
		: array();

	if ( ! wp_verify_nonce( $nonce, 'ap_update_visit_' . $hive_visit_id ) ) {
		$form_error = __( 'The visit could not be saved. Reload and try again.', 'apiary-press' );
	} elseif ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $visit_date_raw ) ) {
		$form_error = __( 'Visit date is invalid.', 'apiary-press' );
	} elseif ( ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $visit_time_raw ) ) {
		$form_error = __( 'Visit time is invalid.', 'apiary-press' );
	} else {
		$visit_timestamp = strtotime( $visit_date_raw . ' ' . $visit_time_raw . ':00' );

		if ( false === $visit_timestamp ) {
			$form_error = __( 'Visit date is invalid.', 'apiary-press' );
		} else {
			$visit_title = sprintf(
				/* translators: 1: hive title, 2: visit date */
				__( '%1$s visit on %2$s', 'apiary-press' ),
				get_the_title( $hive ),
				date_i18n( get_option( 'date_format' ), $visit_timestamp )
			);

			$updated_id = wp_update_post(
				array(
					'ID'           => $hive_visit_id,
					'post_title'   => $visit_title,
					'post_content' => $notes,
					'post_date'    => $visit_date_raw . ' ' . $visit_time_raw . ':00',
					'post_parent'  => $hive_id,
				),
				true
			);

			if ( is_wp_error( $updated_id ) ) {
				$form_error = $updated_id->get_error_message();
			} else {
				foreach ( App::VISIT_BOOLEAN_META_KEYS as $meta_key ) {
					update_post_meta( $hive_visit_id, $meta_key, in_array( $meta_key, $selected_meta, true ) ? '1' : '0' );
				}

				wp_safe_redirect( add_query_arg( 'updated', '1', App::get_url( 'hive/' . $hive_id . '/visit/' . $hive_visit_id ) ) );
				exit;
			}
		}
	}
}

if ( ! $not_found && ! $is_new_visit ) {
	$visit = get_post( $hive_visit_id );
}

$visit_date        = ! $not_found && ! $is_new_visit ? mysql2date( 'Y-m-d', $visit->post_date, false ) : current_time( 'Y-m-d' );
$visit_time        = ! $not_found && ! $is_new_visit ? mysql2date( 'H:i', $visit->post_date, false ) : current_time( 'H:i' );
$visit_notes       = ! $not_found && ! $is_new_visit ? $visit->post_content : '';
$active_flags      = array();
$form_checked_meta = array();
$weather_values    = array();
$weather_error     = '';
$page_title        = $is_new_visit ? __( 'New Visit', 'apiary-press' ) : ( $visit ? get_the_title( $visit ) : __( 'Hive Visit', 'apiary-press' ) );
$form_action       = $is_new_visit ? 'create_visit' : 'update_visit';
$form_nonce        = $is_new_visit ? 'ap_create_visit_' . $hive_id : 'ap_update_visit_' . $hive_visit_id;
$form_heading      = $is_new_visit ? __( 'New Visit', 'apiary-press' ) : __( 'Edit Visit', 'apiary-press' );
$form_button       = $is_new_visit ? __( 'Save Visit', 'apiary-press' ) : __( 'Update Visit', 'apiary-press' );
$form_url_visit    = $is_new_visit ? 'new' : (string) $hive_visit_id;

if ( ! $not_found && ! $is_new_visit ) {
	foreach ( $meta_labels as $meta_key => $label ) {
		if ( rest_sanitize_boolean( get_post_meta( $hive_visit_id, $meta_key, true ) ) ) {
			$active_flags[ $meta_key ] = $label;
			$form_checked_meta[]       = $meta_key;
		}
	}

	$weather_error  = get_post_meta( $hive_visit_id, 'weather_error', true );

	foreach ( Weather::FORECAST_UNITS as $meta_key => $label ) {
		$value = get_post_meta( $hive_visit_id, $meta_key, true );

		if ( '' !== $value && null !== $value ) {
			$value = Weather::get_forecast_display_value( $meta_key, $value );

			if ( $value ) {
				$weather_values[ $meta_key ] = $value;
			}
		}
	}
}

if ( $form_error ) {
	$visit_date        = isset( $_POST['ap_visit_date'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_visit_date'] ) ) : $visit_date;
	$visit_time        = isset( $_POST['ap_visit_time'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_visit_time'] ) ) : $visit_time;
	$visit_notes       = isset( $_POST['ap_visit_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ap_visit_notes'] ) ) : $visit_notes;
	$form_checked_meta = isset( $_POST['ap_visit_meta'] ) && is_array( $_POST['ap_visit_meta'] )
		? array_map( 'sanitize_key', wp_unslash( $_POST['ap_visit_meta'] ) )
		: array();
}
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php wp_app_title( $page_title ); ?></title>
	<?php wp_app_head(); ?>
	<style>
		:root { color-scheme: light dark; }
		* { box-sizing: border-box; }
		body {
			margin: 0;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			background: var(--wp-app-color-background);
			color: var(--wp-app-color-text);
		}
		a { color: var(--wp-app-color-link); }
		.shell {
			width: min(960px, calc(100% - 32px));
			margin: 0 auto;
			padding: 32px 0 56px;
		}
		.topbar {
			display: flex;
			justify-content: space-between;
			gap: 16px;
			margin-bottom: 24px;
		}
		.crumb {
			color: var(--wp-app-color-muted);
			display: inline-flex;
			font-size: 13px;
			font-weight: 700;
			margin-bottom: 8px;
			text-decoration: none;
		}
		h1 {
			margin: 0;
			font-size: 32px;
			line-height: 1.15;
			letter-spacing: 0;
		}
		h2 {
			margin: 0 0 16px;
			font-size: 19px;
			line-height: 1.3;
			letter-spacing: 0;
		}
		.admin-link {
			border: 1px solid var(--wp-app-color-border);
			border-radius: 6px;
			color: var(--wp-app-color-text);
			display: inline-flex;
			font-weight: 700;
			height: fit-content;
			padding: 9px 12px;
			text-decoration: none;
			white-space: nowrap;
		}
		.notice,
		.error {
			border-radius: 6px;
			margin-bottom: 18px;
			padding: 12px 14px;
		}
		.notice {
			background: rgba(30, 130, 76, 0.12);
			border: 1px solid rgba(30, 130, 76, 0.35);
		}
		.error {
			background: rgba(176, 30, 30, 0.12);
			border: 1px solid rgba(176, 30, 30, 0.35);
		}
		.panel,
		.summary,
		.message {
			background: var(--wp-app-color-surface);
			border: 1px solid var(--wp-app-color-border);
			border-radius: 8px;
			padding: 20px;
		}
		.layout {
			display: grid;
			gap: 24px;
			grid-template-columns: 1fr 300px;
			align-items: start;
		}
		.layout-single {
			grid-template-columns: 1fr;
		}
		.summary h2 { margin-bottom: 10px; }
		.visit-notes {
			color: var(--wp-app-color-muted);
			line-height: 1.55;
			margin: 0 0 14px;
		}
		label {
			display: block;
			font-size: 13px;
			font-weight: 700;
			margin-bottom: 6px;
		}
		input[type="date"],
		input[type="time"],
		textarea {
			width: 100%;
			border: 1px solid var(--wp-app-color-border);
			border-radius: 6px;
			background: var(--wp-app-color-background);
			color: var(--wp-app-color-text);
			font: inherit;
			padding: 10px 12px;
		}
		textarea { min-height: 140px; resize: vertical; }
		.field { margin-bottom: 16px; }
		.date-time-grid {
			display: grid;
			gap: 16px;
			grid-template-columns: repeat(2, minmax(0, 1fr));
		}
		.check-grid {
			display: grid;
			gap: 8px;
			grid-template-columns: repeat(2, minmax(0, 1fr));
			margin-bottom: 18px;
		}
		.check-option {
			align-items: center;
			background: var(--wp-app-color-background);
			border: 1px solid var(--wp-app-color-border);
			border-radius: 6px;
			display: flex;
			gap: 10px;
			min-height: 42px;
			padding: 9px 10px;
		}
		.check-option label {
			cursor: pointer;
			font-size: 14px;
			font-weight: 600;
			margin: 0;
		}
		.check-option input { flex: 0 0 auto; }
		.button {
			appearance: none;
			background: #1e824c;
			border: 0;
			border-radius: 6px;
			color: #fff;
			cursor: pointer;
			font: inherit;
			font-weight: 700;
			line-height: 1.2;
			padding: 11px 14px;
		}
		.button-danger {
			background: #b91c1c;
		}
		.badge-list {
			display: flex;
			flex-wrap: wrap;
			gap: 7px;
		}
		.badge {
			background: var(--wp-app-color-surface-alt);
			border: 1px solid var(--wp-app-color-border);
			border-radius: 999px;
			display: inline-flex;
			font-size: 13px;
			font-weight: 700;
			padding: 5px 9px;
		}
		.badge-attention {
			background: rgba(214, 126, 0, 0.14);
			border-color: rgba(214, 126, 0, 0.4);
		}
		.muted {
			color: var(--wp-app-color-muted);
			font-size: 13px;
		}
		.summary-section {
			border-top: 1px solid var(--wp-app-color-border);
			margin-top: 18px;
			padding-top: 18px;
		}
		.summary-section h3 {
			font-size: 14px;
			line-height: 1.3;
			margin: 0 0 10px;
		}
		.weather-list {
			display: grid;
			gap: 9px;
			margin: 0;
		}
		.weather-list div {
			display: grid;
			gap: 2px;
		}
		.weather-list dt {
			color: var(--wp-app-color-muted);
			font-size: 12px;
			font-weight: 700;
		}
		.weather-list dd {
			font-size: 14px;
			font-weight: 700;
			margin: 0;
		}
		.delete-form {
			border-top: 1px solid var(--wp-app-color-border);
			margin-top: 18px;
			padding-top: 18px;
		}
		.danger-text {
			color: var(--wp-app-color-muted);
			font-size: 13px;
			line-height: 1.45;
			margin: 0 0 12px;
		}
		@media (max-width: 760px) {
			.shell { width: min(100% - 24px, 960px); padding-top: 24px; }
			.topbar { flex-direction: column; }
			.layout { grid-template-columns: 1fr; }
			.date-time-grid { grid-template-columns: 1fr; }
			.check-grid { grid-template-columns: 1fr; }
		}
	</style>
</head>
<body>
	<?php wp_app_body_open(); ?>

	<main class="shell">
		<?php if ( $not_found ) : ?>
			<section class="message">
				<h1><?php echo esc_html__( 'Visit Not Found', 'apiary-press' ); ?></h1>
				<p class="visit-notes"><?php echo esc_html__( 'The requested visit is not available for this hive.', 'apiary-press' ); ?></p>
				<p><a class="admin-link" href="<?php echo esc_url( App::get_url() ); ?>"><?php echo esc_html__( 'Back to Hives', 'apiary-press' ); ?></a></p>
			</section>
		<?php elseif ( $forbidden ) : ?>
			<section class="message">
				<h1><?php echo esc_html__( 'Access Denied', 'apiary-press' ); ?></h1>
				<p class="visit-notes">
					<?php
					echo esc_html(
						$is_new_visit
							? __( 'You do not have permission to add a visit to this hive.', 'apiary-press' )
							: __( 'You do not have permission to edit this visit.', 'apiary-press' )
					);
					?>
				</p>
				<p><a class="admin-link" href="<?php echo esc_url( App::get_url( 'hive/' . $hive_id ) ); ?>"><?php echo esc_html__( 'Back to Hive', 'apiary-press' ); ?></a></p>
			</section>
		<?php else : ?>
			<header class="topbar">
				<div>
					<a class="crumb" href="<?php echo esc_url( App::get_url( 'hive/' . $hive_id ) ); ?>"><?php echo esc_html( get_the_title( $hive ) ); ?></a>
					<h1>
						<?php
						echo esc_html(
							$is_new_visit
								? __( 'New Visit', 'apiary-press' )
								: mysql2date( get_option( 'date_format' ), $visit->post_date )
						);
						?>
					</h1>
				</div>
				<?php if ( ! $is_new_visit ) : ?>
					<a class="admin-link" href="<?php echo esc_url( get_edit_post_link( $hive_visit_id, '' ) ); ?>">
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

			<?php if ( $form_error ) : ?>
				<div class="error"><?php echo esc_html( $form_error ); ?></div>
			<?php endif; ?>

			<div class="layout <?php echo $is_new_visit ? 'layout-single' : ''; ?>">
				<section class="panel" aria-labelledby="edit-visit-heading">
					<h2 id="edit-visit-heading"><?php echo esc_html( $form_heading ); ?></h2>
					<form method="post" action="<?php echo esc_url( App::get_url( 'hive/' . $hive_id . '/visit/' . $form_url_visit ) ); ?>">
						<input type="hidden" name="ap_action" value="<?php echo esc_attr( $form_action ); ?>">
						<?php wp_nonce_field( $form_nonce, 'ap_nonce' ); ?>

						<div class="date-time-grid">
							<div class="field">
								<label for="ap_visit_date"><?php echo esc_html__( 'Date', 'apiary-press' ); ?></label>
								<input id="ap_visit_date" name="ap_visit_date" type="date" value="<?php echo esc_attr( $visit_date ); ?>" required>
							</div>

							<div class="field">
								<label for="ap_visit_time"><?php echo esc_html__( 'Time', 'apiary-press' ); ?></label>
								<input id="ap_visit_time" name="ap_visit_time" type="time" value="<?php echo esc_attr( $visit_time ); ?>" required>
							</div>
						</div>

						<div class="check-grid">
							<?php foreach ( $meta_labels as $meta_key => $label ) : ?>
								<div class="check-option">
									<input
										id="ap_visit_meta_<?php echo esc_attr( $meta_key ); ?>"
										name="ap_visit_meta[]"
										type="checkbox"
										value="<?php echo esc_attr( $meta_key ); ?>"
										<?php checked( in_array( $meta_key, $form_checked_meta, true ) ); ?>
									>
									<label for="ap_visit_meta_<?php echo esc_attr( $meta_key ); ?>"><?php echo esc_html( $label ); ?></label>
								</div>
							<?php endforeach; ?>
						</div>

						<div class="field">
							<label for="ap_visit_notes"><?php echo esc_html__( 'Notes', 'apiary-press' ); ?></label>
							<textarea id="ap_visit_notes" name="ap_visit_notes"><?php echo esc_textarea( $visit_notes ); ?></textarea>
						</div>

						<button class="button" type="submit"><?php echo esc_html( $form_button ); ?></button>
					</form>
				</section>

				<?php if ( ! $is_new_visit ) : ?>
					<aside class="summary" aria-labelledby="visit-summary-heading">
						<h2 id="visit-summary-heading"><?php echo esc_html__( 'Visit Summary', 'apiary-press' ); ?></h2>

						<?php if ( trim( $visit->post_content ) ) : ?>
							<p class="visit-notes"><?php echo esc_html( wp_strip_all_tags( $visit->post_content ) ); ?></p>
						<?php else : ?>
							<p class="visit-notes"><?php echo esc_html__( 'No notes recorded.', 'apiary-press' ); ?></p>
						<?php endif; ?>

						<?php if ( empty( $active_flags ) ) : ?>
							<div class="muted"><?php echo esc_html__( 'No observations marked.', 'apiary-press' ); ?></div>
						<?php else : ?>
							<div class="badge-list">
								<?php foreach ( $active_flags as $meta_key => $label ) : ?>
									<span class="badge <?php echo 'check_soon' === $meta_key ? 'badge-attention' : ''; ?>">
										<?php echo esc_html( $label ); ?>
									</span>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

						<div class="summary-section" aria-labelledby="visit-weather-heading">
							<h3 id="visit-weather-heading"><?php echo esc_html__( 'Registered Weather', 'apiary-press' ); ?></h3>

							<?php if ( $weather_error ) : ?>
								<div class="muted"><?php echo esc_html( $weather_error ); ?></div>
							<?php elseif ( empty( $weather_values ) ) : ?>
								<div class="muted"><?php echo esc_html__( 'No weather snapshot recorded.', 'apiary-press' ); ?></div>
							<?php else : ?>
								<dl class="weather-list">
									<?php foreach ( $weather_values as $name => $weather_value ) : ?>
										<div>
											<dt><?php echo esc_html( $name ); ?></dt>
											<dd><?php echo esc_html( $weather_value ); ?></dd>
										</div>
									<?php endforeach; ?>
								</dl>
							<?php endif; ?>
						</div>

						<form class="delete-form" method="post" action="<?php echo esc_url( App::get_url( 'hive/' . $hive_id . '/visit/' . $hive_visit_id ) ); ?>">
							<input type="hidden" name="ap_action" value="delete_visit">
							<?php wp_nonce_field( 'ap_delete_visit_' . $hive_visit_id, 'ap_delete_nonce' ); ?>
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
