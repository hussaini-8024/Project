<?php
/**
 * Course service.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Course CRUD/search helpers.
 */
class GCM_Course_Service {

	/**
	 * Create a course.
	 *
	 * @param array $data Course data.
	 * @return int|WP_Error
	 */
	public static function create( $data ) {
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'gcm_course',
				'post_title'   => sanitize_text_field( $data['title'] ?? '' ),
				'post_content' => wp_kses_post( $data['content'] ?? '' ),
				'post_excerpt' => sanitize_textarea_field( $data['excerpt'] ?? '' ),
				'post_status'  => sanitize_key( $data['status'] ?? 'draft' ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		self::save_meta( $post_id, $data );
		return (int) $post_id;
	}

	/**
	 * Update a course.
	 *
	 * @param int   $course_id Course ID.
	 * @param array $data Course data.
	 * @return int|WP_Error
	 */
	public static function update( $course_id, $data ) {
		$course_id = absint( $course_id );
		$post      = get_post( $course_id );

		if ( ! $post || 'gcm_course' !== $post->post_type ) {
			return new WP_Error( 'gcm_invalid_course', __( 'Invalid course.', 'giga-class-market' ) );
		}

		$post_data = array( 'ID' => $course_id );
		if ( isset( $data['title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $data['title'] );
		}
		if ( isset( $data['content'] ) ) {
			$post_data['post_content'] = wp_kses_post( $data['content'] );
		}
		if ( isset( $data['excerpt'] ) ) {
			$post_data['post_excerpt'] = sanitize_textarea_field( $data['excerpt'] );
		}
		if ( isset( $data['status'] ) ) {
			$post_data['post_status'] = sanitize_key( $data['status'] );
		}

		$result = wp_update_post( $post_data, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		self::save_meta( $course_id, $data );
		return $course_id;
	}

	/**
	 * Delete a course.
	 *
	 * @param int  $course_id Course ID.
	 * @param bool $force Force delete.
	 * @return bool
	 */
	public static function delete( $course_id, $force = false ) {
		$post = get_post( absint( $course_id ) );
		if ( ! $post || 'gcm_course' !== $post->post_type ) {
			return false;
		}

		return (bool) wp_delete_post( $post->ID, (bool) $force );
	}

	/**
	 * Get one course with meta.
	 *
	 * @param int $course_id Course ID.
	 * @return array|null
	 */
	public static function get( $course_id ) {
		$post = get_post( absint( $course_id ) );
		if ( ! $post || 'gcm_course' !== $post->post_type ) {
			return null;
		}

		return self::format_course( $post );
	}

	/**
	 * Get featured courses.
	 *
	 * @param int $limit Limit.
	 * @return array
	 */
	/**
	 * Theme-compatible featured courses alias.
	 *
	 * @param int $limit Limit.
	 * @return array
	 */
	public static function get_featured_courses( $limit = 3 ) {
		return self::get_featured( $limit );
	}

	/**
	 * Get featured courses (max 3 by default).
	 *
	 * @param int $limit Limit.
	 * @return array
	 */
	public static function get_featured( $limit = 3 ) {
		global $wpdb;

		$limit = min( 3, max( 1, absint( $limit ) ) );
		$sql   = $wpdb->prepare(
			"SELECT p.ID
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} featured ON featured.post_id = p.ID AND featured.meta_key = %s AND featured.meta_value = %s
			LEFT JOIN {$wpdb->postmeta} priority ON priority.post_id = p.ID AND priority.meta_key = %s
			WHERE p.post_type = %s AND p.post_status = %s
			ORDER BY CAST(priority.meta_value AS UNSIGNED) DESC, p.post_date DESC
			LIMIT %d",
			'_gcm_featured',
			'1',
			'_gcm_featured_priority',
			'gcm_course',
			'publish',
			$limit
		);

		$ids = $wpdb->get_col( $sql );
		return array_values( array_filter( array_map( array( __CLASS__, 'get' ), $ids ) ) );
	}

	/**
	 * Search/filter published courses.
	 *
	 * @param array $args Search args.
	 * @return array
	 */
	public static function search( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'search'   => '',
				'category' => '',
				'featured' => null,
				'limit'    => 12,
				'offset'   => 0,
				'orderby'  => 'date',
				'order'    => 'DESC',
			)
		);

		$where  = array( $wpdb->prepare( 'p.post_type = %s', 'gcm_course' ), $wpdb->prepare( 'p.post_status = %s', 'publish' ) );
		$joins  = array();
		$params = array();

		if ( '' !== $args['search'] ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = '(p.post_title LIKE %s OR p.post_content LIKE %s OR p.post_excerpt LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		if ( '' !== $args['category'] ) {
			$joins[] = "INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID";
			$joins[] = "INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'gcm_category'";
			$joins[] = "INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id";
			if ( is_numeric( $args['category'] ) ) {
				$where[]  = 't.term_id = %d';
				$params[] = absint( $args['category'] );
			} else {
				$where[]  = 't.slug = %s';
				$params[] = sanitize_title( $args['category'] );
			}
		}

		if ( null !== $args['featured'] ) {
			$joins[] = "INNER JOIN {$wpdb->postmeta} fm ON fm.post_id = p.ID AND fm.meta_key = '_gcm_featured'";
			$where[] = 'fm.meta_value = %s';
			$params[] = $args['featured'] ? '1' : '0';
		}

		$allowed_orderby = array(
			'title' => 'p.post_title',
			'date'  => 'p.post_date',
			'price' => 'CAST(pm_price.meta_value AS DECIMAL(12,2))',
		);
		$orderby         = $allowed_orderby[ $args['orderby'] ] ?? $allowed_orderby['date'];
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		if ( 'price' === $args['orderby'] ) {
			$joins[] = "LEFT JOIN {$wpdb->postmeta} pm_price ON pm_price.post_id = p.ID AND pm_price.meta_key = '_gcm_price'";
		}

		$limit    = min( 50, max( 1, absint( $args['limit'] ) ) );
		$offset   = max( 0, absint( $args['offset'] ) );
		$join_sql = implode( ' ', array_unique( $joins ) );
		$sql      = "SELECT DISTINCT p.ID FROM {$wpdb->posts} p {$join_sql} WHERE " . implode( ' AND ', $where ) . " ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$params[] = $limit;
		$params[] = $offset;

		$ids = $wpdb->get_col( $wpdb->prepare( $sql, $params ) );
		return array_values( array_filter( array_map( array( __CLASS__, 'get' ), $ids ) ) );
	}

	/**
	 * Save course meta.
	 *
	 * @param int   $course_id Course ID.
	 * @param array $data Data.
	 * @return void
	 */
	private static function save_meta( $course_id, $data ) {
		$map = array(
			'price'          => '_gcm_price',
			'duration'       => '_gcm_duration',
			'instructor'     => '_gcm_instructor',
			'what_you_learn' => '_gcm_what_you_learn',
			'requirements'   => '_gcm_requirements',
			'rating'         => '_gcm_rating',
		);

		foreach ( $map as $field => $meta_key ) {
			if ( ! array_key_exists( $field, $data ) ) {
				continue;
			}
			$value = $data[ $field ];
			if ( in_array( $field, array( 'price', 'rating' ), true ) ) {
				$value = max( 0, (float) $value );
			} elseif ( in_array( $field, array( 'what_you_learn', 'requirements' ), true ) ) {
				$value = sanitize_textarea_field( $value );
			} else {
				$value = sanitize_text_field( $value );
			}
			update_post_meta( $course_id, $meta_key, $value );
		}

		if ( array_key_exists( 'featured', $data ) ) {
			GCM_Post_Types::set_featured( $course_id, ! empty( $data['featured'] ) );
		}
	}

	/**
	 * Format course data.
	 *
	 * @param WP_Post $post Post.
	 * @return array
	 */
	private static function format_course( $post ) {
		return array(
			'id'              => (int) $post->ID,
			'title'           => get_the_title( $post ),
			'content'         => apply_filters( 'the_content', $post->post_content ),
			'excerpt'         => get_the_excerpt( $post ),
			'permalink'       => get_permalink( $post ),
			'thumbnail'       => get_the_post_thumbnail_url( $post, 'large' ),
			'price'             => (float) get_post_meta( $post->ID, '_gcm_price', true ),
			'discount_price'    => (float) get_post_meta( $post->ID, '_gcm_discount_price', true ),
			'sale_label'        => (string) get_post_meta( $post->ID, '_gcm_sale_label', true ),
			'effective_price'   => class_exists( 'GCM_Coupon_Service' ) ? GCM_Coupon_Service::get_course_price( $post->ID ) : (float) get_post_meta( $post->ID, '_gcm_price', true ),
			'duration'          => (string) get_post_meta( $post->ID, '_gcm_duration', true ),
			'instructor'        => (string) get_post_meta( $post->ID, '_gcm_instructor', true ),
			'what_you_learn'    => (string) get_post_meta( $post->ID, '_gcm_what_you_learn', true ),
			'requirements'      => (string) get_post_meta( $post->ID, '_gcm_requirements', true ),
			'featured'          => (bool) get_post_meta( $post->ID, '_gcm_featured', true ),
			'featured_priority' => (int) get_post_meta( $post->ID, '_gcm_featured_priority', true ),
			'rating'            => (float) get_post_meta( $post->ID, '_gcm_rating', true ),
			'categories'        => wp_get_post_terms( $post->ID, 'gcm_category', array( 'fields' => 'names' ) ),
		);
	}
}
