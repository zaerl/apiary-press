<?php
/**
 * Hive template for displaying hive details and visits in the Apiary Press app.
 *
 * @package ApiaryPress
 */

namespace ApiaryPress;

use ApiaryPress\App;
use ApiaryPress\Visit;
use ApiaryPress\Weather;
use chillerlan\QRCode\QRCode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wp_app_route;

$appr_route_params = isset( $wp_app_route['params'] ) && is_array( $wp_app_route['params'] ) ? $wp_app_route['params'] : array();
$appr_apiary_id    = isset( $appr_route_params['apiary_id'] ) ? absint( $appr_route_params['apiary_id'] ) : absint( get_query_var( 'apiary_id' ) );
$appr_hive_id      = isset( $appr_route_params['id'] ) ? absint( $appr_route_params['id'] ) : absint( get_query_var( 'id' ) );
$appr_apiary       = $appr_apiary_id ? get_post( $appr_apiary_id ) : null;
$appr_hive         = $appr_hive_id ? get_post( $appr_hive_id ) : null;
$appr_not_found    = ! $appr_apiary
	|| Apiary::APIARY_POST_TYPE !== $appr_apiary->post_type
	|| ! $appr_hive
	|| Hive::HIVE_POST_TYPE !== $appr_hive->post_type
	|| absint( $appr_hive->post_parent ) !== $appr_apiary_id;
$appr_forbidden    = ! $appr_not_found && ! current_user_can( 'edit_post', $appr_hive_id );
$appr_meta_labels  = Visit::get_boolean_meta_labels();
$appr_hive_url     = '';
$appr_hive_qr      = '';

if ( $appr_not_found ) {
	status_header( 404 );
} elseif ( $appr_forbidden ) {
	status_header( 403 );
}

$appr_visits = array();

if ( ! $appr_not_found && ! $appr_forbidden ) {
	$appr_hive_url = App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id );
	$appr_hive_qr  = ( new QRCode() )->render( $appr_hive_url );

	$appr_visits = get_posts(
		array(
			'post_type'        => Visit::HIVE_VISIT_POST_TYPE,
			'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
			'post_parent'      => $appr_hive_id,
			'numberposts'      => -1,
			'orderby'          => 'date',
			'order'            => 'DESC',
			'suppress_filters' => false,
		)
	);
}
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php wp_app_title( $appr_hive ? get_the_title( $appr_hive ) : __( 'Hive', 'apiary-press' ) ); ?></title>
	<?php wp_app_head(); ?>
</head>
<body>
	<?php wp_app_body_open(); ?>

	<main class="shell">
		<?php if ( $appr_not_found ) : ?>
			<section class="message">
				<h1><?php echo esc_html__( 'Hive Not Found', 'apiary-press' ); ?></h1>
				<p class="hive-notes"><?php echo esc_html__( 'The requested hive is not available.', 'apiary-press' ); ?></p>
				<p><a class="admin-link" href="<?php echo esc_url( App::get_url() ); ?>"><?php echo esc_html__( 'Back to Apiaries', 'apiary-press' ); ?></a></p>
			</section>
		<?php elseif ( $appr_forbidden ) : ?>
			<section class="message">
				<h1><?php echo esc_html__( 'Access Denied', 'apiary-press' ); ?></h1>
				<p class="hive-notes"><?php echo esc_html__( 'You do not have permission to edit this hive.', 'apiary-press' ); ?></p>
				<p><a class="admin-link" href="<?php echo esc_url( App::get_url() ); ?>"><?php echo esc_html__( 'Back to Apiaries', 'apiary-press' ); ?></a></p>
			</section>
		<?php else : ?>
			<header class="topbar">
				<div>
					<a class="crumb" href="<?php echo esc_url( App::get_url( 'apiary/' . $appr_apiary_id ) ); ?>"><?php echo esc_html( get_the_title( $appr_apiary ) ); ?></a>
					<h1><?php echo esc_html( get_the_title( $appr_hive ) ); ?></h1>
					<?php if ( trim( $appr_hive->post_content ) ) : ?>
						<p class="hive-notes"><?php echo esc_html( wp_strip_all_tags( $appr_hive->post_content ) ); ?></p>
					<?php endif; ?>
				</div>
				<div class="actions">
					<a class="admin-link admin-link-primary" href="<?php echo esc_url( App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id . '/visit/new' ) ); ?>">
						<?php echo esc_html__( 'New Visit', 'apiary-press' ); ?>
					</a>
					<a class="admin-link" href="<?php echo esc_url( App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id . '/qr' ) ); ?>">
						<?php echo esc_html__( 'Print QR', 'apiary-press' ); ?>
					</a>
					<a class="admin-link" href="<?php echo esc_url( App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id . '/edit' ) ); ?>">
						<?php echo esc_html__( 'Edit Hive', 'apiary-press' ); ?>
					</a>
				</div>
			</header>

			<?php if ( isset( $_GET['created'] ) ) : ?>
				<div class="notice"><?php echo esc_html__( 'Hive saved.', 'apiary-press' ); ?></div>
			<?php endif; ?>

			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice"><?php echo esc_html__( 'Hive updated.', 'apiary-press' ); ?></div>
			<?php endif; ?>

			<?php if ( isset( $_GET['visit_added'] ) ) : ?>
				<div class="notice"><?php echo esc_html__( 'Visit saved.', 'apiary-press' ); ?></div>
			<?php endif; ?>

			<?php if ( isset( $_GET['visit_deleted'] ) ) : ?>
				<div class="notice"><?php echo esc_html__( 'Visit removed.', 'apiary-press' ); ?></div>
			<?php endif; ?>

			<?php if ( $appr_hive_qr ) : ?>
				<section class="qr-panel" aria-labelledby="hive-qr-heading">
					<img
						src="<?php echo esc_attr( $appr_hive_qr ); ?>"
						alt="<?php /* translators: %s: the title of the hive. */ echo esc_attr( sprintf( __( 'QR code for %s', 'apiary-press' ), get_the_title( $appr_hive ) ) ); ?>"
					>
					<div>
						<h2 id="hive-qr-heading"><?php echo esc_html__( 'Hive QR', 'apiary-press' ); ?></h2>
						<a class="qr-link" href="<?php echo esc_url( $appr_hive_url ); ?>"><?php echo esc_html( $appr_hive_url ); ?></a>
						<p><a class="admin-link" href="<?php echo esc_url( App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id . '/qr' ) ); ?>"><?php echo esc_html__( 'Print QR', 'apiary-press' ); ?></a></p>
					</div>
				</section>
			<?php endif; ?>

			<section aria-labelledby="visit-list-heading">
				<h2 id="visit-list-heading"><?php echo esc_html__( 'Visits', 'apiary-press' ); ?></h2>

				<?php if ( empty( $appr_visits ) ) : ?>
					<div class="empty-state"><?php echo esc_html__( 'No visits yet.', 'apiary-press' ); ?></div>
				<?php else : ?>
					<div class="visit-list">
						<?php foreach ( $appr_visits as $appr_visit ) : ?>
							<?php
							$appr_active_flags  = array();
							$appr_weather_error = get_post_meta( $appr_visit->ID, 'weather_error', true );
							$appr_weather_icon  = get_post_meta( $appr_visit->ID, 'symbol_code', true );
							$appr_author_name   = get_the_author_meta( 'display_name', (int) $appr_visit->post_author );
							$appr_visit_reason  = (string) get_post_meta( $appr_visit->ID, Visit::REASON_META_KEY, true );
							$appr_reason_label  = Visit::get_visit_meta_labels()[ $appr_visit_reason ] ?? '';
							$appr_media_count   = count( Visit::get_visit_media( (int) $appr_visit->ID ) );

							foreach ( $appr_meta_labels as $appr_meta_key => $appr_label ) {
								if ( rest_sanitize_boolean( get_post_meta( $appr_visit->ID, $appr_meta_key, true ) ) ) {
									$appr_active_flags[ $appr_meta_key ] = $appr_label;
								}
							}
							?>
							<article class="visit-row">
								<div class="visit-head">
									<div>
										<div class="visit-date"><?php echo esc_html( mysql2date( get_option( 'date_format' ), $appr_visit->post_date ) ); ?></div>
										<div class="muted">
											<?php
											printf(
												/* translators: %s: the display name of the user who recorded the visit. */
												esc_html__( 'by %s', 'apiary-press' ),
												esc_html( $appr_author_name ? $appr_author_name : __( 'Unknown', 'apiary-press' ) )
											);
											?>
										</div>
									</div>
									<a href="<?php echo esc_url( App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id . '/visit/' . absint( $appr_visit->ID ) ) ); ?>" class="muted">
										<?php echo esc_html__( 'View / Edit', 'apiary-press' ); ?>
									</a>
								</div>

								<?php if ( trim( $appr_visit->post_content ) ) : ?>
									<p class="visit-notes"><?php echo esc_html( wp_strip_all_tags( $appr_visit->post_content ) ); ?></p>
								<?php endif; ?>

								<?php if ( $appr_reason_label ) : ?>
									<p class="visit-reason">
										<strong><?php echo esc_html__( 'Reason:', 'apiary-press' ); ?></strong>
										<?php echo esc_html( $appr_reason_label ); ?>
									</p>
								<?php endif; ?>

								<?php
								$appr_weather_icon_html = $appr_weather_icon ? Weather::render_symbol_icon_html( $appr_weather_icon, 48 ) : '';
								?>
								<?php if ( $appr_weather_error || $appr_weather_icon_html ) : ?>
									<div class="weather-summary" aria-label="<?php echo esc_attr__( 'Registered weather', 'apiary-press' ); ?>">
										<div class="weather-title"><?php echo esc_html__( 'Registered Weather', 'apiary-press' ); ?></div>

										<?php if ( $appr_weather_error ) : ?>
											<div class="muted"><?php echo esc_html( $appr_weather_error ); ?></div>
										<?php else : ?>
											<?php echo $appr_weather_icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										<?php endif; ?>
									</div>
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

								<?php if ( $appr_media_count > 0 ) : ?>
									<div class="muted visit-media-count">
										<?php
										printf(
											/* translators: %d: number of media files attached to a visit. */
											esc_html( _n( '%d media file', '%d media files', $appr_media_count, 'apiary-press' ) ),
											(int) $appr_media_count
										);
										?>
									</div>
								<?php endif; ?>
							</article>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</section>
		<?php endif; ?>
	</main>

	<?php wp_app_body_close(); ?>
</body>
</html>
