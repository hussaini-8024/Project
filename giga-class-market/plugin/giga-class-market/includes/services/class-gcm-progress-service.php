<?php
/**
 * Progress service.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tracks lesson completion.
 */
class GCM_Progress_Service {

	/**
	 * Mark lesson progress.
	 *
	 * @param int  $user_id User ID.
	 * @param int  $lesson_id Lesson ID.
	 * @param bool $completed Completed flag.
	 * @param int  $last_position Last video position.
	 * @return bool
	 */
	public static function mark_complete( $user_id, $lesson_id, $completed = true, $last_position = 0 ) {
		global $wpdb;

		$user_id = absint( $user_id );
		$lesson  = GCM_Curriculum_Service::get_lesson( $lesson_id );

		if ( ! $user_id || ! $lesson || ! GCM_Enrollment_Service::has_access( $user_id, $lesson->course_id ) ) {
			return false;
		}

		$completed    = $completed ? 1 : 0;
		$completed_at = $completed ? current_time( 'mysql' ) : null;
		$table        = $wpdb->prefix . 'gcm_progress';
		$existing     = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE user_id = %d AND lesson_id = %d",
				$user_id,
				absint( $lesson_id )
			)
		);

		if ( $existing ) {
			$result = $wpdb->update(
				$table,
				array(
					'completed'     => $completed,
					'last_position' => absint( $last_position ),
					'completed_at'  => $completed_at,
					'updated_at'    => current_time( 'mysql' ),
				),
				array( 'id' => absint( $existing->id ) ),
				array( '%d', '%d', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$result = $wpdb->insert(
				$table,
				array(
					'user_id'       => $user_id,
					'course_id'     => absint( $lesson->course_id ),
					'lesson_id'     => absint( $lesson_id ),
					'completed'     => $completed,
					'last_position' => absint( $last_position ),
					'completed_at'  => $completed_at,
					'updated_at'    => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%d', '%d', '%d', '%s', '%s' )
			);
		}

		if ( false !== $result ) {
			self::maybe_complete_course( $user_id, $lesson->course_id );
		}

		return false !== $result;
	}

	/**
	 * Get course completion percentage.
	 *
	 * @param int $user_id User ID.
	 * @param int $course_id Course ID.
	 * @return int
	 */
	public static function get_percentage( $user_id, $course_id ) {
		global $wpdb;

		$user_id   = absint( $user_id );
		$course_id = absint( $course_id );
		$total     = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}gcm_lessons WHERE course_id = %d",
				$course_id
			)
		);

		if ( 0 === $total ) {
			return 0;
		}

		$completed = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}gcm_progress WHERE user_id = %d AND course_id = %d AND completed = 1",
				$user_id,
				$course_id
			)
		);

		return (int) floor( ( $completed / $total ) * 100 );
	}

	/**
	 * Get last touched lesson for a course.
	 *
	 * @param int $user_id User ID.
	 * @param int $course_id Course ID.
	 * @return object|null
	 */
	public static function get_last_lesson( $user_id, $course_id ) {
		global $wpdb;

		$progress = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_progress WHERE user_id = %d AND course_id = %d ORDER BY updated_at DESC LIMIT 1",
				absint( $user_id ),
				absint( $course_id )
			)
		);

		return $progress ? GCM_Curriculum_Service::get_lesson( $progress->lesson_id ) : null;
	}

	/**
	 * Complete course when every lesson is complete.
	 *
	 * @param int $user_id User ID.
	 * @param int $course_id Course ID.
	 * @return void
	 */
	private static function maybe_complete_course( $user_id, $course_id ) {
		if ( 100 === self::get_percentage( $user_id, $course_id ) ) {
			GCM_Enrollment_Service::update_status( $user_id, $course_id, 'completed' );
		}
	}
}
