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
			sort_order INT(11) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY module_id (module_id),
			KEY course_id (course_id),
			KEY sort_order (sort_order)
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

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		self::seed_default_options();
		update_option( 'gcm_db_version', GCM_DB_VERSION );
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
					'email'    => get_option( 'admin_email' ),
					'phone'    => '+966509136037',
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
					'theme_color'      => '#0b1f3a',
					'accent_color'     => '#d4af37',
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

		$settings = get_option( 'gcm_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		if ( empty( $settings['company'] ) || ! is_array( $settings['company'] ) ) {
			$settings['company'] = array();
		}

		// Business sender WhatsApp used for student messages and public contact links.
		$settings['company']['whatsapp'] = '+966509136037';
		if ( empty( $settings['company']['phone'] ) ) {
			$settings['company']['phone'] = '+966509136037';
		} else {
			$settings['company']['phone'] = '+966509136037';
		}

		update_option( 'gcm_settings', $settings, false );
		update_option( 'gcm_plugin_version', GCM_VERSION, false );
	}
}
