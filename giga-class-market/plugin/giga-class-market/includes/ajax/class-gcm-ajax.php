<?php
/**
 * AJAX handlers.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and handles AJAX actions.
 */
class GCM_Ajax {

	/**
	 * Register actions.
	 *
	 * @return void
	 */
	public function register() {
		$public_actions = array( 'contact_submit', 'payment_submit', 'course_search' );
		foreach ( $public_actions as $action ) {
			add_action( 'wp_ajax_gcm_' . $action, array( $this, $action ) );
			add_action( 'wp_ajax_nopriv_gcm_' . $action, array( $this, $action ) );
		}

		$student_actions = array( 'mark_lesson_complete', 'update_profile', 'change_password' );
		foreach ( $student_actions as $action ) {
			add_action( 'wp_ajax_gcm_' . $action, array( $this, $action ) );
		}

		$admin_actions = array(
			'approve_payment',
			'reject_payment',
			'create_student_account',
			'send_credentials',
			'freeze_student',
			'unfreeze_student',
			'update_contact_status',
			'save_settings',
			'save_curriculum',
			'toggle_featured',
		);
		foreach ( $admin_actions as $action ) {
			add_action( 'wp_ajax_gcm_' . $action, array( $this, $action ) );
		}
	}

	/**
	 * Public contact submission.
	 *
	 * @return void
	 */
	public function contact_submit() {
		GCM_Security::verify_ajax_nonce();

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$key   = 'contact|' . GCM_Security::get_ip_address() . '|' . $email;
		if ( ! GCM_Security::rate_limit( $key, 3, 10 * MINUTE_IN_SECONDS ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many submissions. Please wait and try again.', 'giga-class-market' ) ), 429 );
		}

		$result = GCM_Contact_Service::submit(
			array(
				'full_name' => isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '',
				'email'     => $email,
				'whatsapp'  => isset( $_POST['whatsapp'] ) ? sanitize_text_field( wp_unslash( $_POST['whatsapp'] ) ) : '',
				'subject'   => isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '',
				'message'   => isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '',
			)
		);

		$this->send_service_response( $result, __( 'Your message has been sent.', 'giga-class-market' ) );
	}

	/**
	 * Public payment submission.
	 *
	 * @return void
	 */
	public function payment_submit() {
		GCM_Security::verify_ajax_nonce();

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$key   = 'payment|' . GCM_Security::get_ip_address() . '|' . $email;
		if ( ! GCM_Security::rate_limit( $key, 5, HOUR_IN_SECONDS ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many payment submissions. Please wait and try again.', 'giga-class-market' ) ), 429 );
		}

		$file = isset( $_FILES['screenshot'] ) ? $_FILES['screenshot'] : array();
		$result = GCM_Payment_Service::submit(
			array(
				'course_id'      => isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0,
				'full_name'      => isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '',
				'email'          => $email,
				'whatsapp'       => isset( $_POST['whatsapp'] ) ? sanitize_text_field( wp_unslash( $_POST['whatsapp'] ) ) : '',
				'address'        => isset( $_POST['address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['address'] ) ) : '',
				'transaction_id' => isset( $_POST['transaction_id'] ) ? sanitize_text_field( wp_unslash( $_POST['transaction_id'] ) ) : '',
				'payment_method' => isset( $_POST['payment_method'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ) : '',
			),
			$file
		);

		$this->send_service_response( $result, __( 'Your payment verification request has been submitted. After verification, you will receive your account/course access details.', 'giga-class-market' ) );
	}

	/**
	 * Public course search.
	 *
	 * @return void
	 */
	public function course_search() {
		GCM_Security::verify_ajax_nonce();

		$courses = GCM_Course_Service::search(
			array(
				'search'   => isset( $_REQUEST['search'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['search'] ) ) : '',
				'category' => isset( $_REQUEST['category'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['category'] ) ) : '',
				'featured' => isset( $_REQUEST['featured'] ) ? (bool) absint( $_REQUEST['featured'] ) : null,
				'limit'    => isset( $_REQUEST['limit'] ) ? absint( $_REQUEST['limit'] ) : 12,
				'offset'   => isset( $_REQUEST['offset'] ) ? absint( $_REQUEST['offset'] ) : 0,
			)
		);

		wp_send_json_success( array( 'courses' => $courses ) );
	}

	/**
	 * Student marks lesson complete.
	 *
	 * @return void
	 */
	public function mark_lesson_complete() {
		GCM_Security::verify_ajax_nonce();
		GCM_Security::require_capability( 'gcm_access_dashboard' );

		$result = GCM_Progress_Service::mark_complete(
			get_current_user_id(),
			isset( $_POST['lesson_id'] ) ? absint( $_POST['lesson_id'] ) : 0,
			isset( $_POST['completed'] ) ? (bool) absint( $_POST['completed'] ) : true,
			isset( $_POST['last_position'] ) ? absint( $_POST['last_position'] ) : 0
		);

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Unable to update progress.', 'giga-class-market' ) ), 400 );
		}

		wp_send_json_success( array( 'message' => __( 'Progress updated.', 'giga-class-market' ) ) );
	}

	/**
	 * Student profile update.
	 *
	 * @return void
	 */
	public function update_profile() {
		GCM_Security::verify_ajax_nonce();
		GCM_Security::require_capability( 'gcm_update_profile' );

		$result = GCM_Student_Service::update_profile(
			get_current_user_id(),
			array(
				'full_name' => isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '',
				'email'     => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
				'whatsapp'  => isset( $_POST['whatsapp'] ) ? sanitize_text_field( wp_unslash( $_POST['whatsapp'] ) ) : '',
				'address'   => isset( $_POST['address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['address'] ) ) : '',
			)
		);

		$this->send_service_response( $result, __( 'Profile updated.', 'giga-class-market' ) );
	}

	/**
	 * Student password change.
	 *
	 * @return void
	 */
	public function change_password() {
		GCM_Security::verify_ajax_nonce();
		GCM_Security::require_capability( 'gcm_access_dashboard' );

		$user             = wp_get_current_user();
		$current_password = isset( $_POST['current_password'] ) ? (string) wp_unslash( $_POST['current_password'] ) : '';
		$new_password     = isset( $_POST['new_password'] ) ? (string) wp_unslash( $_POST['new_password'] ) : '';

		if ( ! wp_check_password( $current_password, $user->user_pass, $user->ID ) ) {
			wp_send_json_error( array( 'message' => __( 'Current password is incorrect.', 'giga-class-market' ) ), 400 );
		}

		if ( strlen( $new_password ) < 8 ) {
			wp_send_json_error( array( 'message' => __( 'New password must be at least 8 characters.', 'giga-class-market' ) ), 400 );
		}

		wp_set_password( $new_password, $user->ID );
		wp_send_json_success( array( 'message' => __( 'Password changed. Please log in again.', 'giga-class-market' ) ) );
	}

	/**
	 * Approve payment.
	 *
	 * @return void
	 */
	public function approve_payment() {
		GCM_Security::verify_ajax_nonce();
		GCM_Security::require_capability( 'gcm_manage_payments' );

		$result = GCM_Payment_Service::approve( isset( $_POST['payment_id'] ) ? absint( $_POST['payment_id'] ) : 0, get_current_user_id() );
		$this->send_service_response( $result, __( 'Payment approved and student enrolled.', 'giga-class-market' ) );
	}

	/**
	 * Reject payment.
	 *
	 * @return void
	 */
	public function reject_payment() {
		GCM_Security::verify_ajax_nonce();
		GCM_Security::require_capability( 'gcm_manage_payments' );

		$result = GCM_Payment_Service::reject(
			isset( $_POST['payment_id'] ) ? absint( $_POST['payment_id'] ) : 0,
			isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '',
			get_current_user_id()
		);
		$this->send_service_response( $result, __( 'Payment rejected.', 'giga-class-market' ) );
	}

	/**
	 * Create student account from payment.
	 *
	 * @return void
	 */
	public function create_student_account() {
		GCM_Security::verify_ajax_nonce();
		GCM_Security::require_capability( 'gcm_manage_students' );

		$result = GCM_Payment_Service::create_student_account( isset( $_POST['payment_id'] ) ? absint( $_POST['payment_id'] ) : 0 );
		$this->send_service_response( $result, __( 'Student account is ready.', 'giga-class-market' ) );
	}

	/**
	 * Send student credentials.
	 *
	 * @return void
	 */
	public function send_credentials() {
		GCM_Security::verify_ajax_nonce();
		GCM_Security::require_capability( 'gcm_manage_students' );

		$result = GCM_Payment_Service::send_credentials(
			isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0,
			isset( $_POST['payment_id'] ) ? absint( $_POST['payment_id'] ) : 0,
			true
		);
		$this->send_service_response( $result, __( 'Credentials sent.', 'giga-class-market' ) );
	}

	/**
	 * Freeze student.
	 *
	 * @return void
	 */
	public function freeze_student() {
		GCM_Security::verify_ajax_nonce();
		GCM_Security::require_capability( 'gcm_manage_students' );

		$result = GCM_Student_Service::freeze(
			isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0,
			isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0
		);
		$this->send_service_response( $result, __( 'Student access frozen.', 'giga-class-market' ) );
	}

	/**
	 * Unfreeze student.
	 *
	 * @return void
	 */
	public function unfreeze_student() {
		GCM_Security::verify_ajax_nonce();
		GCM_Security::require_capability( 'gcm_manage_students' );

		$result = GCM_Student_Service::unfreeze(
			isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0,
			isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0
		);
		$this->send_service_response( $result, __( 'Student access restored.', 'giga-class-market' ) );
	}

	/**
	 * Update contact status.
	 *
	 * @return void
	 */
	public function update_contact_status() {
		GCM_Security::verify_ajax_nonce();
		GCM_Security::require_capability( 'gcm_manage_contacts' );

		$result = GCM_Contact_Service::update_status(
			isset( $_POST['contact_id'] ) ? absint( $_POST['contact_id'] ) : 0,
			isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : ''
		);
		$this->send_service_response( $result, __( 'Contact status updated.', 'giga-class-market' ) );
	}

	/**
	 * Save settings.
	 *
	 * @return void
	 */
	public function save_settings() {
		GCM_Security::verify_ajax_nonce();
		GCM_Security::require_capability( 'gcm_manage_settings' );

		$settings = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();
		$result   = GCM_Settings_Service::update_settings( $settings );
		GCM_Audit_Service::log( 'settings_saved', 'settings', 0, array() );

		$this->send_service_response( $result, __( 'Settings saved.', 'giga-class-market' ) );
	}

	/**
	 * Save course curriculum.
	 *
	 * @return void
	 */
	public function save_curriculum() {
		GCM_Security::verify_ajax_nonce();
		GCM_Security::require_capability( 'gcm_manage_courses' );

		$course_id = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;
		$modules   = array();
		if ( isset( $_POST['modules'] ) ) {
			$raw     = is_string( $_POST['modules'] ) ? wp_unslash( $_POST['modules'] ) : wp_json_encode( wp_unslash( $_POST['modules'] ) );
			$modules = json_decode( $raw, true );
		}

		$result = GCM_Curriculum_Service::save_course_curriculum( $course_id, is_array( $modules ) ? $modules : array() );
		GCM_Audit_Service::log( 'curriculum_saved', 'course', $course_id, array() );

		$this->send_service_response( $result, __( 'Curriculum saved.', 'giga-class-market' ) );
	}

	/**
	 * Toggle featured course state.
	 *
	 * @return void
	 */
	public function toggle_featured() {
		GCM_Security::verify_ajax_nonce();
		GCM_Security::require_capability( 'gcm_manage_courses' );

		$course_id = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;
		$featured  = isset( $_POST['featured'] ) ? (bool) absint( $_POST['featured'] ) : false;
		GCM_Post_Types::set_featured( $course_id, $featured );
		GCM_Audit_Service::log( 'course_featured_toggled', 'course', $course_id, array( 'featured' => $featured ) );

		wp_send_json_success( array( 'message' => __( 'Featured status updated.', 'giga-class-market' ) ) );
	}

	/**
	 * Send consistent service response.
	 *
	 * @param mixed  $result Service result.
	 * @param string $success_message Success message.
	 * @return void
	 */
	private function send_service_response( $result, $success_message ) {
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		if ( false === $result || null === $result ) {
			wp_send_json_error( array( 'message' => __( 'The request could not be completed.', 'giga-class-market' ) ), 400 );
		}

		$data = array(
			'message' => $success_message,
			'id'      => is_numeric( $result ) ? (int) $result : 0,
		);

		if ( is_array( $result ) ) {
			if ( ! empty( $result['message'] ) ) {
				$data['message'] = $result['message'];
			}
			if ( ! empty( $result['whatsapp_url'] ) ) {
				$data['whatsapp_url'] = esc_url_raw( $result['whatsapp_url'] );
			}
			if ( ! empty( $result['user_id'] ) ) {
				$data['id'] = (int) $result['user_id'];
			}
		}

		wp_send_json_success( $data );
	}
}
