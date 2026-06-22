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
		'post_status'      => array( 'publish', 'future', 'draft', 'pending', 'private' ),
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
	<title><?php wp_app_title( __( 'Hives & Apiaries', 'apiary-press' ) ); ?></title>
	<?php wp_app_head(); ?>
</head>
<body>
	<?php wp_app_body_open(); ?>

	<main class="shell">
		<header class="topbar">
			<div>
				<p class="eyebrow"><?php echo esc_html__( 'Apiary Press', 'apiary-press' ); ?></p>
				<h1><?php echo esc_html__( 'Hives & Apiaries', 'apiary-press' ); ?></h1>
			</div>
			<div class="actions">
				<a class="admin-link admin-link-primary" href="<?php echo esc_url( App::get_url( 'hive/new' ) ); ?>">
					<?php echo esc_html__( 'New Hive', 'apiary-press' ); ?>
				</a>
				<a class="admin-link admin-link-primary" href="<?php echo esc_url( App::get_url( 'apiary/new' ) ); ?>">
					<?php echo esc_html__( 'New Apiary', 'apiary-press' ); ?>
				</a>
				<a class="admin-link" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Apiary::APIARY_POST_TYPE ) ); ?>">
					<?php echo esc_html__( 'WordPress Admin', 'apiary-press' ); ?>
				</a>
			</div>
		</header>

		<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Post-redirect query flag used only for a notice. ?>
		<?php if ( isset( $_GET['deleted'] ) ) : ?>
			<div class="notice"><?php echo esc_html__( 'Apiary removed.', 'apiary-press' ); ?></div>
		<?php endif; ?>

		<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Post-redirect query flag used only for a notice. ?>
		<?php if ( isset( $_GET['hive_deleted'] ) ) : ?>
			<div class="notice"><?php echo esc_html__( 'Hive removed.', 'apiary-press' ); ?></div>
		<?php endif; ?>

		<section aria-labelledby="apiary-list-heading">
			<div class="section-header">
				<div>
					<h2 id="apiary-list-heading"><?php echo esc_html__( 'Saved Apiaries', 'apiary-press' ); ?></h2>
				</div>
			</div>

			<?php if ( empty( $appr_apiaries ) ) : ?>
				<div class="empty-state">
					<h3><?php echo esc_html__( 'No apiaries yet.', 'apiary-press' ); ?></h3>
					<a class="admin-link admin-link-primary" href="<?php echo esc_url( App::get_url( 'apiary/new' ) ); ?>">
						<?php echo esc_html__( 'New Apiary', 'apiary-press' ); ?>
					</a>
				</div>
			<?php else : ?>
				<div class="apiary-list">
					<?php foreach ( $appr_apiaries as $appr_apiary ) : ?>
						<?php
						$appr_hive_ids = get_posts(
							array(
								'post_type'        => Hive::HIVE_POST_TYPE,
								'post_status'      => array( 'publish', 'future', 'draft', 'pending', 'private' ),
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
									<?php echo esc_html( sprintf( /* translators: %d: number of hives */ _n( '%d hive', '%d hives', count( $appr_hive_ids ), 'apiary-press' ), count( $appr_hive_ids ) ) ); ?>
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
