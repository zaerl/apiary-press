<?php
/**
 * Treatment / feeding template — create, view, edit, and delete a single intervention.
 *
 * @package ApiaryPress
 */

namespace ApiaryPress;

use ApiaryPress\App;
use ApiaryPress\Treatment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wp_app_route;

$appr_route_params     = isset( $wp_app_route['params'] ) && is_array( $wp_app_route['params'] ) ? $wp_app_route['params'] : array();
$appr_apiary_id        = isset( $appr_route_params['apiary_id'] ) ? absint( $appr_route_params['apiary_id'] ) : absint( get_query_var( 'apiary_id' ) );
$appr_hive_id          = isset( $appr_route_params['id'] ) ? absint( $appr_route_params['id'] ) : absint( get_query_var( 'id' ) );
$appr_treatment_slug   = isset( $appr_route_params['hive_treatment'] ) ? sanitize_key( $appr_route_params['hive_treatment'] ) : sanitize_key( get_query_var( 'hive_treatment' ) );
$appr_is_new_treatment = 'new' === $appr_treatment_slug;
$appr_treatment_id     = $appr_is_new_treatment ? 0 : absint( $appr_treatment_slug );
$appr_apiary           = $appr_apiary_id ? get_post( $appr_apiary_id ) : null;
$appr_hive             = $appr_hive_id ? get_post( $appr_hive_id ) : null;
$appr_treatment        = $appr_treatment_id ? get_post( $appr_treatment_id ) : null;
$appr_form_error       = '';

$appr_not_found = ! $appr_apiary
	|| Apiary::APIARY_POST_TYPE !== $appr_apiary->post_type
	|| ! $appr_hive
	|| Hive::HIVE_POST_TYPE !== $appr_hive->post_type
	|| absint( $appr_hive->post_parent ) !== $appr_apiary_id
	|| ( ! $appr_is_new_treatment && ( ! $appr_treatment || Treatment::HIVE_TREATMENT_POST_TYPE !== $appr_treatment->post_type || absint( $appr_treatment->post_parent ) !== $appr_hive_id ) );

$appr_forbidden = ! $appr_not_found && ( $appr_is_new_treatment ? ! current_user_can( 'edit_post', $appr_hive_id ) : ! current_user_can( 'edit_post', $appr_treatment_id ) );

if ( $appr_not_found ) {
	status_header( 404 );
} elseif ( $appr_forbidden ) {
	status_header( 403 );
}

$appr_kind_labels   = Treatment::get_kind_labels();
$appr_target_labels = Treatment::get_target_labels();
$appr_unit_labels   = Treatment::get_unit_labels();

$appr_action = isset( $_POST['ap_action'] ) ? sanitize_key( wp_unslash( $_POST['ap_action'] ) ) : '';

if ( ! function_exists( 'appr_build_treatment_title' ) ) {
	/**
	 * Generate a human-readable title for a treatment post, e.g. "Hive A — Treatment on 2026-06-15".
	 *
	 * @param \WP_Post $hive          The parent hive post.
	 * @param string   $kind          One of Treatment::KIND_VALUES.
	 * @param string   $applied_date  YYYY-MM-DD date the entry applies to.
	 */
	function appr_build_treatment_title( \WP_Post $hive, string $kind, string $applied_date ): string {
		$kind_labels = Treatment::get_kind_labels();
		$kind_label  = $kind_labels[ $kind ] ?? $kind_labels[ Treatment::KIND_TREATMENT ];
		$timestamp   = strtotime( $applied_date );
		$date_label  = false === $timestamp ? $applied_date : date_i18n( get_option( 'date_format' ), $timestamp );

		return sprintf(
			/* translators: 1: hive title, 2: kind (Treatment or Feeding), 3: applied date. */
			__( '%1$s — %2$s on %3$s', 'apiary-press' ),
			get_the_title( $hive ),
			$kind_label,
			$date_label
		);
	}
}

if ( ! $appr_not_found && ! $appr_forbidden && $appr_is_new_treatment && 'create_treatment' === $appr_action ) {
	$appr_nonce        = isset( $_POST['ap_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_nonce'] ) ) : '';
	$appr_kind_raw     = isset( $_POST['ap_treatment_kind'] ) ? sanitize_key( wp_unslash( $_POST['ap_treatment_kind'] ) ) : Treatment::KIND_TREATMENT;
	$appr_kind         = Treatment::sanitize_kind_meta( $appr_kind_raw );
	$appr_applied_raw  = isset( $_POST['ap_treatment_date'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_treatment_date'] ) ) : current_time( 'Y-m-d' );
	$appr_end_raw      = isset( $_POST['ap_treatment_end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_treatment_end_date'] ) ) : '';
	$appr_product_raw  = isset( $_POST['ap_treatment_product'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_treatment_product'] ) ) : '';
	$appr_target_raw   = isset( $_POST['ap_treatment_target'] ) ? sanitize_key( wp_unslash( $_POST['ap_treatment_target'] ) ) : '';
	$appr_quantity_raw = isset( $_POST['ap_treatment_quantity'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_treatment_quantity'] ) ) : '';
	$appr_unit_raw     = isset( $_POST['ap_treatment_unit'] ) ? sanitize_key( wp_unslash( $_POST['ap_treatment_unit'] ) ) : '';
	$appr_notes        = isset( $_POST['ap_treatment_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ap_treatment_notes'] ) ) : '';

	if ( ! wp_verify_nonce( $appr_nonce, 'ap_create_treatment_' . $appr_hive_id ) ) {
		$appr_form_error = __( 'The entry could not be saved. Reload and try again.', 'apiary-press' );
	} elseif ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $appr_applied_raw ) ) {
		$appr_form_error = __( 'Applied date is invalid.', 'apiary-press' );
	} elseif ( '' === $appr_product_raw ) {
		$appr_form_error = __( 'Product is required.', 'apiary-press' );
	} elseif ( '' !== $appr_quantity_raw && ! is_numeric( $appr_quantity_raw ) ) {
		$appr_form_error = __( 'Quantity must be a number.', 'apiary-press' );
	} elseif ( '' !== $appr_end_raw && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $appr_end_raw ) ) {
		$appr_form_error = __( 'End date is invalid.', 'apiary-press' );
	} elseif ( '' !== $appr_end_raw && strtotime( $appr_end_raw ) < strtotime( $appr_applied_raw ) ) {
		$appr_form_error = __( 'End date must be on or after the applied date.', 'apiary-press' );
	} else {
		$appr_title         = appr_build_treatment_title( $appr_hive, $appr_kind, $appr_applied_raw );
		$appr_new_treatment = wp_insert_post(
			array(
				'post_type'    => Treatment::HIVE_TREATMENT_POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => $appr_title,
				'post_content' => $appr_notes,
				'post_parent'  => $appr_hive_id,
				'post_author'  => get_current_user_id(),
				'post_date'    => $appr_applied_raw . ' 12:00:00',
			),
			true
		);

		if ( is_wp_error( $appr_new_treatment ) ) {
			$appr_form_error = $appr_new_treatment->get_error_message();
		} else {
			update_post_meta( $appr_new_treatment, Treatment::KIND_META_KEY, $appr_kind );
			update_post_meta( $appr_new_treatment, Treatment::PRODUCT_META_KEY, Treatment::sanitize_text_meta( $appr_product_raw ) );
			update_post_meta( $appr_new_treatment, Treatment::TARGET_META_KEY, Treatment::KIND_TREATMENT === $appr_kind ? Treatment::sanitize_target_meta( $appr_target_raw ) : '' );
			update_post_meta( $appr_new_treatment, Treatment::QUANTITY_META_KEY, Treatment::sanitize_quantity_meta( $appr_quantity_raw ) );
			update_post_meta( $appr_new_treatment, Treatment::UNIT_META_KEY, Treatment::sanitize_unit_meta( $appr_unit_raw ) );
			update_post_meta( $appr_new_treatment, Treatment::END_DATE_META_KEY, Treatment::KIND_TREATMENT === $appr_kind ? Treatment::sanitize_date_meta( $appr_end_raw ) : '' );

			wp_safe_redirect( add_query_arg( 'created', '1', App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id . '/treatment/' . absint( $appr_new_treatment ) ) ) );
			exit;
		}
	}
}

if ( ! $appr_not_found && ! $appr_forbidden && ! $appr_is_new_treatment && 'delete_treatment' === $appr_action ) {
	$appr_nonce = isset( $_POST['ap_delete_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_delete_nonce'] ) ) : '';

	if ( ! wp_verify_nonce( $appr_nonce, 'ap_delete_treatment_' . $appr_treatment_id ) ) {
		$appr_form_error = __( 'The entry could not be removed. Reload and try again.', 'apiary-press' );
	} elseif ( ! current_user_can( 'delete_post', $appr_treatment_id ) ) {
		$appr_form_error = __( 'You do not have permission to remove this entry.', 'apiary-press' );
	} else {
		$appr_deleted = wp_delete_post( $appr_treatment_id, true );

		if ( ! $appr_deleted ) {
			$appr_form_error = __( 'The entry could not be removed.', 'apiary-press' );
		} else {
			wp_safe_redirect( add_query_arg( 'treatment_deleted', '1', App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id ) ) );
			exit;
		}
	}
}

if ( ! $appr_not_found && ! $appr_forbidden && ! $appr_is_new_treatment && 'update_treatment' === $appr_action ) {
	$appr_nonce        = isset( $_POST['ap_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_nonce'] ) ) : '';
	$appr_kind_raw     = isset( $_POST['ap_treatment_kind'] ) ? sanitize_key( wp_unslash( $_POST['ap_treatment_kind'] ) ) : Treatment::KIND_TREATMENT;
	$appr_kind         = Treatment::sanitize_kind_meta( $appr_kind_raw );
	$appr_applied_raw  = isset( $_POST['ap_treatment_date'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_treatment_date'] ) ) : '';
	$appr_end_raw      = isset( $_POST['ap_treatment_end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_treatment_end_date'] ) ) : '';
	$appr_product_raw  = isset( $_POST['ap_treatment_product'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_treatment_product'] ) ) : '';
	$appr_target_raw   = isset( $_POST['ap_treatment_target'] ) ? sanitize_key( wp_unslash( $_POST['ap_treatment_target'] ) ) : '';
	$appr_quantity_raw = isset( $_POST['ap_treatment_quantity'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_treatment_quantity'] ) ) : '';
	$appr_unit_raw     = isset( $_POST['ap_treatment_unit'] ) ? sanitize_key( wp_unslash( $_POST['ap_treatment_unit'] ) ) : '';
	$appr_notes        = isset( $_POST['ap_treatment_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ap_treatment_notes'] ) ) : '';

	if ( ! wp_verify_nonce( $appr_nonce, 'ap_update_treatment_' . $appr_treatment_id ) ) {
		$appr_form_error = __( 'The entry could not be saved. Reload and try again.', 'apiary-press' );
	} elseif ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $appr_applied_raw ) ) {
		$appr_form_error = __( 'Applied date is invalid.', 'apiary-press' );
	} elseif ( '' === $appr_product_raw ) {
		$appr_form_error = __( 'Product is required.', 'apiary-press' );
	} elseif ( '' !== $appr_quantity_raw && ! is_numeric( $appr_quantity_raw ) ) {
		$appr_form_error = __( 'Quantity must be a number.', 'apiary-press' );
	} elseif ( '' !== $appr_end_raw && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $appr_end_raw ) ) {
		$appr_form_error = __( 'End date is invalid.', 'apiary-press' );
	} elseif ( '' !== $appr_end_raw && strtotime( $appr_end_raw ) < strtotime( $appr_applied_raw ) ) {
		$appr_form_error = __( 'End date must be on or after the applied date.', 'apiary-press' );
	} else {
		$appr_title      = appr_build_treatment_title( $appr_hive, $appr_kind, $appr_applied_raw );
		$appr_updated_id = wp_update_post(
			array(
				'ID'           => $appr_treatment_id,
				'post_title'   => $appr_title,
				'post_content' => $appr_notes,
				'post_date'    => $appr_applied_raw . ' 12:00:00',
				'post_parent'  => $appr_hive_id,
			),
			true
		);

		if ( is_wp_error( $appr_updated_id ) ) {
			$appr_form_error = $appr_updated_id->get_error_message();
		} else {
			update_post_meta( $appr_treatment_id, Treatment::KIND_META_KEY, $appr_kind );
			update_post_meta( $appr_treatment_id, Treatment::PRODUCT_META_KEY, Treatment::sanitize_text_meta( $appr_product_raw ) );
			update_post_meta( $appr_treatment_id, Treatment::TARGET_META_KEY, Treatment::KIND_TREATMENT === $appr_kind ? Treatment::sanitize_target_meta( $appr_target_raw ) : '' );
			update_post_meta( $appr_treatment_id, Treatment::QUANTITY_META_KEY, Treatment::sanitize_quantity_meta( $appr_quantity_raw ) );
			update_post_meta( $appr_treatment_id, Treatment::UNIT_META_KEY, Treatment::sanitize_unit_meta( $appr_unit_raw ) );
			update_post_meta( $appr_treatment_id, Treatment::END_DATE_META_KEY, Treatment::KIND_TREATMENT === $appr_kind ? Treatment::sanitize_date_meta( $appr_end_raw ) : '' );

			wp_safe_redirect( add_query_arg( 'updated', '1', App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id . '/treatment/' . $appr_treatment_id ) ) );
			exit;
		}
	}
}

if ( ! $appr_not_found && ! $appr_is_new_treatment ) {
	$appr_treatment = get_post( $appr_treatment_id );
}

$appr_existing = ! $appr_not_found && ! $appr_is_new_treatment ? Treatment::get_treatment( $appr_treatment_id ) : array(
	'kind'     => Treatment::KIND_TREATMENT,
	'product'  => '',
	'target'   => '',
	'quantity' => 0.0,
	'unit'     => '',
	'end_date' => '',
);

$appr_treatment_kind     = $appr_existing['kind'];
$appr_treatment_product  = $appr_existing['product'];
$appr_treatment_target   = $appr_existing['target'];
$appr_treatment_quantity = $appr_existing['quantity'] > 0 ? rtrim( rtrim( number_format( $appr_existing['quantity'], 3, '.', '' ), '0' ), '.' ) : '';
$appr_treatment_unit     = $appr_existing['unit'];
$appr_treatment_end_date = $appr_existing['end_date'];
$appr_treatment_date     = ! $appr_not_found && ! $appr_is_new_treatment ? mysql2date( 'Y-m-d', $appr_treatment->post_date, false ) : current_time( 'Y-m-d' );
$appr_treatment_notes    = ! $appr_not_found && ! $appr_is_new_treatment ? $appr_treatment->post_content : '';

if ( $appr_form_error ) {
	$appr_treatment_kind     = isset( $_POST['ap_treatment_kind'] ) ? Treatment::sanitize_kind_meta( sanitize_key( wp_unslash( $_POST['ap_treatment_kind'] ) ) ) : $appr_treatment_kind;
	$appr_treatment_date     = isset( $_POST['ap_treatment_date'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_treatment_date'] ) ) : $appr_treatment_date;
	$appr_treatment_end_date = isset( $_POST['ap_treatment_end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_treatment_end_date'] ) ) : $appr_treatment_end_date;
	$appr_treatment_product  = isset( $_POST['ap_treatment_product'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_treatment_product'] ) ) : $appr_treatment_product;
	$appr_treatment_target   = isset( $_POST['ap_treatment_target'] ) ? sanitize_key( wp_unslash( $_POST['ap_treatment_target'] ) ) : $appr_treatment_target;
	$appr_treatment_quantity = isset( $_POST['ap_treatment_quantity'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_treatment_quantity'] ) ) : $appr_treatment_quantity;
	$appr_treatment_unit     = isset( $_POST['ap_treatment_unit'] ) ? sanitize_key( wp_unslash( $_POST['ap_treatment_unit'] ) ) : $appr_treatment_unit;
	$appr_treatment_notes    = isset( $_POST['ap_treatment_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ap_treatment_notes'] ) ) : $appr_treatment_notes;
}

$appr_page_title    = $appr_is_new_treatment ? __( 'New Treatment / Feeding', 'apiary-press' ) : ( $appr_kind_labels[ $appr_treatment_kind ] ?? __( 'Treatment / Feeding', 'apiary-press' ) );
$appr_form_action   = $appr_is_new_treatment ? 'create_treatment' : 'update_treatment';
$appr_form_nonce    = $appr_is_new_treatment ? 'ap_create_treatment_' . $appr_hive_id : 'ap_update_treatment_' . $appr_treatment_id;
$appr_form_heading  = $appr_is_new_treatment ? __( 'New Treatment / Feeding', 'apiary-press' ) : __( 'Edit Entry', 'apiary-press' );
$appr_form_button   = $appr_is_new_treatment ? __( 'Save Entry', 'apiary-press' ) : __( 'Update Entry', 'apiary-press' );
$appr_form_url_slug = $appr_is_new_treatment ? 'new' : (string) $appr_treatment_id;
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
				<h1><?php echo esc_html__( 'Entry Not Found', 'apiary-press' ); ?></h1>
				<p class="hive-notes"><?php echo esc_html__( 'The requested treatment or feeding entry is not available for this hive.', 'apiary-press' ); ?></p>
				<p><a class="admin-link" href="<?php echo esc_url( App::get_url() ); ?>"><?php echo esc_html__( 'Back to Apiaries', 'apiary-press' ); ?></a></p>
			</section>
		<?php elseif ( $appr_forbidden ) : ?>
			<section class="message">
				<h1><?php echo esc_html__( 'Access Denied', 'apiary-press' ); ?></h1>
				<p class="hive-notes">
					<?php
					echo esc_html(
						$appr_is_new_treatment
							? __( 'You do not have permission to add an entry to this hive.', 'apiary-press' )
							: __( 'You do not have permission to edit this entry.', 'apiary-press' )
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
					<?php if ( ! $appr_is_new_treatment ) : ?>
						<?php $appr_author_name = get_the_author_meta( 'display_name', (int) $appr_treatment->post_author ); ?>
						<div class="muted">
							<?php
							printf(
								/* translators: %s: the display name of the user who recorded the entry. */
								esc_html__( 'by %s', 'apiary-press' ),
								esc_html( $appr_author_name ? $appr_author_name : __( 'Unknown', 'apiary-press' ) )
							);
							?>
						</div>
					<?php endif; ?>
				</div>
			</header>

			<?php if ( isset( $_GET['created'] ) ) : ?>
				<div class="notice"><?php echo esc_html__( 'Entry saved.', 'apiary-press' ); ?></div>
			<?php endif; ?>

			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice"><?php echo esc_html__( 'Entry updated.', 'apiary-press' ); ?></div>
			<?php endif; ?>

			<?php if ( $appr_form_error ) : ?>
				<div class="error"><?php echo esc_html( $appr_form_error ); ?></div>
			<?php endif; ?>

			<section class="panel" aria-labelledby="edit-treatment-heading">
				<h2 id="edit-treatment-heading"><?php echo esc_html( $appr_form_heading ); ?></h2>
				<form method="post" action="<?php echo esc_url( App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id . '/treatment/' . $appr_form_url_slug ) ); ?>">
					<input type="hidden" name="ap_action" value="<?php echo esc_attr( $appr_form_action ); ?>">
					<?php wp_nonce_field( $appr_form_nonce, 'ap_nonce' ); ?>

					<div class="kind-toggle" role="radiogroup" aria-label="<?php echo esc_attr__( 'Kind', 'apiary-press' ); ?>">
						<?php foreach ( $appr_kind_labels as $appr_kind_slug => $appr_kind_label ) : ?>
							<label class="kind-option <?php echo $appr_treatment_kind === $appr_kind_slug ? 'is-selected' : ''; ?>">
								<input
									type="radio"
									name="ap_treatment_kind"
									value="<?php echo esc_attr( $appr_kind_slug ); ?>"
									data-ap-kind-input
									<?php checked( $appr_treatment_kind, $appr_kind_slug ); ?>
								>
								<?php echo esc_html( $appr_kind_label ); ?>
							</label>
						<?php endforeach; ?>
					</div>

					<div class="date-time-grid">
						<div class="field">
							<label for="ap_treatment_date"><?php echo esc_html__( 'Applied on', 'apiary-press' ); ?></label>
							<input id="ap_treatment_date" name="ap_treatment_date" type="date" value="<?php echo esc_attr( $appr_treatment_date ); ?>" required>
						</div>

						<div class="field" data-ap-treatment-only<?php echo Treatment::KIND_TREATMENT === $appr_treatment_kind ? '' : ' hidden'; ?>>
							<label for="ap_treatment_end_date"><?php echo esc_html__( 'End date (optional)', 'apiary-press' ); ?></label>
							<input id="ap_treatment_end_date" name="ap_treatment_end_date" type="date" value="<?php echo esc_attr( $appr_treatment_end_date ); ?>">
						</div>
					</div>

					<div class="field">
						<label for="ap_treatment_product"><?php echo esc_html__( 'Product', 'apiary-press' ); ?></label>
						<input id="ap_treatment_product" name="ap_treatment_product" type="text" value="<?php echo esc_attr( $appr_treatment_product ); ?>" required placeholder="<?php echo esc_attr__( 'e.g. Apivar, oxalic acid, 1:1 syrup', 'apiary-press' ); ?>">
					</div>

					<div class="field" data-ap-treatment-only<?php echo Treatment::KIND_TREATMENT === $appr_treatment_kind ? '' : ' hidden'; ?>>
						<label for="ap_treatment_target"><?php echo esc_html__( 'Target', 'apiary-press' ); ?></label>
						<select id="ap_treatment_target" name="ap_treatment_target">
							<option value=""><?php echo esc_html__( '— Select —', 'apiary-press' ); ?></option>
							<?php foreach ( $appr_target_labels as $appr_target_slug => $appr_target_label ) : ?>
								<option value="<?php echo esc_attr( $appr_target_slug ); ?>" <?php selected( $appr_treatment_target, $appr_target_slug ); ?>>
									<?php echo esc_html( $appr_target_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="quantity-grid">
						<div class="field">
							<label for="ap_treatment_quantity"><?php echo esc_html__( 'Quantity', 'apiary-press' ); ?></label>
							<input
								id="ap_treatment_quantity"
								name="ap_treatment_quantity"
								type="number"
								inputmode="decimal"
								min="0"
								step="any"
								value="<?php echo esc_attr( $appr_treatment_quantity ); ?>"
							>
						</div>

						<div class="field">
							<label for="ap_treatment_unit"><?php echo esc_html__( 'Unit', 'apiary-press' ); ?></label>
							<select id="ap_treatment_unit" name="ap_treatment_unit" data-ap-unit-select>
								<option value=""><?php echo esc_html__( '— Select —', 'apiary-press' ); ?></option>
								<?php
								foreach ( Treatment::UNIT_VALUES as $appr_unit_slug ) :
									$appr_unit_kind = '';
									foreach ( Treatment::UNITS_BY_KIND as $appr_kind_key => $appr_units ) {
										if ( in_array( $appr_unit_slug, $appr_units, true ) ) {
											$appr_unit_kind .= ( '' === $appr_unit_kind ? '' : ' ' ) . $appr_kind_key;
										}
									}
									?>
									<option
										value="<?php echo esc_attr( $appr_unit_slug ); ?>"
										data-ap-kinds="<?php echo esc_attr( $appr_unit_kind ); ?>"
										<?php selected( $appr_treatment_unit, $appr_unit_slug ); ?>
									>
										<?php echo esc_html( $appr_unit_labels[ $appr_unit_slug ] ?? $appr_unit_slug ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>

					<div class="field">
						<label for="ap_treatment_notes"><?php echo esc_html__( 'Notes', 'apiary-press' ); ?></label>
						<textarea id="ap_treatment_notes" name="ap_treatment_notes"><?php echo esc_textarea( $appr_treatment_notes ); ?></textarea>
					</div>

					<div class="form-actions">
						<button class="button" type="submit"><?php echo esc_html( $appr_form_button ); ?></button>
					</div>
				</form>
			</section>

			<?php if ( ! $appr_is_new_treatment ) : ?>
				<section class="panel danger-zone" aria-labelledby="treatment-danger-heading">
					<h2 id="treatment-danger-heading"><?php echo esc_html__( 'Danger Zone', 'apiary-press' ); ?></h2>
					<form class="delete-form" method="post" action="<?php echo esc_url( App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id . '/treatment/' . $appr_treatment_id ) ); ?>">
						<input type="hidden" name="ap_action" value="delete_treatment">
						<?php wp_nonce_field( 'ap_delete_treatment_' . $appr_treatment_id, 'ap_delete_nonce' ); ?>
						<p class="danger-text"><?php echo esc_html__( 'Remove this entry from the hive record.', 'apiary-press' ); ?></p>
						<button
							class="button button-danger"
							type="submit"
							onclick="return confirm('<?php echo esc_js( __( 'Remove this entry?', 'apiary-press' ) ); ?>');"
						>
							<?php echo esc_html__( 'Remove Entry', 'apiary-press' ); ?>
						</button>
					</form>
				</section>
			<?php endif; ?>
		<?php endif; ?>
	</main>

	<?php wp_app_body_close(); ?>

	<?php if ( ! $appr_not_found && ! $appr_forbidden ) : ?>
		<script>
			(function() {
				const kindInputs = document.querySelectorAll('[data-ap-kind-input]');
				const treatmentOnlyFields = document.querySelectorAll('[data-ap-treatment-only]');
				const unitSelect = document.querySelector('[data-ap-unit-select]');

				const refresh = function(kind) {
					treatmentOnlyFields.forEach(function(field) {
						if (kind === <?php echo wp_json_encode( Treatment::KIND_TREATMENT ); ?>) {
							field.removeAttribute('hidden');
						} else {
							field.setAttribute('hidden', 'hidden');
						}
					});

					if (unitSelect) {
						Array.prototype.forEach.call(unitSelect.options, function(option) {
							if (!option.value) {
								return;
							}
							const kinds = (option.getAttribute('data-ap-kinds') || '').split(' ');
							const fits = kinds.indexOf(kind) !== -1;
							option.hidden = !fits;
							option.disabled = !fits;
						});

						const current = unitSelect.options[unitSelect.selectedIndex];
						if (current && current.disabled) {
							unitSelect.value = '';
						}
					}

					kindInputs.forEach(function(input) {
						const label = input.closest('.kind-option');
						if (!label) return;
						label.classList.toggle('is-selected', input.checked);
					});
				};

				kindInputs.forEach(function(input) {
					input.addEventListener('change', function() {
						if (input.checked) {
							refresh(input.value);
						}
					});
				});

				const selected = document.querySelector('[data-ap-kind-input]:checked');
				refresh(selected ? selected.value : <?php echo wp_json_encode( Treatment::KIND_TREATMENT ); ?>);
			})();
		</script>
	<?php endif; ?>
</body>
</html>
