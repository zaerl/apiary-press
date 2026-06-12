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

$route_params = isset( $wp_app_route['params'] ) && is_array( $wp_app_route['params'] ) ? $wp_app_route['params'] : array();
$hive_id      = isset( $route_params['id'] ) ? absint( $route_params['id'] ) : absint( get_query_var( 'id' ) );
$hive         = $hive_id ? get_post( $hive_id ) : null;
$not_found    = ! $hive || Hive::HIVE_POST_TYPE !== $hive->post_type;
$forbidden    = ! $not_found && ! current_user_can( 'edit_post', $hive_id );
$meta_labels  = Visit::get_boolean_meta_labels();
$hive_url     = '';
$hive_qr      = '';

if ( $not_found ) {
	status_header( 404 );
} elseif ( $forbidden ) {
	status_header( 403 );
}

$visits = array();

if ( ! $not_found && ! $forbidden ) {
	$hive_url = App::get_url( 'hive/' . $hive_id );
	$hive_qr  = ( new QRCode() )->render( $hive_url );

	$visits = get_posts(
		array(
			'post_type'        => Visit::HIVE_VISIT_POST_TYPE,
			'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
			'post_parent'      => $hive_id,
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
	<title><?php wp_app_title( $hive ? get_the_title( $hive ) : __( 'Hive', 'apiary-press' ) ); ?></title>
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
			width: min(1120px, calc(100% - 32px));
			margin: 0 auto;
			padding: 32px 0 56px;
		}
		.topbar {
			display: flex;
			justify-content: space-between;
			gap: 16px;
			margin-bottom: 24px;
		}
		.actions {
			display: flex;
			flex-wrap: wrap;
			gap: 10px;
			justify-content: flex-end;
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
		.admin-link-primary {
			background: #1e824c;
			border-color: #1e824c;
			color: #fff;
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
		.qr-panel,
		.visit-row,
		.empty-state,
		.message {
			background: var(--wp-app-color-surface);
			border: 1px solid var(--wp-app-color-border);
			border-radius: 8px;
		}
		.panel,
		.message { padding: 20px; }
		.qr-panel {
			align-items: center;
			display: flex;
			gap: 16px;
			margin: 0 0 24px;
			padding: 14px;
		}
		.qr-panel img {
			background: #fff;
			border-radius: 4px;
			display: block;
			height: 132px;
			padding: 8px;
			width: 132px;
		}
		.qr-panel h2 {
			margin-bottom: 6px;
		}
		.qr-link {
			color: var(--wp-app-color-muted);
			display: inline-block;
			font-size: 13px;
			line-height: 1.45;
			overflow-wrap: anywhere;
		}
		.hive-notes {
			color: var(--wp-app-color-muted);
			line-height: 1.55;
			margin: 8px 0 0;
			max-width: 720px;
		}
		label {
			display: block;
			font-size: 13px;
			font-weight: 700;
			margin-bottom: 6px;
		}
		input[type="date"],
		textarea {
			width: 100%;
			border: 1px solid var(--wp-app-color-border);
			border-radius: 6px;
			background: var(--wp-app-color-background);
			color: var(--wp-app-color-text);
			font: inherit;
			padding: 10px 12px;
		}
		textarea { min-height: 116px; resize: vertical; }
		.field { margin-bottom: 16px; }
		.check-grid {
			display: grid;
			gap: 8px;
			grid-template-columns: 1fr;
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
		.visit-list {
			display: grid;
			gap: 12px;
		}
		.visit-row {
			padding: 18px;
		}
		.visit-head {
			align-items: start;
			display: flex;
			justify-content: space-between;
			gap: 16px;
			margin-bottom: 10px;
		}
		.visit-date {
			font-size: 17px;
			font-weight: 800;
		}
		.visit-notes {
			color: var(--wp-app-color-muted);
			line-height: 1.55;
			margin: 0 0 12px;
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
		.weather-summary {
			border-top: 1px solid var(--wp-app-color-border);
			display: grid;
			gap: 8px;
			margin: 12px 0;
			padding-top: 12px;
		}
		.weather-title {
			color: var(--wp-app-color-muted);
			font-size: 12px;
			font-weight: 800;
			line-height: 1.2;
		}
		.weather-icon {
			display: block;
			height: 48px;
			width: 48px;
		}
		.muted {
			color: var(--wp-app-color-muted);
			font-size: 13px;
		}
		.empty-state {
			color: var(--wp-app-color-muted);
			padding: 24px;
		}
		@media (max-width: 760px) {
			.shell { width: min(100% - 24px, 1120px); padding-top: 24px; }
			.topbar { flex-direction: column; align-items: flex-start; }
			.actions { justify-content: flex-start; }
			.qr-panel { align-items: flex-start; flex-direction: column; }
			.visit-head { flex-direction: column; gap: 4px; }
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
				<p><a class="admin-link" href="<?php echo esc_url( App::get_url() ); ?>"><?php echo esc_html__( 'Back to Hives', 'apiary-press' ); ?></a></p>
			</section>
		<?php elseif ( $forbidden ) : ?>
			<section class="message">
				<h1><?php echo esc_html__( 'Access Denied', 'apiary-press' ); ?></h1>
				<p class="hive-notes"><?php echo esc_html__( 'You do not have permission to edit this hive.', 'apiary-press' ); ?></p>
				<p><a class="admin-link" href="<?php echo esc_url( App::get_url() ); ?>"><?php echo esc_html__( 'Back to Hives', 'apiary-press' ); ?></a></p>
			</section>
		<?php else : ?>
			<header class="topbar">
				<div>
					<a class="crumb" href="<?php echo esc_url( App::get_url() ); ?>"><?php echo esc_html__( 'Hives', 'apiary-press' ); ?></a>
					<h1><?php echo esc_html( get_the_title( $hive ) ); ?></h1>
					<?php if ( trim( $hive->post_content ) ) : ?>
						<p class="hive-notes"><?php echo esc_html( wp_strip_all_tags( $hive->post_content ) ); ?></p>
					<?php endif; ?>
				</div>
				<div class="actions">
					<a class="admin-link admin-link-primary" href="<?php echo esc_url( App::get_url( 'hive/' . $hive_id . '/visit/new' ) ); ?>">
						<?php echo esc_html__( 'New Visit', 'apiary-press' ); ?>
					</a>
					<a class="admin-link" href="<?php echo esc_url( App::get_url( 'hive/' . $hive_id . '/qr' ) ); ?>">
						<?php echo esc_html__( 'Print QR', 'apiary-press' ); ?>
					</a>
					<a class="admin-link" href="<?php echo esc_url( App::get_url( 'hive/' . $hive_id . '/edit' ) ); ?>">
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

			<?php if ( $hive_qr ) : ?>
				<section class="qr-panel" aria-labelledby="hive-qr-heading">
					<img src="<?php echo esc_attr( $hive_qr ); ?>" alt="<?php echo esc_attr( sprintf( __( 'QR code for %s', 'apiary-press' ), get_the_title( $hive ) ) ); ?>">
					<div>
						<h2 id="hive-qr-heading"><?php echo esc_html__( 'Hive QR', 'apiary-press' ); ?></h2>
						<a class="qr-link" href="<?php echo esc_url( $hive_url ); ?>"><?php echo esc_html( $hive_url ); ?></a>
						<p><a class="admin-link" href="<?php echo esc_url( App::get_url( 'hive/' . $hive_id . '/qr' ) ); ?>"><?php echo esc_html__( 'Print QR', 'apiary-press' ); ?></a></p>
					</div>
				</section>
			<?php endif; ?>

			<section aria-labelledby="visit-list-heading">
				<h2 id="visit-list-heading"><?php echo esc_html__( 'Visits', 'apiary-press' ); ?></h2>

				<?php if ( empty( $visits ) ) : ?>
					<div class="empty-state"><?php echo esc_html__( 'No visits yet.', 'apiary-press' ); ?></div>
				<?php else : ?>
					<div class="visit-list">
						<?php foreach ( $visits as $visit ) : ?>
							<?php
							$active_flags    = array();
							$weather_error   = get_post_meta( $visit->ID, 'weather_error', true );
							$weather_icon    = get_post_meta( $visit->ID, 'symbol_code', true );
							$author_name     = get_the_author_meta( 'display_name', (int) $visit->post_author );
							$visit_reason    = (string) get_post_meta( $visit->ID, Visit::REASON_META_KEY, true );
							$reason_label    = Visit::get_visit_meta_labels()[ $visit_reason ] ?? '';

							foreach ( $meta_labels as $meta_key => $label ) {
								if ( rest_sanitize_boolean( get_post_meta( $visit->ID, $meta_key, true ) ) ) {
									$active_flags[ $meta_key ] = $label;
								}
							}
							?>
							<article class="visit-row">
								<div class="visit-head">
									<div>
										<div class="visit-date"><?php echo esc_html( mysql2date( get_option( 'date_format' ), $visit->post_date ) ); ?></div>
										<div class="muted">
											<?php
											printf(
												/* translators: %s: the display name of the user who recorded the visit. */
												esc_html__( 'by %s', 'apiary-press' ),
												esc_html( $author_name ? $author_name : __( 'Unknown', 'apiary-press' ) )
											);
											?>
										</div>
									</div>
									<a href="<?php echo esc_url( App::get_url( 'hive/' . $hive_id . '/visit/' . absint( $visit->ID ) ) ); ?>" class="muted">
										<?php echo esc_html__( 'View / Edit', 'apiary-press' ); ?>
									</a>
								</div>

								<?php if ( trim( $visit->post_content ) ) : ?>
									<p class="visit-notes"><?php echo esc_html( wp_strip_all_tags( $visit->post_content ) ); ?></p>
								<?php endif; ?>

								<?php if ( $reason_label ) : ?>
									<p class="visit-reason">
										<strong><?php echo esc_html__( 'Reason:', 'apiary-press' ); ?></strong>
										<?php echo esc_html( $reason_label ); ?>
									</p>
								<?php endif; ?>

								<?php
								$weather_icon_html = $weather_icon ? Weather::render_symbol_icon_html( $weather_icon, 48 ) : '';
								?>
								<?php if ( $weather_error || $weather_icon_html ) : ?>
									<div class="weather-summary" aria-label="<?php echo esc_attr__( 'Registered weather', 'apiary-press' ); ?>">
										<div class="weather-title"><?php echo esc_html__( 'Registered Weather', 'apiary-press' ); ?></div>

										<?php if ( $weather_error ) : ?>
											<div class="muted"><?php echo esc_html( $weather_error ); ?></div>
										<?php else : ?>
											<?php echo $weather_icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										<?php endif; ?>
									</div>
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
