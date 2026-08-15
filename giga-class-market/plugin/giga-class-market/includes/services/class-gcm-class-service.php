<?php
/**
 * Live class service.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schedule and start live classes.
 */
class GCM_Class_Service {

	/**
	 * Schedule a class.
	 *
	 * @param array $data Class data.
	 * @return int|WP_Error
	 */
	public static function schedule( $data ) {
		global $wpdb;

		$course_id     = absint( $data['course_id'] ?? 0 );
		$teacher_id    = absint( $data['teacher_id'] ?? get_current_user_id() );
		$title         = sanitize_text_field( $data['title'] ?? '' );
		$scheduled_at  = sanitize_text_field( $data['scheduled_at'] ?? '' );

		if ( ! $course_id || ! get_post( $course_id ) ) {
			return new WP_Error( 'gcm_invalid_course', __( 'Invalid course.', 'giga-class-market' ) );
		}
		if ( ! GCM_Teacher_Service::teacher_can_manage_course( $teacher_id, $course_id ) ) {
			return new WP_Error( 'gcm_forbidden', __( 'You are not assigned to this course.', 'giga-class-market' ) );
		}
		if ( ! $title ) {
			$title = sprintf( __( 'Live class — %s', 'giga-class-market' ), get_the_title( $course_id ) );
		}
		try {
			$dt = new DateTimeImmutable( $scheduled_at, wp_timezone() );
		} catch ( Exception $e ) {
			return new WP_Error( 'gcm_invalid_time', __( 'Choose a valid class date and time.', 'giga-class-market' ) );
		}

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'gcm_classes',
			array(
				'course_id'     => $course_id,
				'teacher_id'    => $teacher_id,
				'title'         => $title,
				'scheduled_at'  => $dt->format( 'Y-m-d H:i:s' ),
				'status'        => 'scheduled',
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'gcm_class_failed', __( 'Unable to schedule class.', 'giga-class-market' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Start a class and create Zoom meeting when possible.
	 *
	 * @param int $class_id Class ID.
	 * @param int $teacher_id Teacher ID.
	 * @return object|WP_Error
	 */
	public static function start( $class_id, $teacher_id = 0 ) {
		global $wpdb;

		$class = self::get( $class_id );
		if ( ! $class ) {
			return new WP_Error( 'gcm_invalid_class', __( 'Class not found.', 'giga-class-market' ) );
		}
		$teacher_id = $teacher_id ? absint( $teacher_id ) : (int) $class->teacher_id;
		if ( ! GCM_Teacher_Service::teacher_can_manage_course( $teacher_id, $class->course_id ) ) {
			return new WP_Error( 'gcm_forbidden', __( 'You cannot start this class.', 'giga-class-market' ) );
		}

		$zoom = GCM_Zoom_Service::create_meeting( $class->title, $class->scheduled_at, 60 );

		if ( is_wp_error( $zoom ) ) {
			return $zoom;
		}

		$join_url   = isset( $zoom['join_url'] ) ? (string) $zoom['join_url'] : '';
		$start_url  = isset( $zoom['start_url'] ) ? (string) $zoom['start_url'] : $join_url;
		$meeting_id = isset( $zoom['meeting_id'] ) ? (string) $zoom['meeting_id'] : '';

		if ( '' === $join_url ) {
			return new WP_Error( 'gcm_zoom_missing', __( 'Zoom meeting link was not created.', 'giga-class-market' ) );
		}

		$wpdb->update(
			$wpdb->prefix . 'gcm_classes',
			array(
				'status'          => 'live',
				'zoom_meeting_id' => $meeting_id,
				'zoom_join_url'   => $join_url,
				'zoom_start_url'  => $start_url,
				'started_at'      => current_time( 'mysql' ),
			),
			array( 'id' => absint( $class_id ) ),
			array( '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		return self::get( $class_id );
	}

	/**
	 * End a live class.
	 *
	 * @param int $class_id Class ID.
	 * @param int $teacher_id Teacher ID.
	 * @return true|WP_Error
	 */
	public static function end( $class_id, $teacher_id = 0 ) {
		global $wpdb;

		$class = self::get( $class_id );
		if ( ! $class ) {
			return new WP_Error( 'gcm_invalid_class', __( 'Class not found.', 'giga-class-market' ) );
		}
		$teacher_id = $teacher_id ? absint( $teacher_id ) : (int) $class->teacher_id;
		if ( ! GCM_Teacher_Service::teacher_can_manage_course( $teacher_id, $class->course_id ) ) {
			return new WP_Error( 'gcm_forbidden', __( 'You cannot end this class.', 'giga-class-market' ) );
		}

		$wpdb->update(
			$wpdb->prefix . 'gcm_classes',
			array(
				'status'   => 'ended',
				'ended_at' => current_time( 'mysql' ),
			),
			array( 'id' => absint( $class_id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		return true;
	}

	/**
	 * Get one class.
	 *
	 * @param int $class_id Class ID.
	 * @return object|null
	 */
	public static function get( $class_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_classes WHERE id = %d",
				absint( $class_id )
			)
		);
	}

	/**
	 * Classes for a teacher.
	 *
	 * @param int $teacher_id Teacher ID.
	 * @return array
	 */
	public static function get_for_teacher( $teacher_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_classes WHERE teacher_id = %d ORDER BY scheduled_at ASC",
				absint( $teacher_id )
			)
		);
	}

	/**
	 * Upcoming/live classes for a course (student view).
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	public static function get_for_course( $course_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_classes
				WHERE course_id = %d AND status IN ('scheduled','live')
				ORDER BY FIELD(status,'live','scheduled'), scheduled_at ASC",
				absint( $course_id )
			)
		);
	}

	/**
	 * Active live class for a course.
	 *
	 * @param int $course_id Course ID.
	 * @return object|null
	 */
	public static function get_live_for_course( $course_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_classes WHERE course_id = %d AND status = 'live' ORDER BY started_at DESC LIMIT 1",
				absint( $course_id )
			)
		);
	}
}
