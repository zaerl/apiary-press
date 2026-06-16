<?php
/**
 * Apiary template for displaying apiary details and hives in the Apiary Press app.
 *
 * @package ApiaryPress
 */

namespace ApiaryPress;

use ApiaryPress\App;
use ApiaryPress\Harvest;
use ApiaryPress\Treatment;
use ApiaryPress\Visit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wp_app_route;

$appr_route_params = isset( $wp_app_route['params'] ) && is_array( $wp_app_route['params'] ) ? $wp_app_route['params'] : array();
$appr_apiary_id    = isset( $appr_route_params['id'] ) ? absint( $appr_route_params['id'] ) : absint( get_query_var( 'id' ) );
$appr_apiary       = $appr_apiary_id ? get_post( $appr_apiary_id ) : null;
$appr_not_found    = ! $appr_apiary || Apiary::APIARY_POST_TYPE !== $appr_apiary->post_type;
$appr_forbidden    = ! $appr_not_found && ! current_user_can( 'edit_post', $appr_apiary_id );
$appr_form_error   = '';

if ( $appr_not_found ) {
	status_header( 404 );
} elseif ( $appr_forbidden ) {
	status_header( 403 );
}

$appr_action = isset( $_POST['ap_action'] ) ? sanitize_key( wp_unslash( $_POST['ap_action'] ) ) : '';

if ( ! $appr_not_found && ! $appr_forbidden && 'delete_hive' === $appr_action ) {
	$appr_hive_id = isset( $_POST['ap_hive_id'] ) ? absint( $_POST['ap_hive_id'] ) : 0;
	$appr_nonce   = isset( $_POST['ap_delete_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_delete_nonce'] ) ) : '';
	$appr_hive    = $appr_hive_id ? get_post( $appr_hive_id ) : null;

	if ( ! wp_verify_nonce( $appr_nonce, 'ap_delete_hive_' . $appr_apiary_id . '_' . $appr_hive_id ) ) {
		$appr_form_error = __( 'The hive could not be removed. Reload and try again.', 'apiary-press' );
	} elseif ( ! $appr_hive || Hive::HIVE_POST_TYPE !== $appr_hive->post_type || absint( $appr_hive->post_parent ) !== $appr_apiary_id ) {
		$appr_form_error = __( 'The hive is not available for this apiary.', 'apiary-press' );
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

$appr_hives                = array();
$appr_map_markers          = array();
$appr_harvests_by_hive     = array();
$appr_treatments_by_hive   = array();
$appr_apiary_total_kg      = 0.0;
$appr_apiary_ongoing_count = 0;
$appr_ongoing_by_hive      = array();
$appr_harvest_kg_by_hive   = array();

if ( ! $appr_not_found && ! $appr_forbidden ) {
	$appr_hives = get_posts(
		array(
			'post_type'        => Hive::HIVE_POST_TYPE,
			'post_status'      => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'post_parent'      => $appr_apiary_id,
			'numberposts'      => -1,
			'orderby'          => 'date',
			'order'            => 'DESC',
			'suppress_filters' => false,
		)
	);

	foreach ( $appr_hives as $appr_hive ) {
		$appr_coords = Hive::get_coordinates( $appr_hive->ID );

		if ( empty( $appr_coords ) ) {
			continue;
		}

		$appr_map_markers[] = array(
			'latitude'  => $appr_coords['latitude'],
			'longitude' => $appr_coords['longitude'],
			'title'     => get_the_title( $appr_hive ),
			'url'       => App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . absint( $appr_hive->ID ) ),
		);
	}

	$appr_hive_ids           = array_map( static fn( $hive ) => (int) $hive->ID, $appr_hives );
	$appr_harvests_by_hive   = Harvest::get_for_hives( $appr_hive_ids );
	$appr_treatments_by_hive = Treatment::get_for_hives( $appr_hive_ids );

	foreach ( $appr_hive_ids as $appr_hid ) {
		$appr_hive_total_kg                   = Harvest::total_kg( $appr_harvests_by_hive[ $appr_hid ] ?? array() );
		$appr_harvest_kg_by_hive[ $appr_hid ] = $appr_hive_total_kg;
		$appr_apiary_total_kg                += $appr_hive_total_kg;

		$appr_hive_ongoing = 0;
		foreach ( $appr_treatments_by_hive[ $appr_hid ] ?? array() as $appr_treatment_post ) {
			if ( Treatment::is_ongoing( $appr_treatment_post ) ) {
				++$appr_hive_ongoing;
			}
		}
		$appr_ongoing_by_hive[ $appr_hid ] = $appr_hive_ongoing;
		$appr_apiary_ongoing_count        += $appr_hive_ongoing;
	}
}
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php wp_app_title( $appr_apiary ? get_the_title( $appr_apiary ) : __( 'Apiary', 'apiary-press' ) ); ?></title>
	<?php wp_app_head(); ?>
	<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
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
				<p class="apiary-notes"><?php echo esc_html__( 'You do not have permission to edit this apiary.', 'apiary-press' ); ?></p>
				<p><a class="admin-link" href="<?php echo esc_url( App::get_url() ); ?>"><?php echo esc_html__( 'Back to Apiaries', 'apiary-press' ); ?></a></p>
			</section>
		<?php else : ?>
			<header class="topbar">
				<div>
					<a class="crumb" href="<?php echo esc_url( App::get_url() ); ?>"><?php echo esc_html__( 'Apiaries', 'apiary-press' ); ?></a>
					<h1><?php echo esc_html( get_the_title( $appr_apiary ) ); ?></h1>
					<?php if ( trim( $appr_apiary->post_content ) ) : ?>
						<p class="apiary-notes"><?php echo esc_html( wp_strip_all_tags( $appr_apiary->post_content ) ); ?></p>
					<?php endif; ?>
				</div>
				<div class="actions">
					<a class="admin-link admin-link-primary" href="<?php echo esc_url( App::get_url( 'apiary/' . $appr_apiary_id . '/hive/new' ) ); ?>">
						<?php echo esc_html__( 'New Hive', 'apiary-press' ); ?>
					</a>
					<a class="admin-link" href="<?php echo esc_url( App::get_url( 'apiary/' . $appr_apiary_id . '/edit' ) ); ?>">
						<?php echo esc_html__( 'Edit Apiary', 'apiary-press' ); ?>
					</a>
				</div>
			</header>

			<?php if ( isset( $_GET['created'] ) ) : ?>
				<div class="notice"><?php echo esc_html__( 'Apiary saved.', 'apiary-press' ); ?></div>
			<?php endif; ?>

			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice"><?php echo esc_html__( 'Apiary updated.', 'apiary-press' ); ?></div>
			<?php endif; ?>

			<?php if ( isset( $_GET['hive_added'] ) ) : ?>
				<div class="notice"><?php echo esc_html__( 'Hive saved.', 'apiary-press' ); ?></div>
			<?php endif; ?>

			<?php if ( isset( $_GET['hive_deleted'] ) ) : ?>
				<div class="notice"><?php echo esc_html__( 'Hive removed.', 'apiary-press' ); ?></div>
			<?php endif; ?>

			<?php if ( $appr_form_error ) : ?>
				<div class="error"><?php echo esc_html( $appr_form_error ); ?></div>
			<?php endif; ?>

			<?php if ( ! empty( $appr_map_markers ) ) : ?>
				<div
					id="ap_hive_map"
					class="hive-map"
					role="region"
					aria-label="<?php echo esc_attr__( 'Hive locations', 'apiary-press' ); ?>"
					data-ap-hive-map
					data-markers="<?php echo esc_attr( wp_json_encode( $appr_map_markers ) ); ?>"
				></div>
			<?php endif; ?>

			<?php if ( ! empty( $appr_hives ) ) : ?>
				<section class="apiary-stats" aria-labelledby="apiary-stats-heading">
					<h2 id="apiary-stats-heading" class="visually-hidden"><?php echo esc_html__( 'Apiary at a glance', 'apiary-press' ); ?></h2>
					<div class="apiary-stat">
						<span class="apiary-stat-value"><?php echo esc_html( (string) count( $appr_hives ) ); ?></span>
						<span class="apiary-stat-label">
							<?php
							echo esc_html( _n( 'Hive', 'Hives', count( $appr_hives ), 'apiary-press' ) );
							?>
						</span>
					</div>
					<div class="apiary-stat">
						<span class="apiary-stat-value">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: kilograms of honey harvested. */
									__( '%s kg', 'apiary-press' ),
									Harvest::format_kg( $appr_apiary_total_kg )
								)
							);
							?>
						</span>
						<span class="apiary-stat-label"><?php echo esc_html__( 'Harvested (all time)', 'apiary-press' ); ?></span>
					</div>
					<div class="apiary-stat <?php echo $appr_apiary_ongoing_count > 0 ? 'apiary-stat-attention' : ''; ?>">
						<span class="apiary-stat-value"><?php echo esc_html( (string) $appr_apiary_ongoing_count ); ?></span>
						<span class="apiary-stat-label"><?php echo esc_html__( 'In progress', 'apiary-press' ); ?></span>
					</div>
				</section>
			<?php endif; ?>

			<section aria-labelledby="hive-list-heading">
				<h2 id="hive-list-heading"><?php echo esc_html__( 'Hives', 'apiary-press' ); ?></h2>

				<?php if ( empty( $appr_hives ) ) : ?>
					<div class="empty-state"><?php echo esc_html__( 'No hives yet.', 'apiary-press' ); ?></div>
				<?php else : ?>
					<div class="hive-list">
						<?php foreach ( $appr_hives as $appr_hive ) : ?>
							<?php
							$appr_visit_ids = get_posts(
								array(
									'post_type'        => Visit::HIVE_VISIT_POST_TYPE,
									'post_status'      => array( 'publish', 'future', 'draft', 'pending', 'private' ),
									'post_parent'      => $appr_hive->ID,
									'numberposts'      => -1,
									'fields'           => 'ids',
									'suppress_filters' => false,
								)
							);

							$appr_latest_visit = get_posts(
								array(
									'post_type'        => Visit::HIVE_VISIT_POST_TYPE,
									'post_status'      => array( 'publish', 'future', 'draft', 'pending', 'private' ),
									'post_parent'      => $appr_hive->ID,
									'numberposts'      => 1,
									'orderby'          => 'date',
									'order'            => 'DESC',
									'suppress_filters' => false,
								)
							);

							$appr_latest_visit_id = ! empty( $appr_latest_visit ) ? $appr_latest_visit[0]->ID : 0;
							$appr_check_soon      = $appr_latest_visit_id ? rest_sanitize_boolean( get_post_meta( $appr_latest_visit_id, 'check_soon', true ) ) : false;
							$appr_summary         = wp_trim_words( wp_strip_all_tags( $appr_hive->post_content ), 24 );
							$appr_hive_url        = App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . absint( $appr_hive->ID ) );
							$appr_hive_kg         = $appr_harvest_kg_by_hive[ $appr_hive->ID ] ?? 0.0;
							$appr_hive_ongoing    = $appr_ongoing_by_hive[ $appr_hive->ID ] ?? 0;
							?>
							<article class="hive-row">
								<div>
									<h3>
										<a href="<?php echo esc_url( $appr_hive_url ); ?>">
											<?php echo esc_html( get_the_title( $appr_hive ) ); ?>
										</a>
									</h3>
									<div class="meta">
										<?php echo esc_html( sprintf( _n( '%d visit', '%d visits', count( $appr_visit_ids ), 'apiary-press' ), count( $appr_visit_ids ) ) ); ?>
										<?php if ( $appr_latest_visit_id ) : ?>
											<?php echo esc_html( ' / ' ); ?>
											<?php echo esc_html( sprintf( __( 'Last visit %s', 'apiary-press' ), mysql2date( get_option( 'date_format' ), $appr_latest_visit[0]->post_date ) ) ); ?>
										<?php endif; ?>
										<?php if ( $appr_hive_kg > 0 ) : ?>
											<?php echo esc_html( ' / ' ); ?>
											<?php
											echo esc_html(
												sprintf(
													/* translators: %s: kilograms of honey harvested from one hive. */
													__( '%s kg harvested', 'apiary-press' ),
													Harvest::format_kg( $appr_hive_kg )
												)
											);
											?>
										<?php endif; ?>
									</div>
									<?php if ( $appr_summary ) : ?>
										<p class="summary"><?php echo esc_html( $appr_summary ); ?></p>
									<?php endif; ?>
								</div>
								<div class="stats">
									<?php if ( $appr_check_soon ) : ?>
										<span class="badge badge-attention"><?php echo esc_html__( 'Check soon', 'apiary-press' ); ?></span>
									<?php endif; ?>
									<?php if ( $appr_hive_ongoing > 0 ) : ?>
										<span class="badge badge-attention"><?php echo esc_html__( 'In treatment', 'apiary-press' ); ?></span>
									<?php endif; ?>
									<a class="admin-link" href="<?php echo esc_url( $appr_hive_url ); ?>">
										<?php echo esc_html__( 'Open', 'apiary-press' ); ?>
									</a>
									<?php if ( current_user_can( 'delete_post', $appr_hive->ID ) ) : ?>
										<form class="inline-action-form" method="post" action="<?php echo esc_url( App::get_url( 'apiary/' . $appr_apiary_id ) ); ?>">
											<input type="hidden" name="ap_action" value="delete_hive">
											<input type="hidden" name="ap_hive_id" value="<?php echo esc_attr( (string) $appr_hive->ID ); ?>">
											<?php wp_nonce_field( 'ap_delete_hive_' . $appr_apiary_id . '_' . $appr_hive->ID, 'ap_delete_nonce' ); ?>
											<button
												class="button button-danger"
												type="submit"
												onclick="return confirm('<?php echo esc_js( __( 'Delete this hive and all related records?', 'apiary-press' ) ); ?>');"
											>
												<?php echo esc_html__( 'Delete', 'apiary-press' ); ?>
											</button>
										</form>
									<?php endif; ?>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</section>
		<?php endif; ?>
	</main>

	<?php wp_app_body_close(); ?>

	<?php if ( ! $appr_not_found && ! $appr_forbidden && ! empty( $appr_map_markers ) ) : ?>
		<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
		<script src="<?php echo esc_url( App::get_asset_url( 'hive-map.js' ) ); ?>"></script>
	<?php endif; ?>
</body>
</html>
