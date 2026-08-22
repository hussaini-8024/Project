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
		$public_actions = array( 'contact_submit', 'payment_submit', 'course_search', 'validate_coupon', 'get_promo_popup' );
		foreach ( $public_actions as $action ) {
			add_action( 'wp_ajax_gcm_' . $action, array( $this, $action ) );
			add_action( 'wp_ajax_nopriv_gcm_' . $action, array( $this, $action ) );
		}

		$student_actions = array(
			'mark_lesson_complete',
			'update_profile',
			'change_password',
			'send_course_message',
			'get_course_messages',
			'join_live_class',
			'submit_review',
			'submit_quiz',
			'submit_assignment',
		);
		foreach ( $student_actions as $action ) {
			add_action( 'wp_ajax_gcm_' . $action, array( $this, $action ) );
		}

		$teacher_actions = array(
			'schedule_class',
			'start_class',
			'end_class',
			'upload_note',
			'delete_note',
			'send_teacher_message',
			'get_teacher_messages',
			'get_course_students',
			'get_class_attendance',
			'add_recording',
			'delete_recording',
			'add_announcement',
			'delete_announcement',
			'create_assignment',
			'grade_assignment',
			'save_quiz',
		);
		foreach ( $teacher_actions as $action ) {
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
			'create_teacher',
			'set_teacher_password',
			'assign_teacher_courses',
			'set_teacher_zoom_host',
			'generate_certificate',
			'create_coupon',
			'toggle_coupon',
			'delete_coupon',
			'bulk_generate_certificates',
			'whatsapp_payment_reminder',
			'moderate_review',
			'clear_operational_test_data',
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
				'coupon_code'    => isset( $_POST['coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) : '',
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

		$this->bust_front_caches();

		$this->send_service_response( $result, __( 'Settings saved. Site cache was refreshed for visitors.', 'giga-class-market' ) );
	}

	/**
	 * Public promo popup config (uncached) so guests see updates even when HTML is CDN-cached.
	 *
	 * @return void
	 */
	public function get_promo_popup() {
		nocache_headers();
		if ( ! headers_sent() ) {
			header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );
		}

		$website = class_exists( 'GCM_Settings_Service' ) ? GCM_Settings_Service::get_section( 'website' ) : array();
		$enabled = ! empty( $website['popup_enabled'] );
		$image_id = absint( $website['popup_image_id'] ?? 0 );
		$image    = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '';
		$link     = esc_url_raw( $website['popup_link_url'] ?? '' );

		if ( ! $enabled || ! $image ) {
			wp_send_json_success(
				array(
					'enabled' => false,
				)
			);
		}

		wp_send_json_success(
			array(
				'enabled' => true,
				'id'      => (string) $image_id,
				'image'   => $image,
				'link'    => $link,
				'alt'     => __( 'Promotional offer', 'giga-class-market' ),
			)
		);
	}

	/**
	 * Bust page/object caches so logged-out visitors see fresh front-end markup.
	 *
	 * @return void
	 */
	private function bust_front_caches() {
		update_option( 'gcm_cache_bust', (string) time(), false );

		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}

		do_action( 'stackcache_purge_all' );
		do_action( 'ce_clear_cache' );
		do_action( 'litespeed_purge_all' );
		do_action( 'cache_enabler_clear_complete_cache' );

		/**
		 * Allow hosts / cache plugins to purge CDN HTML after GCM settings change.
		 */
		do_action( 'gcm_purge_front_caches' );
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
	 * Admin creates a teacher account.
	 *
	 * @return void
	 */
	public function create_teacher() {
		GCM_Security::verify_ajax_nonce();
		GCM_Security::require_capability( 'gcm_manage_teachers' );

		$result = GCM_Teacher_Service::create_teacher(
			array(
				'full_name'       => isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '',
				'email'           => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
				'username'        => isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ), true ) : '',
				'password'        => isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '',
				'whatsapp'        => isset( $_POST['whatsapp'] ) ? sanitize_text_field( wp_unslash( $_POST['whatsapp'] ) ) : '',
				'zoom_host_email' => isset( $_POST['zoom_host_email'] ) ? sanitize_email( wp_unslash( $_POST['zoom_host_email'] ) ) : '',
				'course_ids'      => isset( $_POST['course_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['course_ids'] ) ) : array(),
			)
		);
		$this->send_service_response( $result, __( 'Teacher account created.', 'giga-class-market' ) );
	}

	/**
	 * Admin sets teacher Zoom host email.
	 *
	 * @return void
	 */
	public function set_teacher_zoom_host() {
		GCM_Security::verify_ajax_nonce();
		GCM_Security::require_capability( 'gcm_manage_teachers' );

		$teacher_id = isset( $_POST['teacher_id'] ) ? absint( $_POST['teacher_id'] ) : 0;
		$email      = isset( $_POST['zoom_host_email'] ) ? sanitize_email( wp_unslash( $_POST['zoom_host_email'] ) ) : '';
		$result     = GCM_Teacher_Service::set_zoom_host_email( $teacher_id, $email );
		if ( ! is_wp_error( $result ) ) {
			GCM_Audit_Service::log( 'teacher_zoom_host_set', 'user', $teacher_id, array( 'zoom_host_email' => $email ) );
		}
		$this->send_service_response( $result, __( 'Teacher Zoom host email saved.', 'giga-class-market' ) );
	}

	/**
	 * Admin sets teacher password.
	 *
	 * @return void
	 */
	public function set_teacher_password() {
		GCM_Security::verify_ajax_nonce();
		GCM_Security::require_capability( 'gcm_manage_teachers' );

		$result = GCM_Teacher_Service::set_password(
			isset( $_POST['teacher_id'] ) ? absint( $_POST['teacher_id'] ) : 0,
			isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : ''
		);
		$this->send_service_response( $result, __( 'Teacher password updated.', 'giga-class-market' ) );
	}

	/**
	 * Admin assigns courses to a teacher.
	 *
	 * @return void
	 */
	public function assign_teacher_courses() {
		GCM_Security::verify_ajax_nonce();
		GCM_Security::require_capability( 'gcm_manage_teachers' );

		$teacher_id = isset( $_POST['teacher_id'] ) ? absint( $_POST['teacher_id'] ) : 0;
		$course_ids = isset( $_POST['course_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['course_ids'] ) ) : array();
		$user       = get_userdata( $teacher_id );
		if ( ! $user || ! GCM_Roles::is_gcm_teacher_only( $user ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid teacher account.', 'giga-class-market' ) ), 400 );
		}

		GCM_Teacher_Service::set_teacher_courses( $teacher_id, $course_ids );
		GCM_Audit_Service::log( 'teacher_courses_assigned', 'user', $teacher_id, array( 'courses' => $course_ids ) );
		wp_send_json_success( array( 'message' => __( 'Teacher courses updated.', 'giga-class-market' ) ) );
	}

	/**
	 * Teacher schedules a live class.
	 *
	 * @return void
	 */
	public function schedule_class() {
		GCM_Security::verify_ajax_nonce();
		$this->require_teacher_or_admin();

		$result = GCM_Class_Service::schedule(
			array(
				'course_id'     => isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0,
				'teacher_id'    => get_current_user_id(),
				'title'         => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
				'scheduled_at'  => isset( $_POST['scheduled_at'] ) ? sanitize_text_field( wp_unslash( $_POST['scheduled_at'] ) ) : '',
				'scheduled_end' => isset( $_POST['scheduled_end'] ) ? sanitize_text_field( wp_unslash( $_POST['scheduled_end'] ) ) : '',
			)
		);
		$this->send_service_response( $result, __( 'Class scheduled.', 'giga-class-market' ) );
	}

	/**
	 * Teacher starts a class (creates Zoom meeting).
	 *
	 * @return void
	 */
	public function start_class() {
		GCM_Security::verify_ajax_nonce();
		$this->require_teacher_or_admin();

		$class_id = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		$existing = GCM_Class_Service::get( $class_id );

		// If already live with a broken link, repair and return URLs.
		if ( $existing && 'live' === $existing->status ) {
			$result = GCM_Class_Service::ensure_meeting_links( $class_id );
		} else {
			$result = GCM_Class_Service::start( $class_id, get_current_user_id() );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success(
			array(
				'message'   => __( 'Class started. Opening the live meeting…', 'giga-class-market' ),
				'join_url'  => $result->zoom_join_url ?? '',
				'start_url' => ! empty( $result->zoom_start_url ) ? $result->zoom_start_url : ( $result->zoom_join_url ?? '' ),
				'id'        => (int) $result->id,
			)
		);
	}

	/**
	 * Teacher ends a live class.
	 *
	 * @return void
	 */
	public function end_class() {
		GCM_Security::verify_ajax_nonce();
		$this->require_teacher_or_admin();

		$result = GCM_Class_Service::end(
			isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0,
			get_current_user_id()
		);
		$this->send_service_response( $result, __( 'Class ended.', 'giga-class-market' ) );
	}

	/**
	 * Teacher uploads course notes.
	 *
	 * @return void
	 */
	public function upload_note() {
		GCM_Security::verify_ajax_nonce();
		$this->require_teacher_or_admin();

		$file = isset( $_FILES['note_file'] ) ? $_FILES['note_file'] : array();
		$result = GCM_Notes_Service::create(
			array(
				'course_id'  => isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0,
				'teacher_id' => get_current_user_id(),
				'title'      => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
				'content'    => isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '',
			),
			$file
		);
		$this->send_service_response( $result, __( 'Notes uploaded for students.', 'giga-class-market' ) );
	}

	/**
	 * Teacher deletes a note.
	 *
	 * @return void
	 */
	public function delete_note() {
		GCM_Security::verify_ajax_nonce();
		$this->require_teacher_or_admin();

		$result = GCM_Notes_Service::delete(
			isset( $_POST['note_id'] ) ? absint( $_POST['note_id'] ) : 0,
			get_current_user_id()
		);
		$this->send_service_response( $result, __( 'Note deleted.', 'giga-class-market' ) );
	}

	/**
	 * Teacher lists enrolled students for a course.
	 *
	 * @return void
	 */
	public function get_course_students() {
		GCM_Security::verify_ajax_nonce();
		$this->require_teacher_or_admin();

		$course_id = isset( $_REQUEST['course_id'] ) ? absint( $_REQUEST['course_id'] ) : 0;
		if ( ! GCM_Teacher_Service::teacher_can_manage_course( get_current_user_id(), $course_id ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not assigned to this course.', 'giga-class-market' ) ), 403 );
		}

		$students = GCM_Enrollment_Service::get_course_students( $course_id );
		wp_send_json_success( array( 'students' => $students ) );
	}

	/**
	 * Teacher sends a message to a student (or course broadcast if recipient empty).
	 *
	 * @return void
	 */
	public function send_teacher_message() {
		GCM_Security::verify_ajax_nonce();
		$this->require_teacher_or_admin();

		$result = GCM_Message_Service::send(
			array(
				'course_id'    => isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0,
				'sender_id'    => get_current_user_id(),
				'recipient_id' => isset( $_POST['recipient_id'] ) ? absint( $_POST['recipient_id'] ) : 0,
				'message'      => isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '',
			)
		);
		$this->send_service_response( $result, __( 'Message sent.', 'giga-class-market' ) );
	}

	/**
	 * Teacher loads message thread.
	 *
	 * @return void
	 */
	public function get_teacher_messages() {
		GCM_Security::verify_ajax_nonce();
		$this->require_teacher_or_admin();

		$course_id = isset( $_REQUEST['course_id'] ) ? absint( $_REQUEST['course_id'] ) : 0;
		$with_user = isset( $_REQUEST['with_user'] ) ? absint( $_REQUEST['with_user'] ) : 0;
		$messages  = GCM_Message_Service::get_thread( $course_id, get_current_user_id(), $with_user );
		wp_send_json_success( array( 'messages' => $messages ) );
	}

	/**
	 * Student (or teacher via shared UI) sends a course message.
	 *
	 * @return void
	 */
	public function send_course_message() {
		GCM_Security::verify_ajax_nonce();

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'giga-class-market' ) ), 403 );
		}

		$result = GCM_Message_Service::send(
			array(
				'course_id'    => isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0,
				'sender_id'    => $user_id,
				'recipient_id' => isset( $_POST['recipient_id'] ) ? absint( $_POST['recipient_id'] ) : 0,
				'message'      => isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '',
			)
		);
		$this->send_service_response( $result, __( 'Message sent.', 'giga-class-market' ) );
	}

	/**
	 * Student loads course messages.
	 *
	 * @return void
	 */
	public function get_course_messages() {
		GCM_Security::verify_ajax_nonce();

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'giga-class-market' ) ), 403 );
		}

		$course_id = isset( $_REQUEST['course_id'] ) ? absint( $_REQUEST['course_id'] ) : 0;
		$with_user = isset( $_REQUEST['with_user'] ) ? absint( $_REQUEST['with_user'] ) : 0;
		$messages  = GCM_Message_Service::get_thread( $course_id, $user_id, $with_user );
		wp_send_json_success( array( 'messages' => $messages ) );
	}

	/**
	 * Student/teacher joins a live class (records attendance, returns Zoom URL).
	 *
	 * @return void
	 */
	public function join_live_class() {
		GCM_Security::verify_ajax_nonce();

		$result = GCM_Attendance_Service::record_join(
			isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0,
			get_current_user_id()
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		$joined_at = ! empty( $result->joined_at ) ? (string) $result->joined_at : '';

		wp_send_json_success(
			array(
				'message'           => __( 'Opening live class…', 'giga-class-market' ),
				'join_url'          => $result->join_url,
				'joined_at'         => $joined_at,
				'joined_at_display' => $joined_at ? mysql2date( get_option( 'date_format' ) . ' H:i:s', $joined_at ) : '',
			)
		);
	}

	/**
	 * Attendance roster for a class.
	 *
	 * @return void
	 */
	public function get_class_attendance() {
		GCM_Security::verify_ajax_nonce();
		$this->require_teacher_or_admin();

		$class_id = isset( $_REQUEST['class_id'] ) ? absint( $_REQUEST['class_id'] ) : 0;
		$class    = GCM_Class_Service::get( $class_id );
		if ( ! $class ) {
			wp_send_json_error( array( 'message' => __( 'Class not found.', 'giga-class-market' ) ), 404 );
		}
		if ( ! GCM_Teacher_Service::teacher_can_manage_course( get_current_user_id(), $class->course_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot view attendance for this class.', 'giga-class-market' ) ), 403 );
		}

		wp_send_json_success(
			array(
				'attendance' => GCM_Attendance_Service::get_for_class( $class_id ),
				'count'      => GCM_Attendance_Service::count_for_class( $class_id ),
			)
		);
	}

	/**
	 * Admin: generate certificate for a student course and email it.
	 *
	 * @return void
	 */
	public function generate_certificate() {
		GCM_Security::verify_ajax_nonce();
		if ( ! current_user_can( 'gcm_manage_students' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to issue certificates.', 'giga-class-market' ) ), 403 );
		}

		$user_id   = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$course_id = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;
		$result    = GCM_Certificate_Service::generate_and_send( $user_id, $course_id, get_current_user_id() );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %s: certificate code */
					__( 'Certificate generated and emailed. ID: %s', 'giga-class-market' ),
					$result->certificate_code
				),
				'code'    => $result->certificate_code,
				'url'     => GCM_Certificate_Service::verify_url( $result->certificate_code ),
			)
		);
	}

	/**
	 * Validate coupon for checkout.
	 *
	 * @return void
	 */
	public function validate_coupon() {
		GCM_Security::verify_ajax_nonce();

		$result = GCM_Coupon_Service::validate_for_course(
			isset( $_POST['coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) : '',
			isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0,
			get_current_user_id()
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success(
			array(
				'message'         => __( 'Coupon applied.', 'giga-class-market' ),
				'discount_amount' => $result['discount_amount'],
				'final_price'     => $result['final_price'],
				'code'            => $result['coupon']->code,
			)
		);
	}

	/**
	 * Admin: create coupon.
	 *
	 * @return void
	 */
	public function create_coupon() {
		GCM_Security::verify_ajax_nonce();
		if ( ! current_user_can( 'gcm_manage_payments' ) && ! current_user_can( 'gcm_manage_settings' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage coupons.', 'giga-class-market' ) ), 403 );
		}

		$result = GCM_Coupon_Service::create(
			array(
				'code'           => isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '',
				'description'    => isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '',
				'discount_type'  => isset( $_POST['discount_type'] ) ? sanitize_key( wp_unslash( $_POST['discount_type'] ) ) : 'percent',
				'discount_value' => isset( $_POST['discount_value'] ) ? (float) wp_unslash( $_POST['discount_value'] ) : 0,
				'course_id'      => isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0,
				'max_uses'       => isset( $_POST['max_uses'] ) ? absint( $_POST['max_uses'] ) : 0,
				'expires_at'     => isset( $_POST['expires_at'] ) ? sanitize_text_field( wp_unslash( $_POST['expires_at'] ) ) : '',
				'created_by'     => get_current_user_id(),
			)
		);
		$this->send_service_response( $result, __( 'Coupon created.', 'giga-class-market' ) );
	}

	/**
	 * Admin: toggle coupon active state.
	 *
	 * @return void
	 */
	public function toggle_coupon() {
		GCM_Security::verify_ajax_nonce();
		if ( ! current_user_can( 'gcm_manage_payments' ) && ! current_user_can( 'gcm_manage_settings' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage coupons.', 'giga-class-market' ) ), 403 );
		}

		$coupon_id = isset( $_POST['coupon_id'] ) ? absint( $_POST['coupon_id'] ) : 0;
		$coupon    = GCM_Coupon_Service::get( $coupon_id );
		if ( ! $coupon ) {
			wp_send_json_error( array( 'message' => __( 'Coupon not found.', 'giga-class-market' ) ), 404 );
		}

		$result = GCM_Coupon_Service::update( $coupon_id, array( 'is_active' => empty( $coupon->is_active ) ? 1 : 0 ) );
		$this->send_service_response( $result, __( 'Coupon status updated.', 'giga-class-market' ) );
	}

	/**
	 * Admin: delete coupon.
	 *
	 * @return void
	 */
	public function delete_coupon() {
		GCM_Security::verify_ajax_nonce();
		if ( ! current_user_can( 'gcm_manage_payments' ) && ! current_user_can( 'gcm_manage_settings' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage coupons.', 'giga-class-market' ) ), 403 );
		}

		$result = GCM_Coupon_Service::delete( isset( $_POST['coupon_id'] ) ? absint( $_POST['coupon_id'] ) : 0 );
		$this->send_service_response( $result, __( 'Coupon deleted.', 'giga-class-market' ) );
	}

	/**
	 * Admin: bulk generate certificates for completed enrollments.
	 *
	 * @return void
	 */
	public function bulk_generate_certificates() {
		GCM_Security::verify_ajax_nonce();
		if ( ! current_user_can( 'gcm_manage_students' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to issue certificates.', 'giga-class-market' ) ), 403 );
		}

		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT e.user_id, e.course_id FROM {$wpdb->prefix}gcm_enrollments e
				LEFT JOIN {$wpdb->prefix}gcm_certificates c ON c.user_id = e.user_id AND c.course_id = e.course_id
				WHERE e.status = %s AND c.id IS NULL
				LIMIT 100",
				'completed'
			)
		);

		$generated = 0;
		$errors    = 0;
		foreach ( (array) $rows as $row ) {
			$result = GCM_Certificate_Service::generate_and_send( (int) $row->user_id, (int) $row->course_id, get_current_user_id() );
			if ( is_wp_error( $result ) ) {
				$errors++;
			} else {
				$generated++;
			}
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: generated count, 2: error count */
					__( 'Generated %1$d certificate(s). %2$d skipped/failed.', 'giga-class-market' ),
					$generated,
					$errors
				),
				'generated' => $generated,
				'errors'    => $errors,
			)
		);
	}

	/**
	 * Admin: WhatsApp reminder for a pending payment.
	 *
	 * @return void
	 */
	public function whatsapp_payment_reminder() {
		GCM_Security::verify_ajax_nonce();
		GCM_Security::require_capability( 'gcm_manage_payments' );

		$payment_id = isset( $_POST['payment_id'] ) ? absint( $_POST['payment_id'] ) : 0;
		$payment    = GCM_Payment_Service::get( $payment_id );
		if ( ! $payment ) {
			wp_send_json_error( array( 'message' => __( 'Payment not found.', 'giga-class-market' ) ), 404 );
		}

		$course = GCM_Course_Service::get( (int) $payment->course_id );
		$message = sprintf(
			/* translators: 1: student name, 2: course title, 3: amount */
			__( 'Hello %1$s, this is a reminder from Giga Class Market about your payment for “%2$s” (amount: %3$s). Please reply here if you need help completing verification.', 'giga-class-market' ),
			$payment->full_name,
			$course ? $course['title'] : __( 'your course', 'giga-class-market' ),
			number_format_i18n( (float) $payment->amount, 2 )
		);

		$url = GCM_Notification_Service::build_whatsapp_url( $payment->whatsapp, $message );
		wp_send_json_success(
			array(
				'message'      => __( 'Opening WhatsApp reminder…', 'giga-class-market' ),
				'whatsapp_url' => $url,
			)
		);
	}

	/**
	 * Admin: moderate a review.
	 *
	 * @return void
	 */
	public function moderate_review() {
		GCM_Security::verify_ajax_nonce();
		if ( ! current_user_can( 'gcm_manage_courses' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to moderate reviews.', 'giga-class-market' ) ), 403 );
		}

		$result = GCM_Review_Service::set_status(
			isset( $_POST['review_id'] ) ? absint( $_POST['review_id'] ) : 0,
			isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : ''
		);
		$this->send_service_response( $result, __( 'Review status updated.', 'giga-class-market' ) );
	}

	/**
	 * Student: submit course review.
	 *
	 * @return void
	 */
	public function submit_review() {
		GCM_Security::verify_ajax_nonce();
		GCM_Security::require_capability( 'gcm_access_dashboard' );

		$result = GCM_Review_Service::submit(
			isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0,
			get_current_user_id(),
			isset( $_POST['rating'] ) ? absint( $_POST['rating'] ) : 5,
			isset( $_POST['review_title'] ) ? sanitize_text_field( wp_unslash( $_POST['review_title'] ) ) : '',
			isset( $_POST['review_body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['review_body'] ) ) : ''
		);
		$this->send_service_response( $result, __( 'Thank you! Your review was submitted for moderation.', 'giga-class-market' ) );
	}

	/**
	 * Student: submit quiz attempt.
	 *
	 * @return void
	 */
	public function submit_quiz() {
		GCM_Security::verify_ajax_nonce();
		GCM_Security::require_capability( 'gcm_access_dashboard' );

		$answers = array();
		if ( isset( $_POST['answers'] ) ) {
			$raw = wp_unslash( $_POST['answers'] );
			if ( is_string( $raw ) ) {
				$decoded = json_decode( $raw, true );
				$answers = is_array( $decoded ) ? $decoded : array();
			} elseif ( is_array( $raw ) ) {
				$answers = $raw;
			}
		}

		$result = GCM_Quiz_Service::submit_attempt(
			isset( $_POST['quiz_id'] ) ? absint( $_POST['quiz_id'] ) : 0,
			get_current_user_id(),
			$answers
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: score, 2: pass/fail */
					__( 'Quiz scored %1$d%% — %2$s', 'giga-class-market' ),
					(int) $result->score,
					! empty( $result->passed ) ? __( 'Passed', 'giga-class-market' ) : __( 'Not passed', 'giga-class-market' )
				),
				'score'   => (int) $result->score,
				'passed'  => (int) $result->passed,
				'id'      => (int) $result->id,
			)
		);
	}

	/**
	 * Student: submit assignment.
	 *
	 * @return void
	 */
	public function submit_assignment() {
		GCM_Security::verify_ajax_nonce();
		GCM_Security::require_capability( 'gcm_access_dashboard' );

		$file_id = 0;
		if ( ! empty( $_FILES['assignment_file']['name'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$upload = media_handle_upload( 'assignment_file', 0 );
			if ( is_wp_error( $upload ) ) {
				wp_send_json_error( array( 'message' => $upload->get_error_message() ), 400 );
			}
			$file_id = (int) $upload;
		}

		$result = GCM_Assignment_Service::submit(
			isset( $_POST['assignment_id'] ) ? absint( $_POST['assignment_id'] ) : 0,
			get_current_user_id(),
			$file_id,
			isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : ''
		);
		$this->send_service_response( $result, __( 'Assignment submitted.', 'giga-class-market' ) );
	}

	/**
	 * Teacher: add recording.
	 *
	 * @return void
	 */
	public function add_recording() {
		GCM_Security::verify_ajax_nonce();
		$this->require_teacher_or_admin();

		$result = GCM_Recording_Service::add(
			array(
				'course_id'  => isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0,
				'class_id'   => isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0,
				'teacher_id' => get_current_user_id(),
				'title'      => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
				'video_url'  => isset( $_POST['video_url'] ) ? esc_url_raw( wp_unslash( $_POST['video_url'] ) ) : '',
			)
		);
		$this->send_service_response( $result, __( 'Recording added.', 'giga-class-market' ) );
	}

	/**
	 * Teacher: delete recording.
	 *
	 * @return void
	 */
	public function delete_recording() {
		GCM_Security::verify_ajax_nonce();
		$this->require_teacher_or_admin();

		$result = GCM_Recording_Service::delete(
			isset( $_POST['recording_id'] ) ? absint( $_POST['recording_id'] ) : 0,
			get_current_user_id()
		);
		$this->send_service_response( $result, __( 'Recording deleted.', 'giga-class-market' ) );
	}

	/**
	 * Teacher: add announcement.
	 *
	 * @return void
	 */
	public function add_announcement() {
		GCM_Security::verify_ajax_nonce();
		$this->require_teacher_or_admin();

		$result = GCM_Announcement_Service::add(
			array(
				'course_id'  => isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0,
				'teacher_id' => get_current_user_id(),
				'title'      => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
				'body'       => isset( $_POST['body'] ) ? wp_kses_post( wp_unslash( $_POST['body'] ) ) : '',
			)
		);
		$this->send_service_response( $result, __( 'Announcement published.', 'giga-class-market' ) );
	}

	/**
	 * Teacher: delete announcement.
	 *
	 * @return void
	 */
	public function delete_announcement() {
		GCM_Security::verify_ajax_nonce();
		$this->require_teacher_or_admin();

		$result = GCM_Announcement_Service::delete(
			isset( $_POST['announcement_id'] ) ? absint( $_POST['announcement_id'] ) : 0,
			get_current_user_id()
		);
		$this->send_service_response( $result, __( 'Announcement deleted.', 'giga-class-market' ) );
	}

	/**
	 * Teacher: create assignment.
	 *
	 * @return void
	 */
	public function create_assignment() {
		GCM_Security::verify_ajax_nonce();
		$this->require_teacher_or_admin();

		$result = GCM_Assignment_Service::create_assignment(
			array(
				'course_id'    => isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0,
				'teacher_id'   => get_current_user_id(),
				'title'        => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
				'instructions' => isset( $_POST['instructions'] ) ? wp_kses_post( wp_unslash( $_POST['instructions'] ) ) : '',
				'due_at'       => isset( $_POST['due_at'] ) ? sanitize_text_field( wp_unslash( $_POST['due_at'] ) ) : '',
			)
		);
		$this->send_service_response( $result, __( 'Assignment created.', 'giga-class-market' ) );
	}

	/**
	 * Teacher: grade assignment submission.
	 *
	 * @return void
	 */
	public function grade_assignment() {
		GCM_Security::verify_ajax_nonce();
		$this->require_teacher_or_admin();

		$result = GCM_Assignment_Service::grade(
			isset( $_POST['submission_id'] ) ? absint( $_POST['submission_id'] ) : 0,
			isset( $_POST['grade'] ) ? sanitize_text_field( wp_unslash( $_POST['grade'] ) ) : '',
			isset( $_POST['feedback'] ) ? wp_kses_post( wp_unslash( $_POST['feedback'] ) ) : ''
		);
		$this->send_service_response( $result, __( 'Grade saved.', 'giga-class-market' ) );
	}

	/**
	 * Teacher/admin: create quiz (title + questions JSON).
	 *
	 * @return void
	 */
	public function save_quiz() {
		GCM_Security::verify_ajax_nonce();
		$this->require_teacher_or_admin();

		$course_id = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;
		if ( ! GCM_Teacher_Service::teacher_can_manage_course( get_current_user_id(), $course_id ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not assigned to this course.', 'giga-class-market' ) ), 403 );
		}

		$questions = array();
		if ( isset( $_POST['questions'] ) ) {
			$raw = wp_unslash( $_POST['questions'] );
			if ( is_string( $raw ) ) {
				$decoded   = json_decode( $raw, true );
				$questions = is_array( $decoded ) ? $decoded : array();
			} elseif ( is_array( $raw ) ) {
				$questions = $raw;
			}
		}

		$result = GCM_Quiz_Service::create_quiz(
			array(
				'course_id'  => $course_id,
				'title'      => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
				'pass_score' => isset( $_POST['pass_score'] ) ? absint( $_POST['pass_score'] ) : 70,
				'questions'  => $questions,
			)
		);
		$this->send_service_response( $result, __( 'Quiz saved.', 'giga-class-market' ) );
	}

	/**
	 * Clear students, payments, and contact messages (test/operational wipe).
	 *
	 * @return void
	 */
	public function clear_operational_test_data() {
		GCM_Security::verify_ajax_nonce();
		GCM_Security::require_capability( 'manage_options' );

		$confirm = isset( $_POST['confirm'] ) ? sanitize_text_field( wp_unslash( $_POST['confirm'] ) ) : '';
		if ( 'CLEAR' !== $confirm ) {
			wp_send_json_error( array( 'message' => __( 'Type CLEAR to confirm this irreversible cleanup.', 'giga-class-market' ) ), 400 );
		}

		$result = GCM_Data_Cleanup_Service::clear_operational_test_data();
		$this->send_service_response( $result, $result['message'] ?? __( 'Operational test data cleared.', 'giga-class-market' ) );
	}

	/**
	 * Require teacher dashboard capability or admin.
	 *
	 * @return void
	 */
	private function require_teacher_or_admin() {
		if ( current_user_can( 'manage_options' ) || current_user_can( 'gcm_teacher_dashboard' ) ) {
			return;
		}
		wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'giga-class-market' ) ), 403 );
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
