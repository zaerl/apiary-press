<?php
/**
 * Plugin Name: Apiary Press
 * Description: Take care of your 🐝
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

// Autoloader for plugin classes, using the WordPress class-name.php file convention.
spl_autoload_register(
	function ( $class_name ) {
		$prefix = 'ApiaryPress\\';
		$len    = strlen( $prefix );

		if ( strncmp( $prefix, $class_name, $len ) !== 0 ) {
			return;
		}

		$relative   = str_replace( '\\', '/', substr( $class_name, $len ) );
		$segments   = explode( '/', $relative );
		$basename   = array_pop( $segments );
		$basename   = strtolower( preg_replace( '/(?<!^)([A-Z])/', '-$1', $basename ) );
		$segments[] = 'class-' . $basename . '.php';

		$file = __DIR__ . '/src/' . implode( '/', $segments );

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
