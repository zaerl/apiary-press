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
$appr_hive_id      = isset( $appr_route_params['id'] ) ? absint( $appr_route_params['id'] ) : absint( get_query_var( 'id' ) );
$appr_hive         = $appr_hive_id ? get_post( $appr_hive_id ) : null;
$appr_not_found    = ! $appr_hive || Hive::HIVE_POST_TYPE !== $appr_hive->post_type;
$appr_forbidden    = ! $appr_not_found && ! current_user_can( 'edit_post', $appr_hive_id );
$appr_hive_url     = '';
$appr_hive_qr      = '';

if ( $appr_not_found ) {
	status_header( 404 );
} elseif ( $appr_forbidden ) {
	status_header( 403 );
} else {
	$appr_hive_url = App::get_url( 'hive/' . $appr_hive_id );
	$appr_hive_qr  = ( new QRCode() )->render( $appr_hive_url );
}
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php wp_app_title( $appr_hive ? sprintf( __( '%s QR', 'apiary-press' ), get_the_title( $appr_hive ) ) : __( 'Hive QR', 'apiary-press' ) ); ?></title>
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
		#wpadminbar,
		.wp-app-masterbar {
			display: none !important;
		}
		a { color: var(--wp-app-color-link); }
		.shell {
			width: min(360px, calc(100% - 32px));
			margin: 0 auto;
			padding: 32px 0 56px;
		}
		h1 {
			margin: 0;
			font-size: 34px;
			line-height: 1.15;
			letter-spacing: 0;
		}
		.admin-link,
		.button {
			border: 1px solid var(--wp-app-color-border);
			border-radius: 6px;
			color: var(--wp-app-color-text);
			display: inline-flex;
			font: inherit;
			font-weight: 700;
			height: fit-content;
			line-height: 1.2;
			padding: 9px 12px;
			text-decoration: none;
			white-space: nowrap;
		}
		.message {
			background: var(--wp-app-color-surface);
			border: 1px solid var(--wp-app-color-border);
			border-radius: 8px;
		}
		.message {
			padding: 20px;
		}
		.print-sheet {
			align-items: center;
			display: flex;
			justify-content: center;
		}
		.qr-frame {
			background: #fff;
			padding: 0;
		}
		.qr-frame img {
			display: block;
			height: 320px;
			width: 320px;
		}
		@media (max-width: 760px) {
			.shell { width: min(100% - 24px, 360px); padding-top: 24px; }
			.qr-frame img {
				height: min(320px, calc(100vw - 24px));
				width: min(320px, calc(100vw - 24px));
			}
		}
		@media print {
			@page { margin: 12mm; }
			:root { color-scheme: light; }
			body {
				background: #fff;
				color: #111;
			}
			.no-print,
			#wpadminbar,
			.wp-app-masterbar {
				display: none !important;
			}
			.shell {
				margin: 0;
				padding: 0;
				width: auto;
			}
			.print-sheet {
				break-inside: avoid;
			}
			.qr-frame {
				border: 0;
				border-radius: 0;
				padding: 0;
			}
			.qr-frame img {
				height: 58mm;
				width: 58mm;
			}
		}
	</style>
</head>
<body>
	<?php wp_app_body_open(); ?>

	<main class="shell">
		<?php if ( $appr_not_found ) : ?>
			<section class="message">
				<h1><?php echo esc_html__( 'Hive Not Found', 'apiary-press' ); ?></h1>
				<p><?php echo esc_html__( 'The requested hive is not available.', 'apiary-press' ); ?></p>
				<p><a class="admin-link" href="<?php echo esc_url( App::get_url() ); ?>"><?php echo esc_html__( 'Back to Hives', 'apiary-press' ); ?></a></p>
			</section>
		<?php elseif ( $appr_forbidden ) : ?>
			<section class="message">
				<h1><?php echo esc_html__( 'Access Denied', 'apiary-press' ); ?></h1>
				<p><?php echo esc_html__( 'You do not have permission to view this hive QR.', 'apiary-press' ); ?></p>
				<p><a class="admin-link" href="<?php echo esc_url( App::get_url() ); ?>"><?php echo esc_html__( 'Back to Hives', 'apiary-press' ); ?></a></p>
			</section>
		<?php else : ?>
			<section class="print-sheet" aria-label="<?php echo esc_attr( sprintf( __( 'QR code for %s', 'apiary-press' ), get_the_title( $appr_hive ) ) ); ?>">
				<div class="qr-frame">
					<img src="<?php echo esc_attr( $appr_hive_qr ); ?>" alt="<?php echo esc_attr( sprintf( __( 'QR code for %s', 'apiary-press' ), get_the_title( $appr_hive ) ) ); ?>">
				</div>
			</section>
		<?php endif; ?>
	</main>

	<?php wp_app_body_close(); ?>
</body>
</html>
