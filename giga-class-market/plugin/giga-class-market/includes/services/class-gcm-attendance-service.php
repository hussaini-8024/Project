<?php
/**
 * Live class attendance.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tracks when enrolled students join a live class.
 */
class GCM_Attendance_Service {

	/**
	 * Record a student joining a live class (idempotent).
	 *
	 * @param int $class_id Class ID.
	 * @param int $user_id User ID.
	 * @return object|WP_Error Attendance row or class with join URL.
	 */
	public static function record_join( $class_id, $user_id = 0 ) {
		global $wpdb;

		$class_id = absint( $class_id );
		$user_id  = $user_id ? absint( $user_id ) : get_current_user_id();
		$class    = GCM_Class_Service::get( $class_id );

		if ( ! $class || 'live' !== $class->status ) {
			return new WP_Error( 'gcm_not_live', __( 'This class is not live yet.', 'giga-class-market' ) );
		}

		// Repair broken legacy /live-class/ placeholder URLs.
		$class = GCM_Class_Service::ensure_meeting_links( $class_id );
		if ( is_wp_error( $class ) ) {
			return $class;
		}
		if ( 'live' !== $class->status ) {
			return new WP_Error( 'gcm_not_live', __( 'This class is not live yet.', 'giga-class-market' ) );
		}

		if ( empty( $class->zoom_join_url ) ) {
			return new WP_Error( 'gcm_not_live', __( 'This class is not live yet.', 'giga-class-market' ) );
		}
		if ( ! $user_id ) {
			return new WP_Error( 'gcm_login_required', __( 'Please log in to join the class.', 'giga-class-market' ) );
		}

		$can = user_can( $user_id, 'manage_options' )
			|| GCM_Teacher_Service::teacher_can_manage_course( $user_id, $class->course_id )
			|| GCM_Enrollment_Service::has_access( $user_id, $class->course_id );

		if ( ! $can ) {
			return new WP_Error( 'gcm_forbidden', __( 'You are not enrolled in this course.', 'giga-class-market' ) );
		}

		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_attendance WHERE class_id = %d AND user_id = %d",
				$class_id,
				$user_id
			)
		);

		$joined_at = $existing ? $existing->joined_at : current_time( 'mysql' );

		if ( ! $existing ) {
			$wpdb->insert(
				$wpdb->prefix . 'gcm_attendance',
				array(
					'class_id'  => $class_id,
					'course_id' => (int) $class->course_id,
					'user_id'   => $user_id,
					'joined_at' => $joined_at,
				),
				array( '%d', '%d', '%d', '%s' )
			);
		}

		return (object) array(
			'class_id'   => $class_id,
			'join_url'   => $class->zoom_join_url,
			'start_url'  => ! empty( $class->zoom_start_url ) ? $class->zoom_start_url : $class->zoom_join_url,
			'joined_at'  => $joined_at,
			'recorded'   => true,
		);
	}

	/**
	 * Exact join timestamp for a user in a class (or null).
	 *
	 * @param int $class_id Class ID.
	 * @param int $user_id User ID.
	 * @return string|null MySQL datetime.
	 */
	public static function get_joined_at( $class_id, $user_id = 0 ) {
		global $wpdb;

		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( ! $user_id || ! $class_id ) {
			return null;
		}

		$joined = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT joined_at FROM {$wpdb->prefix}gcm_attendance WHERE class_id = %d AND user_id = %d LIMIT 1",
				absint( $class_id ),
				$user_id
			)
		);

		return $joined ? (string) $joined : null;
	}

	/**
	 * Attendance list for a class.
	 *
	 * @param int $class_id Class ID.
	 * @return array
	 */
	public static function get_for_class( $class_id ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.*, u.display_name, u.user_email
				FROM {$wpdb->prefix}gcm_attendance a
				INNER JOIN {$wpdb->users} u ON u.ID = a.user_id
				WHERE a.class_id = %d
				ORDER BY a.joined_at ASC",
				absint( $class_id )
			)
		);

		$list = array();
		foreach ( (array) $rows as $row ) {
			$list[] = (object) array(
				'user_id'      => (int) $row->user_id,
				'display_name' => $row->display_name,
				'user_email'   => $row->user_email,
				'joined_at'    => $row->joined_at,
			);
		}
		return $list;
	}

	/**
	 * Attendance count for a class.
	 *
	 * @param int $class_id Class ID.
	 * @return int
	 */
	public static function count_for_class( $class_id ) {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}gcm_attendance WHERE class_id = %d",
				absint( $class_id )
			)
		);
	}
}
