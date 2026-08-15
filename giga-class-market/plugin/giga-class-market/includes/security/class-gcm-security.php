<?php
/**
 * Security helpers.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared security helpers.
 */
class GCM_Security {

	/**
	 * Verify AJAX nonce.
	 *
	 * @param string $action Nonce action.
	 * @param string $field Request field.
	 * @return void
	 */
	public static function verify_ajax_nonce( $action = 'gcm_ajax_nonce', $field = 'nonce' ) {
		$nonce = isset( $_REQUEST[ $field ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $field ] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'giga-class-market' ) ), 403 );
		}
	}

	/**
	 * Require capability for AJAX.
	 *
	 * @param string $capability Capability.
	 * @return void
	 */
	public static function require_capability( $capability ) {
		if ( ! current_user_can( $capability ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'giga-class-market' ) ), 403 );
		}
	}

	/**
	 * Sanitize request array recursively.
	 *
	 * @param array $data Data.
	 * @return array
	 */
	public static function sanitize_array( $data ) {
		$clean = array();
		foreach ( (array) $data as $key => $value ) {
			$key = sanitize_key( $key );
			if ( is_array( $value ) ) {
				$clean[ $key ] = self::sanitize_array( $value );
			} else {
				$clean[ $key ] = sanitize_text_field( wp_unslash( $value ) );
			}
		}
		return $clean;
	}

	/**
	 * Validate uploaded file.
	 *
	 * @param array $file File array.
	 * @return true|WP_Error
	 */
	public static function validate_upload( $file ) {
		if ( empty( $file['name'] ) || empty( $file['tmp_name'] ) ) {
			return new WP_Error( 'gcm_no_file', __( 'No file was uploaded.', 'giga-class-market' ) );
		}

		if ( ! empty( $file['error'] ) ) {
			return new WP_Error( 'gcm_upload_error', __( 'The uploaded file could not be processed.', 'giga-class-market' ) );
		}

		$settings = GCM_Settings_Service::get_settings();
		$max_mb   = isset( $settings['security']['max_upload_mb'] ) ? absint( $settings['security']['max_upload_mb'] ) : 5;
		$max_size = $max_mb * MB_IN_BYTES;

		if ( ! empty( $file['size'] ) && $file['size'] > $max_size ) {
			return new WP_Error( 'gcm_file_too_large', sprintf( __( 'File size must be under %dMB.', 'giga-class-market' ), $max_mb ) );
		}

		$allowed_mimes = array(
			'jpg|jpeg' => 'image/jpeg',
			'png'      => 'image/png',
			'pdf'      => 'application/pdf',
		);
		$filetype = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $allowed_mimes );
		$ext      = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

		if ( empty( $filetype['type'] ) || ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'pdf' ), true ) ) {
			return new WP_Error( 'gcm_invalid_file_type', __( 'Only JPG, PNG, and PDF files are allowed.', 'giga-class-market' ) );
		}

		return true;
	}

	/**
	 * Apply a transient rate limit.
	 *
	 * @param string $key Unique key.
	 * @param int    $limit Allowed attempts.
	 * @param int    $window Window in seconds.
	 * @return bool
	 */
	public static function rate_limit( $key, $limit = 5, $window = MINUTE_IN_SECONDS ) {
		$key       = 'gcm_rate_' . md5( $key );
		$attempts  = (int) get_transient( $key );
		$attempts += 1;

		set_transient( $key, $attempts, absint( $window ) );

		return $attempts <= absint( $limit );
	}

	/**
	 * Get remote IP address.
	 *
	 * @return string
	 */
	public static function get_ip_address() {
		return ! empty( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}
}
