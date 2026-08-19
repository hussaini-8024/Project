<?php
/**
 * Teacher service.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin-managed teacher accounts and course assignments.
 */
class GCM_Teacher_Service {

	/**
	 * Create a teacher with password set by admin.
	 *
	 * @param array $data Teacher data.
	 * @return int|WP_Error User ID.
	 */
	public static function create_teacher( $data ) {
		$email    = sanitize_email( $data['email'] ?? '' );
		$name     = sanitize_text_field( $data['full_name'] ?? '' );
		$password = (string) ( $data['password'] ?? '' );
		$username = sanitize_user( $data['username'] ?? '', true );
		$courses  = isset( $data['course_ids'] ) ? array_map( 'absint', (array) $data['course_ids'] ) : array();

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'gcm_invalid_email', __( 'Enter a valid teacher email.', 'giga-class-market' ) );
		}
		if ( strlen( $password ) < 8 ) {
			return new WP_Error( 'gcm_weak_password', __( 'Password must be at least 8 characters.', 'giga-class-market' ) );
		}
		if ( email_exists( $email ) ) {
			return new WP_Error( 'gcm_email_exists', __( 'That email is already registered.', 'giga-class-market' ) );
		}
		if ( ! $username ) {
			$username = sanitize_user( current( explode( '@', $email ) ), true );
		}
		if ( ! $username ) {
			$username = 'teacher';
		}
		$base = $username;
		$i    = 1;
		while ( username_exists( $username ) ) {
			$username = $base . $i;
			++$i;
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => $username,
				'user_pass'    => $password,
				'user_email'   => $email,
				'display_name' => $name ? $name : $username,
				'first_name'   => $name,
				'role'         => 'gcm_teacher',
			)
		);
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		GCM_Roles::assign_teacher_identity( $user_id );
		wp_set_password( $password, $user_id );
		update_user_meta( $user_id, 'gcm_whatsapp', sanitize_text_field( $data['whatsapp'] ?? '' ) );
		self::set_zoom_host_email( $user_id, $data['zoom_host_email'] ?? '' );
		self::set_teacher_courses( $user_id, $courses );

		GCM_Audit_Service::log( 'teacher_created', 'user', $user_id, array( 'courses' => $courses ) );
		return (int) $user_id;
	}

	/**
	 * Zoom licensed-user email for this teacher (must exist in the Zoom account).
	 *
	 * @param int $teacher_id Teacher user ID.
	 * @return string
	 */
	public static function get_zoom_host_email( $teacher_id ) {
		$email = sanitize_email( (string) get_user_meta( absint( $teacher_id ), 'gcm_zoom_host_email', true ) );
		return is_email( $email ) ? $email : '';
	}

	/**
	 * Save per-teacher Zoom host email.
	 *
	 * @param int    $teacher_id Teacher ID.
	 * @param string $email Zoom user email.
	 * @return true|WP_Error
	 */
	public static function set_zoom_host_email( $teacher_id, $email ) {
		$teacher_id = absint( $teacher_id );
		$user       = get_userdata( $teacher_id );
		if ( ! $user || ( ! user_can( $teacher_id, 'manage_options' ) && ! GCM_Roles::is_gcm_teacher_only( $user ) ) ) {
			return new WP_Error( 'gcm_invalid_teacher', __( 'Invalid teacher account.', 'giga-class-market' ) );
		}

		$email = sanitize_email( (string) $email );
		if ( '' !== $email && ! is_email( $email ) ) {
			return new WP_Error( 'gcm_invalid_email', __( 'Enter a valid Zoom host email.', 'giga-class-market' ) );
		}

		if ( '' === $email ) {
			delete_user_meta( $teacher_id, 'gcm_zoom_host_email' );
		} else {
			update_user_meta( $teacher_id, 'gcm_zoom_host_email', $email );
		}

		return true;
	}

	/**
	 * Resolve which Zoom host should own a meeting for this course/teacher.
	 * Priority: actor teacher meta → course teacher meta → global Settings host.
	 *
	 * @param int $course_id Course ID.
	 * @param int $actor_id Teacher/admin starting the class.
	 * @return string
	 */
	public static function resolve_zoom_host_email( $course_id, $actor_id = 0 ) {
		$actor_id = absint( $actor_id );
		if ( $actor_id && ! user_can( $actor_id, 'manage_options' ) ) {
			$host = self::get_zoom_host_email( $actor_id );
			if ( $host ) {
				return $host;
			}
		}

		$course_teacher = self::get_teacher_for_course( absint( $course_id ) );
		if ( $course_teacher ) {
			$host = self::get_zoom_host_email( (int) $course_teacher->ID );
			if ( $host ) {
				return $host;
			}
		}

		$settings = gcm_get_setting( 'zoom', array() );
		$fallback = isset( $settings['host_email'] ) ? sanitize_email( (string) $settings['host_email'] ) : '';
		return is_email( $fallback ) ? $fallback : '';
	}

	/**
	 * Reset teacher password from admin.
	 *
	 * @param int    $teacher_id Teacher ID.
	 * @param string $password New password.
	 * @return true|WP_Error
	 */
	public static function set_password( $teacher_id, $password ) {
		$teacher_id = absint( $teacher_id );
		$user       = get_userdata( $teacher_id );
		if ( ! $user || ! GCM_Roles::is_gcm_teacher_only( $user ) ) {
			return new WP_Error( 'gcm_invalid_teacher', __( 'Invalid teacher account.', 'giga-class-market' ) );
		}
		if ( strlen( (string) $password ) < 8 ) {
			return new WP_Error( 'gcm_weak_password', __( 'Password must be at least 8 characters.', 'giga-class-market' ) );
		}
		wp_set_password( $password, $teacher_id );
		GCM_Audit_Service::log( 'teacher_password_set', 'user', $teacher_id, array() );
		return true;
	}

	/**
	 * Assign courses to a teacher (one teacher per course).
	 *
	 * @param int   $teacher_id Teacher ID.
	 * @param array $course_ids Course IDs.
	 * @return void
	 */
	public static function set_teacher_courses( $teacher_id, $course_ids ) {
		global $wpdb;

		$teacher_id = absint( $teacher_id );
		$course_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $course_ids ) ) ) );
		$table      = $wpdb->prefix . 'gcm_teacher_courses';

		$wpdb->delete( $table, array( 'teacher_id' => $teacher_id ), array( '%d' ) );
		foreach ( $course_ids as $course_id ) {
			if ( ! get_post( $course_id ) ) {
				continue;
			}
			// Enforce one teacher per course: free the course from any other teacher.
			$wpdb->delete( $table, array( 'course_id' => $course_id ), array( '%d' ) );
			$wpdb->insert(
				$table,
				array(
					'teacher_id'  => $teacher_id,
					'course_id'   => $course_id,
					'assigned_at' => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s' )
			);
		}
	}

	/**
	 * Teacher assigned to a course (single).
	 *
	 * @param int $course_id Course ID.
	 * @return WP_User|null
	 */
	public static function get_teacher_for_course( $course_id ) {
		global $wpdb;

		$teacher_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT teacher_id FROM {$wpdb->prefix}gcm_teacher_courses WHERE course_id = %d LIMIT 1",
				absint( $course_id )
			)
		);
		if ( ! $teacher_id ) {
			return null;
		}
		$user = get_userdata( $teacher_id );
		return $user ? $user : null;
	}

	/**
	 * Courses assigned to a teacher.
	 *
	 * @param int $teacher_id Teacher ID.
	 * @return array
	 */
	public static function get_teacher_courses( $teacher_id ) {
		global $wpdb;

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT course_id FROM {$wpdb->prefix}gcm_teacher_courses WHERE teacher_id = %d ORDER BY assigned_at DESC",
				absint( $teacher_id )
			)
		);

		$courses = array();
		foreach ( $ids as $course_id ) {
			$course = GCM_Course_Service::get( (int) $course_id );
			if ( $course ) {
				$courses[] = $course;
			}
		}
		return $courses;
	}

	/**
	 * Whether teacher (or admin) can manage a course.
	 *
	 * @param int $teacher_id Teacher ID.
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	public static function teacher_can_manage_course( $teacher_id, $course_id ) {
		global $wpdb;

		if ( user_can( $teacher_id, 'manage_options' ) ) {
			return true;
		}

		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}gcm_teacher_courses WHERE teacher_id = %d AND course_id = %d",
				absint( $teacher_id ),
				absint( $course_id )
			)
		);
		return ! empty( $found );
	}

	/**
	 * List teachers for admin.
	 *
	 * @param array $args Args.
	 * @return array
	 */
	public static function get_teachers_list( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'search' => '',
				'number' => 100,
			)
		);

		$query = array(
			'role'   => 'gcm_teacher',
			'number' => min( 200, max( 1, absint( $args['number'] ) ) ),
			'orderby'=> 'registered',
			'order'  => 'DESC',
		);
		if ( $args['search'] ) {
			$query['search']         = '*' . $args['search'] . '*';
			$query['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}

		$users = get_users( $query );
		$list  = array();
		foreach ( $users as $user ) {
			$list[] = (object) array(
				'ID'           => $user->ID,
				'user_login'   => $user->user_login,
				'user_email'   => $user->user_email,
				'display_name' => $user->display_name,
				'whatsapp'         => get_user_meta( $user->ID, 'gcm_whatsapp', true ),
				'zoom_host_email'  => self::get_zoom_host_email( $user->ID ),
				'courses'          => self::get_teacher_courses( $user->ID ),
				'registered'       => $user->user_registered,
			);
		}
		return $list;
	}
}
