<?php
/**
 * Apiary template for displaying apiary details and hives in the Apiary Press app.
 *
 * @package ApiaryPress
 */

namespace ApiaryPress;

use ApiaryPress\App;
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

if ( $appr_not_found ) {
	status_header( 404 );
} elseif ( $appr_forbidden ) {
	status_header( 403 );
}

$appr_hives       = array();
$appr_map_markers = array();

if ( ! $appr_not_found && ! $appr_forbidden ) {
	$appr_hives = get_posts(
		array(
			'post_type'        => Hive::HIVE_POST_TYPE,
			'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
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
									'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
									'post_parent'      => $appr_hive->ID,
									'numberposts'      => -1,
									'fields'           => 'ids',
									'suppress_filters' => false,
								)
							);

							$appr_latest_visit = get_posts(
								array(
									'post_type'        => Visit::HIVE_VISIT_POST_TYPE,
									'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
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
									</div>
									<?php if ( $appr_summary ) : ?>
										<p class="summary"><?php echo esc_html( $appr_summary ); ?></p>
									<?php endif; ?>
								</div>
								<div class="stats">
									<?php if ( $appr_check_soon ) : ?>
										<span class="badge badge-attention"><?php echo esc_html__( 'Check soon', 'apiary-press' ); ?></span>
									<?php endif; ?>
									<a class="admin-link" href="<?php echo esc_url( $appr_hive_url ); ?>">
										<?php echo esc_html__( 'Open', 'apiary-press' ); ?>
									</a>
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
