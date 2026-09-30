<?php
/**
 * Course reviews / ratings.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Student course reviews with moderated status.
 */
class GCM_Review_Service {

	/**
	 * Submit or update a review (enrolled students only).
	 *
	 * @param int    $course_id Course ID.
	 * @param int    $user_id User ID.
	 * @param int    $rating Rating 1–5.
	 * @param string $title Review title.
	 * @param string $body Review body.
	 * @return int|WP_Error Review ID.
	 */
	public static function submit( $course_id, $user_id, $rating, $title, $body ) {
		global $wpdb;

		$course_id = absint( $course_id );
		$user_id   = absint( $user_id );
		$rating    = max( 1, min( 5, absint( $rating ) ) );
		$title     = sanitize_text_field( $title );
		$body      = sanitize_textarea_field( $body );

		if ( ! $course_id || ! get_post( $course_id ) ) {
			return new WP_Error( 'gcm_invalid_course', __( 'Invalid course.', 'giga-class-market' ) );
		}
		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			return new WP_Error( 'gcm_invalid_user', __( 'Invalid student.', 'giga-class-market' ) );
		}
		if ( ! GCM_Enrollment_Service::has_access( $user_id, $course_id ) ) {
			return new WP_Error( 'gcm_not_enrolled', __( 'Only enrolled students can leave a review.', 'giga-class-market' ) );
		}

		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_reviews WHERE course_id = %d AND user_id = %d LIMIT 1",
				$course_id,
				$user_id
			)
		);

		if ( $existing ) {
			$updated = $wpdb->update(
				$wpdb->prefix . 'gcm_reviews',
				array(
					'rating'       => $rating,
					'review_title' => $title,
					'review_body'  => $body,
					'status'       => 'pending',
				),
				array( 'id' => (int) $existing->id ),
				array( '%d', '%s', '%s', '%s' ),
				array( '%d' )
			);
			if ( false === $updated ) {
				return new WP_Error( 'gcm_review_failed', __( 'Unable to update review.', 'giga-class-market' ) );
			}
			self::sync_course_rating( $course_id );
			return (int) $existing->id;
		}

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'gcm_reviews',
			array(
				'course_id'    => $course_id,
				'user_id'      => $user_id,
				'rating'       => $rating,
				'review_title' => $title,
				'review_body'  => $body,
				'status'       => 'pending',
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'gcm_review_failed', __( 'Unable to save review.', 'giga-class-market' ) );
		}

		self::sync_course_rating( $course_id );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Reviews for a course.
	 *
	 * @param int    $course_id Course ID.
	 * @param string $status Status filter (approved, pending, rejected, or all).
	 * @return array
	 */
	public static function get_for_course( $course_id, $status = 'approved' ) {
		global $wpdb;

		$course_id = absint( $course_id );
		$status    = sanitize_key( $status );

		if ( 'all' === $status || '' === $status ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}gcm_reviews WHERE course_id = %d ORDER BY created_at DESC",
					$course_id
				)
			);
		} else {
			if ( ! in_array( $status, array( 'approved', 'pending', 'rejected' ), true ) ) {
				$status = 'approved';
			}
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}gcm_reviews WHERE course_id = %d AND status = %s ORDER BY created_at DESC",
					$course_id,
					$status
				)
			);
		}

		$list = array();
		foreach ( (array) $rows as $row ) {
			$user = get_userdata( (int) $row->user_id );
			$row->author_name = $user ? $user->display_name : __( 'Student', 'giga-class-market' );
			$list[]           = $row;
		}
		return $list;
	}

	/**
	 * Average approved rating for a course.
	 *
	 * @param int $course_id Course ID.
	 * @return float
	 */
	public static function get_average( $course_id ) {
		global $wpdb;

		$avg = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(rating) FROM {$wpdb->prefix}gcm_reviews WHERE course_id = %d AND status = 'approved'",
				absint( $course_id )
			)
		);

		return $avg ? round( (float) $avg, 1 ) : 0.0;
	}

	/**
	 * Set review moderation status.
	 *
	 * @param int    $id Review ID.
	 * @param string $status approved|pending|rejected.
	 * @return true|WP_Error
	 */
	public static function set_status( $id, $status ) {
		global $wpdb;

		$id     = absint( $id );
		$status = sanitize_key( $status );
		if ( ! in_array( $status, array( 'approved', 'pending', 'rejected' ), true ) ) {
			return new WP_Error( 'gcm_invalid_status', __( 'Invalid review status.', 'giga-class-market' ) );
		}

		$review = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_reviews WHERE id = %d LIMIT 1",
				$id
			)
		);
		if ( ! $review ) {
			return new WP_Error( 'gcm_invalid_review', __( 'Review not found.', 'giga-class-market' ) );
		}

		$updated = $wpdb->update(
			$wpdb->prefix . 'gcm_reviews',
			array( 'status' => $status ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'gcm_review_failed', __( 'Unable to update review status.', 'giga-class-market' ) );
		}

		self::sync_course_rating( (int) $review->course_id );
		return true;
	}

	/**
	 * Sync course _gcm_rating meta from approved average.
	 *
	 * @param int $course_id Course ID.
	 * @return float Synced average.
	 */
	public static function sync_course_rating( $course_id ) {
		$course_id = absint( $course_id );
		$average   = self::get_average( $course_id );
		update_post_meta( $course_id, '_gcm_rating', $average );
		return $average;
	}
}
