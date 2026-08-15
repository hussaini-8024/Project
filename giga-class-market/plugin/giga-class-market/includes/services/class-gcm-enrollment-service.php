<?php
/**
 * Enrollment service.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages course enrollments.
 */
class GCM_Enrollment_Service {

	/**
	 * Enroll a user in a course.
	 *
	 * @param int    $user_id User ID.
	 * @param int    $course_id Course ID.
	 * @param int    $payment_id Payment ID.
	 * @param string $status Enrollment status.
	 * @return int|false
	 */
	public static function enroll( $user_id, $course_id, $payment_id = 0, $status = 'active' ) {
		global $wpdb;

		$user_id   = absint( $user_id );
		$course_id = absint( $course_id );
		$status    = self::sanitize_status( $status );

		if ( ! $user_id || ! $course_id || ! get_post( $course_id ) ) {
			return false;
		}

		$existing = self::get_enrollment( $user_id, $course_id );
		if ( $existing ) {
			$wpdb->update(
				$wpdb->prefix . 'gcm_enrollments',
				array(
					'payment_id' => $payment_id ? absint( $payment_id ) : $existing->payment_id,
					'status'     => $status,
				),
				array( 'id' => absint( $existing->id ) ),
				array( '%d', '%s' ),
				array( '%d' )
			);
			return (int) $existing->id;
		}

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'gcm_enrollments',
			array(
				'user_id'      => $user_id,
				'course_id'    => $course_id,
				'payment_id'   => $payment_id ? absint( $payment_id ) : null,
				'status'       => $status,
				'enrolled_at'  => current_time( 'mysql' ),
				'completed_at' => null,
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Get enrollment.
	 *
	 * @param int $user_id User ID.
	 * @param int $course_id Course ID.
	 * @return object|null
	 */
	public static function get_enrollment( $user_id, $course_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_enrollments WHERE user_id = %d AND course_id = %d",
				absint( $user_id ),
				absint( $course_id )
			)
		);
	}

	/**
	 * Check active access.
	 *
	 * @param int $user_id User ID.
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	public static function has_access( $user_id, $course_id ) {
		$enrollment = self::get_enrollment( $user_id, $course_id );
		return $enrollment && in_array( $enrollment->status, array( 'active', 'completed' ), true );
	}

	/**
	 * Theme-compatible access alias.
	 *
	 * @param int $user_id User ID.
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	public static function user_has_access( $user_id, $course_id ) {
		return self::has_access( $user_id, $course_id );
	}

	/**
	 * Theme-compatible enrollment alias.
	 *
	 * @param int $user_id User ID.
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	public static function is_enrolled( $user_id, $course_id ) {
		return (bool) self::get_enrollment( $user_id, $course_id );
	}

	/**
	 * Get student courses.
	 *
	 * @param int $user_id User ID.
	 * @param string $status Optional status.
	 * @return array
	 */
	public static function get_student_courses( $user_id, $status = '' ) {
		global $wpdb;

		$params = array( absint( $user_id ) );
		$where  = 'e.user_id = %d';

		if ( $status ) {
			$where   .= ' AND e.status = %s';
			$params[] = self::sanitize_status( $status );
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT e.* FROM {$wpdb->prefix}gcm_enrollments e WHERE {$where} ORDER BY e.enrolled_at DESC",
				$params
			)
		);

		$courses = array();
		foreach ( $rows as $row ) {
			$course = GCM_Course_Service::get( $row->course_id );
			if ( ! $course ) {
				continue;
			}
			$course['enrollment'] = $row;
			$course['progress']   = GCM_Progress_Service::get_percentage( $user_id, $row->course_id );
			$courses[]            = $course;
		}

		return $courses;
	}

	/**
	 * Update enrollment status.
	 *
	 * @param int    $user_id User ID.
	 * @param int    $course_id Course ID.
	 * @param string $status Status.
	 * @return bool
	 */
	public static function update_status( $user_id, $course_id, $status ) {
		global $wpdb;

		$status = self::sanitize_status( $status );
		$data   = array( 'status' => $status );
		$format = array( '%s' );

		if ( 'completed' === $status ) {
			$data['completed_at'] = current_time( 'mysql' );
			$format[]            = '%s';
		}

		return false !== $wpdb->update(
			$wpdb->prefix . 'gcm_enrollments',
			$data,
			array(
				'user_id'   => absint( $user_id ),
				'course_id' => absint( $course_id ),
			),
			$format,
			array( '%d', '%d' )
		);
	}

	/**
	 * Sanitize enrollment status.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	private static function sanitize_status( $status ) {
		$status = sanitize_key( $status );
		return in_array( $status, array( 'active', 'frozen', 'completed' ), true ) ? $status : 'active';
	}
}
