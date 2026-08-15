<?php
/**
 * Course-wide chat room (everyone sees all messages).
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared course chat.
 */
class GCM_Message_Service {

	/**
	 * Post to the course chat room.
	 *
	 * @param array $data Message data.
	 * @return int|WP_Error
	 */
	public static function send( $data ) {
		global $wpdb;

		$course_id = absint( $data['course_id'] ?? 0 );
		$sender_id = absint( $data['sender_id'] ?? get_current_user_id() );
		$message   = sanitize_textarea_field( $data['message'] ?? '' );

		if ( ! $course_id || ! get_post( $course_id ) ) {
			return new WP_Error( 'gcm_invalid_course', __( 'Invalid course.', 'giga-class-market' ) );
		}
		if ( '' === trim( $message ) ) {
			return new WP_Error( 'gcm_empty_message', __( 'Enter a message.', 'giga-class-market' ) );
		}
		if ( ! self::can_participate( $sender_id, $course_id ) ) {
			return new WP_Error( 'gcm_forbidden', __( 'You cannot post in this course chat.', 'giga-class-market' ) );
		}

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'gcm_messages',
			array(
				'course_id'    => $course_id,
				'sender_id'    => $sender_id,
				'recipient_id' => null, // Course room — visible to everyone in the course.
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
	 * Course chat room messages (all participants see all).
	 *
	 * @param int $course_id Course ID.
	 * @param int $viewer_id Viewer user ID.
	 * @param int $with_user Ignored (kept for call-site compatibility).
	 * @return array
	 */
	public static function get_thread( $course_id, $viewer_id, $with_user = 0 ) {
		global $wpdb;

		unset( $with_user );
		$course_id = absint( $course_id );
		$viewer_id = absint( $viewer_id );

		if ( ! self::can_participate( $viewer_id, $course_id ) ) {
			return array();
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_messages WHERE course_id = %d ORDER BY created_at ASC LIMIT 400",
				$course_id
			)
		);

		$list = array();
		foreach ( (array) $rows as $row ) {
			$sender     = get_userdata( (int) $row->sender_id );
			$roles      = $sender ? (array) $sender->roles : array();
			$is_teacher = in_array( 'gcm_teacher', $roles, true );
			$is_admin   = user_can( (int) $row->sender_id, 'manage_options' );
			$list[]      = (object) array(
				'id'           => (int) $row->id,
				'course_id'    => (int) $row->course_id,
				'sender_id'    => (int) $row->sender_id,
				'recipient_id' => (int) $row->recipient_id,
				'message'      => $row->message,
				'created_at'   => $row->created_at,
				'sender_name'  => $sender ? $sender->display_name : __( 'User', 'giga-class-market' ),
				'sender_role'  => $is_admin ? 'admin' : ( $is_teacher ? 'teacher' : 'student' ),
				'is_mine'      => (int) $row->sender_id === $viewer_id,
			);
		}
		return $list;
	}

	/**
	 * Whether user may use the course chat.
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
