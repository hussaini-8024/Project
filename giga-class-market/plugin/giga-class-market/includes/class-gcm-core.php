<?php
/**
 * Core plugin loader.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires plugin components to WordPress hooks.
 */
class GCM_Core {

	/**
	 * Run plugin hooks.
	 *
	 * @return void
	 */
	public function run() {
		$roles      = new GCM_Roles();
		$post_types = new GCM_Post_Types();
		$ajax       = new GCM_Ajax();
		$frontend   = new GCM_Frontend();

		add_action( 'init', array( $roles, 'register' ), 1 );
		add_action( 'init', array( $post_types, 'register' ) );
		add_action( 'init', array( $frontend, 'register_rewrites' ) );
		add_action( 'init', array( $frontend, 'register_shortcodes' ) );
		add_action( 'template_redirect', array( $frontend, 'protect_student_pages' ) );
		add_action( 'template_redirect', array( $frontend, 'serve_private_screenshot' ) );
		add_action( 'wp_enqueue_scripts', array( $frontend, 'enqueue_assets' ) );
		add_filter( 'wp_robots', array( $frontend, 'noindex_student_pages' ) );
		add_action( 'wp_head', array( $frontend, 'print_course_schema' ) );
		add_filter( 'login_redirect', array( $frontend, 'login_redirect' ), 10, 3 );
		add_action( 'admin_init', array( $frontend, 'redirect_students_from_admin' ) );

		add_action( 'add_meta_boxes', array( $post_types, 'register_meta_boxes' ) );
		add_action( 'save_post_gcm_course', array( $post_types, 'save_course_meta' ), 10, 2 );

		$ajax->register();

		if ( is_admin() ) {
			$admin = new GCM_Admin();
			add_action( 'admin_menu', array( $admin, 'register_menus' ) );
			add_action( 'admin_enqueue_scripts', array( $admin, 'enqueue_assets' ) );
		}
	}
}
