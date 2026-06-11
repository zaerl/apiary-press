<?php
/**
 * Hive form template for creating and editing hives in the Apiary Press app.
 */

use ApiaryPress\App;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ap_app_url' ) ) {
	function ap_app_url( string $path = '' ): string {
		return trailingslashit( home_url( '/apiary-press/' . ltrim( $path, '/' ) ) );
	}
}

if ( ! function_exists( 'ap_read_coordinate_input' ) ) {
	function ap_read_coordinate_input( string $field_name, string $label, float $minimum, float $maximum ): array {
		$raw_value = isset( $_POST[ $field_name ] ) ? trim( sanitize_text_field( wp_unslash( $_POST[ $field_name ] ) ) ) : '';

		if ( '' === $raw_value ) {
			return [
				'value' => '',
				'error' => '',
			];
		}

		if ( ! is_numeric( $raw_value ) ) {
			return [
				'value' => '',
				'error' => sprintf(
					/* translators: %s: coordinate field label */
					__( '%s must be a number.', 'apiary-press' ),
					$label
				),
			];
		}

		$value = (float) $raw_value;

		if ( $value < $minimum || $value > $maximum ) {
			return [
				'value' => '',
				'error' => sprintf(
					/* translators: 1: coordinate field label, 2: minimum value, 3: maximum value */
					__( '%1$s must be between %2$s and %3$s.', 'apiary-press' ),
					$label,
					(string) $minimum,
					(string) $maximum
				),
			];
		}

		return [
			'value' => (string) $value,
			'error' => '',
		];
	}
}

if ( ! function_exists( 'ap_update_coordinate_meta' ) ) {
	function ap_update_coordinate_meta( int $hive_id, string $meta_key, string $value ): void {
		if ( '' === $value ) {
			delete_post_meta( $hive_id, $meta_key );
			return;
		}

		update_post_meta( $hive_id, $meta_key, (float) $value );
	}
}

global $wp_app_route;

$route_params = isset( $wp_app_route['params'] ) && is_array( $wp_app_route['params'] ) ? $wp_app_route['params'] : [];
$hive_id	  = isset( $route_params['id'] ) ? absint( $route_params['id'] ) : absint( get_query_var( 'id' ) );
$is_new_hive  = 0 === $hive_id;
$hive		 = $hive_id ? get_post( $hive_id ) : null;
$form_error   = '';

$not_found = ! $is_new_hive && ( ! $hive || App::HIVE_POST_TYPE !== $hive->post_type );
$forbidden = ! $not_found && ( $is_new_hive ? ! current_user_can( 'edit_posts' ) : ! current_user_can( 'edit_post', $hive_id ) );

if ( $not_found ) {
	status_header( 404 );
} elseif ( $forbidden ) {
	status_header( 403 );
}

$action = isset( $_POST['ap_action'] ) ? sanitize_key( wp_unslash( $_POST['ap_action'] ) ) : '';

if ( ! $not_found && ! $forbidden && $is_new_hive && 'create_hive' === $action ) {
	$nonce		   = isset( $_POST['ap_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_nonce'] ) ) : '';
	$title		   = isset( $_POST['ap_hive_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_hive_name'] ) ) : '';
	$notes		   = isset( $_POST['ap_hive_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ap_hive_notes'] ) ) : '';
	$latitude_input  = ap_read_coordinate_input( 'ap_hive_latitude', __( 'Latitude', 'apiary-press' ), -90, 90 );
	$longitude_input = ap_read_coordinate_input( 'ap_hive_longitude', __( 'Longitude', 'apiary-press' ), -180, 180 );

	if ( ! wp_verify_nonce( $nonce, 'ap_create_hive' ) ) {
		$form_error = __( 'The hive could not be saved. Reload and try again.', 'apiary-press' );
	} elseif ( '' === $title ) {
		$form_error = __( 'Hive name is required.', 'apiary-press' );
	} elseif ( $latitude_input['error'] ) {
		$form_error = $latitude_input['error'];
	} elseif ( $longitude_input['error'] ) {
		$form_error = $longitude_input['error'];
	} elseif ( ( '' === $latitude_input['value'] ) !== ( '' === $longitude_input['value'] ) ) {
		$form_error = __( 'Add both latitude and longitude, or leave both blank.', 'apiary-press' );
	} else {
		$new_hive_id = wp_insert_post( [
			'post_type'	=> App::HIVE_POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => $notes,
			'post_author'  => get_current_user_id(),
		], true );

		if ( is_wp_error( $new_hive_id ) ) {
			$form_error = $new_hive_id->get_error_message();
		} else {
			ap_update_coordinate_meta( $new_hive_id, 'latitude', $latitude_input['value'] );
			ap_update_coordinate_meta( $new_hive_id, 'longitude', $longitude_input['value'] );

			wp_safe_redirect( add_query_arg( 'created', '1', ap_app_url( 'hive/' . absint( $new_hive_id ) ) ) );
			exit;
		}
	}
}

if ( ! $not_found && ! $forbidden && ! $is_new_hive && 'update_hive' === $action ) {
	$nonce		   = isset( $_POST['ap_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_nonce'] ) ) : '';
	$title		   = isset( $_POST['ap_hive_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_hive_name'] ) ) : '';
	$notes		   = isset( $_POST['ap_hive_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ap_hive_notes'] ) ) : '';
	$latitude_input  = ap_read_coordinate_input( 'ap_hive_latitude', __( 'Latitude', 'apiary-press' ), -90, 90 );
	$longitude_input = ap_read_coordinate_input( 'ap_hive_longitude', __( 'Longitude', 'apiary-press' ), -180, 180 );

	if ( ! wp_verify_nonce( $nonce, 'ap_update_hive_' . $hive_id ) ) {
		$form_error = __( 'The hive could not be saved. Reload and try again.', 'apiary-press' );
	} elseif ( '' === $title ) {
		$form_error = __( 'Hive name is required.', 'apiary-press' );
	} elseif ( $latitude_input['error'] ) {
		$form_error = $latitude_input['error'];
	} elseif ( $longitude_input['error'] ) {
		$form_error = $longitude_input['error'];
	} elseif ( ( '' === $latitude_input['value'] ) !== ( '' === $longitude_input['value'] ) ) {
		$form_error = __( 'Add both latitude and longitude, or leave both blank.', 'apiary-press' );
	} else {
		$updated_id = wp_update_post( [
			'ID'		   => $hive_id,
			'post_title'   => $title,
			'post_content' => $notes,
		], true );

		if ( is_wp_error( $updated_id ) ) {
			$form_error = $updated_id->get_error_message();
		} else {
			ap_update_coordinate_meta( $hive_id, 'latitude', $latitude_input['value'] );
			ap_update_coordinate_meta( $hive_id, 'longitude', $longitude_input['value'] );

			wp_safe_redirect( add_query_arg( 'updated', '1', ap_app_url( 'hive/' . $hive_id ) ) );
			exit;
		}
	}
}

if ( ! $not_found && ! $is_new_hive ) {
	$hive = get_post( $hive_id );
}

$hive_title	 = ! $not_found && ! $is_new_hive ? get_the_title( $hive ) : '';
$hive_notes	 = ! $not_found && ! $is_new_hive ? $hive->post_content : '';
$hive_latitude  = ! $not_found && ! $is_new_hive ? get_post_meta( $hive_id, 'latitude', true ) : '';
$hive_longitude = ! $not_found && ! $is_new_hive ? get_post_meta( $hive_id, 'longitude', true ) : '';
$page_title	 = $is_new_hive ? __( 'New Hive', 'apiary-press' ) : __( 'Edit Hive', 'apiary-press' );
$form_action	= $is_new_hive ? 'create_hive' : 'update_hive';
$form_nonce	 = $is_new_hive ? 'ap_create_hive' : 'ap_update_hive_' . $hive_id;
$form_url	   = $is_new_hive ? ap_app_url( 'hive/new' ) : ap_app_url( 'hive/' . $hive_id . '/edit' );
$button_text	= $is_new_hive ? __( 'Save Hive', 'apiary-press' ) : __( 'Update Hive', 'apiary-press' );

if ( $form_error ) {
	$hive_title	 = isset( $_POST['ap_hive_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_hive_name'] ) ) : $hive_title;
	$hive_notes	 = isset( $_POST['ap_hive_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ap_hive_notes'] ) ) : $hive_notes;
	$hive_latitude  = isset( $_POST['ap_hive_latitude'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_hive_latitude'] ) ) : $hive_latitude;
	$hive_longitude = isset( $_POST['ap_hive_longitude'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_hive_longitude'] ) ) : $hive_longitude;
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
			width: min(760px, calc(100% - 32px));
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
			font-size: 34px;
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
		.error {
			background: rgba(176, 30, 30, 0.12);
			border: 1px solid rgba(176, 30, 30, 0.35);
		}
		.panel,
		.message {
			background: var(--wp-app-color-surface);
			border: 1px solid var(--wp-app-color-border);
			border-radius: 8px;
			padding: 20px;
		}
		.hive-notes {
			color: var(--wp-app-color-muted);
			line-height: 1.55;
			margin: 8px 0 0;
		}
		label {
			display: block;
			font-size: 13px;
			font-weight: 700;
			margin-bottom: 6px;
		}
		input[type="text"],
		input[type="number"],
		textarea {
			width: 100%;
			border: 1px solid var(--wp-app-color-border);
			border-radius: 6px;
			background: var(--wp-app-color-background);
			color: var(--wp-app-color-text);
			font: inherit;
			padding: 10px 12px;
		}
		textarea {
			min-height: 160px;
			resize: vertical;
		}
		.field { margin-bottom: 16px; }
		.coordinate-grid {
			display: grid;
			gap: 16px;
			grid-template-columns: repeat(2, minmax(0, 1fr));
		}
		.coordinate-actions {
			align-items: center;
			display: flex;
			flex-wrap: wrap;
			gap: 10px;
			margin: -2px 0 18px;
		}
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
		.button-secondary {
			background: transparent;
			border: 1px solid var(--wp-app-color-border);
			color: var(--wp-app-color-text);
		}
		.button:disabled {
			cursor: not-allowed;
			opacity: 0.65;
		}
		.location-status {
			color: var(--wp-app-color-muted);
			font-size: 13px;
			min-height: 18px;
		}
		@media (max-width: 760px) {
			.shell { width: min(100% - 24px, 760px); padding-top: 24px; }
			.topbar { flex-direction: column; align-items: flex-start; }
			.coordinate-grid { grid-template-columns: 1fr; }
			.coordinate-actions { align-items: flex-start; flex-direction: column; }
		}
	</style>
</head>
<body>
	<?php wp_app_body_open(); ?>

	<main class="shell">
		<?php if ( $not_found ) : ?>
			<section class="message">
				<h1><?php echo esc_html__( 'Hive Not Found', 'apiary-press' ); ?></h1>
				<p class="hive-notes"><?php echo esc_html__( 'The requested hive is not available.', 'apiary-press' ); ?></p>
				<p><a class="admin-link" href="<?php echo esc_url( ap_app_url() ); ?>"><?php echo esc_html__( 'Back to Hives', 'apiary-press' ); ?></a></p>
			</section>
		<?php elseif ( $forbidden ) : ?>
			<section class="message">
				<h1><?php echo esc_html__( 'Access Denied', 'apiary-press' ); ?></h1>
				<p class="hive-notes">
					<?php
					echo esc_html(
						$is_new_hive
							? __( 'You do not have permission to add hives.', 'apiary-press' )
							: __( 'You do not have permission to edit this hive.', 'apiary-press' )
					);
					?>
				</p>
				<p><a class="admin-link" href="<?php echo esc_url( ap_app_url() ); ?>"><?php echo esc_html__( 'Back to Hives', 'apiary-press' ); ?></a></p>
			</section>
		<?php else : ?>
			<header class="topbar">
				<div>
					<a class="crumb" href="<?php echo esc_url( $is_new_hive ? ap_app_url() : ap_app_url( 'hive/' . $hive_id ) ); ?>">
						<?php echo esc_html( $is_new_hive ? __( 'Hives', 'apiary-press' ) : get_the_title( $hive ) ); ?>
					</a>
					<h1><?php echo esc_html( $page_title ); ?></h1>
				</div>
			</header>

			<?php if ( $form_error ) : ?>
				<div class="error"><?php echo esc_html( $form_error ); ?></div>
			<?php endif; ?>

			<section class="panel" aria-labelledby="hive-form-heading">
				<h2 id="hive-form-heading"><?php echo esc_html( $page_title ); ?></h2>
				<form method="post" action="<?php echo esc_url( $form_url ); ?>">
					<input type="hidden" name="ap_action" value="<?php echo esc_attr( $form_action ); ?>">
					<?php wp_nonce_field( $form_nonce, 'ap_nonce' ); ?>

					<div class="field">
						<label for="ap_hive_name"><?php echo esc_html__( 'Name', 'apiary-press' ); ?></label>
						<input id="ap_hive_name" name="ap_hive_name" type="text" value="<?php echo esc_attr( $hive_title ); ?>" required>
					</div>

					<div class="coordinate-grid">
						<div class="field">
							<label for="ap_hive_latitude"><?php echo esc_html__( 'Latitude', 'apiary-press' ); ?></label>
							<input id="ap_hive_latitude" name="ap_hive_latitude" type="number" inputmode="decimal" min="-90" max="90" step="any" value="<?php echo esc_attr( $hive_latitude ); ?>">
						</div>

						<div class="field">
							<label for="ap_hive_longitude"><?php echo esc_html__( 'Longitude', 'apiary-press' ); ?></label>
							<input id="ap_hive_longitude" name="ap_hive_longitude" type="number" inputmode="decimal" min="-180" max="180" step="any" value="<?php echo esc_attr( $hive_longitude ); ?>">
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
						<textarea id="ap_hive_notes" name="ap_hive_notes"><?php echo esc_textarea( $hive_notes ); ?></textarea>
					</div>

					<button class="button" type="submit"><?php echo esc_html( $button_text ); ?></button>
				</form>
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
			const messages = <?php echo wp_json_encode( [
				'idle'		=> '',
				'locating'	=> __( 'Getting current location...', 'apiary-press' ),
				'ready'	   => __( 'Current location set.', 'apiary-press' ),
				'unavailable' => __( 'Location access is unavailable in this browser.', 'apiary-press' ),
				'insecure'	=> __( 'Location access requires HTTPS or localhost.', 'apiary-press' ),
				'denied'	  => __( 'Allow location access and try again.', 'apiary-press' ),
				'failed'	  => __( 'Current location could not be read.', 'apiary-press' ),
			] ); ?>;

			if (!locationButton || !latitudeInput || !longitudeInput || !status) {
				return;
			}

			const setStatus = function(message) {
				status.textContent = message;
			};

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
