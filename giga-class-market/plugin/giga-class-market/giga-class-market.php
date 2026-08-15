<?php
/**
 * Plugin Name: Giga Class Market
 * Plugin URI:  https://gigaclassmarket.com/
 * Description: Core course marketplace, enrollment, payment verification, student dashboard, and administration plugin for Giga Class Market.
 * Version:     1.0.5
 * Author:      Giga Class Market
 * Text Domain: giga-class-market
 * Domain Path: /languages
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GCM_VERSION', '1.0.5' );
define( 'GCM_PLUGIN_FILE', __FILE__ );
define( 'GCM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GCM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GCM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'GCM_DB_VERSION', '1.0.0' );

/**
 * Autoload plugin classes.
 *
 * @param string $class Class name.
 * @return void
 */
function gcm_autoload( $class ) {
	if ( 0 !== strpos( $class, 'GCM_' ) ) {
		return;
	}

	$file_name = 'class-' . str_replace( '_', '-', strtolower( $class ) ) . '.php';
	$paths     = array(
		GCM_PLUGIN_DIR . 'includes/',
		GCM_PLUGIN_DIR . 'includes/database/',
		GCM_PLUGIN_DIR . 'includes/roles/',
		GCM_PLUGIN_DIR . 'includes/services/',
		GCM_PLUGIN_DIR . 'includes/security/',
		GCM_PLUGIN_DIR . 'includes/ajax/',
	);

	foreach ( $paths as $path ) {
		$file = $path . $file_name;
		if ( file_exists( $file ) ) {
			require_once $file;
			return;
		}
	}
}
spl_autoload_register( 'gcm_autoload' );

register_activation_hook( __FILE__, array( 'GCM_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'GCM_Deactivator', 'deactivate' ) );

/**
 * Boot the plugin.
 *
 * @return void
 */
function gcm_run() {
	$plugin = new GCM_Core();
	$plugin->run();
}
add_action( 'plugins_loaded', 'gcm_run' );
