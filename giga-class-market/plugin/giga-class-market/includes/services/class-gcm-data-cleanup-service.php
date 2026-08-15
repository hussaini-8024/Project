<?php
/**
 * One-shot operational data cleanup (students, payments, contacts).
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clears test/operational student-facing records while keeping courses, teachers, and settings.
 */
class GCM_Data_Cleanup_Service {

	/**
	 * Clear students, payments, contact messages, and related LMS learner records.
	 *
	 * @return array{payments:int,contacts:int,students:int,enrollments:int,message:string}
	 */
	public static function clear_operational_test_data() {
		global $wpdb;

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'gcm_manage_students' ) ) {
			return array(
				'payments'    => 0,
				'contacts'    => 0,
				'students'    => 0,
				'enrollments' => 0,
				'message'     => __( 'Permission denied.', 'giga-class-market' ),
			);
		}

		$prefix = $wpdb->prefix;

		// Collect student user IDs before deleting related rows.
		$student_ids = get_users(
			array(
				'role'   => 'gcm_student',
				'fields' => 'ID',
				'number' => 9999,
			)
		);
		$student_ids = array_map( 'absint', (array) $student_ids );

		// Also include users flagged as GCM students.
		$meta_students = get_users(
			array(
				'meta_key'   => 'gcm_is_student',
				'meta_value' => '1',
				'fields'     => 'ID',
				'number'     => 9999,
			)
		);
		foreach ( (array) $meta_students as $uid ) {
			$student_ids[] = absint( $uid );
		}
		$student_ids = array_values( array_unique( array_filter( $student_ids ) ) );

		// Delete payment screenshot private files when present.
		$screenshot_ids = $wpdb->get_col( "SELECT screenshot_id FROM {$prefix}gcm_payments WHERE screenshot_id > 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( (array) $screenshot_ids as $shot_id ) {
			$shot_id = absint( $shot_id );
			if ( ! $shot_id ) {
				continue;
			}
			$file = get_post_meta( $shot_id, '_gcm_private_file', true );
			if ( $file && is_string( $file ) && file_exists( $file ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				@unlink( $file );
			}
			wp_delete_attachment( $shot_id, true );
		}

		$payments_deleted = (int) $wpdb->query( "DELETE FROM {$prefix}gcm_payments" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$contacts_deleted = (int) $wpdb->query( "DELETE FROM {$prefix}gcm_contacts" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Learner operational tables (safe to wipe for a clean marketplace restart).
		$wpdb->query( "DELETE FROM {$prefix}gcm_coupon_uses" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$enrollments_deleted = (int) $wpdb->query( "DELETE FROM {$prefix}gcm_enrollments" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM {$prefix}gcm_progress" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM {$prefix}gcm_certificates" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM {$prefix}gcm_attendance" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM {$prefix}gcm_messages" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM {$prefix}gcm_quiz_attempts" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM {$prefix}gcm_assignment_submissions" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM {$prefix}gcm_reviews" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM {$prefix}gcm_notifications" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		require_once ABSPATH . 'wp-admin/includes/user.php';

		$students_deleted = 0;
		foreach ( $student_ids as $user_id ) {
			$user = get_userdata( $user_id );
			if ( ! $user ) {
				continue;
			}
			// Never delete administrators or teachers.
			if ( user_can( $user_id, 'manage_options' ) || in_array( 'gcm_teacher', (array) $user->roles, true ) || in_array( 'administrator', (array) $user->roles, true ) ) {
				continue;
			}
			if ( wp_delete_user( $user_id ) ) {
				++$students_deleted;
			}
		}

		GCM_Audit_Service::log(
			'clear_operational_test_data',
			'system',
			0,
			array(
				'payments'    => $payments_deleted,
				'contacts'    => $contacts_deleted,
				'students'    => $students_deleted,
				'enrollments' => $enrollments_deleted,
			)
		);

		return array(
			'payments'    => max( 0, $payments_deleted ),
			'contacts'    => max( 0, $contacts_deleted ),
			'students'    => $students_deleted,
			'enrollments' => max( 0, $enrollments_deleted ),
			'message'     => sprintf(
				/* translators: 1: payments, 2: contacts, 3: students */
				__( 'Cleared %1$d payments, %2$d contact messages, and %3$d student accounts.', 'giga-class-market' ),
				max( 0, $payments_deleted ),
				max( 0, $contacts_deleted ),
				$students_deleted
			),
		);
	}
}
