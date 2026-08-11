<?php
/**
 * Plugin activation tasks.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles activation.
 */
class GCM_Activator {

	/**
	 * Activate plugin.
	 *
	 * @return void
	 */
	public static function activate() {
		GCM_Installer::install();

		$roles = new GCM_Roles();
		$roles->register();

		$post_types = new GCM_Post_Types();
		$post_types->register();

		self::create_pages();

		flush_rewrite_rules();
	}

	/**
	 * Create required public pages when they do not exist.
	 *
	 * @return void
	 */
	private static function create_pages() {
		$pages = array(
			'home'                 => array(
				'title'    => __( 'Home', 'giga-class-market' ),
				'content'  => '[gcm_courses featured="1"]',
				'template' => 'templates/gcm-home.php',
			),
			'about'                => array(
				'title'    => __( 'About', 'giga-class-market' ),
				'content'  => '',
				'template' => 'templates/gcm-about.php',
			),
			'contact'              => array(
				'title'    => __( 'Contact', 'giga-class-market' ),
				'content'  => '[gcm_contact_form]',
				'template' => 'templates/gcm-contact.php',
			),
			'courses'              => array(
				'title'    => __( 'Courses', 'giga-class-market' ),
				'content'  => '[gcm_courses]',
				'template' => 'templates/gcm-courses.php',
			),
			'login'                => array(
				'title'    => __( 'Login', 'giga-class-market' ),
				'content'  => '[gcm_login_form]',
				'template' => 'templates/gcm-login.php',
			),
			'student-dashboard'    => array(
				'title'    => __( 'Student Dashboard', 'giga-class-market' ),
				'content'  => '[gcm_student_dashboard]',
				'template' => 'templates/gcm-student-dashboard.php',
			),
			'payment'              => array(
				'title'    => __( 'Payment', 'giga-class-market' ),
				'content'  => '[gcm_payment_form]',
				'template' => 'templates/gcm-payment.php',
			),
			'payment-verification' => array(
				'title'    => __( 'Payment Verification', 'giga-class-market' ),
				'content'  => '[gcm_payment_verification]',
				'template' => 'templates/gcm-payment-verification.php',
			),
			'privacy-policy'       => array(
				'title'    => __( 'Privacy Policy', 'giga-class-market' ),
				'content'  => '',
				'template' => 'templates/gcm-privacy.php',
			),
			'terms'                => array(
				'title'    => __( 'Terms', 'giga-class-market' ),
				'content'  => '',
				'template' => 'templates/gcm-terms.php',
			),
		);

		foreach ( $pages as $slug => $page ) {
			$existing = get_page_by_path( $slug );

			if ( $existing ) {
				update_post_meta( $existing->ID, '_wp_page_template', $page['template'] );
				continue;
			}

			$page_id = wp_insert_post(
				array(
					'post_title'   => $page['title'],
					'post_name'    => $slug,
					'post_content' => $page['content'],
					'post_status'  => 'publish',
					'post_type'    => 'page',
				)
			);

			if ( ! is_wp_error( $page_id ) && $page_id ) {
				update_post_meta( $page_id, '_wp_page_template', $page['template'] );

				if ( 'home' === $slug ) {
					update_option( 'show_on_front', 'page' );
					update_option( 'page_on_front', (int) $page_id );
				}
			}
		}
	}
}
