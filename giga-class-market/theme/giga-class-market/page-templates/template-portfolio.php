<?php
/**
 * Template Name: GCM Portfolio
 *
 * Legacy shared page — redirects to the Navyan portfolio URL when available.
 *
 * @package GigaClassMarket
 */

$navyan = get_posts(
	array(
		'post_type'      => 'gcm_portfolio',
		'name'           => 'navyan',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
	)
);

if ( ! empty( $navyan ) ) {
	wp_safe_redirect( get_permalink( $navyan[0] ), 301 );
	exit;
}

wp_safe_redirect( home_url( '/' ), 302 );
exit;
