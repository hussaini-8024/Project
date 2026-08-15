<?php
/**
 * Course messaging between teachers and enrolled students.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Direct course messages.
 */
class GCM_Message_Service {

	/**
	 * Send a message.
	 *
	 * @param array $data Message data.
	 * @return int|WP_Error
	 */
	public static function send( $data ) {
		global $wpdb;

		$course_id    = absint( $data['course_id'] ?? 0 );
		$sender_id    = absint( $data['sender_id'] ?? get_current_user_id() );
		$recipient_id = absint( $data['recipient_id'] ?? 0 );
		$message      = sanitize_textarea_field( $data['message'] ?? '' );

		if ( ! $course_id || ! get_post( $course_id ) ) {
			return new WP_Error( 'gcm_invalid_course', __( 'Invalid course.', 'giga-class-market' ) );
		}
		if ( '' === trim( $message ) ) {
			return new WP_Error( 'gcm_empty_message', __( 'Enter a message.', 'giga-class-market' ) );
		}
		if ( ! self::can_participate( $sender_id, $course_id ) ) {
			return new WP_Error( 'gcm_forbidden', __( 'You cannot message in this course.', 'giga-class-market' ) );
		}
		if ( $recipient_id && ! self::can_participate( $recipient_id, $course_id ) ) {
			return new WP_Error( 'gcm_invalid_recipient', __( 'Recipient is not part of this course.', 'giga-class-market' ) );
		}

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'gcm_messages',
			array(
				'course_id'    => $course_id,
				'sender_id'    => $sender_id,
				'recipient_id' => $recipient_id ? $recipient_id : null,
				'message'      => $message,
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'gcm_message_failed', __( 'Unable to send message.', 'giga-class-market' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Thread for a course (teacher sees all; student sees own + teacher replies).
	 *
	 * @param int $course_id Course ID.
	 * @param int $viewer_id Viewer user ID.
	 * @param int $with_user Optional peer filter (teacher ↔ student).
	 * @return array
	 */
	public static function get_thread( $course_id, $viewer_id, $with_user = 0 ) {
		global $wpdb;

		$course_id  = absint( $course_id );
		$viewer_id  = absint( $viewer_id );
		$with_user  = absint( $with_user );

		if ( ! self::can_participate( $viewer_id, $course_id ) ) {
			return array();
		}

		$is_teacher = GCM_Teacher_Service::teacher_can_manage_course( $viewer_id, $course_id )
			|| user_can( $viewer_id, 'manage_options' );

		if ( $is_teacher && $with_user ) {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_messages
				WHERE course_id = %d
				AND (
					(sender_id = %d AND (recipient_id = %d OR recipient_id IS NULL OR recipient_id = 0))
					OR (sender_id = %d AND (recipient_id = %d OR recipient_id IS NULL OR recipient_id = 0))
					OR (sender_id = %d AND recipient_id = %d)
					OR (sender_id = %d AND recipient_id = %d)
				)
				ORDER BY created_at ASC
				LIMIT 200",
				$course_id,
				$viewer_id,
				$with_user,
				$with_user,
				$viewer_id,
				$viewer_id,
				$with_user,
				$with_user,
				$viewer_id
			);
		} elseif ( $is_teacher ) {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_messages WHERE course_id = %d ORDER BY created_at ASC LIMIT 300",
				$course_id
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_messages
				WHERE course_id = %d
				AND (
					sender_id = %d
					OR recipient_id = %d
					OR recipient_id IS NULL
					OR recipient_id = 0
				)
				ORDER BY created_at ASC
				LIMIT 200",
				$course_id,
				$viewer_id,
				$viewer_id
			);
		}

		$rows = $wpdb->get_results( $sql );
		$list = array();
		foreach ( (array) $rows as $row ) {
			$sender = get_userdata( (int) $row->sender_id );
			$list[] = (object) array(
				'id'             => (int) $row->id,
				'course_id'      => (int) $row->course_id,
				'sender_id'      => (int) $row->sender_id,
				'recipient_id'   => (int) $row->recipient_id,
				'message'        => $row->message,
				'created_at'     => $row->created_at,
				'sender_name'    => $sender ? $sender->display_name : __( 'User', 'giga-class-market' ),
				'is_mine'        => (int) $row->sender_id === $viewer_id,
			);
		}
		return $list;
	}

	/**
	 * Whether user may message in a course.
	 *
	 * @param int $user_id User ID.
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	public static function can_participate( $user_id, $course_id ) {
		$user_id   = absint( $user_id );
		$course_id = absint( $course_id );

		if ( ! $user_id || ! $course_id ) {
			return false;
		}
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}
		if ( GCM_Teacher_Service::teacher_can_manage_course( $user_id, $course_id ) ) {
			return true;
		}
		return GCM_Enrollment_Service::has_access( $user_id, $course_id );
	}
}
