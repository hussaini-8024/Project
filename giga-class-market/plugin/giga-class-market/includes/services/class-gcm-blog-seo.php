<?php
/**
 * Blog SEO helpers — titles, descriptions, focus keywords.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Seeds and resolves SEO meta for blog posts.
 */
class GCM_Blog_SEO {

	/**
	 * Ensure SEO fields exist for a blog.
	 *
	 * @param int $blog_id Blog ID.
	 * @return bool
	 */
	public static function ensure_blog( $blog_id ) {
		$blog_id = absint( $blog_id );
		if ( ! $blog_id || 'gcm_blog' !== get_post_type( $blog_id ) ) {
			return false;
		}

		$changed = false;
		$title   = (string) get_post_meta( $blog_id, '_gcm_seo_title', true );
		if ( '' === trim( $title ) ) {
			update_post_meta( $blog_id, '_gcm_seo_title', self::build_seo_title( $blog_id ) );
			$changed = true;
		}

		$desc = (string) get_post_meta( $blog_id, '_gcm_seo_description', true );
		if ( '' === trim( $desc ) ) {
			update_post_meta( $blog_id, '_gcm_seo_description', self::build_seo_description( $blog_id ) );
			$changed = true;
		}

		$keyword = (string) get_post_meta( $blog_id, '_gcm_seo_focus_keyword', true );
		if ( '' === trim( $keyword ) ) {
			update_post_meta( $blog_id, '_gcm_seo_focus_keyword', self::build_focus_keyword( $blog_id ) );
			$changed = true;
		}

		return $changed;
	}

	/**
	 * Resolve SEO title.
	 *
	 * @param int $blog_id Blog ID.
	 * @return string
	 */
	public static function resolve_seo_title( $blog_id ) {
		$custom = trim( (string) get_post_meta( $blog_id, '_gcm_seo_title', true ) );
		return $custom ? $custom : self::build_seo_title( $blog_id );
	}

	/**
	 * Resolve SEO description.
	 *
	 * @param int $blog_id Blog ID.
	 * @return string
	 */
	public static function resolve_seo_description( $blog_id ) {
		$custom = trim( (string) get_post_meta( $blog_id, '_gcm_seo_description', true ) );
		return $custom ? $custom : self::build_seo_description( $blog_id );
	}

	/**
	 * Resolve focus keyword.
	 *
	 * @param int $blog_id Blog ID.
	 * @return string
	 */
	public static function resolve_focus_keyword( $blog_id ) {
		$custom = trim( (string) get_post_meta( $blog_id, '_gcm_seo_focus_keyword', true ) );
		return $custom ? $custom : self::build_focus_keyword( $blog_id );
	}

	/**
	 * Build SEO title.
	 *
	 * @param int $blog_id Blog ID.
	 * @return string
	 */
	public static function build_seo_title( $blog_id ) {
		$post_title = get_the_title( $blog_id );
		$cats       = get_the_terms( $blog_id, 'gcm_blog_category' );
		$cat_name   = ( $cats && ! is_wp_error( $cats ) ) ? $cats[0]->name : '';
		if ( $cat_name ) {
			return self::clip( sprintf( '%s | %s | Giga Class Market', $post_title, $cat_name ), 65 );
		}
		return self::clip( sprintf( '%s | Blog | Giga Class Market', $post_title ), 65 );
	}

	/**
	 * Build meta description.
	 *
	 * @param int $blog_id Blog ID.
	 * @return string
	 */
	public static function build_seo_description( $blog_id ) {
		$excerpt = get_the_excerpt( $blog_id );
		if ( ! $excerpt ) {
			$excerpt = wp_trim_words( wp_strip_all_tags( (string) get_post_field( 'post_content', $blog_id ) ), 28 );
		}
		$title = get_the_title( $blog_id );
		$text  = sprintf(
			'%s — practical guide from Giga Class Market. %s Read more and explore related online courses.',
			$title,
			$excerpt
		);
		return self::clip( $text, 155 );
	}

	/**
	 * Build focus keyword from title + category.
	 *
	 * @param int $blog_id Blog ID.
	 * @return string
	 */
	public static function build_focus_keyword( $blog_id ) {
		$title = strtolower( wp_strip_all_tags( get_the_title( $blog_id ) ) );
		$title = trim( preg_replace( '/[^a-z0-9\s\-]+/', '', $title ) );
		$cats  = get_the_terms( $blog_id, 'gcm_blog_category' );
		if ( $cats && ! is_wp_error( $cats ) ) {
			$cat = strtolower( $cats[0]->name );
			if ( false === strpos( $title, $cat ) ) {
				$title .= ' ' . $cat;
			}
		}
		return self::clip( $title ? $title : 'online learning blog', 60 );
	}

	/**
	 * Clip text.
	 *
	 * @param string $text Text.
	 * @param int    $max Max length.
	 * @return string
	 */
	private static function clip( $text, $max ) {
		$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $text ) ) );
		if ( strlen( $text ) <= $max ) {
			return $text;
		}
		$cut = substr( $text, 0, $max - 1 );
		$sp  = strrpos( $cut, ' ' );
		if ( false !== $sp && $sp > (int) ( $max * 0.6 ) ) {
			$cut = substr( $cut, 0, $sp );
		}
		return rtrim( $cut, '.,;:|- ' ) . '…';
	}
}
