<?php
/**
 * Roles and capabilities.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers plugin roles/capabilities.
 */
class GCM_Roles {

	/**
	 * Student capabilities.
	 *
	 * @var string[]
	 */
	private $student_caps = array(
		'read',
		'gcm_access_dashboard',
		'gcm_view_courses',
		'gcm_update_profile',
	);

	/**
	 * Administrator capabilities.
	 *
	 * @var string[]
	 */
	private $admin_caps = array(
		'gcm_manage_courses',
		'gcm_manage_students',
		'gcm_manage_payments',
		'gcm_manage_contacts',
		'gcm_manage_settings',
		'gcm_view_dashboard',
		'gcm_manage_testimonials',
	);

	/**
	 * Register role and caps.
	 *
	 * @return void
	 */
	public function register() {
		$role = get_role( 'gcm_student' );

		if ( ! $role ) {
			add_role(
				'gcm_student',
				__( 'GCM Student', 'giga-class-market' ),
				array_fill_keys( $this->student_caps, true )
			);
		} else {
			foreach ( $this->student_caps as $cap ) {
				$role->add_cap( $cap );
			}
		}

		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			foreach ( $this->admin_caps as $cap ) {
				$administrator->add_cap( $cap );
			}
		}
	}
}
