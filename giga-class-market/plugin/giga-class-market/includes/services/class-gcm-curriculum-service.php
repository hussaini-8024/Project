<?php
/**
 * Curriculum service.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages course modules and lessons.
 */
class GCM_Curriculum_Service {

	/**
	 * Create module.
	 *
	 * @param int    $course_id Course ID.
	 * @param string $title Title.
	 * @param int    $sort_order Sort order.
	 * @return int|false
	 */
	public static function create_module( $course_id, $title, $sort_order = 0 ) {
		global $wpdb;

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'gcm_modules',
			array(
				'course_id'  => absint( $course_id ),
				'title'      => sanitize_text_field( $title ),
				'sort_order' => absint( $sort_order ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update module.
	 *
	 * @param int   $module_id Module ID.
	 * @param array $data Data.
	 * @return bool
	 */
	public static function update_module( $module_id, $data ) {
		global $wpdb;

		$update = array();
		$format = array();
		if ( isset( $data['title'] ) ) {
			$update['title'] = sanitize_text_field( $data['title'] );
			$format[]        = '%s';
		}
		if ( isset( $data['sort_order'] ) ) {
			$update['sort_order'] = absint( $data['sort_order'] );
			$format[]             = '%d';
		}

		if ( empty( $update ) ) {
			return true;
		}

		return false !== $wpdb->update(
			$wpdb->prefix . 'gcm_modules',
			$update,
			array( 'id' => absint( $module_id ) ),
			$format,
			array( '%d' )
		);
	}

	/**
	 * Delete module and its lessons.
	 *
	 * @param int $module_id Module ID.
	 * @return bool
	 */
	public static function delete_module( $module_id ) {
		global $wpdb;

		$module_id = absint( $module_id );
		$wpdb->delete( $wpdb->prefix . 'gcm_lessons', array( 'module_id' => $module_id ), array( '%d' ) );
		return false !== $wpdb->delete( $wpdb->prefix . 'gcm_modules', array( 'id' => $module_id ), array( '%d' ) );
	}

	/**
	 * Create lesson.
	 *
	 * @param array $data Lesson data.
	 * @return int|false
	 */
	public static function create_lesson( $data ) {
		global $wpdb;

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'gcm_lessons',
			array(
				'module_id'           => absint( $data['module_id'] ?? 0 ),
				'course_id'           => absint( $data['course_id'] ?? 0 ),
				'title'               => sanitize_text_field( $data['title'] ?? '' ),
				'content'             => wp_kses_post( $data['content'] ?? '' ),
				'video_url'           => esc_url_raw( $data['video_url'] ?? '' ),
				'video_attachment_id' => absint( $data['video_attachment_id'] ?? 0 ),
				'duration_minutes'    => absint( $data['duration_minutes'] ?? 0 ),
				'is_preview'          => ! empty( $data['is_preview'] ) ? 1 : 0,
				'sort_order'          => absint( $data['sort_order'] ?? 0 ),
				'created_at'          => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update lesson.
	 *
	 * @param int   $lesson_id Lesson ID.
	 * @param array $data Data.
	 * @return bool
	 */
	public static function update_lesson( $lesson_id, $data ) {
		global $wpdb;

		$map = array(
			'module_id'           => array( 'sanitize' => 'absint', 'format' => '%d' ),
			'course_id'           => array( 'sanitize' => 'absint', 'format' => '%d' ),
			'title'               => array( 'sanitize' => 'sanitize_text_field', 'format' => '%s' ),
			'content'             => array( 'sanitize' => 'wp_kses_post', 'format' => '%s' ),
			'video_url'           => array( 'sanitize' => 'esc_url_raw', 'format' => '%s' ),
			'video_attachment_id' => array( 'sanitize' => 'absint', 'format' => '%d' ),
			'duration_minutes'    => array( 'sanitize' => 'absint', 'format' => '%d' ),
			'is_preview'          => array( 'sanitize' => 'absint', 'format' => '%d' ),
			'sort_order'          => array( 'sanitize' => 'absint', 'format' => '%d' ),
		);
		$update = array();
		$format = array();

		foreach ( $map as $field => $config ) {
			if ( array_key_exists( $field, $data ) ) {
				$update[ $field ] = call_user_func( $config['sanitize'], $data[ $field ] );
				$format[]         = $config['format'];
			}
		}

		if ( empty( $update ) ) {
			return true;
		}

		return false !== $wpdb->update(
			$wpdb->prefix . 'gcm_lessons',
			$update,
			array( 'id' => absint( $lesson_id ) ),
			$format,
			array( '%d' )
		);
	}

	/**
	 * Delete lesson.
	 *
	 * @param int $lesson_id Lesson ID.
	 * @return bool
	 */
	public static function delete_lesson( $lesson_id ) {
		global $wpdb;

		return false !== $wpdb->delete( $wpdb->prefix . 'gcm_lessons', array( 'id' => absint( $lesson_id ) ), array( '%d' ) );
	}

	/**
	 * Get course curriculum.
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	public static function get_course_curriculum( $course_id ) {
		global $wpdb;

		$course_id = absint( $course_id );
		$modules   = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_modules WHERE course_id = %d ORDER BY sort_order ASC, id ASC",
				$course_id
			),
			ARRAY_A
		);

		foreach ( $modules as &$module ) {
			$module['lessons'] = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}gcm_lessons WHERE module_id = %d AND course_id = %d ORDER BY sort_order ASC, id ASC",
					absint( $module['id'] ),
					$course_id
				),
				ARRAY_A
			);
		}

		return $modules;
	}

	/**
	 * Get a single lesson.
	 *
	 * @param int $lesson_id Lesson ID.
	 * @return object|null
	 */
	public static function get_lesson( $lesson_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_lessons WHERE id = %d",
				absint( $lesson_id )
			)
		);
	}

	/**
	 * Save complete curriculum payload.
	 *
	 * @param int   $course_id Course ID.
	 * @param array $modules Modules with lessons.
	 * @return bool
	 */
	public static function save_course_curriculum( $course_id, $modules ) {
		global $wpdb;

		$course_id = absint( $course_id );
		if ( ! $course_id || ! is_array( $modules ) ) {
			return false;
		}

		$existing_modules = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}gcm_modules WHERE course_id = %d",
				$course_id
			)
		);
		$seen_modules     = array();
		$seen_lessons     = array();

		foreach ( array_values( $modules ) as $module_index => $module ) {
			$module_id = absint( $module['id'] ?? 0 );
			$title     = sanitize_text_field( $module['title'] ?? '' );
			if ( '' === $title ) {
				continue;
			}

			if ( $module_id ) {
				self::update_module( $module_id, array( 'title' => $title, 'sort_order' => $module_index ) );
			} else {
				$module_id = self::create_module( $course_id, $title, $module_index );
			}

			if ( ! $module_id ) {
				continue;
			}

			$seen_modules[] = $module_id;
			$lessons        = isset( $module['lessons'] ) && is_array( $module['lessons'] ) ? $module['lessons'] : array();

			foreach ( array_values( $lessons ) as $lesson_index => $lesson ) {
				$lesson_data = array(
					'module_id'           => $module_id,
					'course_id'           => $course_id,
					'title'               => sanitize_text_field( $lesson['title'] ?? '' ),
					'content'             => wp_kses_post( $lesson['content'] ?? '' ),
					'video_url'           => esc_url_raw( $lesson['video_url'] ?? '' ),
					'video_attachment_id' => absint( $lesson['video_attachment_id'] ?? 0 ),
					'duration_minutes'    => absint( $lesson['duration_minutes'] ?? 0 ),
					'is_preview'          => ! empty( $lesson['is_preview'] ) ? 1 : 0,
					'sort_order'          => $lesson_index,
				);

				if ( '' === $lesson_data['title'] ) {
					continue;
				}

				$lesson_id = absint( $lesson['id'] ?? 0 );
				if ( $lesson_id ) {
					self::update_lesson( $lesson_id, $lesson_data );
				} else {
					$lesson_id = self::create_lesson( $lesson_data );
				}

				if ( $lesson_id ) {
					$seen_lessons[] = $lesson_id;
				}
			}
		}

		foreach ( array_map( 'absint', $existing_modules ) as $module_id ) {
			if ( ! in_array( $module_id, $seen_modules, true ) ) {
				self::delete_module( $module_id );
			}
		}

		if ( ! empty( $seen_modules ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $seen_modules ), '%d' ) );
			$params       = array_merge( array( $course_id ), $seen_modules, $seen_lessons );
			$not_lessons  = '';
			if ( ! empty( $seen_lessons ) ) {
				$lesson_placeholders = implode( ',', array_fill( 0, count( $seen_lessons ), '%d' ) );
				$not_lessons         = " AND id NOT IN ({$lesson_placeholders})";
			}
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}gcm_lessons WHERE course_id = %d AND module_id IN ({$placeholders}){$not_lessons}",
					$params
				)
			);
		}

		return true;
	}
}
