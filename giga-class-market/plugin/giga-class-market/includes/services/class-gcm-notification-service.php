<?php
/**
 * Notification service.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queues and sends notifications.
 */
class GCM_Notification_Service {

	/**
	 * Queue and send an email.
	 *
	 * @param int    $user_id User ID.
	 * @param string $type Type.
	 * @param string $title Title.
	 * @param string $message Message.
	 * @param string $email Email override.
	 * @param array  $meta Meta data.
	 * @return int|false
	 */
	public static function queue_email( $user_id, $type, $title, $message, $email = '', $meta = array() ) {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( ! $email && $user_id ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				$email = $user->user_email;
			}
		}

		$table = $wpdb->prefix . 'gcm_notifications';
		$wpdb->insert(
			$table,
			array(
				'user_id'    => $user_id ? $user_id : null,
				'type'       => sanitize_key( $type ),
				'title'      => sanitize_text_field( $title ),
				'message'    => wp_kses_post( $message ),
				'channel'    => 'email',
				'status'     => 'queued',
				'meta'       => wp_json_encode( $meta ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		$notification_id = (int) $wpdb->insert_id;
		$status          = 'failed';

		if ( is_email( $email ) ) {
			$from_email = GCM_Settings_Service::get_from_email();
			$from_name  = GCM_Settings_Service::get_from_name();
			$headers    = array(
				'Content-Type: text/html; charset=UTF-8',
				sprintf( 'From: %s <%s>', $from_name, $from_email ),
			);

			$reply_to      = isset( $meta['reply_to'] ) ? sanitize_email( (string) $meta['reply_to'] ) : '';
			$reply_to_name = isset( $meta['reply_to_name'] ) ? sanitize_text_field( (string) $meta['reply_to_name'] ) : '';
			if ( is_email( $reply_to ) ) {
				$headers[] = sprintf(
					'Reply-To: %s <%s>',
					$reply_to_name ? $reply_to_name : $reply_to,
					$reply_to
				);
			} else {
				$headers[] = sprintf( 'Reply-To: %s <%s>', $from_name, $from_email );
			}

			$sent   = wp_mail( $email, $title, wpautop( $message ), $headers );
			$status = $sent ? 'sent' : 'failed';
		}

		if ( $notification_id ) {
			$wpdb->update(
				$table,
				array( 'status' => $status ),
				array( 'id' => $notification_id ),
				array( '%s' ),
				array( '%d' )
			);
		}

		return $notification_id ? $notification_id : false;
	}

	/**
	 * Filter WordPress default From email (wordpress@host).
	 *
	 * @param string $email Current from email.
	 * @return string
	 */
	public static function filter_mail_from( $email ) {
		$from = GCM_Settings_Service::get_from_email();
		return is_email( $from ) ? $from : $email;
	}

	/**
	 * Filter WordPress default From name.
	 *
	 * @param string $name Current from name.
	 * @return string
	 */
	public static function filter_mail_from_name( $name ) {
		$from_name = GCM_Settings_Service::get_from_name();
		return $from_name ? $from_name : $name;
	}

	/**
	 * Queue an internal WhatsApp notification record and return wa.me URL.
	 *
	 * @param int    $user_id User ID.
	 * @param string $type Type.
	 * @param string $title Title.
	 * @param string $message Message.
	 * @param string $recipient_number Recipient number.
	 * @param array  $meta Meta.
	 * @return string
	 */
	public static function queue_whatsapp( $user_id, $type, $title, $message, $recipient_number = '', $meta = array() ) {
		global $wpdb;

		$url    = self::build_whatsapp_url( $recipient_number, $message );
		$sender = GCM_Settings_Service::get_company_whatsapp();
		$table  = $wpdb->prefix . 'gcm_notifications';

		$wpdb->insert(
			$table,
			array(
				'user_id'    => $user_id ? absint( $user_id ) : null,
				'type'       => sanitize_key( $type ),
				'title'      => sanitize_text_field( $title ),
				'message'    => wp_kses_post( $message ),
				'channel'    => 'whatsapp',
				'status'     => $url ? 'ready' : 'failed',
				'meta'       => wp_json_encode(
					array_merge(
						$meta,
						array(
							'url'             => $url,
							'sender_whatsapp' => $sender,
							'to_whatsapp'     => $recipient_number,
						)
					)
				),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $url;
	}

	/**
	 * Build wa.me URL.
	 *
	 * @param string $recipient_number Recipient number, falls back to company WhatsApp from settings.
	 * @param string $message Message.
	 * @return string
	 */
	public static function build_whatsapp_url( $recipient_number = '', $message = '' ) {
		$number = $recipient_number ? $recipient_number : GCM_Settings_Service::get_company_whatsapp();
		$number = preg_replace( '/\D+/', '', (string) $number );

		if ( '' === $number ) {
			return '';
		}

		return 'https://wa.me/' . rawurlencode( $number ) . ( $message ? '?text=' . rawurlencode( wp_strip_all_tags( $message ) ) : '' );
	}
}
