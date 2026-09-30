<?php
/**
 * Contact service.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages contact form submissions.
 */
class GCM_Contact_Service {

	/**
	 * Submit contact request.
	 *
	 * @param array $data Contact data.
	 * @return int|WP_Error
	 */
	public static function submit( $data ) {
		global $wpdb;

		$email = sanitize_email( $data['email'] ?? '' );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'gcm_invalid_email', __( 'Please provide a valid email address.', 'giga-class-market' ) );
		}

		$full_name = sanitize_text_field( $data['full_name'] ?? '' );
		$subject   = sanitize_text_field( $data['subject'] ?? '' );
		$message   = sanitize_textarea_field( $data['message'] ?? '' );

		if ( '' === $full_name || '' === $subject || '' === $message ) {
			return new WP_Error( 'gcm_missing_fields', __( 'Please fill in your name, service, and message.', 'giga-class-market' ) );
		}

		$table = $wpdb->prefix . 'gcm_contacts';
		if ( class_exists( 'GCM_Installer' ) && ! GCM_Installer::table_exists( $table ) ) {
			GCM_Installer::install();
		}

		$contact_id = GCM_Installer::insert_row(
			$table,
			array(
				'full_name'  => $full_name,
				'email'      => $email,
				'whatsapp'   => sanitize_text_field( $data['whatsapp'] ?? '' ),
				'subject'    => $subject,
				'message'    => $message,
				'status'     => 'new',
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			)
		);

		if ( ! $contact_id ) {
			return new WP_Error( 'gcm_contact_failed', __( 'Unable to submit your message. Please try again or WhatsApp us.', 'giga-class-market' ) );
		}

		$settings   = class_exists( 'GCM_Settings_Service' ) ? GCM_Settings_Service::get_settings() : array();
		$admin_mail = $settings['company']['email'] ?? get_option( 'admin_email' );

		if ( class_exists( 'GCM_Notification_Service' ) ) {
			GCM_Notification_Service::queue_email(
				0,
				'contact_received',
				__( 'New contact message received', 'giga-class-market' ),
				sprintf(
					/* translators: 1: name, 2: subject */
					__( 'A new contact message was submitted by %1$s about "%2$s".', 'giga-class-market' ),
					esc_html( $full_name ),
					esc_html( $subject )
				),
				is_email( $admin_mail ) ? $admin_mail : get_option( 'admin_email' ),
				array( 'contact_id' => $contact_id )
			);
		}

		return $contact_id;
	}

	/**
	 * Update contact status.
	 *
	 * @param int    $contact_id Contact ID.
	 * @param string $status Status.
	 * @return bool|WP_Error
	 */
	public static function update_status( $contact_id, $status ) {
		global $wpdb;

		$status = self::sanitize_status( $status );
		$data   = array(
			'status'     => $status,
			'updated_at' => current_time( 'mysql' ),
		);
		$format = array( '%s', '%s' );

		if ( in_array( $status, array( 'contacted', 'resolved' ), true ) ) {
			$data['contacted_at'] = current_time( 'mysql' );
			$format[]             = '%s';
		}

		$result = $wpdb->update(
			$wpdb->prefix . 'gcm_contacts',
			$data,
			array( 'id' => absint( $contact_id ) ),
			$format,
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'gcm_contact_update_failed', __( 'Unable to update contact status.', 'giga-class-market' ) );
		}

		GCM_Audit_Service::log( 'contact_status_updated', 'contact', $contact_id, array( 'status' => $status ) );
		return true;
	}

	/**
	 * List contact messages.
	 *
	 * @param string $status Status.
	 * @param int    $limit Limit.
	 * @param int    $offset Offset.
	 * @return array
	 */
	public static function get_contacts( $status = '', $limit = 50, $offset = 0 ) {
		global $wpdb;

		$limit  = min( 200, max( 1, absint( $limit ) ) );
		$offset = max( 0, absint( $offset ) );

		if ( $status ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}gcm_contacts WHERE status = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
					self::sanitize_status( $status ),
					$limit,
					$offset
				)
			);
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_contacts ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);
	}

	/**
	 * Sanitize contact status.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	private static function sanitize_status( $status ) {
		$status = sanitize_key( $status );
		return in_array( $status, array( 'new', 'in_progress', 'contacted', 'resolved' ), true ) ? $status : 'new';
	}
}
