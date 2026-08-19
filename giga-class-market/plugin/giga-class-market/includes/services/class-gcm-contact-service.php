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

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'gcm_contacts',
			array(
				'full_name'    => sanitize_text_field( $data['full_name'] ?? '' ),
				'email'        => $email,
				'whatsapp'     => sanitize_text_field( $data['whatsapp'] ?? '' ),
				'subject'      => sanitize_text_field( $data['subject'] ?? '' ),
				'message'      => sanitize_textarea_field( $data['message'] ?? '' ),
				'status'       => 'new',
				'created_at'   => current_time( 'mysql' ),
				'contacted_at' => null,
				'updated_at'   => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'gcm_contact_failed', __( 'Unable to submit your message.', 'giga-class-market' ) );
		}

		$contact_id = (int) $wpdb->insert_id;
		$admin_mail = class_exists( 'GCM_Settings_Service' )
			? GCM_Settings_Service::get_inbox_email()
			: 'info@gigaclassmarket.com';

		$visitor_name    = sanitize_text_field( $data['full_name'] ?? '' );
		$visitor_subject = sanitize_text_field( $data['subject'] ?? '' );
		$visitor_whatsapp = sanitize_text_field( $data['whatsapp'] ?? '' );
		$visitor_message = sanitize_textarea_field( $data['message'] ?? '' );

		$body = sprintf(
			/* translators: 1: name, 2: email, 3: whatsapp, 4: subject, 5: message */
			__( "A new contact message was submitted.\n\nName: %1\$s\nEmail: %2\$s\nWhatsApp: %3\$s\nSubject: %4\$s\n\nMessage:\n%5\$s", 'giga-class-market' ),
			$visitor_name,
			$email,
			$visitor_whatsapp,
			$visitor_subject,
			$visitor_message
		);

		GCM_Notification_Service::queue_email(
			0,
			'contact_received',
			sprintf(
				/* translators: %s: subject */
				__( 'New contact message: %s', 'giga-class-market' ),
				$visitor_subject ? $visitor_subject : __( 'Website inquiry', 'giga-class-market' )
			),
			$body,
			$admin_mail,
			array(
				'contact_id'    => $contact_id,
				'reply_to'      => $email,
				'reply_to_name' => $visitor_name,
			)
		);

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
