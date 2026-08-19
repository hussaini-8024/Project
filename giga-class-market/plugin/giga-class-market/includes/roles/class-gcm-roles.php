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
	 * Teacher capabilities.
	 *
	 * @var string[]
	 */
	private $teacher_caps = array(
		'read',
		'gcm_teacher_dashboard',
		'gcm_manage_live_class',
		'gcm_manage_notes',
		'gcm_message_students',
		'gcm_view_course_students',
	);

	/**
	 * Administrator capabilities.
	 *
	 * @var string[]
	 */
	private $admin_caps = array(
		'gcm_manage_courses',
		'gcm_manage_students',
		'gcm_manage_teachers',
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
			foreach ( array( 'edit_posts', 'upload_files', 'publish_posts', 'delete_posts', 'edit_pages' ) as $cap ) {
				$role->remove_cap( $cap );
			}
		}

		$teacher = get_role( 'gcm_teacher' );
		if ( ! $teacher ) {
			add_role(
				'gcm_teacher',
				__( 'GCM Teacher', 'giga-class-market' ),
				array_fill_keys( $this->teacher_caps, true )
			);
		} else {
			foreach ( $this->teacher_caps as $cap ) {
				$teacher->add_cap( $cap );
			}
			foreach ( array( 'edit_posts', 'publish_posts', 'delete_posts', 'edit_pages' ) as $cap ) {
				$teacher->remove_cap( $cap );
			}
			// Teachers need uploads for notes.
			$teacher->add_cap( 'upload_files' );
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
	 * Assign GCM Teacher identity (never alter site administrators).
	 *
	 * @param int|WP_User $user User.
	 * @return WP_User|false
	 */
	public static function assign_teacher_identity( $user ) {
		if ( ! ( $user instanceof WP_User ) ) {
			$user = get_userdata( absint( $user ) );
		}
		if ( ! $user ) {
			return false;
		}
		if ( user_can( $user, 'manage_options' ) || in_array( 'administrator', (array) $user->roles, true ) ) {
			return $user;
		}
		$user->set_role( 'gcm_teacher' );
		update_user_meta( $user->ID, 'gcm_is_teacher', 1 );
		update_user_meta( $user->ID, 'gcm_account_type', 'teacher' );
		delete_user_meta( $user->ID, 'gcm_is_student' );
		return $user;
	}

	/**
	 * Whether user is a GCM teacher only.
	 *
	 * @param int|WP_User $user User.
	 * @return bool
	 */
	public static function is_gcm_teacher_only( $user ) {
		if ( ! ( $user instanceof WP_User ) ) {
			$user = get_userdata( absint( $user ) );
		}
		if ( ! $user || user_can( $user, 'manage_options' ) ) {
			return false;
		}
		return in_array( 'gcm_teacher', (array) $user->roles, true );
	}

	/**
	 * Assign GCM Student as the only role (never alter site administrators).
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

		// Administrators must stay normal WP admins — do not attach student role.
		if ( user_can( $user, 'manage_options' ) || in_array( 'administrator', (array) $user->roles, true ) ) {
			$user->remove_role( 'gcm_student' );
			delete_user_meta( $user->ID, 'gcm_is_student' );
			delete_user_meta( $user->ID, 'gcm_account_type' );
			return $user;
		}

		$user->set_role( 'gcm_student' );
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
	 * Never hide administrators (even if they were wrongly given the student role).
	 *
	 * @param WP_User_Query $query Query.
	 * @return void
	 */
	public function exclude_students_from_users_list( $query ) {
		if ( ! is_admin() || ! $query instanceof WP_User_Query ) {
			return;
		}

		// Prevent recursion when we call get_users() below.
		static $running = false;
		if ( $running ) {
			return;
		}

		// Allow explicit role filter to see students.
		$role = isset( $_REQUEST['role'] ) ? sanitize_key( wp_unslash( $_REQUEST['role'] ) ) : '';
		if ( 'gcm_student' === $role || 'gcm_teacher' === $role || 'administrator' === $role ) {
			return;
		}

		global $pagenow;
		if ( ! in_array( $pagenow, array( 'users.php', 'user-edit.php', 'user-new.php' ), true ) && ! wp_doing_ajax() ) {
			return;
		}

		$running = true;
		// Exclude only pure students/teachers — keep administrators.
		$student_only_ids = get_users(
			array(
				'role'         => 'gcm_student',
				'role__not_in' => array( 'administrator' ),
				'fields'       => 'ID',
				'number'       => 9999,
			)
		);
		$teacher_only_ids = get_users(
			array(
				'role'         => 'gcm_teacher',
				'role__not_in' => array( 'administrator' ),
				'fields'       => 'ID',
				'number'       => 9999,
			)
		);
		$running = false;

		$hide_ids = array_merge( array_map( 'absint', $student_only_ids ), array_map( 'absint', $teacher_only_ids ) );
		if ( empty( $hide_ids ) ) {
			return;
		}

		$exclude = array_merge( array_map( 'absint', (array) $query->get( 'exclude' ) ), $hide_ids );
		$query->set( 'exclude', array_values( array_unique( array_filter( $exclude ) ) ) );
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
		if ( isset( $views['gcm_teacher'] ) ) {
			unset( $views['gcm_teacher'] );
		}
		$views['gcm_students_panel'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=gcm-students' ) ),
			esc_html__( 'GCM Students (manage here)', 'giga-class-market' )
		);
		$views['gcm_teachers_panel'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=gcm-teachers' ) ),
			esc_html__( 'GCM Teachers (manage here)', 'giga-class-market' )
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
			unset( $roles['gcm_student'], $roles['gcm_teacher'] );
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
		echo esc_html__( 'Giga Class Market students and teachers are not normal WordPress users. Manage them under Giga Class Market → Students / Teachers.', 'giga-class-market' );
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
		if ( self::is_gcm_student_only( $user ) || self::is_gcm_teacher_only( $user ) || get_user_meta( $user->ID, 'gcm_is_student', true ) || get_user_meta( $user->ID, 'gcm_is_teacher', true ) ) {
			return false;
		}
		return $send;
	}
}
