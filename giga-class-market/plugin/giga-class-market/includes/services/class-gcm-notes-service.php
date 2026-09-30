<?php
/**
 * Course notes service.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Teacher-uploaded course notes for students.
 */
class GCM_Notes_Service {

	/**
	 * Upload / create a note.
	 *
	 * @param array $data Note data.
	 * @param array $file Optional uploaded file ($_FILES item).
	 * @return int|WP_Error Note ID.
	 */
	public static function create( $data, $file = array() ) {
		global $wpdb;

		$course_id  = absint( $data['course_id'] ?? 0 );
		$teacher_id = absint( $data['teacher_id'] ?? get_current_user_id() );
		$title      = sanitize_text_field( $data['title'] ?? '' );
		$content    = wp_kses_post( $data['content'] ?? '' );

		if ( ! $course_id || ! get_post( $course_id ) ) {
			return new WP_Error( 'gcm_invalid_course', __( 'Invalid course.', 'giga-class-market' ) );
		}
		if ( ! GCM_Teacher_Service::teacher_can_manage_course( $teacher_id, $course_id ) ) {
			return new WP_Error( 'gcm_forbidden', __( 'You are not assigned to this course.', 'giga-class-market' ) );
		}
		if ( '' === $title ) {
			return new WP_Error( 'gcm_missing_title', __( 'Enter a notes title.', 'giga-class-market' ) );
		}

		$file_id = 0;
		if ( ! empty( $file['name'] ) ) {
			$settings = GCM_Settings_Service::get_settings();
			$max_mb   = isset( $settings['security']['max_upload_mb'] ) ? absint( $settings['security']['max_upload_mb'] ) : 5;
			if ( ! empty( $file['size'] ) && $file['size'] > ( $max_mb * MB_IN_BYTES ) ) {
				return new WP_Error( 'gcm_file_too_large', sprintf( __( 'File size must be under %dMB.', 'giga-class-market' ), $max_mb ) );
			}

			$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, array( 'pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png' ), true ) ) {
				return new WP_Error( 'gcm_invalid_file_type', __( 'Allowed note files: PDF, DOC, DOCX, PPT, PPTX, TXT, JPG, PNG.', 'giga-class-market' ) );
			}

			// Ensure media_handle_upload reads the expected field name.
			$_FILES['note_file'] = $file;

			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			$attachment_id = media_handle_upload( 'note_file', $course_id );
			if ( is_wp_error( $attachment_id ) ) {
				return $attachment_id;
			}
			$file_id = (int) $attachment_id;
		}

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'gcm_notes',
			array(
				'course_id'  => $course_id,
				'teacher_id' => $teacher_id,
				'title'      => $title,
				'content'    => $content,
				'file_id'    => $file_id ? $file_id : null,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'gcm_note_failed', __( 'Unable to save notes.', 'giga-class-market' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Notes for a course.
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	public static function get_for_course( $course_id ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_notes WHERE course_id = %d ORDER BY created_at DESC",
				absint( $course_id )
			)
		);

		$list = array();
		foreach ( (array) $rows as $row ) {
			$list[] = self::hydrate( $row );
		}
		return $list;
	}

	/**
	 * Delete a note (teacher or admin).
	 *
	 * @param int $note_id Note ID.
	 * @param int $teacher_id Teacher ID.
	 * @return true|WP_Error
	 */
	public static function delete( $note_id, $teacher_id = 0 ) {
		global $wpdb;

		$note = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_notes WHERE id = %d",
				absint( $note_id )
			)
		);
		if ( ! $note ) {
			return new WP_Error( 'gcm_invalid_note', __( 'Note not found.', 'giga-class-market' ) );
		}

		$teacher_id = $teacher_id ? absint( $teacher_id ) : get_current_user_id();
		if ( ! GCM_Teacher_Service::teacher_can_manage_course( $teacher_id, $note->course_id ) ) {
			return new WP_Error( 'gcm_forbidden', __( 'You cannot delete this note.', 'giga-class-market' ) );
		}

		$wpdb->delete( $wpdb->prefix . 'gcm_notes', array( 'id' => (int) $note->id ), array( '%d' ) );
		return true;
	}

	/**
	 * Hydrate note with file URL.
	 *
	 * @param object $row DB row.
	 * @return object
	 */
	private static function hydrate( $row ) {
		$row->file_url  = '';
		$row->file_name = '';
		if ( ! empty( $row->file_id ) ) {
			$row->file_url  = (string) wp_get_attachment_url( (int) $row->file_id );
			$row->file_name = get_the_title( (int) $row->file_id );
		}
		return $row;
	}
}
