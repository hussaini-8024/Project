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
	public static function create_pages() {
		$pages = array(
			'home'                 => array(
				'title'    => __( 'Home', 'giga-class-market' ),
				'content'  => '',
				'template' => '',
			),
			'about'                => array(
				'title'    => __( 'About', 'giga-class-market' ),
				'content'  => '',
				'template' => 'page-templates/template-about.php',
			),
			'contact'              => array(
				'title'    => __( 'Contact', 'giga-class-market' ),
				'content'  => '',
				'template' => 'page-templates/template-contact.php',
			),
			'courses'              => array(
				'title'    => __( 'Courses', 'giga-class-market' ),
				'content'  => '',
				'template' => '',
				'skip'     => true, // CPT archive owns /courses/
			),
			'login'                => array(
				'title'    => __( 'Login', 'giga-class-market' ),
				'content'  => '',
				'template' => 'page-templates/template-login.php',
			),
			'student-dashboard'    => array(
				'title'    => __( 'Student Dashboard', 'giga-class-market' ),
				'content'  => '',
				'template' => 'page-templates/template-student-dashboard.php',
			),
			'teacher-dashboard'    => array(
				'title'    => __( 'Teacher Dashboard', 'giga-class-market' ),
				'content'  => '',
				'template' => 'page-templates/template-teacher-dashboard.php',
			),
			'live-class'           => array(
				'title'    => __( 'Live Class', 'giga-class-market' ),
				'content'  => '',
				'template' => 'page-templates/template-live-class.php',
			),
			'course-learn'         => array(
				'title'    => __( 'Course Learn', 'giga-class-market' ),
				'content'  => '',
				'template' => 'page-templates/template-course-learn.php',
			),
			'payment'              => array(
				'title'    => __( 'Payment', 'giga-class-market' ),
				'content'  => '',
				'template' => 'page-templates/template-payment.php',
			),
			'payment-verification' => array(
				'title'    => __( 'Payment Verification', 'giga-class-market' ),
				'content'  => '',
				'template' => 'page-templates/template-payment-verify.php',
			),
			'privacy-policy'       => array(
				'title'    => __( 'Privacy Policy', 'giga-class-market' ),
				'content'  => '',
				'template' => 'page-templates/template-privacy.php',
			),
			'terms'                => array(
				'title'    => __( 'Terms & Conditions', 'giga-class-market' ),
				'content'  => '',
				'template' => 'page-templates/template-terms.php',
			),
			'verify-certificate'   => array(
				'title'    => __( 'Verify Certificate', 'giga-class-market' ),
				'content'  => '',
				'template' => 'page-templates/template-verify-certificate.php',
			),
		);

		foreach ( $pages as $slug => $page ) {
			if ( ! empty( $page['skip'] ) ) {
				continue;
			}

			$existing = get_page_by_path( $slug );

			if ( $existing ) {
				if ( ! empty( $page['template'] ) ) {
					update_post_meta( $existing->ID, '_wp_page_template', $page['template'] );
				}
				if ( 'home' === $slug ) {
					update_option( 'show_on_front', 'page' );
					update_option( 'page_on_front', (int) $existing->ID );
				}
				if ( 'courses' === $slug ) {
					update_option( 'gcm_courses_page_id', (int) $existing->ID );
				}
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
				if ( ! empty( $page['template'] ) ) {
					update_post_meta( $page_id, '_wp_page_template', $page['template'] );
				}

				if ( 'home' === $slug ) {
					update_option( 'show_on_front', 'page' );
					update_option( 'page_on_front', (int) $page_id );
				}
				if ( 'courses' === $slug ) {
					update_option( 'gcm_courses_page_id', (int) $page_id );
				}
			}
		}
	}
}
