<?php
/**
 * Course announcements.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Teacher announcements for enrolled students.
 */
class GCM_Announcement_Service {

	/**
	 * Add an announcement.
	 *
	 * @param array $data Announcement data.
	 * @return int|WP_Error Announcement ID.
	 */
	public static function add( $data ) {
		global $wpdb;

		$course_id  = absint( $data['course_id'] ?? 0 );
		$teacher_id = absint( $data['teacher_id'] ?? get_current_user_id() );
		$title      = sanitize_text_field( $data['title'] ?? '' );
		$body       = wp_kses_post( $data['body'] ?? '' );

		if ( ! $course_id || ! get_post( $course_id ) ) {
			return new WP_Error( 'gcm_invalid_course', __( 'Invalid course.', 'giga-class-market' ) );
		}
		if ( ! GCM_Teacher_Service::teacher_can_manage_course( $teacher_id, $course_id ) ) {
			return new WP_Error( 'gcm_forbidden', __( 'You are not assigned to this course.', 'giga-class-market' ) );
		}
		if ( '' === $title ) {
			return new WP_Error( 'gcm_missing_title', __( 'Enter an announcement title.', 'giga-class-market' ) );
		}
		if ( '' === trim( wp_strip_all_tags( $body ) ) ) {
			return new WP_Error( 'gcm_missing_body', __( 'Enter announcement content.', 'giga-class-market' ) );
		}

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'gcm_announcements',
			array(
				'course_id'  => $course_id,
				'teacher_id' => $teacher_id,
				'title'      => $title,
				'body'       => $body,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'gcm_announcement_failed', __( 'Unable to save announcement.', 'giga-class-market' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete an announcement.
	 *
	 * @param int $id Announcement ID.
	 * @param int $teacher_id Actor teacher/admin ID.
	 * @return true|WP_Error
	 */
	public static function delete( $id, $teacher_id = 0 ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_announcements WHERE id = %d LIMIT 1",
				absint( $id )
			)
		);
		if ( ! $row ) {
			return new WP_Error( 'gcm_invalid_announcement', __( 'Announcement not found.', 'giga-class-market' ) );
		}

		$teacher_id = $teacher_id ? absint( $teacher_id ) : get_current_user_id();
		if ( ! GCM_Teacher_Service::teacher_can_manage_course( $teacher_id, $row->course_id ) ) {
			return new WP_Error( 'gcm_forbidden', __( 'You cannot delete this announcement.', 'giga-class-market' ) );
		}

		$wpdb->delete( $wpdb->prefix . 'gcm_announcements', array( 'id' => (int) $row->id ), array( '%d' ) );
		return true;
	}

	/**
	 * Announcements for a course.
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	public static function get_for_course( $course_id ) {
		global $wpdb;

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_announcements WHERE course_id = %d ORDER BY created_at DESC",
				absint( $course_id )
			)
		);
	}
}
