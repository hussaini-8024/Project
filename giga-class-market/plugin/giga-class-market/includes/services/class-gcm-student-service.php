<?php
/**
 * Student service.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages students.
 */
class GCM_Student_Service {

	/**
	 * Freeze all or one student enrollment.
	 *
	 * @param int $user_id User ID.
	 * @param int $course_id Optional course ID.
	 * @return bool
	 */
	public static function freeze( $user_id, $course_id = 0 ) {
		return self::set_enrollment_status( $user_id, 'frozen', $course_id );
	}

	/**
	 * Unfreeze all or one student enrollment.
	 *
	 * @param int $user_id User ID.
	 * @param int $course_id Optional course ID.
	 * @return bool
	 */
	public static function unfreeze( $user_id, $course_id = 0 ) {
		return self::set_enrollment_status( $user_id, 'active', $course_id );
	}

	/**
	 * Update student profile.
	 *
	 * @param int   $user_id User ID.
	 * @param array $data Profile data.
	 * @return bool|WP_Error
	 */
	public static function update_profile( $user_id, $data ) {
		$user_id = absint( $user_id );
		$user    = get_userdata( $user_id );
		if ( ! $user ) {
			return new WP_Error( 'gcm_invalid_user', __( 'Invalid user.', 'giga-class-market' ) );
		}

		$email = sanitize_email( $data['email'] ?? $user->user_email );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'gcm_invalid_email', __( 'Invalid email address.', 'giga-class-market' ) );
		}

		$email_owner = get_user_by( 'email', $email );
		if ( $email_owner && (int) $email_owner->ID !== $user_id ) {
			return new WP_Error( 'gcm_email_exists', __( 'That email is already in use.', 'giga-class-market' ) );
		}

		$result = wp_update_user(
			array(
				'ID'           => $user_id,
				'user_email'   => $email,
				'display_name' => sanitize_text_field( $data['full_name'] ?? $user->display_name ),
				'first_name'   => sanitize_text_field( $data['full_name'] ?? $user->first_name ),
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		update_user_meta( $user_id, 'gcm_whatsapp', sanitize_text_field( $data['whatsapp'] ?? '' ) );
		update_user_meta( $user_id, 'gcm_address', sanitize_textarea_field( $data['address'] ?? '' ) );

		return true;
	}

	/**
	 * Get students with enrollment/payment counts.
	 *
	 * @param array $args Args.
	 * @return array
	 */
	public static function get_students_list( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'search' => '',
				'limit'  => 50,
				'offset' => 0,
			)
		);

		$limit  = min( 200, max( 1, absint( $args['limit'] ) ) );
		$offset = max( 0, absint( $args['offset'] ) );
		$role_like = '%"gcm_student"%';
		$where     = array( 'um.meta_key = %s', 'um.meta_value LIKE %s' );
		$params    = array( $wpdb->prefix . 'capabilities', $role_like );

		if ( $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(u.display_name LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$params[] = $limit;
		$params[] = $offset;

		$users = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT u.ID, u.user_login, u.user_email, u.display_name, u.user_registered,
					COUNT(DISTINCT e.id) AS enrollments,
					SUM(CASE WHEN e.status = 'frozen' THEN 1 ELSE 0 END) AS frozen_enrollments
				FROM {$wpdb->users} u
				INNER JOIN {$wpdb->usermeta} um ON um.user_id = u.ID
				LEFT JOIN {$wpdb->prefix}gcm_enrollments e ON e.user_id = u.ID
				WHERE " . implode( ' AND ', $where ) . '
				GROUP BY u.ID
				ORDER BY u.user_registered DESC
				LIMIT %d OFFSET %d',
				$params
			)
		);

		foreach ( $users as $user ) {
			$user->whatsapp = get_user_meta( $user->ID, 'gcm_whatsapp', true );
			$user->address  = get_user_meta( $user->ID, 'gcm_address', true );
			$user->courses  = self::get_student_course_summaries( (int) $user->ID );
		}

		return $users;
	}

	/**
	 * Course titles, status, and progress for one student (admin panel).
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public static function get_student_course_summaries( $user_id ) {
		$courses   = GCM_Enrollment_Service::get_student_courses( absint( $user_id ) );
		$summaries = array();

		foreach ( $courses as $course ) {
			$enrollment = isset( $course['enrollment'] ) ? $course['enrollment'] : null;
			$summaries[] = array(
				'id'       => (int) $course['id'],
				'title'    => (string) $course['title'],
				'status'   => $enrollment ? sanitize_key( $enrollment->status ) : 'active',
				'progress' => isset( $course['progress'] ) ? (int) $course['progress'] : 0,
			);
		}

		return $summaries;
	}

	/**
	 * Set enrollment status.
	 *
	 * @param int    $user_id User ID.
	 * @param string $status Status.
	 * @param int    $course_id Optional course ID.
	 * @return bool
	 */
	private static function set_enrollment_status( $user_id, $status, $course_id = 0 ) {
		global $wpdb;

		$user_id = absint( $user_id );
		$status  = in_array( $status, array( 'active', 'frozen' ), true ) ? $status : 'active';
		$where   = array( 'user_id' => $user_id );
		$formats = array( '%d' );
		if ( $course_id ) {
			$where['course_id'] = absint( $course_id );
			$formats[]          = '%d';
		}

		$result = $wpdb->update(
			$wpdb->prefix . 'gcm_enrollments',
			array( 'status' => $status ),
			$where,
			array( '%s' ),
			$formats
		);

		if ( false !== $result ) {
			GCM_Audit_Service::log( 'student_' . $status, 'user', $user_id, array( 'course_id' => absint( $course_id ) ) );
		}

		return false !== $result;
	}
}
