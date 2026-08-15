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
 * Schedule and start live classes (online class system).
 */
class GCM_Class_Service {

	/**
	 * Schedule a class with start and end times.
	 *
	 * @param array $data Class data.
	 * @return int|WP_Error
	 */
	public static function schedule( $data ) {
		global $wpdb;

		$course_id    = absint( $data['course_id'] ?? 0 );
		$teacher_id   = absint( $data['teacher_id'] ?? get_current_user_id() );
		$title        = sanitize_text_field( $data['title'] ?? '' );
		$scheduled_at = sanitize_text_field( $data['scheduled_at'] ?? '' );
		$scheduled_end = sanitize_text_field( $data['scheduled_end'] ?? '' );

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
			$start = new DateTimeImmutable( $scheduled_at, wp_timezone() );
		} catch ( Exception $e ) {
			return new WP_Error( 'gcm_invalid_time', __( 'Choose a valid class start time.', 'giga-class-market' ) );
		}

		$end = null;
		if ( $scheduled_end ) {
			try {
				$end = new DateTimeImmutable( $scheduled_end, wp_timezone() );
			} catch ( Exception $e ) {
				return new WP_Error( 'gcm_invalid_end', __( 'Choose a valid class end time.', 'giga-class-market' ) );
			}
			if ( $end <= $start ) {
				return new WP_Error( 'gcm_invalid_range', __( 'End time must be after the start time.', 'giga-class-market' ) );
			}
		} else {
			$end = $start->modify( '+60 minutes' );
		}

		// Prefer the course’s assigned teacher when an admin schedules.
		$assigned = GCM_Teacher_Service::get_teacher_for_course( $course_id );
		if ( $assigned && user_can( $teacher_id, 'manage_options' ) ) {
			$teacher_id = (int) $assigned->ID;
		}

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'gcm_classes',
			array(
				'course_id'     => $course_id,
				'teacher_id'    => $teacher_id,
				'title'         => $title,
				'scheduled_at'  => $start->format( 'Y-m-d H:i:s' ),
				'scheduled_end' => $end->format( 'Y-m-d H:i:s' ),
				'status'        => 'scheduled',
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'gcm_class_failed', __( 'Unable to schedule class.', 'giga-class-market' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Duration in minutes from schedule.
	 *
	 * @param object $class Class row.
	 * @return int
	 */
	public static function duration_minutes( $class ) {
		if ( empty( $class->scheduled_at ) ) {
			return 60;
		}
		$start = strtotime( $class->scheduled_at );
		$end   = ! empty( $class->scheduled_end ) ? strtotime( $class->scheduled_end ) : 0;
		if ( ! $start || ! $end || $end <= $start ) {
			return 60;
		}
		return max( 15, (int) ceil( ( $end - $start ) / 60 ) );
	}

	/**
	 * Start a class and create Zoom meeting.
	 *
	 * @param int $class_id Class ID.
	 * @param int $actor_id Teacher or admin ID.
	 * @return object|WP_Error
	 */
	public static function start( $class_id, $actor_id = 0 ) {
		global $wpdb;

		$class = self::get( $class_id );
		if ( ! $class ) {
			return new WP_Error( 'gcm_invalid_class', __( 'Class not found.', 'giga-class-market' ) );
		}
		$actor_id = $actor_id ? absint( $actor_id ) : get_current_user_id();
		if ( ! GCM_Teacher_Service::teacher_can_manage_course( $actor_id, $class->course_id ) ) {
			return new WP_Error( 'gcm_forbidden', __( 'You cannot start this class.', 'giga-class-market' ) );
		}

		$duration = self::duration_minutes( $class );
		$zoom     = GCM_Zoom_Service::create_meeting( $class->title, $class->scheduled_at, $duration, (int) $class->id );

		if ( is_wp_error( $zoom ) ) {
			return $zoom;
		}

		$join_url   = isset( $zoom['join_url'] ) ? (string) $zoom['join_url'] : '';
		$start_url  = isset( $zoom['start_url'] ) ? (string) $zoom['start_url'] : $join_url;
		$meeting_id = isset( $zoom['meeting_id'] ) ? (string) $zoom['meeting_id'] : '';

		if ( '' === $join_url || ! GCM_Zoom_Service::is_usable_meeting_url( $join_url ) ) {
			$zoom       = GCM_Zoom_Service::create_jitsi_meeting( $class->title, (int) $class->id );
			$join_url   = $zoom['join_url'];
			$start_url  = $zoom['start_url'];
			$meeting_id = $zoom['meeting_id'];
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
	 * Ensure a live class has a usable Zoom/Jitsi URL (repairs legacy /live-class/ 404 links).
	 *
	 * @param int $class_id Class ID.
	 * @return object|WP_Error
	 */
	public static function ensure_meeting_links( $class_id ) {
		global $wpdb;

		$class = self::get( $class_id );
		if ( ! $class ) {
			return new WP_Error( 'gcm_invalid_class', __( 'Class not found.', 'giga-class-market' ) );
		}

		if ( GCM_Zoom_Service::is_usable_meeting_url( $class->zoom_join_url ?? '' ) ) {
			return $class;
		}

		$meeting = GCM_Zoom_Service::create_meeting(
			$class->title,
			$class->scheduled_at,
			self::duration_minutes( $class ),
			(int) $class->id
		);
		if ( is_wp_error( $meeting ) ) {
			$meeting = GCM_Zoom_Service::create_jitsi_meeting( $class->title, (int) $class->id );
		}

		$wpdb->update(
			$wpdb->prefix . 'gcm_classes',
			array(
				'zoom_meeting_id' => $meeting['meeting_id'],
				'zoom_join_url'   => $meeting['join_url'],
				'zoom_start_url'  => $meeting['start_url'],
			),
			array( 'id' => absint( $class_id ) ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		return self::get( $class_id );
	}

	/**
	 * End a live class.
	 *
	 * @param int $class_id Class ID.
	 * @param int $actor_id Teacher or admin ID.
	 * @return true|WP_Error
	 */
	public static function end( $class_id, $actor_id = 0 ) {
		global $wpdb;

		$class = self::get( $class_id );
		if ( ! $class ) {
			return new WP_Error( 'gcm_invalid_class', __( 'Class not found.', 'giga-class-market' ) );
		}
		$actor_id = $actor_id ? absint( $actor_id ) : get_current_user_id();
		if ( ! GCM_Teacher_Service::teacher_can_manage_course( $actor_id, $class->course_id ) ) {
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
	 * All classes (admin).
	 *
	 * @param int $limit Limit.
	 * @return array
	 */
	public static function get_all( $limit = 100 ) {
		global $wpdb;
		$limit = min( 200, max( 1, absint( $limit ) ) );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_classes ORDER BY FIELD(status,'live','scheduled','ended'), scheduled_at DESC LIMIT %d",
				$limit
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
