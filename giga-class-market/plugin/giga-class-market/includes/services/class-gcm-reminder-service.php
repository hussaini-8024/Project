<?php
/**
 * Upcoming live-class email reminders.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cron-driven reminders for classes starting soon.
 */
class GCM_Reminder_Service {

	const CRON_HOOK = 'gcm_send_class_reminders';
	const CRON_INTERVAL = 'hourly';

	/**
	 * Register cron hook and schedule if missing.
	 *
	 * @return void
	 */
	public static function schedule_hooks() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'send_upcoming_class_reminders' ) );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, self::CRON_INTERVAL, self::CRON_HOOK );
		}
	}

	/**
	 * Clear scheduled cron (e.g. on deactivation).
	 *
	 * @return void
	 */
	public static function clear_hooks() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
			$timestamp = wp_next_scheduled( self::CRON_HOOK );
		}
	}

	/**
	 * Email enrolled students about classes starting within the next 2 hours.
	 * Already-reminded classes are detected via gcm_notifications type class_reminder + class_id meta.
	 *
	 * @return int Number of classes reminded.
	 */
	public static function send_upcoming_class_reminders() {
		global $wpdb;

		$now   = current_time( 'mysql' );
		$until = wp_date( 'Y-m-d H:i:s', strtotime( $now ) + ( 2 * HOUR_IN_SECONDS ) );

		$classes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_classes
				WHERE status = 'scheduled'
				AND scheduled_at >= %s
				AND scheduled_at <= %s
				ORDER BY scheduled_at ASC",
				$now,
				$until
			)
		);

		$reminded = 0;

		foreach ( (array) $classes as $class ) {
			if ( self::already_reminded( (int) $class->id ) ) {
				continue;
			}

			$students = GCM_Enrollment_Service::get_course_students( (int) $class->course_id );
			if ( empty( $students ) ) {
				self::mark_reminded( $class, 0 );
				$reminded++;
				continue;
			}

			$course_title = get_the_title( (int) $class->course_id );
			$when         = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $class->scheduled_at );
			$subject      = sprintf(
				/* translators: 1: class title, 2: course title */
				__( 'Reminder: “%1$s” starts soon — %2$s', 'giga-class-market' ),
				$class->title,
				$course_title
			);

			$body  = '<p>' . esc_html__( 'Your live class is starting within the next 2 hours.', 'giga-class-market' ) . '</p>';
			$body .= '<p><strong>' . esc_html( $class->title ) . '</strong><br />';
			$body .= esc_html( $course_title ) . '<br />';
			$body .= esc_html( $when ) . '</p>';
			$body .= '<p>' . esc_html__( 'Open your student dashboard to join when the class goes live.', 'giga-class-market' ) . '</p>';

			$from_email = GCM_Settings_Service::get_from_email();
			$from_name  = GCM_Settings_Service::get_from_name();
			$headers    = array(
				'Content-Type: text/html; charset=UTF-8',
				sprintf( 'From: %s <%s>', $from_name, $from_email ),
				sprintf( 'Reply-To: %s <%s>', $from_name, $from_email ),
			);

			foreach ( $students as $student ) {
				$email = isset( $student->user_email ) ? $student->user_email : '';
				if ( ! is_email( $email ) ) {
					continue;
				}

				$sent = wp_mail( $email, $subject, $body, $headers );

				$wpdb->insert(
					$wpdb->prefix . 'gcm_notifications',
					array(
						'user_id'    => (int) $student->user_id,
						'type'       => 'class_reminder',
						'title'      => sanitize_text_field( $subject ),
						'message'    => wp_kses_post( $body ),
						'channel'    => 'email',
						'status'     => $sent ? 'sent' : 'failed',
						'meta'       => wp_json_encode(
							array(
								'class_id'  => (int) $class->id,
								'course_id' => (int) $class->course_id,
							)
						),
						'created_at' => current_time( 'mysql' ),
					),
					array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
				);
			}

			self::mark_reminded( $class, count( $students ) );
			$reminded++;
		}

		return $reminded;
	}

	/**
	 * Whether a class_reminder notification already exists for this class.
	 *
	 * @param int $class_id Class ID.
	 * @return bool
	 */
	private static function already_reminded( $class_id ) {
		global $wpdb;

		$class_id = absint( $class_id );
		$rows     = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta FROM {$wpdb->prefix}gcm_notifications
				WHERE type = %s
				ORDER BY id DESC
				LIMIT 500",
				'class_reminder'
			)
		);

		foreach ( (array) $rows as $row ) {
			$meta = json_decode( (string) $row->meta, true );
			if ( is_array( $meta ) && isset( $meta['class_id'] ) && (int) $meta['class_id'] === $class_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Insert a marker notification so the class is not reminded twice
	 * even when there are no enrolled students.
	 *
	 * @param object $class Class row.
	 * @param int    $student_count Students emailed.
	 * @return void
	 */
	private static function mark_reminded( $class, $student_count = 0 ) {
		global $wpdb;

		if ( self::already_reminded( (int) $class->id ) ) {
			return;
		}

		$wpdb->insert(
			$wpdb->prefix . 'gcm_notifications',
			array(
				'user_id'    => null,
				'type'       => 'class_reminder',
				'title'      => sprintf(
					/* translators: %s: class title */
					__( 'Class reminder sent: %s', 'giga-class-market' ),
					$class->title
				),
				'message'    => sprintf(
					/* translators: %d: student count */
					__( 'Reminder processed for %d enrolled student(s).', 'giga-class-market' ),
					absint( $student_count )
				),
				'channel'    => 'system',
				'status'     => 'sent',
				'meta'       => wp_json_encode(
					array(
						'class_id'  => (int) $class->id,
						'course_id' => (int) $class->course_id,
						'marker'    => 1,
					)
				),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}
}
