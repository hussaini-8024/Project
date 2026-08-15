<?php
/**
 * Coupon / discount codes.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create, validate, and apply course coupons.
 */
class GCM_Coupon_Service {

	/**
	 * Create a coupon.
	 *
	 * @param array $args Coupon fields.
	 * @return int|WP_Error Coupon ID.
	 */
	public static function create( $args ) {
		global $wpdb;

		$code = self::normalize_code( $args['code'] ?? '' );
		if ( '' === $code ) {
			return new WP_Error( 'gcm_invalid_code', __( 'Enter a valid coupon code (letters and numbers only).', 'giga-class-market' ) );
		}

		if ( self::get_by_code( $code ) ) {
			return new WP_Error( 'gcm_code_exists', __( 'That coupon code already exists.', 'giga-class-market' ) );
		}

		$discount_type  = self::sanitize_discount_type( $args['discount_type'] ?? 'percent' );
		$discount_value = max( 0, (float) ( $args['discount_value'] ?? 0 ) );
		if ( $discount_value <= 0 ) {
			return new WP_Error( 'gcm_invalid_discount', __( 'Discount value must be greater than zero.', 'giga-class-market' ) );
		}
		if ( 'percent' === $discount_type && $discount_value > 100 ) {
			return new WP_Error( 'gcm_invalid_discount', __( 'Percent discount cannot exceed 100.', 'giga-class-market' ) );
		}

		$course_id = ! empty( $args['course_id'] ) ? absint( $args['course_id'] ) : null;
		if ( $course_id && ! get_post( $course_id ) ) {
			return new WP_Error( 'gcm_invalid_course', __( 'Invalid course.', 'giga-class-market' ) );
		}

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'gcm_coupons',
			array(
				'code'           => $code,
				'description'    => sanitize_text_field( $args['description'] ?? '' ),
				'discount_type'  => $discount_type,
				'discount_value' => $discount_value,
				'course_id'      => $course_id,
				'max_uses'       => max( 0, absint( $args['max_uses'] ?? 0 ) ),
				'used_count'     => 0,
				'min_amount'     => max( 0, (float) ( $args['min_amount'] ?? 0 ) ),
				'starts_at'      => self::sanitize_datetime( $args['starts_at'] ?? '' ),
				'expires_at'     => self::sanitize_datetime( $args['expires_at'] ?? '' ),
				'is_active'      => empty( $args['is_active'] ) && isset( $args['is_active'] ) ? 0 : 1,
				'created_by'     => absint( $args['created_by'] ?? get_current_user_id() ) ?: null,
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%f', '%d', '%d', '%d', '%f', '%s', '%s', '%d', '%d', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'gcm_coupon_failed', __( 'Unable to save coupon.', 'giga-class-market' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a coupon.
	 *
	 * @param int   $id Coupon ID.
	 * @param array $args Fields to update.
	 * @return true|WP_Error
	 */
	public static function update( $id, $args ) {
		global $wpdb;

		$id     = absint( $id );
		$coupon = self::get( $id );
		if ( ! $coupon ) {
			return new WP_Error( 'gcm_invalid_coupon', __( 'Coupon not found.', 'giga-class-market' ) );
		}

		$data   = array();
		$format = array();

		if ( isset( $args['code'] ) ) {
			$code = self::normalize_code( $args['code'] );
			if ( '' === $code ) {
				return new WP_Error( 'gcm_invalid_code', __( 'Enter a valid coupon code (letters and numbers only).', 'giga-class-market' ) );
			}
			$existing = self::get_by_code( $code );
			if ( $existing && (int) $existing->id !== $id ) {
				return new WP_Error( 'gcm_code_exists', __( 'That coupon code already exists.', 'giga-class-market' ) );
			}
			$data['code'] = $code;
			$format[]     = '%s';
		}

		if ( array_key_exists( 'description', $args ) ) {
			$data['description'] = sanitize_text_field( $args['description'] );
			$format[]            = '%s';
		}

		if ( isset( $args['discount_type'] ) ) {
			$data['discount_type'] = self::sanitize_discount_type( $args['discount_type'] );
			$format[]              = '%s';
		}

		if ( isset( $args['discount_value'] ) ) {
			$discount_value = max( 0, (float) $args['discount_value'] );
			$type           = isset( $data['discount_type'] ) ? $data['discount_type'] : $coupon->discount_type;
			if ( $discount_value <= 0 ) {
				return new WP_Error( 'gcm_invalid_discount', __( 'Discount value must be greater than zero.', 'giga-class-market' ) );
			}
			if ( 'percent' === $type && $discount_value > 100 ) {
				return new WP_Error( 'gcm_invalid_discount', __( 'Percent discount cannot exceed 100.', 'giga-class-market' ) );
			}
			$data['discount_value'] = $discount_value;
			$format[]               = '%f';
		}

		if ( array_key_exists( 'course_id', $args ) ) {
			$course_id = $args['course_id'] ? absint( $args['course_id'] ) : null;
			if ( $course_id && ! get_post( $course_id ) ) {
				return new WP_Error( 'gcm_invalid_course', __( 'Invalid course.', 'giga-class-market' ) );
			}
			$data['course_id'] = $course_id;
			$format[]          = '%d';
		}

		if ( isset( $args['max_uses'] ) ) {
			$data['max_uses'] = max( 0, absint( $args['max_uses'] ) );
			$format[]         = '%d';
		}

		if ( isset( $args['min_amount'] ) ) {
			$data['min_amount'] = max( 0, (float) $args['min_amount'] );
			$format[]           = '%f';
		}

		if ( array_key_exists( 'starts_at', $args ) ) {
			$data['starts_at'] = self::sanitize_datetime( $args['starts_at'] );
			$format[]          = '%s';
		}

		if ( array_key_exists( 'expires_at', $args ) ) {
			$data['expires_at'] = self::sanitize_datetime( $args['expires_at'] );
			$format[]           = '%s';
		}

		if ( isset( $args['is_active'] ) ) {
			$data['is_active'] = $args['is_active'] ? 1 : 0;
			$format[]          = '%d';
		}

		if ( empty( $data ) ) {
			return true;
		}

		$updated = $wpdb->update(
			$wpdb->prefix . 'gcm_coupons',
			$data,
			array( 'id' => $id ),
			$format,
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'gcm_coupon_failed', __( 'Unable to update coupon.', 'giga-class-market' ) );
		}

		return true;
	}

	/**
	 * Delete a coupon and its use records.
	 *
	 * @param int $id Coupon ID.
	 * @return true|WP_Error
	 */
	public static function delete( $id ) {
		global $wpdb;

		$id = absint( $id );
		if ( ! self::get( $id ) ) {
			return new WP_Error( 'gcm_invalid_coupon', __( 'Coupon not found.', 'giga-class-market' ) );
		}

		$wpdb->delete( $wpdb->prefix . 'gcm_coupon_uses', array( 'coupon_id' => $id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'gcm_coupons', array( 'id' => $id ), array( '%d' ) );

		return true;
	}

	/**
	 * Get coupon by ID.
	 *
	 * @param int $id Coupon ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_coupons WHERE id = %d LIMIT 1",
				absint( $id )
			)
		);
	}

	/**
	 * Get coupon by code.
	 *
	 * @param string $code Coupon code.
	 * @return object|null
	 */
	public static function get_by_code( $code ) {
		global $wpdb;

		$code = self::normalize_code( $code );
		if ( '' === $code ) {
			return null;
		}

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_coupons WHERE code = %s LIMIT 1",
				$code
			)
		);
	}

	/**
	 * List coupons.
	 *
	 * @param int $limit Max rows.
	 * @return array
	 */
	public static function get_all( $limit = 100 ) {
		global $wpdb;

		$limit = max( 1, min( 500, absint( $limit ) ) );

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_coupons ORDER BY created_at DESC LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * Validate a coupon for a course checkout.
	 *
	 * @param string $code Coupon code.
	 * @param int    $course_id Course ID.
	 * @param int    $user_id Optional user ID.
	 * @return array|WP_Error Keys: coupon, discount_amount, final_price.
	 */
	public static function validate_for_course( $code, $course_id, $user_id = 0 ) {
		$course_id = absint( $course_id );
		$user_id   = absint( $user_id );
		$coupon    = self::get_by_code( $code );

		if ( ! $coupon ) {
			return new WP_Error( 'gcm_invalid_coupon', __( 'Coupon not found.', 'giga-class-market' ) );
		}

		if ( empty( $coupon->is_active ) ) {
			return new WP_Error( 'gcm_coupon_inactive', __( 'This coupon is inactive.', 'giga-class-market' ) );
		}

		$now = current_time( 'mysql' );
		if ( ! empty( $coupon->starts_at ) && $coupon->starts_at > $now ) {
			return new WP_Error( 'gcm_coupon_not_started', __( 'This coupon is not active yet.', 'giga-class-market' ) );
		}
		if ( ! empty( $coupon->expires_at ) && $coupon->expires_at < $now ) {
			return new WP_Error( 'gcm_coupon_expired', __( 'This coupon has expired.', 'giga-class-market' ) );
		}

		if ( (int) $coupon->max_uses > 0 && (int) $coupon->used_count >= (int) $coupon->max_uses ) {
			return new WP_Error( 'gcm_coupon_exhausted', __( 'This coupon has reached its usage limit.', 'giga-class-market' ) );
		}

		if ( ! empty( $coupon->course_id ) && (int) $coupon->course_id !== $course_id ) {
			return new WP_Error( 'gcm_coupon_wrong_course', __( 'This coupon does not apply to this course.', 'giga-class-market' ) );
		}

		$price = self::get_course_price( $course_id );
		if ( $price < 0 ) {
			return new WP_Error( 'gcm_invalid_course', __( 'Invalid course.', 'giga-class-market' ) );
		}

		if ( (float) $coupon->min_amount > 0 && $price < (float) $coupon->min_amount ) {
			return new WP_Error( 'gcm_coupon_min_amount', __( 'Order total is below the coupon minimum.', 'giga-class-market' ) );
		}

		$discount = self::calculate_discount( $coupon, $price );
		$final    = max( 0, round( $price - $discount, 2 ) );

		unset( $user_id ); // Reserved for per-user limits in future.

		return array(
			'coupon'          => $coupon,
			'discount_amount' => $discount,
			'final_price'     => $final,
		);
	}

	/**
	 * Record coupon use against a payment (via coupon_uses table).
	 *
	 * @param int   $coupon_id Coupon ID.
	 * @param int   $payment_id Payment ID.
	 * @param int   $user_id User ID.
	 * @param int   $course_id Course ID.
	 * @param float $discount_amount Discount applied.
	 * @return int|WP_Error Use row ID.
	 */
	public static function apply_to_payment( $coupon_id, $payment_id, $user_id, $course_id, $discount_amount ) {
		global $wpdb;

		$coupon_id       = absint( $coupon_id );
		$payment_id      = absint( $payment_id );
		$user_id         = absint( $user_id );
		$course_id       = absint( $course_id );
		$discount_amount = max( 0, (float) $discount_amount );

		$coupon = self::get( $coupon_id );
		if ( ! $coupon ) {
			return new WP_Error( 'gcm_invalid_coupon', __( 'Coupon not found.', 'giga-class-market' ) );
		}

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'gcm_coupon_uses',
			array(
				'coupon_id'       => $coupon_id,
				'user_id'         => $user_id ? $user_id : null,
				'payment_id'      => $payment_id ? $payment_id : null,
				'course_id'       => $course_id,
				'discount_amount' => $discount_amount,
				'used_at'         => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%d', '%f', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'gcm_coupon_use_failed', __( 'Unable to record coupon use.', 'giga-class-market' ) );
		}

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}gcm_coupons SET used_count = used_count + 1 WHERE id = %d",
				$coupon_id
			)
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Effective course price (sale price if valid).
	 *
	 * @param int $course_id Course ID.
	 * @return float Negative when course missing.
	 */
	public static function get_course_price( $course_id ) {
		$course_id = absint( $course_id );
		$post      = get_post( $course_id );
		if ( ! $post || 'gcm_course' !== $post->post_type ) {
			return -1.0;
		}

		$regular = (float) get_post_meta( $course_id, '_gcm_price', true );
		$sale    = (float) get_post_meta( $course_id, '_gcm_discount_price', true );

		if ( $sale > 0 && $sale < $regular ) {
			return round( $sale, 2 );
		}

		return round( max( 0, $regular ), 2 );
	}

	/**
	 * Normalize coupon codes to uppercase alphanumeric.
	 *
	 * @param string $code Raw code.
	 * @return string
	 */
	public static function normalize_code( $code ) {
		$code = strtoupper( trim( (string) $code ) );
		$code = preg_replace( '/[^A-Z0-9]/', '', $code );
		return is_string( $code ) ? $code : '';
	}

	/**
	 * Calculate discount amount for a price.
	 *
	 * @param object $coupon Coupon row.
	 * @param float  $price Course price.
	 * @return float
	 */
	private static function calculate_discount( $coupon, $price ) {
		$price = (float) $price;
		$value = (float) $coupon->discount_value;

		if ( 'fixed' === $coupon->discount_type ) {
			$discount = min( $price, $value );
		} else {
			$discount = $price * ( min( 100, $value ) / 100 );
		}

		return round( max( 0, $discount ), 2 );
	}

	/**
	 * Sanitize discount type.
	 *
	 * @param string $type Type.
	 * @return string
	 */
	private static function sanitize_discount_type( $type ) {
		$type = sanitize_key( $type );
		return in_array( $type, array( 'percent', 'fixed' ), true ) ? $type : 'percent';
	}

	/**
	 * Sanitize optional datetime or null.
	 *
	 * @param string $value Datetime string.
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
