<?php
/**
 * Admin menus and views.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI controller.
 */
class GCM_Admin {

	/**
	 * Register admin menus.
	 *
	 * @return void
	 */
	public function register_menus() {
		add_menu_page(
			__( 'Giga Class Market', 'giga-class-market' ),
			__( 'Giga Class Market', 'giga-class-market' ),
			'gcm_view_dashboard',
			'gcm-dashboard',
			array( $this, 'render_dashboard' ),
			'dashicons-welcome-learn-more',
			26
		);

		add_submenu_page( 'gcm-dashboard', __( 'Dashboard', 'giga-class-market' ), __( 'Dashboard', 'giga-class-market' ), 'gcm_view_dashboard', 'gcm-dashboard', array( $this, 'render_dashboard' ) );
		add_submenu_page( 'gcm-dashboard', __( 'Courses', 'giga-class-market' ), __( 'Courses', 'giga-class-market' ), 'gcm_manage_courses', 'edit.php?post_type=gcm_course' );
		add_submenu_page( 'gcm-dashboard', __( 'Payments', 'giga-class-market' ), __( 'Payments', 'giga-class-market' ), 'gcm_manage_payments', 'gcm-payments', array( $this, 'render_payments' ) );
		add_submenu_page( 'gcm-dashboard', __( 'Students', 'giga-class-market' ), __( 'Students', 'giga-class-market' ), 'gcm_manage_students', 'gcm-students', array( $this, 'render_students' ) );
		add_submenu_page( 'gcm-dashboard', __( 'Contact Messages', 'giga-class-market' ), __( 'Contact Messages', 'giga-class-market' ), 'gcm_manage_contacts', 'gcm-contacts', array( $this, 'render_contacts' ) );
		add_submenu_page( 'gcm-dashboard', __( 'Testimonials', 'giga-class-market' ), __( 'Testimonials', 'giga-class-market' ), 'gcm_manage_testimonials', 'edit.php?post_type=gcm_testimonial' );
		add_submenu_page( 'gcm-dashboard', __( 'Hero Slides', 'giga-class-market' ), __( 'Hero Slides', 'giga-class-market' ), 'gcm_manage_settings', 'edit.php?post_type=gcm_slide' );
		add_submenu_page( 'gcm-dashboard', __( 'Settings', 'giga-class-market' ), __( 'Settings', 'giga-class-market' ), 'gcm_manage_settings', 'gcm-settings', array( $this, 'render_settings' ) );
		add_submenu_page( 'gcm-dashboard', __( 'Activity Log', 'giga-class-market' ), __( 'Activity Log', 'giga-class-market' ), 'gcm_view_dashboard', 'gcm-activity-log', array( $this, 'render_activity_log' ) );
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		$screen = get_current_screen();
		$is_gcm = false !== strpos( $hook, 'gcm' ) || ( $screen && in_array( $screen->post_type, array( 'gcm_course', 'gcm_testimonial', 'gcm_slide' ), true ) );
		if ( ! $is_gcm ) {
			return;
		}

		wp_enqueue_style( 'gcm-admin', GCM_PLUGIN_URL . 'admin/css/gcm-admin.css', array(), GCM_VERSION );
		wp_enqueue_script( 'gcm-admin', GCM_PLUGIN_URL . 'admin/js/gcm-admin.js', array( 'jquery' ), GCM_VERSION, true );
		wp_localize_script(
			'gcm-admin',
			'gcmAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'gcm_ajax_nonce' ),
			)
		);
	}

	/**
	 * Render dashboard.
	 *
	 * @return void
	 */
	public function render_dashboard() {
		$this->view( 'dashboard', array( 'stats' => $this->get_stats() ) );
	}

	/**
	 * Render payments.
	 *
	 * @return void
	 */
	public function render_payments() {
		$status   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$payments = GCM_Payment_Service::get_by_status( $status, 100 );
		$this->view( 'payments', array( 'payments' => $payments, 'status' => $status ) );
	}

	/**
	 * Render students.
	 *
	 * @return void
	 */
	public function render_students() {
		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$students = GCM_Student_Service::get_students_list( array( 'search' => $search, 'limit' => 100 ) );
		$this->view( 'students', array( 'students' => $students, 'search' => $search ) );
	}

	/**
	 * Render contacts.
	 *
	 * @return void
	 */
	public function render_contacts() {
		$status   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$contacts = GCM_Contact_Service::get_contacts( $status, 100 );
		$this->view( 'contacts', array( 'contacts' => $contacts, 'status' => $status ) );
	}

	/**
	 * Render settings.
	 *
	 * @return void
	 */
	public function render_settings() {
		$this->view( 'settings', array( 'settings' => GCM_Settings_Service::get_settings() ) );
	}

	/**
	 * Render activity log.
	 *
	 * @return void
	 */
	public function render_activity_log() {
		$this->view( 'activity-log', array( 'logs' => GCM_Audit_Service::get_logs( 100 ) ) );
	}

	/**
	 * Load a view.
	 *
	 * @param string $view View name.
	 * @param array  $vars Variables.
	 * @return void
	 */
	private function view( $view, $vars = array() ) {
		if ( ! current_user_can( 'gcm_view_dashboard' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'giga-class-market' ) );
		}

		$file = GCM_PLUGIN_DIR . 'admin/views/' . sanitize_file_name( $view ) . '.php';
		if ( ! file_exists( $file ) ) {
			wp_die( esc_html__( 'View not found.', 'giga-class-market' ) );
		}

		extract( $vars, EXTR_SKIP );
		include $file;
	}

	/**
	 * Dashboard statistics.
	 *
	 * @return array
	 */
	private function get_stats() {
		global $wpdb;

		return array(
			'courses'     => (int) wp_count_posts( 'gcm_course' )->publish,
			'students'    => (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}gcm_enrollments WHERE status IN (%s, %s, %s)",
					'active',
					'frozen',
					'completed'
				)
			),
			'pending'     => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}gcm_payments WHERE status = %s", 'under_review' ) ),
			'contacts'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}gcm_contacts WHERE status = %s", 'new' ) ),
			'enrollments' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}gcm_enrollments" ),
			'revenue'     => (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}gcm_payments WHERE status = %s", 'approved' ) ),
		);
	}
}
