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
		GCM_Installer::maybe_upgrade();

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
		add_filter( 'login_url', array( $frontend, 'filter_login_url' ), 10, 3 );
		add_action( 'login_init', array( $frontend, 'redirect_wp_login_to_branded' ) );
		add_action( 'admin_init', array( $frontend, 'redirect_students_from_admin' ) );
		add_filter( 'show_admin_bar', array( $frontend, 'maybe_hide_admin_bar' ) );
		add_action( 'wp_login', array( $frontend, 'force_session_login_cookie' ), 20, 2 );

		// Force branded From address (replaces wordpress@domain default).
		add_filter( 'wp_mail_from', array( 'GCM_Notification_Service', 'filter_mail_from' ) );
		add_filter( 'wp_mail_from_name', array( 'GCM_Notification_Service', 'filter_mail_from_name' ) );

		add_action( 'add_meta_boxes', array( $post_types, 'register_meta_boxes' ) );
		add_action( 'add_meta_boxes', array( $post_types, 'promote_course_thumbnail_box' ), 20 );
		add_action( 'save_post_gcm_course', array( $post_types, 'save_course_meta' ), 10, 2 );
		add_filter( 'wp_insert_post_data', array( $post_types, 'require_course_thumbnail_on_publish' ), 20, 2 );
		add_action( 'admin_notices', array( $post_types, 'course_thumbnail_admin_notices' ) );
		add_filter( 'manage_gcm_course_posts_columns', array( $post_types, 'course_list_columns' ) );
		add_action( 'manage_gcm_course_posts_custom_column', array( $post_types, 'render_course_list_column' ), 10, 2 );
		add_filter( 'post_type_labels_gcm_course', array( $post_types, 'course_thumbnail_labels' ) );

		$ajax->register();

		add_action( 'init', array( 'GCM_Reminder_Service', 'schedule_hooks' ) );

		if ( is_admin() ) {
			$admin = new GCM_Admin();
			add_action( 'admin_menu', array( $admin, 'register_menus' ) );
			add_action( 'admin_enqueue_scripts', array( $admin, 'enqueue_assets' ) );
		}
	}
}
