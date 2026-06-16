<?php
/**
 * Hive template for displaying hive details and visits in the Apiary Press app.
 *
 * @package ApiaryPress
 */

namespace ApiaryPress;

use ApiaryPress\App;
use ApiaryPress\Harvest;
use ApiaryPress\Treatment;
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

$appr_visits     = array();
$appr_treatments = array();
$appr_harvests   = array();
$appr_map_marker = array();

if ( ! $appr_not_found && ! $appr_forbidden ) {
	$appr_hive_url = App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id );
	$appr_hive_qr  = ( new QRCode() )->render( $appr_hive_url );

	$appr_coords = Hive::get_coordinates( $appr_hive_id );

	if ( ! empty( $appr_coords ) ) {
		$appr_map_marker[] = array(
			'latitude'  => $appr_coords['latitude'],
			'longitude' => $appr_coords['longitude'],
			'title'     => get_the_title( $appr_hive ),
		);
	}

	$appr_visits = get_posts(
		array(
			'post_type'        => Visit::HIVE_VISIT_POST_TYPE,
			'post_status'      => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'post_parent'      => $appr_hive_id,
			'numberposts'      => -1,
			'orderby'          => 'date',
			'order'            => 'DESC',
			'suppress_filters' => false,
		)
	);

	$appr_treatments = get_posts(
		array(
			'post_type'        => Treatment::HIVE_TREATMENT_POST_TYPE,
			'post_status'      => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'post_parent'      => $appr_hive_id,
			'numberposts'      => -1,
			'orderby'          => 'date',
			'order'            => 'DESC',
			'suppress_filters' => false,
		)
	);

	$appr_harvests = get_posts(
		array(
			'post_type'        => Harvest::HIVE_HARVEST_POST_TYPE,
			'post_status'      => array( 'publish', 'future', 'draft', 'pending', 'private' ),
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
	<?php if ( ! empty( $appr_map_marker ) ) : ?>
		<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Leaflet is loaded only on map views in this standalone app template. ?>
		<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
	<?php endif; ?>
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
					<a class="admin-link" href="<?php echo esc_url( App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id . '/treatment/new' ) ); ?>">
						<?php echo esc_html__( 'New Treatment / Feeding', 'apiary-press' ); ?>
					</a>
					<a class="admin-link" href="<?php echo esc_url( App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id . '/harvest/new' ) ); ?>">
						<?php echo esc_html__( 'New Harvest', 'apiary-press' ); ?>
					</a>
					<a class="admin-link" href="<?php echo esc_url( App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id . '/edit' ) ); ?>">
						<?php echo esc_html__( 'Edit Hive', 'apiary-press' ); ?>
					</a>
				</div>
			</header>

			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Post-redirect query flag used only for a notice. ?>
			<?php if ( isset( $_GET['created'] ) ) : ?>
				<div class="notice"><?php echo esc_html__( 'Hive saved.', 'apiary-press' ); ?></div>
			<?php endif; ?>

			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Post-redirect query flag used only for a notice. ?>
			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice"><?php echo esc_html__( 'Hive updated.', 'apiary-press' ); ?></div>
			<?php endif; ?>

			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Post-redirect query flag used only for a notice. ?>
			<?php if ( isset( $_GET['visit_added'] ) ) : ?>
				<div class="notice"><?php echo esc_html__( 'Visit saved.', 'apiary-press' ); ?></div>
			<?php endif; ?>

			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Post-redirect query flag used only for a notice. ?>
			<?php if ( isset( $_GET['visit_deleted'] ) ) : ?>
				<div class="notice"><?php echo esc_html__( 'Visit removed.', 'apiary-press' ); ?></div>
			<?php endif; ?>

			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Post-redirect query flag used only for a notice. ?>
			<?php if ( isset( $_GET['treatment_deleted'] ) ) : ?>
				<div class="notice"><?php echo esc_html__( 'Entry removed.', 'apiary-press' ); ?></div>
			<?php endif; ?>

			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Post-redirect query flag used only for a notice. ?>
			<?php if ( isset( $_GET['harvest_deleted'] ) ) : ?>
				<div class="notice"><?php echo esc_html__( 'Harvest removed.', 'apiary-press' ); ?></div>
			<?php endif; ?>

			<?php
			$appr_kind_labels   = Treatment::get_kind_labels();
			$appr_target_labels = Treatment::get_target_labels();
			$appr_unit_labels   = Treatment::get_unit_labels();
			$appr_method_labels = Harvest::get_method_labels();
			$appr_harvest_total = Harvest::total_kg( $appr_harvests );
			$appr_ongoing_count = 0;

			foreach ( $appr_treatments as $appr_treatment_post ) {
				if ( Treatment::is_ongoing( $appr_treatment_post ) ) {
					++$appr_ongoing_count;
				}
			}

			$appr_queen          = Hive::get_queen( $appr_hive_id );
			$appr_has_queen_info = $appr_queen['year']
				|| '' !== $appr_queen['color']
				|| '' !== $appr_queen['origin']
				|| '' !== $appr_queen['installed_at']
				|| $appr_queen['marked']
				|| $appr_queen['clipped'];
			?>

			<section class="apiary-stats" aria-labelledby="hive-stats-heading">
				<h2 id="hive-stats-heading" class="visually-hidden"><?php echo esc_html__( 'Hive at a glance', 'apiary-press' ); ?></h2>
				<div class="apiary-stat">
					<span class="apiary-stat-value"><?php echo esc_html( (string) count( $appr_visits ) ); ?></span>
					<span class="apiary-stat-label">
						<?php echo esc_html( _n( 'Visit', 'Visits', count( $appr_visits ), 'apiary-press' ) ); ?>
					</span>
				</div>
				<div class="apiary-stat">
					<span class="apiary-stat-value">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: kilograms of honey harvested. */
								__( '%s kg', 'apiary-press' ),
								Harvest::format_kg( $appr_harvest_total )
							)
						);
						?>
					</span>
					<span class="apiary-stat-label"><?php echo esc_html__( 'Harvested', 'apiary-press' ); ?></span>
				</div>
				<div class="apiary-stat <?php echo $appr_ongoing_count > 0 ? 'apiary-stat-attention' : ''; ?>">
					<span class="apiary-stat-value"><?php echo esc_html( (string) $appr_ongoing_count ); ?></span>
					<span class="apiary-stat-label"><?php echo esc_html__( 'In progress', 'apiary-press' ); ?></span>
				</div>
			</section>

			<?php if ( $appr_has_queen_info ) : ?>
				<?php
				$appr_queen_color_label = $appr_queen['color'] ? Hive::queen_color_label( $appr_queen['color'] ) : '';
				$appr_queen_swatch      = $appr_queen['color'] ? Hive::queen_color_swatch( $appr_queen['color'] ) : '';
				?>
				<section class="queen-panel" aria-labelledby="hive-queen-heading">
					<h2 id="hive-queen-heading"><?php echo esc_html__( 'Queen', 'apiary-press' ); ?></h2>
					<dl class="queen-summary">
						<?php if ( $appr_queen['year'] ) : ?>
							<div class="queen-summary-row">
								<dt><?php echo esc_html__( 'Year', 'apiary-press' ); ?></dt>
								<dd><?php echo esc_html( (string) $appr_queen['year'] ); ?></dd>
							</div>
						<?php endif; ?>

						<?php if ( $appr_queen_color_label ) : ?>
							<div class="queen-summary-row">
								<dt><?php echo esc_html__( 'Marking color', 'apiary-press' ); ?></dt>
								<dd>
									<span class="queen-color-swatch" style="background-color: <?php echo esc_attr( $appr_queen_swatch ); ?>;" aria-hidden="true"></span>
									<?php echo esc_html( $appr_queen_color_label ); ?>
									<?php if ( '' === $appr_queen['color_override'] && $appr_queen['year'] ) : ?>
										<span class="muted">(<?php echo esc_html__( 'auto from year', 'apiary-press' ); ?>)</span>
									<?php endif; ?>
								</dd>
							</div>
						<?php endif; ?>

						<?php if ( '' !== $appr_queen['installed_at'] ) : ?>
							<div class="queen-summary-row">
								<dt><?php echo esc_html__( 'Installed on', 'apiary-press' ); ?></dt>
								<dd><?php echo esc_html( mysql2date( get_option( 'date_format' ), $appr_queen['installed_at'] ) ); ?></dd>
							</div>
						<?php endif; ?>

						<?php if ( '' !== $appr_queen['origin'] ) : ?>
							<div class="queen-summary-row">
								<dt><?php echo esc_html__( 'Origin', 'apiary-press' ); ?></dt>
								<dd><?php echo esc_html( $appr_queen['origin'] ); ?></dd>
							</div>
						<?php endif; ?>

						<?php if ( $appr_queen['marked'] || $appr_queen['clipped'] ) : ?>
							<div class="queen-summary-row">
								<dt><?php echo esc_html__( 'Flags', 'apiary-press' ); ?></dt>
								<dd>
									<div class="badge-list">
										<?php if ( $appr_queen['marked'] ) : ?>
											<span class="badge"><?php echo esc_html__( 'Marked', 'apiary-press' ); ?></span>
										<?php endif; ?>
										<?php if ( $appr_queen['clipped'] ) : ?>
											<span class="badge"><?php echo esc_html__( 'Clipped', 'apiary-press' ); ?></span>
										<?php endif; ?>
									</div>
								</dd>
							</div>
						<?php endif; ?>
					</dl>
				</section>
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
					<?php if ( ! empty( $appr_map_marker ) ) : ?>
						<div
							class="qr-panel-map"
							role="region"
							aria-label="<?php echo esc_attr__( 'Hive location', 'apiary-press' ); ?>"
							data-ap-hive-map
							data-markers="<?php echo esc_attr( wp_json_encode( $appr_map_marker ) ); ?>"
							data-zoom="15"
						></div>
					<?php endif; ?>
				</section>
			<?php endif; ?>

			<section aria-labelledby="harvest-list-heading">
				<div class="section-header">
					<div>
						<h2 id="harvest-list-heading"><?php echo esc_html__( 'Harvests', 'apiary-press' ); ?></h2>
					</div>
					<div class="section-actions">
						<a class="admin-link" href="<?php echo esc_url( App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id . '/harvest/new' ) ); ?>">
							<?php echo esc_html__( 'New Harvest', 'apiary-press' ); ?>
						</a>
					</div>
				</div>

				<?php if ( ! empty( $appr_harvests ) ) : ?>
					<div class="harvest-total" role="status">
						<span class="harvest-total-label"><?php echo esc_html__( 'Total harvested', 'apiary-press' ); ?></span>
						<span class="harvest-total-value">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: kilograms of honey harvested. */
									__( '%s kg', 'apiary-press' ),
									Harvest::format_kg( $appr_harvest_total )
								)
							);
							?>
						</span>
						<span class="harvest-total-count muted">
							<?php
							printf(
								/* translators: %d: number of harvest entries. */
								esc_html( _n( 'over %d entry', 'over %d entries', count( $appr_harvests ), 'apiary-press' ) ),
								(int) count( $appr_harvests )
							);
							?>
						</span>
					</div>
				<?php endif; ?>

				<?php if ( empty( $appr_harvests ) ) : ?>
					<div class="empty-state">
						<h3><?php echo esc_html__( 'No harvests logged yet.', 'apiary-press' ); ?></h3>
						<a class="admin-link admin-link-primary" href="<?php echo esc_url( App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id . '/harvest/new' ) ); ?>">
							<?php echo esc_html__( 'New Harvest', 'apiary-press' ); ?>
						</a>
					</div>
				<?php else : ?>
					<div class="harvest-list">
						<?php foreach ( $appr_harvests as $appr_harvest_post ) : ?>
							<?php
							$appr_h            = Harvest::get_harvest( (int) $appr_harvest_post->ID );
							$appr_method_label = $appr_h['method'] && isset( $appr_method_labels[ $appr_h['method'] ] ) ? $appr_method_labels[ $appr_h['method'] ] : '';
							$appr_h_author     = get_the_author_meta( 'display_name', (int) $appr_harvest_post->post_author );
							?>
							<article class="visit-row harvest-row">
								<div class="visit-head">
									<div>
										<div class="visit-date"><?php echo esc_html( mysql2date( get_option( 'date_format' ), $appr_harvest_post->post_date ) ); ?></div>
										<div class="muted">
											<?php
											printf(
												/* translators: %s: the display name of the user who recorded the harvest. */
												esc_html__( 'by %s', 'apiary-press' ),
												esc_html( $appr_h_author ? $appr_h_author : __( 'Unknown', 'apiary-press' ) )
											);
											?>
										</div>
									</div>
									<a href="<?php echo esc_url( App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id . '/harvest/' . absint( $appr_harvest_post->ID ) ) ); ?>" class="row-link">
										<?php echo esc_html__( 'View / Edit', 'apiary-press' ); ?>
									</a>
								</div>

								<div class="harvest-headline">
									<span class="harvest-headline-value">
										<?php
										echo esc_html(
											sprintf(
												/* translators: %s: kilograms of honey harvested. */
												__( '%s kg', 'apiary-press' ),
												Harvest::format_kg( $appr_h['quantity_kg'] )
											)
										);
										?>
									</span>
									<?php if ( '' !== $appr_h['honey_type'] ) : ?>
										<span class="harvest-type"><?php echo esc_html( $appr_h['honey_type'] ); ?></span>
									<?php endif; ?>
								</div>

								<?php if ( $appr_h['frames'] > 0 || $appr_method_label ) : ?>
									<div class="badge-list">
										<?php if ( $appr_h['frames'] > 0 ) : ?>
											<span class="badge">
												<?php
												printf(
													/* translators: %d: number of frames extracted. */
													esc_html( _n( '%d frame', '%d frames', $appr_h['frames'], 'apiary-press' ) ),
													(int) $appr_h['frames']
												);
												?>
											</span>
										<?php endif; ?>
										<?php if ( $appr_method_label ) : ?>
											<span class="badge"><?php echo esc_html( $appr_method_label ); ?></span>
										<?php endif; ?>
									</div>
								<?php endif; ?>

								<?php if ( trim( $appr_harvest_post->post_content ) ) : ?>
									<p class="visit-notes"><?php echo esc_html( wp_strip_all_tags( $appr_harvest_post->post_content ) ); ?></p>
								<?php endif; ?>
							</article>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</section>

			<section aria-labelledby="treatment-list-heading">
				<div class="section-header">
					<div>
						<h2 id="treatment-list-heading"><?php echo esc_html__( 'Treatments &amp; Feedings', 'apiary-press' ); ?></h2>
					</div>
					<div class="section-actions">
						<a class="admin-link" href="<?php echo esc_url( App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id . '/treatment/new' ) ); ?>">
							<?php echo esc_html__( 'New Treatment / Feeding', 'apiary-press' ); ?>
						</a>
					</div>
				</div>

				<?php if ( $appr_ongoing_count > 0 ) : ?>
					<div class="notice notice-attention">
						<?php
						printf(
							/* translators: %d: the number of treatments currently in progress. */
							esc_html( _n( '%d treatment is currently in progress.', '%d treatments are currently in progress.', $appr_ongoing_count, 'apiary-press' ) ),
							(int) $appr_ongoing_count
						);
						?>
					</div>
				<?php endif; ?>

				<?php if ( empty( $appr_treatments ) ) : ?>
					<div class="empty-state">
						<h3><?php echo esc_html__( 'No treatments or feedings logged.', 'apiary-press' ); ?></h3>
						<a class="admin-link admin-link-primary" href="<?php echo esc_url( App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id . '/treatment/new' ) ); ?>">
							<?php echo esc_html__( 'New Treatment / Feeding', 'apiary-press' ); ?>
						</a>
					</div>
				<?php else : ?>
					<div class="treatment-list">
						<?php foreach ( $appr_treatments as $appr_treatment_post ) : ?>
							<?php
							$appr_t              = Treatment::get_treatment( (int) $appr_treatment_post->ID );
							$appr_kind_label     = $appr_kind_labels[ $appr_t['kind'] ] ?? '';
							$appr_target_label   = $appr_t['target'] && isset( $appr_target_labels[ $appr_t['target'] ] ) ? $appr_target_labels[ $appr_t['target'] ] : '';
							$appr_unit_label     = $appr_t['unit'] && isset( $appr_unit_labels[ $appr_t['unit'] ] ) ? $appr_unit_labels[ $appr_t['unit'] ] : '';
							$appr_t_author       = get_the_author_meta( 'display_name', (int) $appr_treatment_post->post_author );
							$appr_is_ongoing     = Treatment::is_ongoing( $appr_treatment_post );
							$appr_quantity_label = $appr_t['quantity'] > 0
								? rtrim( rtrim( number_format( $appr_t['quantity'], 3, '.', '' ), '0' ), '.' )
								: '';
							?>
							<article class="visit-row treatment-row">
								<div class="visit-head">
									<div>
										<div class="visit-date"><?php echo esc_html( mysql2date( get_option( 'date_format' ), $appr_treatment_post->post_date ) ); ?></div>
										<div class="muted">
											<?php
											printf(
												/* translators: %s: the display name of the user who recorded the entry. */
												esc_html__( 'by %s', 'apiary-press' ),
												esc_html( $appr_t_author ? $appr_t_author : __( 'Unknown', 'apiary-press' ) )
											);
											?>
										</div>
									</div>
									<a href="<?php echo esc_url( App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id . '/treatment/' . absint( $appr_treatment_post->ID ) ) ); ?>" class="row-link">
										<?php echo esc_html__( 'View / Edit', 'apiary-press' ); ?>
									</a>
								</div>

								<div class="badge-list">
									<span class="badge badge-kind badge-kind-<?php echo esc_attr( $appr_t['kind'] ); ?>">
										<?php echo esc_html( $appr_kind_label ); ?>
									</span>
									<?php if ( $appr_target_label ) : ?>
										<span class="badge"><?php echo esc_html( $appr_target_label ); ?></span>
									<?php endif; ?>
									<?php if ( $appr_is_ongoing ) : ?>
										<span class="badge badge-attention"><?php echo esc_html__( 'In progress', 'apiary-press' ); ?></span>
									<?php endif; ?>
								</div>

								<dl class="treatment-summary">
									<?php if ( '' !== $appr_t['product'] ) : ?>
										<div>
											<dt><?php echo esc_html__( 'Product', 'apiary-press' ); ?></dt>
											<dd><?php echo esc_html( $appr_t['product'] ); ?></dd>
										</div>
									<?php endif; ?>

									<?php if ( '' !== $appr_quantity_label ) : ?>
										<div>
											<dt><?php echo esc_html__( 'Quantity', 'apiary-press' ); ?></dt>
											<dd>
												<?php echo esc_html( $appr_quantity_label ); ?>
												<?php if ( $appr_unit_label ) : ?>
													<span class="muted"><?php echo esc_html( $appr_unit_label ); ?></span>
												<?php endif; ?>
											</dd>
										</div>
									<?php endif; ?>

									<?php if ( '' !== $appr_t['end_date'] ) : ?>
										<div>
											<dt><?php echo esc_html__( 'End date', 'apiary-press' ); ?></dt>
											<dd><?php echo esc_html( mysql2date( get_option( 'date_format' ), $appr_t['end_date'] ) ); ?></dd>
										</div>
									<?php endif; ?>
								</dl>

								<?php if ( trim( $appr_treatment_post->post_content ) ) : ?>
									<p class="visit-notes"><?php echo esc_html( wp_strip_all_tags( $appr_treatment_post->post_content ) ); ?></p>
								<?php endif; ?>
							</article>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</section>

			<section aria-labelledby="visit-list-heading">
				<div class="section-header">
					<div>
						<h2 id="visit-list-heading"><?php echo esc_html__( 'Visits', 'apiary-press' ); ?></h2>
					</div>
					<div class="section-actions">
						<a class="admin-link" href="<?php echo esc_url( App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id . '/visit/new' ) ); ?>">
							<?php echo esc_html__( 'New Visit', 'apiary-press' ); ?>
						</a>
					</div>
				</div>

				<?php if ( empty( $appr_visits ) ) : ?>
					<div class="empty-state">
						<h3><?php echo esc_html__( 'No visits yet.', 'apiary-press' ); ?></h3>
						<a class="admin-link admin-link-primary" href="<?php echo esc_url( App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id . '/visit/new' ) ); ?>">
							<?php echo esc_html__( 'New Visit', 'apiary-press' ); ?>
						</a>
					</div>
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
									<a href="<?php echo esc_url( App::get_url( 'apiary/' . $appr_apiary_id . '/hive/' . $appr_hive_id . '/visit/' . absint( $appr_visit->ID ) ) ); ?>" class="row-link">
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

	<?php if ( ! $appr_not_found && ! $appr_forbidden && ! empty( $appr_map_marker ) ) : ?>
		<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Leaflet is loaded only on map views in this standalone app template. ?>
		<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
		<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- App map boot code is loaded only when map markup is present. ?>
		<script src="<?php echo esc_url( App::get_asset_url( 'hive-map.js' ) ); ?>"></script>
	<?php endif; ?>
</body>
</html>
