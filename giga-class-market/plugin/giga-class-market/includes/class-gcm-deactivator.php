<?php
/**
 * Plugin deactivation tasks.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles deactivation.
 */
class GCM_Deactivator {

	/**
	 * Deactivate plugin.
	 *
	 * @return void
	 */
	public static function deactivate() {
		if ( class_exists( 'GCM_Reminder_Service' ) ) {
			GCM_Reminder_Service::clear_hooks();
		}
		flush_rewrite_rules();
	}
}
