<?php
/**
 * Course assignments and submissions.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Teacher assignments with student submissions and grading.
 */
class GCM_Assignment_Service {

	/**
	 * Create an assignment.
	 *
	 * @param array $args Assignment fields.
	 * @return int|WP_Error Assignment ID.
	 */
	public static function create_assignment( $args ) {
		global $wpdb;

		$course_id    = absint( $args['course_id'] ?? 0 );
		$teacher_id   = absint( $args['teacher_id'] ?? get_current_user_id() );
		$title        = sanitize_text_field( $args['title'] ?? '' );
		$instructions = wp_kses_post( $args['instructions'] ?? '' );
		$due_at       = self::sanitize_datetime( $args['due_at'] ?? '' );

		if ( ! $course_id || ! get_post( $course_id ) ) {
			return new WP_Error( 'gcm_invalid_course', __( 'Invalid course.', 'giga-class-market' ) );
		}
		if ( ! GCM_Teacher_Service::teacher_can_manage_course( $teacher_id, $course_id ) ) {
			return new WP_Error( 'gcm_forbidden', __( 'You are not assigned to this course.', 'giga-class-market' ) );
		}
		if ( '' === $title ) {
			return new WP_Error( 'gcm_missing_title', __( 'Enter an assignment title.', 'giga-class-market' ) );
		}

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'gcm_assignments',
			array(
				'course_id'    => $course_id,
				'teacher_id'   => $teacher_id,
				'title'        => $title,
				'instructions' => $instructions,
				'due_at'       => $due_at,
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'gcm_assignment_failed', __( 'Unable to create assignment.', 'giga-class-market' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Assignments for a course.
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	public static function get_for_course( $course_id ) {
		global $wpdb;

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_assignments WHERE course_id = %d ORDER BY created_at DESC",
				absint( $course_id )
			)
		);
	}

	/**
	 * Student submission (create or replace).
	 *
	 * @param int    $assignment_id Assignment ID.
	 * @param int    $user_id Student user ID.
	 * @param int    $file_id Attachment ID.
	 * @param string $notes Optional notes.
	 * @return int|WP_Error Submission ID.
	 */
	public static function submit( $assignment_id, $user_id, $file_id, $notes ) {
		global $wpdb;

		$assignment_id = absint( $assignment_id );
		$user_id       = absint( $user_id );
		$file_id       = absint( $file_id );
		$notes         = sanitize_textarea_field( $notes );

		$assignment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_assignments WHERE id = %d LIMIT 1",
				$assignment_id
			)
		);
		if ( ! $assignment ) {
			return new WP_Error( 'gcm_invalid_assignment', __( 'Assignment not found.', 'giga-class-market' ) );
		}

		if ( ! GCM_Enrollment_Service::has_access( $user_id, (int) $assignment->course_id ) ) {
			return new WP_Error( 'gcm_not_enrolled', __( 'Only enrolled students can submit.', 'giga-class-market' ) );
		}

		if ( ! $file_id && '' === trim( $notes ) ) {
			return new WP_Error( 'gcm_empty_submission', __( 'Attach a file or add notes to submit.', 'giga-class-market' ) );
		}

		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_assignment_submissions WHERE assignment_id = %d AND user_id = %d LIMIT 1",
				$assignment_id,
				$user_id
			)
		);

		if ( $existing ) {
			$updated = $wpdb->update(
				$wpdb->prefix . 'gcm_assignment_submissions',
				array(
					'file_id'      => $file_id ? $file_id : null,
					'notes'        => $notes,
					'status'       => 'submitted',
					'grade'        => null,
					'feedback'     => null,
					'submitted_at' => current_time( 'mysql' ),
					'graded_at'    => null,
				),
				array( 'id' => (int) $existing->id ),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
			if ( false === $updated ) {
				return new WP_Error( 'gcm_submit_failed', __( 'Unable to update submission.', 'giga-class-market' ) );
			}
			return (int) $existing->id;
		}

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'gcm_assignment_submissions',
			array(
				'assignment_id' => $assignment_id,
				'course_id'     => (int) $assignment->course_id,
				'user_id'       => $user_id,
				'file_id'       => $file_id ? $file_id : null,
				'notes'         => $notes,
				'grade'         => null,
				'feedback'      => null,
				'status'        => 'submitted',
				'submitted_at'  => current_time( 'mysql' ),
				'graded_at'     => null,
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'gcm_submit_failed', __( 'Unable to save submission.', 'giga-class-market' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Grade a submission.
	 *
	 * @param int    $submission_id Submission ID.
	 * @param string $grade Grade value.
	 * @param string $feedback Teacher feedback.
	 * @return true|WP_Error
	 */
	public static function grade( $submission_id, $grade, $feedback ) {
		global $wpdb;

		$submission_id = absint( $submission_id );
		$grade         = sanitize_text_field( $grade );
		$feedback      = wp_kses_post( $feedback );

		$submission = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_assignment_submissions WHERE id = %d LIMIT 1",
				$submission_id
			)
		);
		if ( ! $submission ) {
			return new WP_Error( 'gcm_invalid_submission', __( 'Submission not found.', 'giga-class-market' ) );
		}

		$actor = get_current_user_id();
		if ( $actor && ! GCM_Teacher_Service::teacher_can_manage_course( $actor, (int) $submission->course_id ) ) {
			return new WP_Error( 'gcm_forbidden', __( 'You cannot grade this submission.', 'giga-class-market' ) );
		}

		$updated = $wpdb->update(
			$wpdb->prefix . 'gcm_assignment_submissions',
			array(
				'grade'     => $grade,
				'feedback'  => $feedback,
				'status'    => 'graded',
				'graded_at' => current_time( 'mysql' ),
			),
			array( 'id' => $submission_id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'gcm_grade_failed', __( 'Unable to save grade.', 'giga-class-market' ) );
		}

		return true;
	}

	/**
	 * Submissions for an assignment.
	 *
	 * @param int $assignment_id Assignment ID.
	 * @return array
	 */
	public static function get_submissions( $assignment_id ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_assignment_submissions WHERE assignment_id = %d ORDER BY submitted_at DESC",
				absint( $assignment_id )
			)
		);

		$list = array();
		foreach ( (array) $rows as $row ) {
			$user = get_userdata( (int) $row->user_id );
			$row->student_name = $user ? $user->display_name : __( 'Student', 'giga-class-market' );
			$row->file_url     = ! empty( $row->file_id ) ? (string) wp_get_attachment_url( (int) $row->file_id ) : '';
			$list[]            = $row;
		}
		return $list;
	}

	/**
	 * Sanitize optional datetime.
	 *
	 * @param string $value Datetime.
	 * @return string|null
	 */
	private static function sanitize_datetime( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return null;
		}
		try {
			$dt = new DateTimeImmutable( $value, wp_timezone() );
			return $dt->format( 'Y-m-d H:i:s' );
		} catch ( Exception $e ) {
			return null;
		}
	}
}
