<?php

use ApiaryPress\App;
use chillerlan\QRCode\QRCode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ap_app_url' ) ) {
	function ap_app_url( string $path = '' ): string {
		return trailingslashit( home_url( '/apiary-press/' . ltrim( $path, '/' ) ) );
	}
}

global $wp_app_route;

$route_params = isset( $wp_app_route['params'] ) && is_array( $wp_app_route['params'] ) ? $wp_app_route['params'] : [];
$hive_id	  = isset( $route_params['id'] ) ? absint( $route_params['id'] ) : absint( get_query_var( 'id' ) );
$hive		 = $hive_id ? get_post( $hive_id ) : null;
$not_found	= ! $hive || App::HIVE_POST_TYPE !== $hive->post_type;
$forbidden	= ! $not_found && ! current_user_can( 'edit_post', $hive_id );
$hive_url	 = '';
$hive_qr	  = '';

if ( $not_found ) {
	status_header( 404 );
} elseif ( $forbidden ) {
	status_header( 403 );
} else {
	$hive_url = ap_app_url( 'hive/' . $hive_id );
	$hive_qr  = ( new QRCode() )->render( $hive_url );
}
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php wp_app_title( $hive ? sprintf( __( '%s QR', 'apiary-press' ), get_the_title( $hive ) ) : __( 'Hive QR', 'apiary-press' ) ); ?></title>
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
		<?php if ( $not_found ) : ?>
			<section class="message">
				<h1><?php echo esc_html__( 'Hive Not Found', 'apiary-press' ); ?></h1>
				<p><?php echo esc_html__( 'The requested hive is not available.', 'apiary-press' ); ?></p>
				<p><a class="admin-link" href="<?php echo esc_url( ap_app_url() ); ?>"><?php echo esc_html__( 'Back to Hives', 'apiary-press' ); ?></a></p>
			</section>
		<?php elseif ( $forbidden ) : ?>
			<section class="message">
				<h1><?php echo esc_html__( 'Access Denied', 'apiary-press' ); ?></h1>
				<p><?php echo esc_html__( 'You do not have permission to view this hive QR.', 'apiary-press' ); ?></p>
				<p><a class="admin-link" href="<?php echo esc_url( ap_app_url() ); ?>"><?php echo esc_html__( 'Back to Hives', 'apiary-press' ); ?></a></p>
			</section>
		<?php else : ?>
			<section class="print-sheet" aria-label="<?php echo esc_attr( sprintf( __( 'QR code for %s', 'apiary-press' ), get_the_title( $hive ) ) ); ?>">
				<div class="qr-frame">
					<img src="<?php echo esc_attr( $hive_qr ); ?>" alt="<?php echo esc_attr( sprintf( __( 'QR code for %s', 'apiary-press' ), get_the_title( $hive ) ) ); ?>">
				</div>
			</section>
		<?php endif; ?>
	</main>

	<?php wp_app_body_close(); ?>
</body>
</html>
