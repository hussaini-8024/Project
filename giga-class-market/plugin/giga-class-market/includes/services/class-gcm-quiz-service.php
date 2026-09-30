<?php
/**
 * Course quizzes and attempts.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Quizzes, questions, and scored attempts.
 */
class GCM_Quiz_Service {

	/**
	 * Create a quiz for a course.
	 *
	 * @param array $args Quiz fields.
	 * @return int|WP_Error Quiz ID.
	 */
	public static function create_quiz( $args ) {
		global $wpdb;

		$course_id  = absint( $args['course_id'] ?? 0 );
		$module_id  = ! empty( $args['module_id'] ) ? absint( $args['module_id'] ) : null;
		$title      = sanitize_text_field( $args['title'] ?? '' );
		$pass_score = isset( $args['pass_score'] ) ? max( 0, min( 100, absint( $args['pass_score'] ) ) ) : 70;
		$is_active  = isset( $args['is_active'] ) ? ( $args['is_active'] ? 1 : 0 ) : 1;

		if ( ! $course_id || ! get_post( $course_id ) ) {
			return new WP_Error( 'gcm_invalid_course', __( 'Invalid course.', 'giga-class-market' ) );
		}
		if ( '' === $title ) {
			return new WP_Error( 'gcm_missing_title', __( 'Enter a quiz title.', 'giga-class-market' ) );
		}

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'gcm_quizzes',
			array(
				'course_id'  => $course_id,
				'module_id'  => $module_id,
				'title'      => $title,
				'pass_score' => $pass_score,
				'is_active'  => $is_active,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%d', '%d', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'gcm_quiz_failed', __( 'Unable to create quiz.', 'giga-class-market' ) );
		}

		$quiz_id = (int) $wpdb->insert_id;

		if ( ! empty( $args['questions'] ) && is_array( $args['questions'] ) ) {
			$saved = self::save_questions( $quiz_id, $args['questions'] );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}

		return $quiz_id;
	}

	/**
	 * Replace quiz questions.
	 *
	 * @param int   $quiz_id Quiz ID.
	 * @param array $questions List of question arrays.
	 * @return true|WP_Error
	 */
	public static function save_questions( $quiz_id, $questions ) {
		global $wpdb;

		$quiz_id = absint( $quiz_id );
		$quiz    = self::get_quiz( $quiz_id );
		if ( ! $quiz ) {
			return new WP_Error( 'gcm_invalid_quiz', __( 'Quiz not found.', 'giga-class-market' ) );
		}

		$wpdb->delete( $wpdb->prefix . 'gcm_quiz_questions', array( 'quiz_id' => $quiz_id ), array( '%d' ) );

		$order = 0;
		foreach ( (array) $questions as $item ) {
			$question = isset( $item['question'] ) ? wp_kses_post( $item['question'] ) : '';
			$options  = isset( $item['options'] ) && is_array( $item['options'] ) ? array_values( $item['options'] ) : array();
			$correct  = isset( $item['correct_index'] ) ? absint( $item['correct_index'] ) : 0;

			if ( '' === trim( wp_strip_all_tags( $question ) ) || count( $options ) < 2 ) {
				continue;
			}

			$clean_options = array();
			foreach ( $options as $opt ) {
				$clean_options[] = sanitize_text_field( $opt );
			}

			if ( $correct >= count( $clean_options ) ) {
				$correct = 0;
			}

			$wpdb->insert(
				$wpdb->prefix . 'gcm_quiz_questions',
				array(
					'quiz_id'       => $quiz_id,
					'question'      => $question,
					'options_json'  => wp_json_encode( $clean_options ),
					'correct_index' => $correct,
					'sort_order'    => $order,
				),
				array( '%d', '%s', '%s', '%d', '%d' )
			);
			$order++;
		}

		return true;
	}

	/**
	 * Quizzes for a course (with questions).
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	public static function get_for_course( $course_id ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_quizzes WHERE course_id = %d ORDER BY created_at ASC",
				absint( $course_id )
			)
		);

		$list = array();
		foreach ( (array) $rows as $row ) {
			$list[] = self::hydrate_quiz( $row );
		}
		return $list;
	}

	/**
	 * Get a single quiz with questions.
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return object|null
	 */
	public static function get_quiz( $quiz_id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_quizzes WHERE id = %d LIMIT 1",
				absint( $quiz_id )
			)
		);

		return $row ? self::hydrate_quiz( $row ) : null;
	}

	/**
	 * Submit and score an attempt.
	 *
	 * @param int   $quiz_id Quiz ID.
	 * @param int   $user_id User ID.
	 * @param array $answers_array Map of question_id => selected index.
	 * @return object|WP_Error Attempt row.
	 */
	public static function submit_attempt( $quiz_id, $user_id, $answers_array ) {
		global $wpdb;

		$quiz_id = absint( $quiz_id );
		$user_id = absint( $user_id );
		$quiz    = self::get_quiz( $quiz_id );

		if ( ! $quiz ) {
			return new WP_Error( 'gcm_invalid_quiz', __( 'Quiz not found.', 'giga-class-market' ) );
		}
		if ( empty( $quiz->is_active ) ) {
			return new WP_Error( 'gcm_quiz_inactive', __( 'This quiz is not active.', 'giga-class-market' ) );
		}
		if ( ! $user_id || ! GCM_Enrollment_Service::has_access( $user_id, (int) $quiz->course_id ) ) {
			return new WP_Error( 'gcm_not_enrolled', __( 'Only enrolled students can take this quiz.', 'giga-class-market' ) );
		}

		$answers_array = is_array( $answers_array ) ? $answers_array : array();
		$questions     = isset( $quiz->questions ) ? $quiz->questions : array();
		$total         = count( $questions );
		$correct_count = 0;
		$scored        = array();

		foreach ( $questions as $q ) {
			$qid      = (int) $q->id;
			$selected = array_key_exists( $qid, $answers_array )
				? (int) $answers_array[ $qid ]
				: ( array_key_exists( (string) $qid, $answers_array ) ? (int) $answers_array[ (string) $qid ] : -1 );
			$is_right = ( $selected === (int) $q->correct_index );
			if ( $is_right ) {
				$correct_count++;
			}
			$scored[ $qid ] = array(
				'selected' => $selected,
				'correct'  => (int) $q->correct_index,
				'is_correct' => $is_right ? 1 : 0,
			);
		}

		$score  = $total > 0 ? (int) round( ( $correct_count / $total ) * 100 ) : 0;
		$passed = $score >= (int) $quiz->pass_score ? 1 : 0;

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'gcm_quiz_attempts',
			array(
				'quiz_id'      => $quiz_id,
				'user_id'      => $user_id,
				'score'        => $score,
				'passed'       => $passed,
				'answers_json' => wp_json_encode( $scored ),
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'gcm_attempt_failed', __( 'Unable to save quiz attempt.', 'giga-class-market' ) );
		}

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_quiz_attempts WHERE id = %d LIMIT 1",
				(int) $wpdb->insert_id
			)
		);
	}

	/**
	 * Best (highest score) attempt for a user.
	 *
	 * @param int $quiz_id Quiz ID.
	 * @param int $user_id User ID.
	 * @return object|null
	 */
	public static function get_best_attempt( $quiz_id, $user_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_quiz_attempts
				WHERE quiz_id = %d AND user_id = %d
				ORDER BY score DESC, created_at DESC
				LIMIT 1",
				absint( $quiz_id ),
				absint( $user_id )
			)
		);
	}

	/**
	 * Attach questions to a quiz row.
	 *
	 * @param object $row Quiz DB row.
	 * @return object
	 */
	private static function hydrate_quiz( $row ) {
		global $wpdb;

		$questions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_quiz_questions WHERE quiz_id = %d ORDER BY sort_order ASC, id ASC",
				(int) $row->id
			)
		);

		$list = array();
		foreach ( (array) $questions as $q ) {
			$opts = json_decode( (string) $q->options_json, true );
			$q->options = is_array( $opts ) ? $opts : array();
			$list[]     = $q;
		}

		$row->questions = $list;
		return $row;
	}
}
