<?php
/**
 * Index template for listing all apiaries in the Apiary Press app.
 *
 * @package ApiaryPress
 */

namespace ApiaryPress;

use ApiaryPress\App;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$appr_apiaries = get_posts(
	array(
		'post_type'        => Apiary::APIARY_POST_TYPE,
		'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
		'author'           => get_current_user_id(),
		'numberposts'      => -1,
		'orderby'          => 'date',
		'order'            => 'DESC',
		'suppress_filters' => false,
	)
);
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php wp_app_title( __( 'Apiaries', 'apiary-press' ) ); ?></title>
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
		.apiary-row,
		.empty-state {
			background: var(--wp-app-color-surface);
			border: 1px solid var(--wp-app-color-border);
			border-radius: 8px;
		}
		.apiary-list {
			display: grid;
			gap: 12px;
		}
		.apiary-row {
			display: grid;
			gap: 16px;
			grid-template-columns: 1fr auto;
			padding: 18px;
		}
		.apiary-row h3 {
			margin: 0 0 6px;
			font-size: 20px;
			letter-spacing: 0;
		}
		.apiary-row h3 a {
			color: var(--wp-app-color-text);
			text-decoration: none;
		}
		.apiary-row h3 a:hover { color: var(--wp-app-color-link); }
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
		.empty-state {
			padding: 24px;
			color: var(--wp-app-color-muted);
		}
		@media (max-width: 760px) {
			.shell { width: min(100% - 24px, 1120px); padding-top: 24px; }
			.topbar { align-items: flex-start; flex-direction: column; }
			.actions { justify-content: flex-start; }
			.apiary-row { grid-template-columns: 1fr; }
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
				<h1><?php echo esc_html__( 'Apiaries', 'apiary-press' ); ?></h1>
			</div>
			<div class="actions">
				<a class="admin-link admin-link-primary" href="<?php echo esc_url( App::get_url( 'apiary/new' ) ); ?>">
					<?php echo esc_html__( 'New Apiary', 'apiary-press' ); ?>
				</a>
				<a class="admin-link" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Apiary::APIARY_POST_TYPE ) ); ?>">
					<?php echo esc_html__( 'WordPress Admin', 'apiary-press' ); ?>
				</a>
			</div>
		</header>

		<?php if ( isset( $_GET['deleted'] ) ) : ?>
			<div class="notice"><?php echo esc_html__( 'Apiary removed.', 'apiary-press' ); ?></div>
		<?php endif; ?>

		<section aria-labelledby="apiary-list-heading">
			<h2 id="apiary-list-heading"><?php echo esc_html__( 'Saved Apiaries', 'apiary-press' ); ?></h2>

			<?php if ( empty( $appr_apiaries ) ) : ?>
				<div class="empty-state"><?php echo esc_html__( 'No apiaries yet.', 'apiary-press' ); ?></div>
			<?php else : ?>
				<div class="apiary-list">
					<?php foreach ( $appr_apiaries as $appr_apiary ) : ?>
						<?php
						$appr_hive_ids = get_posts(
							array(
								'post_type'        => Hive::HIVE_POST_TYPE,
								'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
								'post_parent'      => $appr_apiary->ID,
								'numberposts'      => -1,
								'fields'           => 'ids',
								'suppress_filters' => false,
							)
						);

						$appr_summary = wp_trim_words( wp_strip_all_tags( $appr_apiary->post_content ), 24 );
						?>
						<article class="apiary-row">
							<div>
								<h3>
									<a href="<?php echo esc_url( App::get_url( 'apiary/' . absint( $appr_apiary->ID ) ) ); ?>">
										<?php echo esc_html( get_the_title( $appr_apiary ) ); ?>
									</a>
								</h3>
								<div class="meta">
									<?php echo esc_html( sprintf( _n( '%d hive', '%d hives', count( $appr_hive_ids ), 'apiary-press' ), count( $appr_hive_ids ) ) ); ?>
								</div>
								<?php if ( $appr_summary ) : ?>
									<p class="summary"><?php echo esc_html( $appr_summary ); ?></p>
								<?php endif; ?>
							</div>
							<div class="stats">
								<a class="admin-link" href="<?php echo esc_url( App::get_url( 'apiary/' . absint( $appr_apiary->ID ) ) ); ?>">
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
