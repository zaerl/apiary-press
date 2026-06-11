<?php
/**
 * Plugin Name: Apiary Press
 * Description: A WordPress app powered by WpApp.
 * Version: 1.0.0
 * Author: Francesco Bigiarini
 * Text Domain: apiary-press
 * Requires PHP: 7.4
 *
 * @package ApiaryPress
 */

namespace ApiaryPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/vendor/autoload.php';

// Autoloader for plugin classes.
spl_autoload_register(
	function ( $class_name ) {
		$prefix = 'ApiaryPress\\';
		$len    = strlen( $prefix );

		if ( strncmp( $prefix, $class_name, $len ) !== 0 ) {
				return;
		}

		$file = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $class_name, $len ) ) . '.php';
		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

add_action(
	'plugins_loaded',
	function () {
		$app = new App();
		$app->init();
	}
);

register_activation_hook(
	__FILE__,
	function () {
		$app = new App();
		$app->activate();
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		flush_rewrite_rules();
	}
);
