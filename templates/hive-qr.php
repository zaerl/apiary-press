<?php
/**
 * Hive QR template for displaying QR codes for hives in the Apiary Press app.
 *
 * @package ApiaryPress
 */

namespace ApiaryPress;

use ApiaryPress\App;
use ApiaryPress\Visit;
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
$appr_hive_url     = '';
$appr_hive_qr      = '';

if ( $appr_not_found ) {
	status_header( 404 );
} elseif ( $appr_forbidden ) {
	status_header( 403 );
} else {
	$appr_hive_url = App::get_hive_url( $appr_hive_id, $appr_apiary_id );
	$appr_hive_qr  = ( new QRCode() )->render( $appr_hive_url );
}
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php wp_app_title( $appr_hive ? /* translators: %s: hive name */ sprintf( __( '%s QR', 'apiary-press' ), get_the_title( $appr_hive ) ) : __( 'Hive QR', 'apiary-press' ) ); ?></title>
	<?php wp_app_head(); ?>
	<style>
		#wpadminbar,
		.wp-app-masterbar { display: none !important; }
	</style>
</head>
<body>
	<?php wp_app_body_open(); ?>

	<main class="shell shell-print">
		<?php if ( $appr_not_found ) : ?>
			<section class="message">
				<h1><?php echo esc_html__( 'Hive Not Found', 'apiary-press' ); ?></h1>
				<p><?php echo esc_html__( 'The requested hive is not available.', 'apiary-press' ); ?></p>
				<p><a class="admin-link" href="<?php echo esc_url( App::get_url() ); ?>"><?php echo esc_html__( 'Back to Apiaries', 'apiary-press' ); ?></a></p>
			</section>
		<?php elseif ( $appr_forbidden ) : ?>
			<section class="message">
				<h1><?php echo esc_html__( 'Access Denied', 'apiary-press' ); ?></h1>
				<p><?php echo esc_html__( 'You do not have permission to view this hive QR.', 'apiary-press' ); ?></p>
				<p><a class="admin-link" href="<?php echo esc_url( App::get_url() ); ?>"><?php echo esc_html__( 'Back to Apiaries', 'apiary-press' ); ?></a></p>
			</section>
		<?php else : ?>
			<section class="print-sheet" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: hive name */ __( 'QR code for %s', 'apiary-press' ), get_the_title( $appr_hive ) ) ); ?>">
				<div class="qr-frame">
					<img src="<?php echo esc_attr( $appr_hive_qr ); ?>" alt="<?php echo esc_attr( sprintf( /* translators: %s: hive name */ __( 'QR code for %s', 'apiary-press' ), get_the_title( $appr_hive ) ) ); ?>">
				</div>
			</section>
		<?php endif; ?>
	</main>

	<?php wp_app_body_close(); ?>
</body>
</html>
