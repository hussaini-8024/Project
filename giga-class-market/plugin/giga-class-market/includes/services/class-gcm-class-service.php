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

		$course_id     = absint( $data['course_id'] ?? 0 );
		$teacher_id    = absint( $data['teacher_id'] ?? get_current_user_id() );
		$title         = sanitize_text_field( $data['title'] ?? '' );
		$scheduled_at  = sanitize_text_field( $data['scheduled_at'] ?? '' );
		$scheduled_end = sanitize_text_field( $data['scheduled_end'] ?? '' );
		$passcode      = isset( $data['passcode'] ) ? GCM_Zoom_Service::sanitize_passcode( $data['passcode'] ) : '';

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

		$row = array(
			'course_id'     => $course_id,
			'teacher_id'    => $teacher_id,
			'title'         => $title,
			'scheduled_at'  => $start->format( 'Y-m-d H:i:s' ),
			'scheduled_end' => $end->format( 'Y-m-d H:i:s' ),
			'status'        => 'scheduled',
			'created_at'    => current_time( 'mysql' ),
		);
		if ( strlen( $passcode ) >= 4 ) {
			$row['zoom_passcode'] = $passcode;
		}

		$inserted = $wpdb->insert( $wpdb->prefix . 'gcm_classes', $row );

		if ( ! $inserted ) {
			return new WP_Error( 'gcm_class_failed', __( 'Unable to schedule class.', 'giga-class-market' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Host a meeting immediately: schedule for now and start it.
	 *
	 * @param array $data Host data.
	 * @return object|WP_Error Live class row.
	 */
	public static function host_now( $data ) {
		$tz    = wp_timezone();
		$start = new DateTimeImmutable( 'now', $tz );
		$end   = $start->modify( '+60 minutes' );
		$title = sanitize_text_field( $data['title'] ?? '' );
		if ( ! $title ) {
			$course_id = absint( $data['course_id'] ?? 0 );
			$title     = sprintf( __( 'Live class — %s', 'giga-class-market' ), $course_id ? get_the_title( $course_id ) : __( 'Giga Class Market', 'giga-class-market' ) );
		}

		$class_id = self::schedule(
			array(
				'course_id'     => $data['course_id'] ?? 0,
				'teacher_id'    => $data['teacher_id'] ?? get_current_user_id(),
				'title'         => $title,
				'scheduled_at'  => $start->format( 'Y-m-d H:i:s' ),
				'scheduled_end' => $end->format( 'Y-m-d H:i:s' ),
				'passcode'      => $data['passcode'] ?? '',
			)
		);
		if ( is_wp_error( $class_id ) ) {
			return $class_id;
		}

		return self::start( (int) $class_id, absint( $data['teacher_id'] ?? get_current_user_id() ), isset( $data['passcode'] ) ? (string) $data['passcode'] : '' );
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
	 * @param int    $class_id Class ID.
	 * @param int    $actor_id Teacher or admin ID.
	 * @param string $passcode Optional custom passcode.
	 * @return object|WP_Error
	 */
	public static function start( $class_id, $actor_id = 0, $passcode = '' ) {
		global $wpdb;

		$class = self::get( $class_id );
		if ( ! $class ) {
			return new WP_Error( 'gcm_invalid_class', __( 'Class not found.', 'giga-class-market' ) );
		}
		$actor_id = $actor_id ? absint( $actor_id ) : get_current_user_id();
		if ( ! GCM_Teacher_Service::teacher_can_manage_course( $actor_id, $class->course_id ) ) {
			return new WP_Error( 'gcm_forbidden', __( 'You cannot start this class.', 'giga-class-market' ) );
		}

		if ( '' === trim( (string) $passcode ) && ! empty( $class->zoom_passcode ) ) {
			$passcode = (string) $class->zoom_passcode;
		}

		$duration = self::duration_minutes( $class );
		$zoom     = GCM_Zoom_Service::create_meeting( $class->title, $class->scheduled_at, $duration, (int) $class->id, $passcode );

		if ( is_wp_error( $zoom ) ) {
			return $zoom;
		}

		$join_url   = isset( $zoom['join_url'] ) ? (string) $zoom['join_url'] : '';
		$start_url  = isset( $zoom['start_url'] ) ? (string) $zoom['start_url'] : $join_url;
		$meeting_id = isset( $zoom['meeting_id'] ) ? (string) $zoom['meeting_id'] : '';
		$stored_pw  = isset( $zoom['passcode'] ) ? (string) $zoom['passcode'] : GCM_Zoom_Service::resolve_passcode( $passcode );

		if ( '' === $join_url || ! GCM_Zoom_Service::is_usable_meeting_url( $join_url ) ) {
			$zoom       = GCM_Zoom_Service::create_jitsi_meeting( $class->title, (int) $class->id, $stored_pw );
			$join_url   = $zoom['join_url'];
			$start_url  = $zoom['start_url'];
			$meeting_id = $zoom['meeting_id'];
			$stored_pw  = $zoom['passcode'];
		}

		$wpdb->update(
			$wpdb->prefix . 'gcm_classes',
			array(
				'status'          => 'live',
				'zoom_meeting_id' => $meeting_id,
				'zoom_join_url'   => $join_url,
				'zoom_start_url'  => $start_url,
				'zoom_passcode'   => $stored_pw,
				'started_at'      => current_time( 'mysql' ),
			),
			array( 'id' => absint( $class_id ) ),
			array( '%s', '%s', '%s', '%s', '%s', '%s' ),
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

		$passcode = ! empty( $class->zoom_passcode ) ? (string) $class->zoom_passcode : '';
		$meeting  = GCM_Zoom_Service::create_meeting(
			$class->title,
			$class->scheduled_at,
			self::duration_minutes( $class ),
			(int) $class->id,
			$passcode
		);
		if ( is_wp_error( $meeting ) ) {
			$meeting = GCM_Zoom_Service::create_jitsi_meeting( $class->title, (int) $class->id, $passcode );
		}

		$wpdb->update(
			$wpdb->prefix . 'gcm_classes',
			array(
				'zoom_meeting_id' => $meeting['meeting_id'],
				'zoom_join_url'   => $meeting['join_url'],
				'zoom_start_url'  => $meeting['start_url'],
				'zoom_passcode'   => isset( $meeting['passcode'] ) ? (string) $meeting['passcode'] : $passcode,
			),
			array( 'id' => absint( $class_id ) ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		return self::get( $class_id );
	}

	/**
	 * Update the meeting passcode (and Zoom, when the meeting is a Zoom id).
	 *
	 * @param int    $class_id Class ID.
	 * @param string $passcode New passcode.
	 * @param int    $actor_id Teacher or admin ID.
	 * @return object|WP_Error
	 */
	public static function update_passcode( $class_id, $passcode, $actor_id = 0 ) {
		global $wpdb;

		$class = self::get( $class_id );
		if ( ! $class ) {
			return new WP_Error( 'gcm_invalid_class', __( 'Class not found.', 'giga-class-market' ) );
		}
		$actor_id = $actor_id ? absint( $actor_id ) : get_current_user_id();
		if ( ! GCM_Teacher_Service::teacher_can_manage_course( $actor_id, $class->course_id ) ) {
			return new WP_Error( 'gcm_forbidden', __( 'You cannot change this passcode.', 'giga-class-market' ) );
		}

		$clean = GCM_Zoom_Service::sanitize_passcode( $passcode );
		if ( strlen( $clean ) < 4 ) {
			return new WP_Error( 'gcm_weak_passcode', __( 'Passcode must be 4–10 characters using letters, numbers, or @ - _ *.', 'giga-class-market' ) );
		}

		$join_url  = isset( $class->zoom_join_url ) ? (string) $class->zoom_join_url : '';
		$start_url = isset( $class->zoom_start_url ) ? (string) $class->zoom_start_url : '';

		if ( 'live' === $class->status && ! empty( $class->zoom_meeting_id ) && GCM_Zoom_Service::is_zoom_meeting_id( $class->zoom_meeting_id ) ) {
			$updated = GCM_Zoom_Service::update_meeting_passcode( $class->zoom_meeting_id, $clean );
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
			if ( ! empty( $updated['passcode'] ) ) {
				$clean = (string) $updated['passcode'];
			}
			if ( ! empty( $updated['join_url'] ) ) {
				$join_url = (string) $updated['join_url'];
			}
		}

		$fields = array( 'zoom_passcode' => $clean );
		$format = array( '%s' );
		if ( $join_url ) {
			$fields['zoom_join_url'] = $join_url;
			$format[]                = '%s';
		}
		if ( $start_url ) {
			$fields['zoom_start_url'] = $start_url;
			$format[]                 = '%s';
		}

		$wpdb->update(
			$wpdb->prefix . 'gcm_classes',
			$fields,
			array( 'id' => absint( $class_id ) ),
			$format,
			array( '%d' )
		);

		return self::get( $class_id );
	}

	/**
	 * Site invitation URL (login + join) for enrolled students.
	 *
	 * @param int $class_id Class ID.
	 * @return string
	 */
	public static function invitation_url( $class_id ) {
		return add_query_arg( 'class_id', absint( $class_id ), home_url( '/live-class/' ) );
	}

	/**
	 * Invitation copy for sharing (Meeting ID, passcode, join link).
	 *
	 * @param object $class Class row.
	 * @return string
	 */
	public static function invitation_text( $class ) {
		if ( ! $class ) {
			return '';
		}

		$when = '';
		if ( ! empty( $class->scheduled_at ) ) {
			$when = function_exists( 'gcm_format_exact_datetime' )
				? gcm_format_exact_datetime( $class->scheduled_at )
				: mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $class->scheduled_at );
			if ( ! empty( $class->scheduled_end ) ) {
				$end = function_exists( 'gcm_format_exact_datetime' )
					? gcm_format_exact_datetime( $class->scheduled_end )
					: mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $class->scheduled_end );
				$when .= ' – ' . $end;
			}
		}

		$meeting_id = GCM_Zoom_Service::format_meeting_id( $class->zoom_meeting_id ?? '' );
		$passcode   = isset( $class->zoom_passcode ) ? (string) $class->zoom_passcode : '';
		$join_url   = isset( $class->zoom_join_url ) ? (string) $class->zoom_join_url : '';
		$invite_url = self::invitation_url( (int) $class->id );
		$course     = ! empty( $class->course_id ) ? get_the_title( (int) $class->course_id ) : '';

		$lines   = array();
		$lines[] = sprintf( __( 'You are invited to a Giga Class Market live class: %s', 'giga-class-market' ), $class->title );
		if ( $course ) {
			$lines[] = sprintf( __( 'Course: %s', 'giga-class-market' ), $course );
		}
		if ( $when ) {
			$lines[] = sprintf( __( 'When: %s', 'giga-class-market' ), $when );
		}
		$lines[] = '';
		if ( $join_url ) {
			$lines[] = __( 'Join by link:', 'giga-class-market' );
			$lines[] = $join_url;
			$lines[] = '';
		}
		if ( $meeting_id ) {
			$lines[] = sprintf( __( 'Meeting ID: %s', 'giga-class-market' ), $meeting_id );
		}
		if ( $passcode ) {
			$lines[] = sprintf( __( 'Passcode: %s', 'giga-class-market' ), $passcode );
		}
		$lines[] = '';
		$lines[] = __( 'Or open this invitation in Giga Class Market:', 'giga-class-market' );
		$lines[] = $invite_url;

		return implode( "\n", $lines );
	}

	/**
	 * Meeting fields for AJAX / UI.
	 *
	 * @param object $class Class row.
	 * @return array
	 */
	public static function meeting_payload( $class ) {
		if ( ! $class ) {
			return array();
		}

		$join_url  = isset( $class->zoom_join_url ) ? (string) $class->zoom_join_url : '';
		$start_url = ! empty( $class->zoom_start_url ) ? (string) $class->zoom_start_url : $join_url;
		$provider  = ( ! empty( $class->zoom_meeting_id ) && GCM_Zoom_Service::is_zoom_meeting_id( $class->zoom_meeting_id ) ) ? 'zoom' : 'jitsi';
		if ( $join_url && false !== strpos( strtolower( $join_url ), 'zoom' ) ) {
			$provider = 'zoom';
		}

		return array(
			'meeting_id'          => isset( $class->zoom_meeting_id ) ? (string) $class->zoom_meeting_id : '',
			'meeting_id_display'  => GCM_Zoom_Service::format_meeting_id( $class->zoom_meeting_id ?? '' ),
			'passcode'            => isset( $class->zoom_passcode ) ? (string) $class->zoom_passcode : '',
			'join_url'            => $join_url,
			'start_url'           => $start_url,
			'invite_url'          => self::invitation_url( (int) $class->id ),
			'invite_text'         => self::invitation_text( $class ),
			'provider'            => $provider,
		);
	}

	/**
	 * Render Meeting ID / passcode / join / share panel.
	 *
	 * @param object $class Class row.
	 * @param array  $args  {
	 *     @type bool   $can_edit_passcode Show passcode editor.
	 *     @type bool   $is_host           Host context (start URL already shown elsewhere).
	 *     @type string $variant           teacher|student|admin.
	 * }
	 * @return void
	 */
	public static function render_invite_panel( $class, $args = array() ) {
		if ( ! $class || empty( $class->zoom_join_url ) ) {
			return;
		}

		$args = wp_parse_args(
			$args,
			array(
				'can_edit_passcode' => false,
				'is_host'           => false,
				'variant'           => 'teacher',
			)
		);

		$payload = self::meeting_payload( $class );
		$view    = GCM_PLUGIN_DIR . 'includes/views/meeting-invite.php';
		if ( file_exists( $view ) ) {
			include $view;
		}
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
