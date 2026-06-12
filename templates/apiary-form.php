<?php
/**
 * Apiary form template for creating and editing apiaries in the Apiary Press app.
 *
 * @package ApiaryPress
 */

namespace ApiaryPress;

use ApiaryPress\App;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wp_app_route;

$appr_route_params  = isset( $wp_app_route['params'] ) && is_array( $wp_app_route['params'] ) ? $wp_app_route['params'] : array();
$appr_apiary_id     = isset( $appr_route_params['id'] ) ? absint( $appr_route_params['id'] ) : absint( get_query_var( 'id' ) );
$appr_is_new_apiary = 0 === $appr_apiary_id;
$appr_apiary        = $appr_apiary_id ? get_post( $appr_apiary_id ) : null;
$appr_form_error    = '';

$appr_not_found = ! $appr_is_new_apiary && ( ! $appr_apiary || Apiary::APIARY_POST_TYPE !== $appr_apiary->post_type );
$appr_forbidden = ! $appr_not_found && ( $appr_is_new_apiary ? ! current_user_can( 'edit_posts' ) : ! current_user_can( 'edit_post', $appr_apiary_id ) );

if ( $appr_not_found ) {
	status_header( 404 );
} elseif ( $appr_forbidden ) {
	status_header( 403 );
}

$appr_action = isset( $_POST['ap_action'] ) ? sanitize_key( wp_unslash( $_POST['ap_action'] ) ) : '';

if ( ! $appr_not_found && ! $appr_forbidden && $appr_is_new_apiary && 'create_apiary' === $appr_action ) {
	$appr_nonce = isset( $_POST['ap_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_nonce'] ) ) : '';
	$appr_title = isset( $_POST['ap_apiary_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_apiary_name'] ) ) : '';
	$appr_notes = isset( $_POST['ap_apiary_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ap_apiary_notes'] ) ) : '';

	if ( ! wp_verify_nonce( $appr_nonce, 'ap_create_apiary' ) ) {
		$appr_form_error = __( 'The apiary could not be saved. Reload and try again.', 'apiary-press' );
	} elseif ( '' === $appr_title ) {
		$appr_form_error = __( 'Apiary name is required.', 'apiary-press' );
	} else {
		$appr_new_apiary_id = wp_insert_post(
			array(
				'post_type'    => Apiary::APIARY_POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => $appr_title,
				'post_content' => $appr_notes,
				'post_author'  => get_current_user_id(),
			),
			true
		);

		if ( is_wp_error( $appr_new_apiary_id ) ) {
			$appr_form_error = $appr_new_apiary_id->get_error_message();
		} else {
			wp_safe_redirect( add_query_arg( 'created', '1', App::get_url( 'apiary/' . absint( $appr_new_apiary_id ) ) ) );
			exit;
		}
	}
}

if ( ! $appr_not_found && ! $appr_forbidden && ! $appr_is_new_apiary && 'update_apiary' === $appr_action ) {
	$appr_nonce = isset( $_POST['ap_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_nonce'] ) ) : '';
	$appr_title = isset( $_POST['ap_apiary_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_apiary_name'] ) ) : '';
	$appr_notes = isset( $_POST['ap_apiary_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ap_apiary_notes'] ) ) : '';

	if ( ! wp_verify_nonce( $appr_nonce, 'ap_update_apiary_' . $appr_apiary_id ) ) {
		$appr_form_error = __( 'The apiary could not be saved. Reload and try again.', 'apiary-press' );
	} elseif ( '' === $appr_title ) {
		$appr_form_error = __( 'Apiary name is required.', 'apiary-press' );
	} else {
		$appr_updated_id = wp_update_post(
			array(
				'ID'           => $appr_apiary_id,
				'post_title'   => $appr_title,
				'post_content' => $appr_notes,
			),
			true
		);

		if ( is_wp_error( $appr_updated_id ) ) {
			$appr_form_error = $appr_updated_id->get_error_message();
		} else {
			wp_safe_redirect( add_query_arg( 'updated', '1', App::get_url( 'apiary/' . $appr_apiary_id ) ) );
			exit;
		}
	}
}

if ( ! $appr_not_found && ! $appr_is_new_apiary ) {
	$appr_apiary = get_post( $appr_apiary_id );
}

$appr_apiary_title = ! $appr_not_found && ! $appr_is_new_apiary ? get_the_title( $appr_apiary ) : '';
$appr_apiary_notes = ! $appr_not_found && ! $appr_is_new_apiary ? $appr_apiary->post_content : '';
$appr_page_title   = $appr_is_new_apiary ? __( 'New Apiary', 'apiary-press' ) : __( 'Edit Apiary', 'apiary-press' );
$appr_form_action  = $appr_is_new_apiary ? 'create_apiary' : 'update_apiary';
$appr_form_nonce   = $appr_is_new_apiary ? 'ap_create_apiary' : 'ap_update_apiary_' . $appr_apiary_id;
$appr_form_url     = $appr_is_new_apiary ? App::get_url( 'apiary/new' ) : App::get_url( 'apiary/' . $appr_apiary_id . '/edit' );
$appr_button_text  = $appr_is_new_apiary ? __( 'Save Apiary', 'apiary-press' ) : __( 'Update Apiary', 'apiary-press' );

if ( $appr_form_error ) {
	$appr_apiary_title = isset( $_POST['ap_apiary_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_apiary_name'] ) ) : $appr_apiary_title;
	$appr_apiary_notes = isset( $_POST['ap_apiary_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ap_apiary_notes'] ) ) : $appr_apiary_notes;
}
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php wp_app_title( $appr_page_title ); ?></title>
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
		.apiary-notes {
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
		@media (max-width: 760px) {
			.shell { width: min(100% - 24px, 760px); padding-top: 24px; }
			.topbar { flex-direction: column; align-items: flex-start; }
		}
	</style>
</head>
<body>
	<?php wp_app_body_open(); ?>

	<main class="shell">
		<?php if ( $appr_not_found ) : ?>
			<section class="message">
				<h1><?php echo esc_html__( 'Apiary Not Found', 'apiary-press' ); ?></h1>
				<p class="apiary-notes"><?php echo esc_html__( 'The requested apiary is not available.', 'apiary-press' ); ?></p>
				<p><a class="admin-link" href="<?php echo esc_url( App::get_url() ); ?>"><?php echo esc_html__( 'Back to Apiaries', 'apiary-press' ); ?></a></p>
			</section>
		<?php elseif ( $appr_forbidden ) : ?>
			<section class="message">
				<h1><?php echo esc_html__( 'Access Denied', 'apiary-press' ); ?></h1>
				<p class="apiary-notes">
					<?php
					echo esc_html(
						$appr_is_new_apiary
							? __( 'You do not have permission to add apiaries.', 'apiary-press' )
							: __( 'You do not have permission to edit this apiary.', 'apiary-press' )
					);
					?>
				</p>
				<p><a class="admin-link" href="<?php echo esc_url( App::get_url() ); ?>"><?php echo esc_html__( 'Back to Apiaries', 'apiary-press' ); ?></a></p>
			</section>
		<?php else : ?>
			<header class="topbar">
				<div>
					<a class="crumb" href="<?php echo esc_url( $appr_is_new_apiary ? App::get_url() : App::get_url( 'apiary/' . $appr_apiary_id ) ); ?>">
						<?php echo esc_html( $appr_is_new_apiary ? __( 'Apiaries', 'apiary-press' ) : get_the_title( $appr_apiary ) ); ?>
					</a>
					<h1><?php echo esc_html( $appr_page_title ); ?></h1>
				</div>
			</header>

			<?php if ( $appr_form_error ) : ?>
				<div class="error"><?php echo esc_html( $appr_form_error ); ?></div>
			<?php endif; ?>

			<section class="panel" aria-labelledby="apiary-form-heading">
				<h2 id="apiary-form-heading"><?php echo esc_html( $appr_page_title ); ?></h2>
				<form method="post" action="<?php echo esc_url( $appr_form_url ); ?>">
					<input type="hidden" name="ap_action" value="<?php echo esc_attr( $appr_form_action ); ?>">
					<?php wp_nonce_field( $appr_form_nonce, 'ap_nonce' ); ?>

					<div class="field">
						<label for="ap_apiary_name"><?php echo esc_html__( 'Name', 'apiary-press' ); ?></label>
						<input id="ap_apiary_name" name="ap_apiary_name" type="text" value="<?php echo esc_attr( $appr_apiary_title ); ?>" required>
					</div>

					<div class="field">
						<label for="ap_apiary_notes"><?php echo esc_html__( 'Notes', 'apiary-press' ); ?></label>
						<textarea id="ap_apiary_notes" name="ap_apiary_notes"><?php echo esc_textarea( $appr_apiary_notes ); ?></textarea>
					</div>

					<button class="button" type="submit"><?php echo esc_html( $appr_button_text ); ?></button>
				</form>
			</section>
		<?php endif; ?>
	</main>

	<?php wp_app_body_close(); ?>
</body>
</html>
