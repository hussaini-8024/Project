<?php
/**
 * Class recordings / replay links.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Teacher-uploaded class recordings for enrolled students.
 */
class GCM_Recording_Service {

	/**
	 * Add a recording.
	 *
	 * @param array $data Recording data.
	 * @return int|WP_Error Recording ID.
	 */
	public static function add( $data ) {
		global $wpdb;

		$course_id  = absint( $data['course_id'] ?? 0 );
		$class_id   = ! empty( $data['class_id'] ) ? absint( $data['class_id'] ) : null;
		$teacher_id = absint( $data['teacher_id'] ?? get_current_user_id() );
		$title      = sanitize_text_field( $data['title'] ?? '' );
		$video_url  = esc_url_raw( $data['video_url'] ?? '' );

		if ( ! $course_id || ! get_post( $course_id ) ) {
			return new WP_Error( 'gcm_invalid_course', __( 'Invalid course.', 'giga-class-market' ) );
		}
		if ( ! GCM_Teacher_Service::teacher_can_manage_course( $teacher_id, $course_id ) ) {
			return new WP_Error( 'gcm_forbidden', __( 'You are not assigned to this course.', 'giga-class-market' ) );
		}
		if ( '' === $title ) {
			return new WP_Error( 'gcm_missing_title', __( 'Enter a recording title.', 'giga-class-market' ) );
		}
		if ( '' === $video_url ) {
			return new WP_Error( 'gcm_missing_url', __( 'Enter a video URL.', 'giga-class-market' ) );
		}

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'gcm_recordings',
			array(
				'course_id'  => $course_id,
				'class_id'   => $class_id,
				'teacher_id' => $teacher_id,
				'title'      => $title,
				'video_url'  => $video_url,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'gcm_recording_failed', __( 'Unable to save recording.', 'giga-class-market' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete a recording.
	 *
	 * @param int $id Recording ID.
	 * @param int $teacher_id Actor teacher/admin ID.
	 * @return true|WP_Error
	 */
	public static function delete( $id, $teacher_id = 0 ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_recordings WHERE id = %d LIMIT 1",
				absint( $id )
			)
		);
		if ( ! $row ) {
			return new WP_Error( 'gcm_invalid_recording', __( 'Recording not found.', 'giga-class-market' ) );
		}

		$teacher_id = $teacher_id ? absint( $teacher_id ) : get_current_user_id();
		if ( ! GCM_Teacher_Service::teacher_can_manage_course( $teacher_id, $row->course_id ) ) {
			return new WP_Error( 'gcm_forbidden', __( 'You cannot delete this recording.', 'giga-class-market' ) );
		}

		$wpdb->delete( $wpdb->prefix . 'gcm_recordings', array( 'id' => (int) $row->id ), array( '%d' ) );
		return true;
	}

	/**
	 * Recordings for a course.
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	public static function get_for_course( $course_id ) {
		global $wpdb;

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_recordings WHERE course_id = %d ORDER BY created_at DESC",
				absint( $course_id )
			)
		);
	}
}
