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
 * Registers plugin roles/capabilities and keeps students out of normal WP user UX.
 */
class GCM_Roles {

	/**
	 * Student capabilities (GCM-only; not editors/authors).
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
	 * Register role, caps, and isolation hooks.
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
			// Strip common WP caps students should never have.
			foreach ( array( 'edit_posts', 'upload_files', 'publish_posts', 'delete_posts', 'edit_pages' ) as $cap ) {
				$role->remove_cap( $cap );
			}
		}

		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			foreach ( $this->admin_caps as $cap ) {
				$administrator->add_cap( $cap );
			}
		}

		add_action( 'pre_get_users', array( $this, 'exclude_students_from_users_list' ) );
		add_filter( 'views_users', array( $this, 'filter_users_views' ) );
		add_filter( 'editable_roles', array( $this, 'filter_editable_roles' ) );
		add_filter( 'wp_is_application_passwords_available_for_user', array( $this, 'disable_app_passwords_for_students' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'users_screen_notice' ) );
		add_filter( 'wp_send_new_user_notification_to_admin', array( $this, 'block_default_student_wp_emails' ), 10, 2 );
		add_filter( 'wp_send_new_user_notification_to_user', array( $this, 'block_default_student_wp_emails' ), 10, 2 );
	}

	/**
	 * Assign GCM Student as the only role (unless the account is a site admin).
	 *
	 * @param int|WP_User $user User ID or object.
	 * @return WP_User|false
	 */
	public static function assign_student_identity( $user ) {
		if ( ! ( $user instanceof WP_User ) ) {
			$user = get_userdata( absint( $user ) );
		}
		if ( ! $user ) {
			return false;
		}

		if ( user_can( $user, 'manage_options' ) ) {
			$user->add_role( 'gcm_student' );
		} else {
			$user->set_role( 'gcm_student' );
		}

		update_user_meta( $user->ID, 'gcm_is_student', 1 );
		update_user_meta( $user->ID, 'gcm_account_type', 'student' );

		return $user;
	}

	/**
	 * Whether a user is a GCM student (and not a WP admin).
	 *
	 * @param int|WP_User $user User.
	 * @return bool
	 */
	public static function is_gcm_student_only( $user ) {
		if ( ! ( $user instanceof WP_User ) ) {
			$user = get_userdata( absint( $user ) );
		}
		if ( ! $user ) {
			return false;
		}
		if ( user_can( $user, 'manage_options' ) ) {
			return false;
		}
		return in_array( 'gcm_student', (array) $user->roles, true );
	}

	/**
	 * Hide GCM students from the default WordPress Users list.
	 *
	 * @param WP_User_Query $query Query.
	 * @return void
	 */
	public function exclude_students_from_users_list( $query ) {
		if ( ! is_admin() || ! $query instanceof WP_User_Query ) {
			return;
		}

		// Allow explicit role filter / GCM screens to see students.
		$role = isset( $_REQUEST['role'] ) ? sanitize_key( wp_unslash( $_REQUEST['role'] ) ) : '';
		if ( 'gcm_student' === $role ) {
			return;
		}

		global $pagenow;
		if ( 'users.php' !== $pagenow && 'user-edit.php' !== $pagenow && 'user-new.php' !== $pagenow ) {
			// Also exclude from some AJAX user pickers in admin.
			if ( ! wp_doing_ajax() ) {
				return;
			}
		}

		$exclude_roles = (array) $query->get( 'role__not_in' );
		$exclude_roles[] = 'gcm_student';
		$query->set( 'role__not_in', array_values( array_unique( $exclude_roles ) ) );
	}

	/**
	 * Replace the GCM Student tab on Users with a link to the GCM Students panel.
	 *
	 * @param array $views Views.
	 * @return array
	 */
	public function filter_users_views( $views ) {
		if ( isset( $views['gcm_student'] ) ) {
			unset( $views['gcm_student'] );
		}
		$views['gcm_students_panel'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=gcm-students' ) ),
			esc_html__( 'GCM Students (manage here)', 'giga-class-market' )
		);
		return $views;
	}

	/**
	 * Do not offer GCM Student in the normal “Add User” role dropdown.
	 * Students are created only through payment approval / GCM Students.
	 *
	 * @param array $roles Roles.
	 * @return array
	 */
	public function filter_editable_roles( $roles ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && in_array( $screen->id, array( 'user', 'user-edit', 'user-new', 'users' ), true ) ) {
			unset( $roles['gcm_student'] );
		}
		return $roles;
	}

	/**
	 * Students should not use WordPress application passwords.
	 *
	 * @param bool    $available Available.
	 * @param WP_User $user User.
	 * @return bool
	 */
	public function disable_app_passwords_for_students( $available, $user ) {
		if ( self::is_gcm_student_only( $user ) ) {
			return false;
		}
		return $available;
	}

	/**
	 * Point admins to the GCM Students panel from Users.
	 *
	 * @return void
	 */
	public function users_screen_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'users' !== $screen->id ) {
			return;
		}
		if ( ! current_user_can( 'gcm_manage_students' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="notice notice-info"><p>';
		echo esc_html__( 'Giga Class Market students are not normal WordPress users. Manage them under Giga Class Market → Students.', 'giga-class-market' );
		echo ' <a href="' . esc_url( admin_url( 'admin.php?page=gcm-students' ) ) . '">' . esc_html__( 'Open Students', 'giga-class-market' ) . '</a>';
		echo '</p></div>';
	}

	/**
	 * Block default WordPress new-user emails for GCM students (GCM sends its own).
	 *
	 * @param bool    $send Whether to send.
	 * @param WP_User $user User.
	 * @return bool
	 */
	public function block_default_student_wp_emails( $send, $user ) {
		if ( self::is_gcm_student_only( $user ) || get_user_meta( $user->ID, 'gcm_is_student', true ) ) {
			return false;
		}
		return $send;
	}
}
