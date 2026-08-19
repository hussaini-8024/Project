<?php
/**
 * Audit service.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes administrative audit events.
 */
class GCM_Audit_Service {

	/**
	 * Log an action.
	 *
	 * @param string $action Action name.
	 * @param string $object_type Object type.
	 * @param int    $object_id Object ID.
	 * @param array  $meta Meta data.
	 * @param int    $admin_id Admin user ID.
	 * @return int|false
	 */
	public static function log( $action, $object_type, $object_id = 0, $meta = array(), $admin_id = 0 ) {
		global $wpdb;

		$admin_id = $admin_id ? absint( $admin_id ) : get_current_user_id();
		$table    = $wpdb->prefix . 'gcm_audit_log';

		$inserted = $wpdb->insert(
			$table,
			array(
				'admin_id'    => $admin_id,
				'action'      => sanitize_key( $action ),
				'object_type' => sanitize_key( $object_type ),
				'object_id'   => $object_id ? absint( $object_id ) : null,
				'meta'        => wp_json_encode( $meta ),
				'ip_address'  => self::get_ip_address(),
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Get audit rows.
	 *
	 * @param int $limit Limit.
	 * @param int $offset Offset.
	 * @return array
	 */
	public static function get_logs( $limit = 50, $offset = 0 ) {
		global $wpdb;

		$table  = $wpdb->prefix . 'gcm_audit_log';
		$limit  = min( 200, max( 1, absint( $limit ) ) );
		$offset = max( 0, absint( $offset ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);
	}

	/**
	 * Get request IP.
	 *
	 * @return string
	 */
	private static function get_ip_address() {
		$ip = '';
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		return substr( $ip, 0, 45 );
	}
}
