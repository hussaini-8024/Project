<?php
/**
 * Blog queries and helpers for the public /blogs/ experience.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Blog listing helpers.
 */
class GCM_Blog_Service {

	/**
	 * Estimate reading time in minutes from post content.
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	public static function reading_minutes( $post_id ) {
		$content = get_post_field( 'post_content', absint( $post_id ) );
		$words   = str_word_count( wp_strip_all_tags( (string) $content ) );
		return max( 1, (int) ceil( $words / 200 ) );
	}

	/**
	 * Increment view counter once per visitor session.
	 *
	 * @param int $post_id Blog ID.
	 * @return void
	 */
	public static function track_view( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id || is_admin() || wp_is_post_revision( $post_id ) ) {
			return;
		}
		$key = 'gcm_blog_viewed_' . $post_id;
		if ( ! empty( $_COOKIE[ $key ] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return;
		}
		$views = (int) get_post_meta( $post_id, '_gcm_blog_views', true );
		update_post_meta( $post_id, '_gcm_blog_views', $views + 1 );
		if ( ! headers_sent() ) {
			setcookie( $key, '1', time() + DAY_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
		}
	}

	/**
	 * Query blogs with optional filters.
	 *
	 * @param array $args Query args.
	 * @return WP_Post[]
	 */
	public static function query( $args = array() ) {
		$defaults = array(
			'post_type'              => 'gcm_blog',
			'post_status'            => 'publish',
			'posts_per_page'         => 9,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
		);
		$query = new WP_Query( array_merge( $defaults, $args ) );
		return $query->posts;
	}

	/**
	 * Featured blogs for the /blogs/ hero.
	 *
	 * @param int $limit Limit.
	 * @return WP_Post[]
	 */
	public static function get_featured( $limit = 3 ) {
		return self::query(
			array(
				'posts_per_page' => absint( $limit ),
				'meta_key'       => '_gcm_blog_featured',
				'meta_value'     => '1',
			)
		);
	}

	/**
	 * Top reading blogs (manual flag, then by views).
	 *
	 * @param int $limit Limit.
	 * @return WP_Post[]
	 */
	public static function get_top_reads( $limit = 4 ) {
		$flagged = self::query(
			array(
				'posts_per_page' => absint( $limit ),
				'meta_key'       => '_gcm_blog_top_read',
				'meta_value'     => '1',
			)
		);
		if ( count( $flagged ) >= $limit ) {
			return $flagged;
		}

		$exclude = wp_list_pluck( $flagged, 'ID' );
		$by_views = self::query(
			array(
				'posts_per_page' => absint( $limit ) - count( $flagged ),
				'post__not_in'   => $exclude,
				'meta_key'       => '_gcm_blog_views',
				'orderby'        => 'meta_value_num',
				'order'          => 'DESC',
			)
		);

		return array_merge( $flagged, $by_views );
	}

	/**
	 * Latest blogs.
	 *
	 * @param int   $limit Limit.
	 * @param int[] $exclude IDs to skip.
	 * @return WP_Post[]
	 */
	public static function get_latest( $limit = 9, $exclude = array() ) {
		$args = array( 'posts_per_page' => absint( $limit ) );
		if ( ! empty( $exclude ) ) {
			$args['post__not_in'] = array_map( 'absint', $exclude );
		}
		return self::query( $args );
	}

	/**
	 * All blog categories.
	 *
	 * @return WP_Term[]
	 */
	public static function get_categories() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'gcm_blog_category',
				'hide_empty' => false,
			)
		);
		return is_wp_error( $terms ) ? array() : $terms;
	}

	/**
	 * Related course ID for a blog.
	 *
	 * @param int $blog_id Blog ID.
	 * @return int
	 */
	public static function get_related_course_id( $blog_id ) {
		return absint( get_post_meta( absint( $blog_id ), '_gcm_related_course_id', true ) );
	}

	/**
	 * Related blogs in same category.
	 *
	 * @param int $blog_id Blog ID.
	 * @param int $limit Limit.
	 * @return WP_Post[]
	 */
	public static function get_related( $blog_id, $limit = 3 ) {
		$blog_id = absint( $blog_id );
		$terms   = wp_get_post_terms( $blog_id, 'gcm_blog_category', array( 'fields' => 'ids' ) );
		$args    = array(
			'posts_per_page' => absint( $limit ),
			'post__not_in'   => array( $blog_id ),
		);
		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'gcm_blog_category',
					'field'    => 'term_id',
					'terms'    => $terms,
				),
			);
		}
		return self::query( $args );
	}
}
