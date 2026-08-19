<?php
/**
 * Database installer.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates and upgrades plugin tables/options.
 */
class GCM_Installer {

	/**
	 * Install database tables and default options.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix;

		$sql = array();

		$sql[] = "CREATE TABLE {$prefix}gcm_modules (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			course_id BIGINT(20) UNSIGNED NOT NULL,
			title VARCHAR(255) NOT NULL,
			sort_order INT(11) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY course_id (course_id),
			KEY sort_order (sort_order)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}gcm_lessons (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			module_id BIGINT(20) UNSIGNED NOT NULL,
			course_id BIGINT(20) UNSIGNED NOT NULL,
			title VARCHAR(255) NOT NULL,
			content LONGTEXT NULL,
			video_url TEXT NULL,
			video_attachment_id BIGINT(20) UNSIGNED NULL,
			duration_minutes INT(11) NOT NULL DEFAULT 0,
			is_preview TINYINT(1) NOT NULL DEFAULT 0,
			sort_order INT(11) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY module_id (module_id),
			KEY course_id (course_id),
			KEY sort_order (sort_order),
			KEY is_preview (is_preview)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}gcm_enrollments (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NOT NULL,
			course_id BIGINT(20) UNSIGNED NOT NULL,
			payment_id BIGINT(20) UNSIGNED NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			enrolled_at DATETIME NOT NULL,
			completed_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_course (user_id, course_id),
			KEY status (status),
			KEY payment_id (payment_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}gcm_progress (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NOT NULL,
			course_id BIGINT(20) UNSIGNED NOT NULL,
			lesson_id BIGINT(20) UNSIGNED NOT NULL,
			completed TINYINT(1) NOT NULL DEFAULT 0,
			last_position INT(11) NOT NULL DEFAULT 0,
			completed_at DATETIME NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_lesson (user_id, lesson_id),
			KEY user_course (user_id, course_id),
			KEY completed (completed)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}gcm_payments (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NULL,
			course_id BIGINT(20) UNSIGNED NOT NULL,
			full_name VARCHAR(190) NOT NULL,
			email VARCHAR(190) NOT NULL,
			whatsapp VARCHAR(60) NOT NULL,
			address TEXT NULL,
			transaction_id VARCHAR(190) NOT NULL,
			payment_method VARCHAR(80) NOT NULL,
			amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			screenshot_id BIGINT(20) UNSIGNED NULL,
			status VARCHAR(30) NOT NULL DEFAULT 'pending',
			rejection_reason TEXT NULL,
			submitted_at DATETIME NOT NULL,
			reviewed_at DATETIME NULL,
			reviewed_by BIGINT(20) UNSIGNED NULL,
			account_created TINYINT(1) NOT NULL DEFAULT 0,
			credentials_sent_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY course_id (course_id),
			KEY status (status),
			KEY email (email),
			KEY transaction_id (transaction_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}gcm_contacts (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			full_name VARCHAR(190) NOT NULL,
			email VARCHAR(190) NOT NULL,
			whatsapp VARCHAR(60) NULL,
			subject VARCHAR(255) NOT NULL,
			message LONGTEXT NOT NULL,
			status VARCHAR(30) NOT NULL DEFAULT 'new',
			created_at DATETIME NOT NULL,
			contacted_at DATETIME NULL,
			updated_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY email (email)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}gcm_audit_log (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			admin_id BIGINT(20) UNSIGNED NOT NULL,
			action VARCHAR(100) NOT NULL,
			object_type VARCHAR(100) NOT NULL,
			object_id BIGINT(20) UNSIGNED NULL,
			meta LONGTEXT NULL,
			ip_address VARCHAR(45) NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY admin_id (admin_id),
			KEY object_type (object_type),
			KEY action (action)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}gcm_notifications (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NULL,
			type VARCHAR(80) NOT NULL,
			title VARCHAR(255) NOT NULL,
			message LONGTEXT NOT NULL,
			channel VARCHAR(30) NOT NULL DEFAULT 'email',
			status VARCHAR(30) NOT NULL DEFAULT 'queued',
			meta LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY type (type),
			KEY status (status),
			KEY channel (channel)
		) {$charset_collate};";


		$sql[] = "CREATE TABLE {$prefix}gcm_teacher_courses (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			teacher_id BIGINT(20) UNSIGNED NOT NULL,
			course_id BIGINT(20) UNSIGNED NOT NULL,
			assigned_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY teacher_course (teacher_id, course_id),
			UNIQUE KEY course_id (course_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}gcm_classes (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			course_id BIGINT(20) UNSIGNED NOT NULL,
			teacher_id BIGINT(20) UNSIGNED NOT NULL,
			title VARCHAR(255) NOT NULL,
			scheduled_at DATETIME NOT NULL,
			scheduled_end DATETIME NULL,
			status VARCHAR(30) NOT NULL DEFAULT 'scheduled',
			zoom_meeting_id VARCHAR(100) NULL,
			zoom_join_url TEXT NULL,
			zoom_start_url TEXT NULL,
			started_at DATETIME NULL,
			ended_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY course_id (course_id),
			KEY teacher_id (teacher_id),
			KEY status (status),
			KEY scheduled_at (scheduled_at)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}gcm_notes (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			course_id BIGINT(20) UNSIGNED NOT NULL,
			teacher_id BIGINT(20) UNSIGNED NOT NULL,
			title VARCHAR(255) NOT NULL,
			content LONGTEXT NULL,
			file_id BIGINT(20) UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY course_id (course_id),
			KEY teacher_id (teacher_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}gcm_messages (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			course_id BIGINT(20) UNSIGNED NOT NULL,
			sender_id BIGINT(20) UNSIGNED NOT NULL,
			recipient_id BIGINT(20) UNSIGNED NULL,
			message LONGTEXT NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY course_id (course_id),
			KEY sender_id (sender_id),
			KEY recipient_id (recipient_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}gcm_attendance (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			class_id BIGINT(20) UNSIGNED NOT NULL,
			course_id BIGINT(20) UNSIGNED NOT NULL,
			user_id BIGINT(20) UNSIGNED NOT NULL,
			joined_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY class_user (class_id, user_id),
			KEY course_id (course_id),
			KEY user_id (user_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}gcm_certificates (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			certificate_code VARCHAR(40) NOT NULL,
			user_id BIGINT(20) UNSIGNED NOT NULL,
			course_id BIGINT(20) UNSIGNED NOT NULL,
			student_name VARCHAR(191) NOT NULL,
			course_title VARCHAR(255) NOT NULL,
			issued_at DATETIME NOT NULL,
			issued_by BIGINT(20) UNSIGNED NULL,
			email_status VARCHAR(20) NOT NULL DEFAULT 'pending',
			meta LONGTEXT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY certificate_code (certificate_code),
			UNIQUE KEY user_course (user_id, course_id),
			KEY course_id (course_id),
			KEY issued_at (issued_at)
		) {$charset_collate};";



		$sql[] = "CREATE TABLE {$prefix}gcm_coupons (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			code VARCHAR(60) NOT NULL,
			description VARCHAR(255) NULL,
			discount_type VARCHAR(20) NOT NULL DEFAULT 'percent',
			discount_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			course_id BIGINT(20) UNSIGNED NULL,
			max_uses INT(11) NOT NULL DEFAULT 0,
			used_count INT(11) NOT NULL DEFAULT 0,
			min_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			starts_at DATETIME NULL,
			expires_at DATETIME NULL,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			created_by BIGINT(20) UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY code (code),
			KEY course_id (course_id),
			KEY is_active (is_active)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}gcm_coupon_uses (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			coupon_id BIGINT(20) UNSIGNED NOT NULL,
			user_id BIGINT(20) UNSIGNED NULL,
			payment_id BIGINT(20) UNSIGNED NULL,
			course_id BIGINT(20) UNSIGNED NOT NULL,
			discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			used_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY coupon_id (coupon_id),
			KEY user_id (user_id),
			KEY payment_id (payment_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}gcm_reviews (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			course_id BIGINT(20) UNSIGNED NOT NULL,
			user_id BIGINT(20) UNSIGNED NOT NULL,
			rating TINYINT(1) NOT NULL DEFAULT 5,
			review_title VARCHAR(190) NULL,
			review_body LONGTEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'approved',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY course_user (course_id, user_id),
			KEY status (status),
			KEY rating (rating)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}gcm_quizzes (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			course_id BIGINT(20) UNSIGNED NOT NULL,
			module_id BIGINT(20) UNSIGNED NULL,
			title VARCHAR(255) NOT NULL,
			pass_score INT(11) NOT NULL DEFAULT 70,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY course_id (course_id),
			KEY module_id (module_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}gcm_quiz_questions (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			quiz_id BIGINT(20) UNSIGNED NOT NULL,
			question LONGTEXT NOT NULL,
			options_json LONGTEXT NOT NULL,
			correct_index INT(11) NOT NULL DEFAULT 0,
			sort_order INT(11) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY quiz_id (quiz_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}gcm_quiz_attempts (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			quiz_id BIGINT(20) UNSIGNED NOT NULL,
			user_id BIGINT(20) UNSIGNED NOT NULL,
			score INT(11) NOT NULL DEFAULT 0,
			passed TINYINT(1) NOT NULL DEFAULT 0,
			answers_json LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY quiz_user (quiz_id, user_id),
			KEY user_id (user_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}gcm_recordings (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			course_id BIGINT(20) UNSIGNED NOT NULL,
			class_id BIGINT(20) UNSIGNED NULL,
			teacher_id BIGINT(20) UNSIGNED NOT NULL,
			title VARCHAR(255) NOT NULL,
			video_url TEXT NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY course_id (course_id),
			KEY class_id (class_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}gcm_announcements (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			course_id BIGINT(20) UNSIGNED NOT NULL,
			teacher_id BIGINT(20) UNSIGNED NOT NULL,
			title VARCHAR(255) NOT NULL,
			body LONGTEXT NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY course_id (course_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}gcm_assignments (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			course_id BIGINT(20) UNSIGNED NOT NULL,
			teacher_id BIGINT(20) UNSIGNED NOT NULL,
			title VARCHAR(255) NOT NULL,
			instructions LONGTEXT NULL,
			due_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY course_id (course_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}gcm_assignment_submissions (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			assignment_id BIGINT(20) UNSIGNED NOT NULL,
			course_id BIGINT(20) UNSIGNED NOT NULL,
			user_id BIGINT(20) UNSIGNED NOT NULL,
			file_id BIGINT(20) UNSIGNED NULL,
			notes LONGTEXT NULL,
			grade VARCHAR(20) NULL,
			feedback LONGTEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'submitted',
			submitted_at DATETIME NOT NULL,
			graded_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY assignment_user (assignment_id, user_id),
			KEY course_id (course_id),
			KEY user_id (user_id)
		) {$charset_collate};";


		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		self::maybe_migrate_one_teacher_per_course();
		self::seed_default_options();
		update_option( 'gcm_db_version', GCM_DB_VERSION );
	}

	/**
	 * Keep one teacher per course (drop older duplicate assignments).
	 *
	 * @return void
	 */
	private static function maybe_migrate_one_teacher_per_course() {
		global $wpdb;

		$table = $wpdb->prefix . 'gcm_teacher_courses';
		$dupes = $wpdb->get_results(
			"SELECT course_id, COUNT(*) AS c FROM {$table} GROUP BY course_id HAVING c > 1"
		);
		foreach ( (array) $dupes as $row ) {
			$keep = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE course_id = %d ORDER BY assigned_at DESC, id DESC LIMIT 1",
					(int) $row->course_id
				)
			);
			if ( $keep ) {
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$table} WHERE course_id = %d AND id <> %d",
						(int) $row->course_id,
						(int) $keep
					)
				);
			}
		}
	}

	/**
	 * Seed settings if missing.
	 *
	 * @return void
	 */
	private static function seed_default_options() {
		if ( false !== get_option( 'gcm_settings', false ) ) {
			return;
		}

		add_option(
			'gcm_settings',
			array(
				'company'  => array(
					'name'     => 'Giga Class Market',
					'email'    => 'Official@gigaclassmarket.com',
					'phone'    => '03288966951',
					'whatsapp' => '+966509136037',
					'address'  => '',
				),
				'payment'  => array(
					'methods' => array(
						'Bank'     => array(
							'enabled'      => 1,
							'account_name' => '',
							'account_no'   => '',
							'instructions' => '',
						),
						'JazzCash' => array(
							'enabled'      => 1,
							'account_name' => '',
							'account_no'   => '',
							'instructions' => '',
						),
						'Easypaisa' => array(
							'enabled'      => 1,
							'account_name' => '',
							'account_no'   => '',
							'instructions' => '',
						),
					),
				),
				'website'  => array(
					'theme_color'      => '#0d3b45',
					'accent_color'     => '#e0a045',
					'student_page_slug' => 'student-dashboard',
				),
				'course'   => array(
					'featured_count'   => 3,
					'default_duration' => '',
					'default_rating'   => '5.0',
				),
				'security' => array(
					'default_password' => 'Student@giga',
					'max_upload_mb'    => 5,
				),
			),
			'',
			false
		);
	}

	/**
	 * Apply versioned settings upgrades (safe to call on every load).
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$stored = get_option( 'gcm_plugin_version', '0' );
		if ( version_compare( (string) $stored, GCM_VERSION, 'ge' ) ) {
			return;
		}

		// Ensure new tables/columns exist on every plugin version bump.
		self::install();

		if ( class_exists( 'GCM_Activator' ) ) {
			GCM_Activator::create_pages();
		}

		$settings = get_option( 'gcm_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		if ( empty( $settings['company'] ) || ! is_array( $settings['company'] ) ) {
			$settings['company'] = array();
		}

		// Keep public mobile number in sync for Contact page / footer.
		$settings['company']['phone'] = '03288966951';
		if ( empty( $settings['company']['whatsapp'] ) ) {
			$settings['company']['whatsapp'] = '+966509136037';
		}
		$settings['company']['email'] = 'Official@gigaclassmarket.com';
		if ( empty( $settings['company']['name'] ) ) {
			$settings['company']['name'] = 'Giga Class Market';
		}

		if ( empty( $settings['zoom'] ) || ! is_array( $settings['zoom'] ) ) {
			$settings['zoom'] = array(
				'account_id'    => '',
				'client_id'     => '',
				'client_secret' => '',
			);
		}

		// Refresh site palette to teal ink + amber (theme CSS is source of truth; keep settings in sync).
		if ( empty( $settings['website'] ) || ! is_array( $settings['website'] ) ) {
			$settings['website'] = array();
		}
		$settings['website']['theme_color']  = '#0d3b45';
		$settings['website']['accent_color'] = '#e0a045';

		// Normalize existing students to GCM-only identity (not Subscriber + Student).
		// Also strip accidental gcm_student role from administrators so all admins remain visible.
		$students = get_users(
			array(
				'role'   => 'gcm_student',
				'fields' => array( 'ID' ),
				'number' => 500,
			)
		);
		foreach ( $students as $student ) {
			GCM_Roles::assign_student_identity( (int) $student->ID );
		}

		$admins = get_users(
			array(
				'role'   => 'administrator',
				'fields' => array( 'ID' ),
				'number' => 200,
			)
		);
		foreach ( $admins as $admin ) {
			$admin_user = get_userdata( (int) $admin->ID );
			if ( $admin_user ) {
				$admin_user->remove_role( 'gcm_student' );
				delete_user_meta( $admin_user->ID, 'gcm_is_student' );
				if ( 'student' === get_user_meta( $admin_user->ID, 'gcm_account_type', true ) ) {
					delete_user_meta( $admin_user->ID, 'gcm_account_type' );
				}
			}
		}

		update_option( 'gcm_settings', $settings, false );

		// Deep course SEO: keyword titles/descriptions/FAQs for every published course.
		if ( class_exists( 'GCM_Course_SEO' ) ) {
			GCM_Course_SEO::ensure_all_published();
		}

		// Keep marketplace SEO strings search-focused.
		if ( empty( $settings['seo'] ) || ! is_array( $settings['seo'] ) ) {
			$settings['seo'] = array();
		}
		$seo_defaults = class_exists( 'GCM_SEO' ) ? GCM_SEO::defaults() : array();
		foreach ( array( 'courses_title', 'courses_description', 'home_title', 'home_description' ) as $seo_key ) {
			if ( empty( $settings['seo'][ $seo_key ] ) && ! empty( $seo_defaults[ $seo_key ] ) ) {
				$settings['seo'][ $seo_key ] = $seo_defaults[ $seo_key ];
			}
		}
		// Refresh courses archive SEO copy to the stronger defaults when still generic.
		if ( ! empty( $seo_defaults['courses_title'] ) ) {
			$current_courses_title = (string) ( $settings['seo']['courses_title'] ?? '' );
			if ( '' === $current_courses_title || false !== stripos( $current_courses_title, 'Explore Premium Courses' ) ) {
				$settings['seo']['courses_title'] = $seo_defaults['courses_title'];
			}
		}
		if ( ! empty( $seo_defaults['courses_description'] ) ) {
			$current_courses_desc = (string) ( $settings['seo']['courses_description'] ?? '' );
			if ( '' === $current_courses_desc || false !== stripos( $current_courses_desc, 'Browse premium digital courses' ) ) {
				$settings['seo']['courses_description'] = $seo_defaults['courses_description'];
			}
		}
		update_option( 'gcm_settings', $settings, false );

		update_option( 'gcm_plugin_version', GCM_VERSION, false );
	}
}
