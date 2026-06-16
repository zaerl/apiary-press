<?php
/**
 * Harvest template — create, view, edit, and delete a single honey harvest.
 *
 * @package ApiaryPress
 */

namespace ApiaryPress;

use ApiaryPress\App;
use ApiaryPress\Harvest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wp_app_route;

$appr_route_params   = isset( $wp_app_route['params'] ) && is_array( $wp_app_route['params'] ) ? $wp_app_route['params'] : array();
$appr_apiary_id      = isset( $appr_route_params['apiary_id'] ) ? absint( $appr_route_params['apiary_id'] ) : absint( get_query_var( 'apiary_id' ) );
$appr_hive_id        = isset( $appr_route_params['id'] ) ? absint( $appr_route_params['id'] ) : absint( get_query_var( 'id' ) );
$appr_harvest_slug   = isset( $appr_route_params['hive_harvest'] ) ? sanitize_key( $appr_route_params['hive_harvest'] ) : sanitize_key( get_query_var( 'hive_harvest' ) );
$appr_is_new_harvest = 'new' === $appr_harvest_slug;
$appr_harvest_id     = $appr_is_new_harvest ? 0 : absint( $appr_harvest_slug );
$appr_apiary         = $appr_apiary_id ? get_post( $appr_apiary_id ) : null;
$appr_hive           = $appr_hive_id ? get_post( $appr_hive_id ) : null;
$appr_harvest        = $appr_harvest_id ? get_post( $appr_harvest_id ) : null;
$appr_form_error     = '';

$appr_not_found = ! $appr_apiary
	|| Apiary::APIARY_POST_TYPE !== $appr_apiary->post_type
	|| ! $appr_hive
	|| Hive::HIVE_POST_TYPE !== $appr_hive->post_type
	|| absint( $appr_hive->post_parent ) !== $appr_apiary_id
	|| ( ! $appr_is_new_harvest && ( ! $appr_harvest || Harvest::HIVE_HARVEST_POST_TYPE !== $appr_harvest->post_type || absint( $appr_harvest->post_parent ) !== $appr_hive_id ) );

$appr_forbidden = ! $appr_not_found && ( $appr_is_new_harvest ? ! current_user_can( 'edit_post', $appr_hive_id ) : ! current_user_can( 'edit_post', $appr_harvest_id ) );

if ( $appr_not_found ) {
	status_header( 404 );
} elseif ( $appr_forbidden ) {
	status_header( 403 );
}

$appr_method_labels = Harvest::get_method_labels();
$appr_action        = isset( $_POST['ap_action'] ) ? sanitize_key( wp_unslash( $_POST['ap_action'] ) ) : '';

if ( ! function_exists( 'appr_build_harvest_title' ) ) {
	/**
	 * Generate a human-readable title for a harvest post, e.g. "Hive A — Harvest on 2026-06-15".
	 *
	 * @param \WP_Post $hive            The parent hive post.
	 * @param string   $harvested_date  YYYY-MM-DD date the entry applies to.
	 */
	function appr_build_harvest_title( \WP_Post $hive, string $harvested_date ): string {
		$timestamp  = strtotime( $harvested_date );
		$date_label = false === $timestamp ? $harvested_date : date_i18n( get_option( 'date_format' ), $timestamp );

		return sprintf(
			/* translators: 1: hive title, 2: harvested date. */
			__( '%1$s — Harvest on %2$s', 'apiary-press' ),
			get_the_title( $hive ),
			$date_label
		);
	}
}

if ( ! $appr_not_found && ! $appr_forbidden && $appr_is_new_harvest && 'create_harvest' === $appr_action ) {
	$appr_nonce        = isset( $_POST['ap_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_nonce'] ) ) : '';
	$appr_date_raw     = isset( $_POST['ap_harvest_date'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_harvest_date'] ) ) : current_time( 'Y-m-d' );
	$appr_quantity_raw = isset( $_POST['ap_harvest_quantity'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_harvest_quantity'] ) ) : '';
	$appr_type_raw     = isset( $_POST['ap_harvest_type'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_harvest_type'] ) ) : '';
	$appr_frames_raw   = isset( $_POST['ap_harvest_frames'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_harvest_frames'] ) ) : '';
	$appr_method_raw   = isset( $_POST['ap_harvest_method'] ) ? sanitize_key( wp_unslash( $_POST['ap_harvest_method'] ) ) : '';
	$appr_notes        = isset( $_POST['ap_harvest_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ap_harvest_notes'] ) ) : '';

	if ( ! wp_verify_nonce( $appr_nonce, 'ap_create_harvest_' . $appr_hive_id ) ) {
		$appr_form_error = __( 'The harvest could not be saved. Reload and try again.', 'apiary-press' );
	} elseif ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $appr_date_raw ) ) {
		$appr_form_error = __( 'Harvest date is invalid.', 'apiary-press' );
	} elseif ( '' === $appr_quantity_raw || ! is_numeric( $appr_quantity_raw ) ) {
		$appr_form_error = __( 'Quantity (kg) is required and must be a number.', 'apiary-press' );
	} elseif ( (float) $appr_quantity_raw < 0 ) {
		$appr_form_error = __( 'Quantity (kg) must be zero or greater.', 'apiary-press' );
	} elseif ( '' !== $appr_frames_raw && ( ! ctype_digit( $appr_frames_raw ) || (int) $appr_frames_raw < 0 ) ) {
		$appr_form_error = __( 'Frames extracted must be a non-negative whole number.', 'apiary-press' );
	} else {
		$appr_title       = appr_build_harvest_title( $appr_hive, $appr_date_raw );
		$appr_new_harvest = wp_insert_post(
			array(
				'post_type'    => Harvest::HIVE_HARVEST_POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => $appr_title,
				'post_content' => $appr_notes,
				'post_parent'  => $appr_hive_id,
				'post_author'  => get_current_user_id(),
				'post_date'    => $appr_date_raw . ' 12:00:00',
			),
			true
		);

		if ( is_wp_error( $appr_new_harvest ) ) {
			$appr_form_error = $appr_new_harvest->get_error_message();
		} else {
			update_post_meta( $appr_new_harvest, Harvest::QUANTITY_META_KEY, Harvest::sanitize_quantity_meta( $appr_quantity_raw ) );
			update_post_meta( $appr_new_harvest, Harvest::HONEY_TYPE_META_KEY, Harvest::sanitize_text_meta( $appr_type_raw ) );
			update_post_meta( $appr_new_harvest, Harvest::FRAMES_META_KEY, Harvest::sanitize_frames_meta( $appr_frames_raw ) );
			update_post_meta( $appr_new_harvest, Harvest::METHOD_META_KEY, Harvest::sanitize_method_meta( $appr_method_raw ) );

			wp_safe_redirect( add_query_arg( 'created', '1', App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id . '/harvest/' . absint( $appr_new_harvest ) ) ) );
			exit;
		}
	}
}

if ( ! $appr_not_found && ! $appr_forbidden && ! $appr_is_new_harvest && 'delete_harvest' === $appr_action ) {
	$appr_nonce = isset( $_POST['ap_delete_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_delete_nonce'] ) ) : '';

	if ( ! wp_verify_nonce( $appr_nonce, 'ap_delete_harvest_' . $appr_harvest_id ) ) {
		$appr_form_error = __( 'The harvest could not be removed. Reload and try again.', 'apiary-press' );
	} elseif ( ! current_user_can( 'delete_post', $appr_harvest_id ) ) {
		$appr_form_error = __( 'You do not have permission to remove this harvest.', 'apiary-press' );
	} else {
		$appr_deleted = wp_delete_post( $appr_harvest_id, true );

		if ( ! $appr_deleted ) {
			$appr_form_error = __( 'The harvest could not be removed.', 'apiary-press' );
		} else {
			wp_safe_redirect( add_query_arg( 'harvest_deleted', '1', App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id ) ) );
			exit;
		}
	}
}

if ( ! $appr_not_found && ! $appr_forbidden && ! $appr_is_new_harvest && 'update_harvest' === $appr_action ) {
	$appr_nonce        = isset( $_POST['ap_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_nonce'] ) ) : '';
	$appr_date_raw     = isset( $_POST['ap_harvest_date'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_harvest_date'] ) ) : '';
	$appr_quantity_raw = isset( $_POST['ap_harvest_quantity'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_harvest_quantity'] ) ) : '';
	$appr_type_raw     = isset( $_POST['ap_harvest_type'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_harvest_type'] ) ) : '';
	$appr_frames_raw   = isset( $_POST['ap_harvest_frames'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_harvest_frames'] ) ) : '';
	$appr_method_raw   = isset( $_POST['ap_harvest_method'] ) ? sanitize_key( wp_unslash( $_POST['ap_harvest_method'] ) ) : '';
	$appr_notes        = isset( $_POST['ap_harvest_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ap_harvest_notes'] ) ) : '';

	if ( ! wp_verify_nonce( $appr_nonce, 'ap_update_harvest_' . $appr_harvest_id ) ) {
		$appr_form_error = __( 'The harvest could not be saved. Reload and try again.', 'apiary-press' );
	} elseif ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $appr_date_raw ) ) {
		$appr_form_error = __( 'Harvest date is invalid.', 'apiary-press' );
	} elseif ( '' === $appr_quantity_raw || ! is_numeric( $appr_quantity_raw ) ) {
		$appr_form_error = __( 'Quantity (kg) is required and must be a number.', 'apiary-press' );
	} elseif ( (float) $appr_quantity_raw < 0 ) {
		$appr_form_error = __( 'Quantity (kg) must be zero or greater.', 'apiary-press' );
	} elseif ( '' !== $appr_frames_raw && ( ! ctype_digit( $appr_frames_raw ) || (int) $appr_frames_raw < 0 ) ) {
		$appr_form_error = __( 'Frames extracted must be a non-negative whole number.', 'apiary-press' );
	} else {
		$appr_title      = appr_build_harvest_title( $appr_hive, $appr_date_raw );
		$appr_updated_id = wp_update_post(
			array(
				'ID'           => $appr_harvest_id,
				'post_title'   => $appr_title,
				'post_content' => $appr_notes,
				'post_date'    => $appr_date_raw . ' 12:00:00',
				'post_parent'  => $appr_hive_id,
			),
			true
		);

		if ( is_wp_error( $appr_updated_id ) ) {
			$appr_form_error = $appr_updated_id->get_error_message();
		} else {
			update_post_meta( $appr_harvest_id, Harvest::QUANTITY_META_KEY, Harvest::sanitize_quantity_meta( $appr_quantity_raw ) );
			update_post_meta( $appr_harvest_id, Harvest::HONEY_TYPE_META_KEY, Harvest::sanitize_text_meta( $appr_type_raw ) );
			update_post_meta( $appr_harvest_id, Harvest::FRAMES_META_KEY, Harvest::sanitize_frames_meta( $appr_frames_raw ) );
			update_post_meta( $appr_harvest_id, Harvest::METHOD_META_KEY, Harvest::sanitize_method_meta( $appr_method_raw ) );

			wp_safe_redirect( add_query_arg( 'updated', '1', App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id . '/harvest/' . $appr_harvest_id ) ) );
			exit;
		}
	}
}

if ( ! $appr_not_found && ! $appr_is_new_harvest ) {
	$appr_harvest = get_post( $appr_harvest_id );
}

$appr_existing = ! $appr_not_found && ! $appr_is_new_harvest ? Harvest::get_harvest( $appr_harvest_id ) : array(
	'quantity_kg' => 0.0,
	'honey_type'  => '',
	'frames'      => 0,
	'method'      => '',
);

$appr_harvest_date     = ! $appr_not_found && ! $appr_is_new_harvest ? mysql2date( 'Y-m-d', $appr_harvest->post_date, false ) : current_time( 'Y-m-d' );
$appr_harvest_quantity = $appr_existing['quantity_kg'] > 0 ? Harvest::format_kg( $appr_existing['quantity_kg'] ) : '';
$appr_harvest_type     = $appr_existing['honey_type'];
$appr_harvest_frames   = $appr_existing['frames'] > 0 ? (string) $appr_existing['frames'] : '';
$appr_harvest_method   = $appr_existing['method'];
$appr_harvest_notes    = ! $appr_not_found && ! $appr_is_new_harvest ? $appr_harvest->post_content : '';

if ( $appr_form_error ) {
	$appr_harvest_date     = isset( $_POST['ap_harvest_date'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_harvest_date'] ) ) : $appr_harvest_date;
	$appr_harvest_quantity = isset( $_POST['ap_harvest_quantity'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_harvest_quantity'] ) ) : $appr_harvest_quantity;
	$appr_harvest_type     = isset( $_POST['ap_harvest_type'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_harvest_type'] ) ) : $appr_harvest_type;
	$appr_harvest_frames   = isset( $_POST['ap_harvest_frames'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_harvest_frames'] ) ) : $appr_harvest_frames;
	$appr_harvest_method   = isset( $_POST['ap_harvest_method'] ) ? sanitize_key( wp_unslash( $_POST['ap_harvest_method'] ) ) : $appr_harvest_method;
	$appr_harvest_notes    = isset( $_POST['ap_harvest_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ap_harvest_notes'] ) ) : $appr_harvest_notes;
}

$appr_page_title    = $appr_is_new_harvest ? __( 'New Harvest', 'apiary-press' ) : __( 'Harvest', 'apiary-press' );
$appr_form_action   = $appr_is_new_harvest ? 'create_harvest' : 'update_harvest';
$appr_form_nonce    = $appr_is_new_harvest ? 'ap_create_harvest_' . $appr_hive_id : 'ap_update_harvest_' . $appr_harvest_id;
$appr_form_heading  = $appr_is_new_harvest ? __( 'New Harvest', 'apiary-press' ) : __( 'Edit Harvest', 'apiary-press' );
$appr_form_button   = $appr_is_new_harvest ? __( 'Save Harvest', 'apiary-press' ) : __( 'Update Harvest', 'apiary-press' );
$appr_form_url_slug = $appr_is_new_harvest ? 'new' : (string) $appr_harvest_id;
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
				<h1><?php echo esc_html__( 'Harvest Not Found', 'apiary-press' ); ?></h1>
				<p class="hive-notes"><?php echo esc_html__( 'The requested harvest is not available for this hive.', 'apiary-press' ); ?></p>
				<p><a class="admin-link" href="<?php echo esc_url( App::get_url() ); ?>"><?php echo esc_html__( 'Back to Apiaries', 'apiary-press' ); ?></a></p>
			</section>
		<?php elseif ( $appr_forbidden ) : ?>
			<section class="message">
				<h1><?php echo esc_html__( 'Access Denied', 'apiary-press' ); ?></h1>
				<p class="hive-notes">
					<?php
					echo esc_html(
						$appr_is_new_harvest
							? __( 'You do not have permission to add a harvest to this hive.', 'apiary-press' )
							: __( 'You do not have permission to edit this harvest.', 'apiary-press' )
					);
					?>
				</p>
				<p><a class="admin-link" href="<?php echo esc_url( App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id ) ); ?>"><?php echo esc_html__( 'Back to Hive', 'apiary-press' ); ?></a></p>
			</section>
		<?php else : ?>
			<header class="topbar">
				<div>
					<a class="crumb" href="<?php echo esc_url( App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id ) ); ?>"><?php echo esc_html( get_the_title( $appr_hive ) ); ?></a>
					<h1><?php echo esc_html( $appr_page_title ); ?></h1>
					<?php if ( ! $appr_is_new_harvest ) : ?>
						<?php $appr_author_name = get_the_author_meta( 'display_name', (int) $appr_harvest->post_author ); ?>
						<div class="muted">
							<?php
							printf(
								/* translators: %s: the display name of the user who recorded the harvest. */
								esc_html__( 'by %s', 'apiary-press' ),
								esc_html( $appr_author_name ? $appr_author_name : __( 'Unknown', 'apiary-press' ) )
							);
							?>
						</div>
					<?php endif; ?>
				</div>
			</header>

			<?php if ( isset( $_GET['created'] ) ) : ?>
				<div class="notice"><?php echo esc_html__( 'Harvest saved.', 'apiary-press' ); ?></div>
			<?php endif; ?>

			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice"><?php echo esc_html__( 'Harvest updated.', 'apiary-press' ); ?></div>
			<?php endif; ?>

			<?php if ( $appr_form_error ) : ?>
				<div class="error"><?php echo esc_html( $appr_form_error ); ?></div>
			<?php endif; ?>

			<section class="panel" aria-labelledby="edit-harvest-heading">
				<h2 id="edit-harvest-heading"><?php echo esc_html( $appr_form_heading ); ?></h2>
				<form method="post" action="<?php echo esc_url( App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id . '/harvest/' . $appr_form_url_slug ) ); ?>">
					<input type="hidden" name="ap_action" value="<?php echo esc_attr( $appr_form_action ); ?>">
					<?php wp_nonce_field( $appr_form_nonce, 'ap_nonce' ); ?>

					<div class="quantity-grid">
						<div class="field">
							<label for="ap_harvest_date"><?php echo esc_html__( 'Harvested on', 'apiary-press' ); ?></label>
							<input id="ap_harvest_date" name="ap_harvest_date" type="date" value="<?php echo esc_attr( $appr_harvest_date ); ?>" required>
						</div>

						<div class="field">
							<label for="ap_harvest_quantity"><?php echo esc_html__( 'Quantity (kg)', 'apiary-press' ); ?></label>
							<input
								id="ap_harvest_quantity"
								name="ap_harvest_quantity"
								type="number"
								inputmode="decimal"
								min="0"
								step="any"
								value="<?php echo esc_attr( $appr_harvest_quantity ); ?>"
								required
							>
						</div>
					</div>

					<div class="field">
						<label for="ap_harvest_type"><?php echo esc_html__( 'Honey type', 'apiary-press' ); ?></label>
						<input
							id="ap_harvest_type"
							name="ap_harvest_type"
							type="text"
							value="<?php echo esc_attr( $appr_harvest_type ); ?>"
							placeholder="<?php echo esc_attr__( 'e.g. Acacia, wildflower, chestnut', 'apiary-press' ); ?>"
						>
					</div>

					<div class="quantity-grid">
						<div class="field">
							<label for="ap_harvest_frames"><?php echo esc_html__( 'Frames extracted (optional)', 'apiary-press' ); ?></label>
							<input
								id="ap_harvest_frames"
								name="ap_harvest_frames"
								type="number"
								inputmode="numeric"
								min="0"
								step="1"
								value="<?php echo esc_attr( $appr_harvest_frames ); ?>"
							>
						</div>

						<div class="field">
							<label for="ap_harvest_method"><?php echo esc_html__( 'Extraction method', 'apiary-press' ); ?></label>
							<select id="ap_harvest_method" name="ap_harvest_method">
								<option value=""><?php echo esc_html__( '— Select —', 'apiary-press' ); ?></option>
								<?php foreach ( $appr_method_labels as $appr_method_slug => $appr_method_label ) : ?>
									<option value="<?php echo esc_attr( $appr_method_slug ); ?>" <?php selected( $appr_harvest_method, $appr_method_slug ); ?>>
										<?php echo esc_html( $appr_method_label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>

					<div class="field">
						<label for="ap_harvest_notes"><?php echo esc_html__( 'Notes', 'apiary-press' ); ?></label>
						<textarea id="ap_harvest_notes" name="ap_harvest_notes"><?php echo esc_textarea( $appr_harvest_notes ); ?></textarea>
					</div>

					<div class="form-actions">
						<button class="button" type="submit"><?php echo esc_html( $appr_form_button ); ?></button>
					</div>
				</form>
			</section>

			<?php if ( ! $appr_is_new_harvest ) : ?>
				<section class="panel danger-zone" aria-labelledby="harvest-danger-heading">
					<h2 id="harvest-danger-heading"><?php echo esc_html__( 'Danger Zone', 'apiary-press' ); ?></h2>
					<form class="delete-form" method="post" action="<?php echo esc_url( App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id . '/harvest/' . $appr_harvest_id ) ); ?>">
						<input type="hidden" name="ap_action" value="delete_harvest">
						<?php wp_nonce_field( 'ap_delete_harvest_' . $appr_harvest_id, 'ap_delete_nonce' ); ?>
						<p class="danger-text"><?php echo esc_html__( 'Remove this harvest from the hive record.', 'apiary-press' ); ?></p>
						<button
							class="button button-danger"
							type="submit"
							onclick="return confirm('<?php echo esc_js( __( 'Remove this harvest?', 'apiary-press' ) ); ?>');"
						>
							<?php echo esc_html__( 'Remove Harvest', 'apiary-press' ); ?>
						</button>
					</form>
				</section>
			<?php endif; ?>
		<?php endif; ?>
	</main>

	<?php wp_app_body_close(); ?>
</body>
</html>
