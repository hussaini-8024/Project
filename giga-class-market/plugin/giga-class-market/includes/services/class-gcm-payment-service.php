<?php
/**
 * Payment service.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles payment submission and review workflows.
 */
class GCM_Payment_Service {

	/**
	 * Submit a payment for review.
	 *
	 * @param array $data Payment data.
	 * @param array $file Uploaded screenshot file.
	 * @return int|WP_Error
	 */
	public static function submit( $data, $file = array() ) {
		global $wpdb;

		$course_id = absint( $data['course_id'] ?? 0 );
		$course    = GCM_Course_Service::get( $course_id );
		if ( ! $course ) {
			return new WP_Error( 'gcm_invalid_course', __( 'Invalid course.', 'giga-class-market' ) );
		}

		$email = sanitize_email( $data['email'] ?? '' );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'gcm_invalid_email', __( 'Please provide a valid email address.', 'giga-class-market' ) );
		}

		$screenshot_id = 0;
		if ( ! empty( $file['name'] ) ) {
			$screenshot_id = self::store_private_screenshot( $file );
			if ( is_wp_error( $screenshot_id ) ) {
				return $screenshot_id;
			}
		}

		$transaction_id = sanitize_text_field( $data['transaction_id'] ?? '' );
		if ( '' === $transaction_id ) {
			return new WP_Error( 'gcm_missing_transaction', __( 'Transaction ID is required.', 'giga-class-market' ) );
		}

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'gcm_payments',
			array(
				'user_id'          => get_current_user_id() ? get_current_user_id() : null,
				'course_id'        => $course_id,
				'full_name'        => sanitize_text_field( $data['full_name'] ?? '' ),
				'email'            => $email,
				'whatsapp'         => sanitize_text_field( $data['whatsapp'] ?? '' ),
				'address'          => sanitize_textarea_field( $data['address'] ?? '' ),
				'transaction_id'   => $transaction_id,
				'payment_method'   => sanitize_text_field( $data['payment_method'] ?? '' ),
				'amount'           => (float) $course['price'],
				'screenshot_id'    => $screenshot_id ? absint( $screenshot_id ) : null,
				'status'           => 'under_review',
				'rejection_reason' => null,
				'submitted_at'     => current_time( 'mysql' ),
				'reviewed_at'      => null,
				'reviewed_by'      => null,
				'account_created'  => 0,
				'credentials_sent_at' => null,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'gcm_payment_failed', __( 'Unable to submit payment. Please try again.', 'giga-class-market' ) );
		}

		$payment_id = (int) $wpdb->insert_id;
		GCM_Audit_Service::log( 'payment_submitted', 'payment', $payment_id, array( 'course_id' => $course_id ), 0 );

		return $payment_id;
	}

	/**
	 * Approve a payment, create/find user, enroll, and notify.
	 *
	 * @param int $payment_id Payment ID.
	 * @param int $reviewed_by Admin ID.
	 * @return int|WP_Error User ID or error.
	 */
	public static function approve( $payment_id, $reviewed_by = 0 ) {
		global $wpdb;

		$payment = self::get( $payment_id );
		if ( ! $payment ) {
			return new WP_Error( 'gcm_invalid_payment', __( 'Invalid payment.', 'giga-class-market' ) );
		}

		if ( 'approved' === $payment->status ) {
			return (int) $payment->user_id;
		}

		$user_result = self::create_or_get_student_user( $payment );
		if ( is_wp_error( $user_result ) ) {
			return $user_result;
		}

		$user_id         = (int) $user_result['user_id'];
		$account_created = ! empty( $user_result['created'] ) ? 1 : 0;
		$enrollment_id   = GCM_Enrollment_Service::enroll( $user_id, $payment->course_id, $payment->id, 'active' );

		if ( ! $enrollment_id ) {
			return new WP_Error( 'gcm_enroll_failed', __( 'Unable to enroll the student.', 'giga-class-market' ) );
		}

		$credentials_sent = null;
		if ( $account_created ) {
			self::send_credentials( $user_id, $payment->id, false );
			$credentials_sent = current_time( 'mysql' );
		}

		$wpdb->update(
			$wpdb->prefix . 'gcm_payments',
			array(
				'user_id'             => $user_id,
				'status'              => 'approved',
				'rejection_reason'    => null,
				'reviewed_at'         => current_time( 'mysql' ),
				'reviewed_by'         => $reviewed_by ? absint( $reviewed_by ) : get_current_user_id(),
				'account_created'     => $account_created,
				'credentials_sent_at' => $credentials_sent,
			),
			array( 'id' => absint( $payment->id ) ),
			array( '%d', '%s', '%s', '%s', '%d', '%d', '%s' ),
			array( '%d' )
		);

		$course = GCM_Course_Service::get( $payment->course_id );
		GCM_Notification_Service::queue_email(
			$user_id,
			'payment_approved',
			__( 'Your enrollment is approved', 'giga-class-market' ),
			sprintf(
				/* translators: %s: course title */
				__( 'Your payment has been approved and you are enrolled in %s. Please log in to your student dashboard to begin.', 'giga-class-market' ),
				esc_html( $course ? $course['title'] : __( 'your course', 'giga-class-market' ) )
			),
			$payment->email,
			array( 'payment_id' => $payment->id, 'course_id' => $payment->course_id )
		);

		GCM_Audit_Service::log( 'payment_approved', 'payment', $payment->id, array( 'user_id' => $user_id, 'course_id' => $payment->course_id ) );

		return $user_id;
	}

	/**
	 * Reject a payment.
	 *
	 * @param int    $payment_id Payment ID.
	 * @param string $reason Rejection reason.
	 * @param int    $reviewed_by Admin ID.
	 * @return bool|WP_Error
	 */
	public static function reject( $payment_id, $reason, $reviewed_by = 0 ) {
		global $wpdb;

		$payment = self::get( $payment_id );
		if ( ! $payment ) {
			return new WP_Error( 'gcm_invalid_payment', __( 'Invalid payment.', 'giga-class-market' ) );
		}

		$reason = sanitize_textarea_field( $reason );
		$result = $wpdb->update(
			$wpdb->prefix . 'gcm_payments',
			array(
				'status'           => 'rejected',
				'rejection_reason' => $reason,
				'reviewed_at'      => current_time( 'mysql' ),
				'reviewed_by'      => $reviewed_by ? absint( $reviewed_by ) : get_current_user_id(),
			),
			array( 'id' => absint( $payment_id ) ),
			array( '%s', '%s', '%s', '%d' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'gcm_reject_failed', __( 'Unable to reject payment.', 'giga-class-market' ) );
		}

		GCM_Notification_Service::queue_email(
			(int) $payment->user_id,
			'payment_rejected',
			__( 'Payment verification update', 'giga-class-market' ),
			sprintf(
				/* translators: %s: rejection reason */
				__( 'Your payment could not be approved. Reason: %s', 'giga-class-market' ),
				esc_html( $reason )
			),
			$payment->email,
			array( 'payment_id' => $payment->id )
		);

		GCM_Audit_Service::log( 'payment_rejected', 'payment', $payment->id, array( 'reason' => $reason ) );

		return true;
	}

	/**
	 * Get a payment.
	 *
	 * @param int $payment_id Payment ID.
	 * @return object|null
	 */
	public static function get( $payment_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_payments WHERE id = %d",
				absint( $payment_id )
			)
		);
	}

	/**
	 * Get payments by status.
	 *
	 * @param string $status Status.
	 * @param int    $limit Limit.
	 * @param int    $offset Offset.
	 * @return array
	 */
	public static function get_by_status( $status = '', $limit = 50, $offset = 0 ) {
		global $wpdb;

		$limit  = min( 200, max( 1, absint( $limit ) ) );
		$offset = max( 0, absint( $offset ) );

		if ( $status ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}gcm_payments WHERE status = %s ORDER BY submitted_at DESC LIMIT %d OFFSET %d",
					self::sanitize_status( $status ),
					$limit,
					$offset
				)
			);
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_payments ORDER BY submitted_at DESC LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);
	}

	/**
	 * Create student account for a payment without approving it.
	 *
	 * @param int $payment_id Payment ID.
	 * @return int|WP_Error
	 */
	public static function create_student_account( $payment_id ) {
		global $wpdb;

		$payment = self::get( $payment_id );
		if ( ! $payment ) {
			return new WP_Error( 'gcm_invalid_payment', __( 'Invalid payment.', 'giga-class-market' ) );
		}

		$result = self::create_or_get_student_user( $payment );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$wpdb->update(
			$wpdb->prefix . 'gcm_payments',
			array(
				'user_id'         => (int) $result['user_id'],
				'account_created' => ! empty( $result['created'] ) ? 1 : 0,
			),
			array( 'id' => absint( $payment_id ) ),
			array( '%d', '%d' ),
			array( '%d' )
		);

		GCM_Audit_Service::log( 'student_account_created', 'payment', $payment_id, array( 'user_id' => (int) $result['user_id'] ) );
		return (int) $result['user_id'];
	}

	/**
	 * Send/reset credentials for a student.
	 *
	 * @param int  $user_id User ID.
	 * @param int  $payment_id Payment ID.
	 * @param bool $reset_password Whether to reset to configured temporary password.
	 * @return bool
	 */
	public static function send_credentials( $user_id, $payment_id = 0, $reset_password = true ) {
		global $wpdb;

		$user = get_userdata( absint( $user_id ) );
		if ( ! $user ) {
			return false;
		}

		$password = GCM_Settings_Service::get_default_password();
		if ( $reset_password ) {
			wp_set_password( $password, $user->ID );
		}

		$login_url = wp_login_url( home_url( '/student-dashboard/' ) );
		$message   = sprintf(
			/* translators: 1: login URL, 2: username, 3: temporary password */
			__( 'Your Giga Class Market student account is ready.<br>Login: %1$s<br>Username: %2$s<br>Temporary password: %3$s<br>Please change this password after logging in.', 'giga-class-market' ),
			esc_url( $login_url ),
			esc_html( $user->user_login ),
			esc_html( $password )
		);

		GCM_Notification_Service::queue_email(
			$user->ID,
			'student_credentials',
			__( 'Your Giga Class Market student login', 'giga-class-market' ),
			$message,
			$user->user_email,
			array( 'payment_id' => absint( $payment_id ) )
		);

		$whatsapp_message = sprintf(
			"Hello %s,\n\nYour Giga Class Market account is ready.\nLogin: %s\nUsername: %s\nTemporary password: %s\n\nPlease change your password after login.",
			$user->display_name,
			$login_url,
			$user->user_login,
			$password
		);
		$student_whatsapp = get_user_meta( $user->ID, 'gcm_whatsapp', true );
		GCM_Notification_Service::queue_whatsapp(
			$user->ID,
			'student_credentials',
			__( 'Student credentials', 'giga-class-market' ),
			$whatsapp_message,
			$student_whatsapp,
			array( 'payment_id' => absint( $payment_id ) )
		);
		$whatsapp_url = GCM_Notification_Service::build_whatsapp_url( $student_whatsapp, $whatsapp_message );

		if ( $payment_id ) {
			$wpdb->update(
				$wpdb->prefix . 'gcm_payments',
				array( 'credentials_sent_at' => current_time( 'mysql' ) ),
				array( 'id' => absint( $payment_id ) ),
				array( '%s' ),
				array( '%d' )
			);
		}

		GCM_Audit_Service::log( 'credentials_sent', 'user', $user->ID, array( 'payment_id' => absint( $payment_id ) ) );
		return array(
			'success'      => true,
			'whatsapp_url' => $whatsapp_url,
			'message'      => __( 'Account details prepared. Email queued and WhatsApp fallback link is ready.', 'giga-class-market' ),
		);
	}

	/**
	 * Create or find student user.
	 *
	 * @param object $payment Payment row.
	 * @return array|WP_Error
	 */
	private static function create_or_get_student_user( $payment ) {
		$user = get_user_by( 'email', $payment->email );

		if ( $user ) {
			$user->add_role( 'gcm_student' );
			return array(
				'user_id' => (int) $user->ID,
				'created' => false,
			);
		}

		$username = self::generate_username( $payment->email, $payment->full_name );
		$password = GCM_Settings_Service::get_default_password();
		$user_id  = wp_create_user( $username, $password, $payment->email );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		wp_update_user(
			array(
				'ID'           => $user_id,
				'display_name' => sanitize_text_field( $payment->full_name ),
				'first_name'   => sanitize_text_field( $payment->full_name ),
				'role'         => 'gcm_student',
			)
		);
		wp_set_password( $password, $user_id );
		update_user_meta( $user_id, 'gcm_whatsapp', sanitize_text_field( $payment->whatsapp ) );
		update_user_meta( $user_id, 'gcm_address', sanitize_textarea_field( $payment->address ) );

		return array(
			'user_id' => (int) $user_id,
			'created' => true,
		);
	}

	/**
	 * Generate a unique username.
	 *
	 * @param string $email Email.
	 * @param string $name Full name.
	 * @return string
	 */
	private static function generate_username( $email, $name ) {
		$base = sanitize_user( current( explode( '@', $email ) ), true );
		if ( ! $base ) {
			$base = sanitize_user( $name, true );
		}
		if ( ! $base ) {
			$base = 'student';
		}

		$username = $base;
		$suffix   = 1;
		while ( username_exists( $username ) ) {
			$username = $base . $suffix;
			++$suffix;
		}

		return $username;
	}

	/**
	 * Store private screenshot as an attachment with direct web access blocked.
	 *
	 * @param array $file Uploaded file.
	 * @return int|WP_Error
	 */
	private static function store_private_screenshot( $file ) {
		$validation = GCM_Security::validate_upload( $file );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return new WP_Error( 'gcm_upload_dir', $upload_dir['error'] );
		}

		$private_dir = trailingslashit( $upload_dir['basedir'] ) . 'gcm-private';
		if ( ! wp_mkdir_p( $private_dir ) ) {
			return new WP_Error( 'gcm_upload_dir', __( 'Unable to create private upload directory.', 'giga-class-market' ) );
		}

		if ( ! file_exists( trailingslashit( $private_dir ) . 'index.php' ) ) {
			file_put_contents( trailingslashit( $private_dir ) . 'index.php', "<?php\n// Silence is golden.\n" );
		}
		if ( ! file_exists( trailingslashit( $private_dir ) . '.htaccess' ) ) {
			file_put_contents( trailingslashit( $private_dir ) . '.htaccess', "Deny from all\n" );
		}

		$file_name = wp_unique_filename( $private_dir, sanitize_file_name( $file['name'] ) );
		$target    = trailingslashit( $private_dir ) . $file_name;

		if ( ! move_uploaded_file( $file['tmp_name'], $target ) ) {
			return new WP_Error( 'gcm_upload_failed', __( 'Unable to store payment screenshot.', 'giga-class-market' ) );
		}

		$filetype = wp_check_filetype( $file_name );
		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $filetype['type'],
				'post_title'     => preg_replace( '/\.[^.]+$/', '', $file_name ),
				'post_content'   => '',
				'post_status'    => 'private',
			),
			$target
		);

		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			wp_delete_file( $target );
			return new WP_Error( 'gcm_attachment_failed', __( 'Unable to save screenshot attachment.', 'giga-class-market' ) );
		}

		update_post_meta( $attachment_id, '_gcm_private_file', $target );
		update_post_meta( $attachment_id, '_gcm_private_upload', 1 );

		return (int) $attachment_id;
	}

	/**
	 * Sanitize payment status.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	private static function sanitize_status( $status ) {
		$status = sanitize_key( $status );
		return in_array( $status, array( 'pending', 'under_review', 'approved', 'rejected' ), true ) ? $status : 'under_review';
	}
}
