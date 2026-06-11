<?php
use ApiaryPress\App;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ap_app_url' ) ) {
	function ap_app_url( string $path = '' ): string {
		return trailingslashit( home_url( '/apiary-press/' . ltrim( $path, '/' ) ) );
	}
}

$hives = get_posts( [
	'post_type'		=> App::HIVE_POST_TYPE,
	'post_status'	  => [ 'publish', 'draft', 'pending', 'private' ],
	'author'		   => get_current_user_id(),
	'numberposts'	  => -1,
	'orderby'		  => 'date',
	'order'			=> 'DESC',
	'suppress_filters' => false,
] );
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php wp_app_title( __( 'Hives', 'apiary-press' ) ); ?></title>
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
			align-items: flex-end;
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
		.eyebrow {
			margin: 0 0 4px;
			color: var(--wp-app-color-muted);
			font-size: 13px;
			font-weight: 700;
			letter-spacing: 0;
			text-transform: uppercase;
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
			padding: 9px 12px;
			text-decoration: none;
			white-space: nowrap;
		}
		.admin-link-primary {
			background: #1e824c;
			border-color: #1e824c;
			color: #fff;
		}
		.notice {
			border-radius: 6px;
			margin-bottom: 18px;
			padding: 12px 14px;
		}
		.notice {
			background: rgba(30, 130, 76, 0.12);
			border: 1px solid rgba(30, 130, 76, 0.35);
		}
		.hive-row,
		.empty-state {
			background: var(--wp-app-color-surface);
			border: 1px solid var(--wp-app-color-border);
			border-radius: 8px;
		}
		.hive-list {
			display: grid;
			gap: 12px;
		}
		.hive-row {
			display: grid;
			gap: 16px;
			grid-template-columns: 1fr auto;
			padding: 18px;
		}
		.hive-row h3 {
			margin: 0 0 6px;
			font-size: 20px;
			letter-spacing: 0;
		}
		.hive-row h3 a {
			color: var(--wp-app-color-text);
			text-decoration: none;
		}
		.hive-row h3 a:hover { color: var(--wp-app-color-link); }
		.meta {
			color: var(--wp-app-color-muted);
			font-size: 13px;
			line-height: 1.5;
		}
		.summary {
			margin: 0;
			color: var(--wp-app-color-muted);
			line-height: 1.5;
		}
		.stats {
			display: flex;
			align-items: flex-end;
			flex-direction: column;
			gap: 8px;
			min-width: 128px;
			text-align: right;
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
			color: inherit;
		}
		.empty-state {
			padding: 24px;
			color: var(--wp-app-color-muted);
		}
		@media (max-width: 760px) {
			.shell { width: min(100% - 24px, 1120px); padding-top: 24px; }
			.topbar { align-items: flex-start; flex-direction: column; }
			.actions { justify-content: flex-start; }
			.hive-row { grid-template-columns: 1fr; }
			.stats { align-items: flex-start; text-align: left; }
		}
	</style>
</head>
<body>
	<?php wp_app_body_open(); ?>

	<main class="shell">
		<header class="topbar">
			<div>
				<p class="eyebrow"><?php echo esc_html__( 'Apiary Press', 'apiary-press' ); ?></p>
				<h1><?php echo esc_html__( 'Hives', 'apiary-press' ); ?></h1>
			</div>
			<div class="actions">
				<a class="admin-link admin-link-primary" href="<?php echo esc_url( ap_app_url( 'hive/new' ) ); ?>">
					<?php echo esc_html__( 'New Hive', 'apiary-press' ); ?>
				</a>
				<a class="admin-link" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . App::HIVE_POST_TYPE ) ); ?>">
					<?php echo esc_html__( 'WordPress Admin', 'apiary-press' ); ?>
				</a>
			</div>
		</header>

		<?php if ( isset( $_GET['deleted'] ) ) : ?>
			<div class="notice"><?php echo esc_html__( 'Hive removed.', 'apiary-press' ); ?></div>
		<?php endif; ?>

		<section aria-labelledby="hive-list-heading">
			<h2 id="hive-list-heading"><?php echo esc_html__( 'Saved Hives', 'apiary-press' ); ?></h2>

			<?php if ( empty( $hives ) ) : ?>
				<div class="empty-state"><?php echo esc_html__( 'No hives yet.', 'apiary-press' ); ?></div>
			<?php else : ?>
				<div class="hive-list">
					<?php foreach ( $hives as $hive ) : ?>
						<?php
						$visit_ids = get_posts( [
							'post_type'		=> App::HIVE_VISIT_POST_TYPE,
							'post_status'	  => [ 'publish', 'draft', 'pending', 'private' ],
							'post_parent'	  => $hive->ID,
							'numberposts'	  => -1,
							'fields'		   => 'ids',
							'suppress_filters' => false,
						] );

						$latest_visit = get_posts( [
							'post_type'		=> App::HIVE_VISIT_POST_TYPE,
							'post_status'	  => [ 'publish', 'draft', 'pending', 'private' ],
							'post_parent'	  => $hive->ID,
							'numberposts'	  => 1,
							'orderby'		  => 'date',
							'order'			=> 'DESC',
							'suppress_filters' => false,
						] );

						$latest_visit_id = ! empty( $latest_visit ) ? $latest_visit[0]->ID : 0;
						$check_soon	  = $latest_visit_id ? rest_sanitize_boolean( get_post_meta( $latest_visit_id, 'check_soon', true ) ) : false;
						$summary		 = wp_trim_words( wp_strip_all_tags( $hive->post_content ), 24 );
						?>
						<article class="hive-row">
							<div>
								<h3>
									<a href="<?php echo esc_url( ap_app_url( 'hive/' . absint( $hive->ID ) ) ); ?>">
										<?php echo esc_html( get_the_title( $hive ) ); ?>
									</a>
								</h3>
								<div class="meta">
									<?php echo esc_html( sprintf( _n( '%d visit', '%d visits', count( $visit_ids ), 'apiary-press' ), count( $visit_ids ) ) ); ?>
									<?php if ( $latest_visit_id ) : ?>
										<?php echo esc_html( ' / ' ); ?>
										<?php echo esc_html( sprintf( __( 'Last visit %s', 'apiary-press' ), mysql2date( get_option( 'date_format' ), $latest_visit[0]->post_date ) ) ); ?>
									<?php endif; ?>
								</div>
								<?php if ( $summary ) : ?>
									<p class="summary"><?php echo esc_html( $summary ); ?></p>
								<?php endif; ?>
							</div>
							<div class="stats">
								<?php if ( $check_soon ) : ?>
									<span class="badge badge-attention"><?php echo esc_html__( 'Check soon', 'apiary-press' ); ?></span>
								<?php endif; ?>
								<a class="admin-link" href="<?php echo esc_url( ap_app_url( 'hive/' . absint( $hive->ID ) ) ); ?>">
									<?php echo esc_html__( 'Open', 'apiary-press' ); ?>
								</a>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>
	</main>

	<?php wp_app_body_close(); ?>
</body>
</html>
