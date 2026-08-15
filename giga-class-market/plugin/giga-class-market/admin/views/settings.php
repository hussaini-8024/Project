<?php
/**
 * Settings view.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$methods = $settings['payment']['methods'];
?>
<div class="wrap gcm-admin-wrap">
	<h1><?php esc_html_e( 'Settings', 'giga-class-market' ); ?></h1>
	<form class="gcm-settings-form">
		<input type="hidden" name="action" value="gcm_save_settings" />
		<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'gcm_ajax_nonce' ) ); ?>" />
		<nav class="gcm-tabs">
			<a href="#gcm-tab-company" class="active"><?php esc_html_e( 'Company', 'giga-class-market' ); ?></a>
			<a href="#gcm-tab-payment"><?php esc_html_e( 'Payment', 'giga-class-market' ); ?></a>
			<a href="#gcm-tab-whatsapp"><?php esc_html_e( 'WhatsApp', 'giga-class-market' ); ?></a>
			<a href="#gcm-tab-website"><?php esc_html_e( 'Website', 'giga-class-market' ); ?></a>
			<a href="#gcm-tab-course"><?php esc_html_e( 'Course', 'giga-class-market' ); ?></a>
			<a href="#gcm-tab-zoom"><?php esc_html_e( 'Zoom', 'giga-class-market' ); ?></a>
		</nav>

		<section id="gcm-tab-company" class="gcm-tab active">
			<h2><?php esc_html_e( 'Company Information', 'giga-class-market' ); ?></h2>
			<label><?php esc_html_e( 'Company name', 'giga-class-market' ); ?><input type="text" name="settings[company][name]" value="<?php echo esc_attr( $settings['company']['name'] ); ?>" /></label>
			<label><?php esc_html_e( 'Email (outgoing From address)', 'giga-class-market' ); ?><input type="email" name="settings[company][email]" value="<?php echo esc_attr( $settings['company']['email'] ); ?>" placeholder="Official@gigaclassmarket.com" /></label>
			<p class="description"><?php esc_html_e( 'Used as the From / Reply-To address for student and admin emails (instead of wordpress@…).', 'giga-class-market' ); ?></p>
			<label><?php esc_html_e( 'Phone', 'giga-class-market' ); ?><input type="text" name="settings[company][phone]" value="<?php echo esc_attr( $settings['company']['phone'] ); ?>" /></label>
			<label><?php esc_html_e( 'Address', 'giga-class-market' ); ?><textarea name="settings[company][address]" rows="3"><?php echo esc_textarea( $settings['company']['address'] ); ?></textarea></label>
			<label><?php esc_html_e( 'Business hours', 'giga-class-market' ); ?><input type="text" name="settings[company][hours]" value="<?php echo esc_attr( $settings['company']['hours'] ?? '' ); ?>" /></label>
			<label><?php esc_html_e( 'Facebook URL', 'giga-class-market' ); ?><input type="url" name="settings[company][facebook]" value="<?php echo esc_attr( $settings['company']['facebook'] ?? '' ); ?>" /></label>
			<label><?php esc_html_e( 'Instagram URL', 'giga-class-market' ); ?><input type="url" name="settings[company][instagram]" value="<?php echo esc_attr( $settings['company']['instagram'] ?? '' ); ?>" /></label>
			<label><?php esc_html_e( 'LinkedIn URL', 'giga-class-market' ); ?><input type="url" name="settings[company][linkedin]" value="<?php echo esc_attr( $settings['company']['linkedin'] ?? '' ); ?>" /></label>
			<label><?php esc_html_e( 'YouTube URL', 'giga-class-market' ); ?><input type="url" name="settings[company][youtube]" value="<?php echo esc_attr( $settings['company']['youtube'] ?? '' ); ?>" /></label>
		</section>

		<section id="gcm-tab-payment" class="gcm-tab">
			<h2><?php esc_html_e( 'Payment Methods', 'giga-class-market' ); ?></h2>
			<?php foreach ( $methods as $name => $method ) : ?>
				<div class="gcm-admin-panel">
					<h3><?php echo esc_html( $name ); ?></h3>
					<input type="hidden" name="settings[payment][methods][<?php echo esc_attr( $name ); ?>][enabled]" value="0" />
					<label><input type="checkbox" name="settings[payment][methods][<?php echo esc_attr( $name ); ?>][enabled]" value="1" <?php checked( $method['enabled'], 1 ); ?> /> <?php esc_html_e( 'Enabled', 'giga-class-market' ); ?></label>
					<label><?php esc_html_e( 'Account name', 'giga-class-market' ); ?><input type="text" name="settings[payment][methods][<?php echo esc_attr( $name ); ?>][account_name]" value="<?php echo esc_attr( $method['account_name'] ); ?>" /></label>
					<label><?php esc_html_e( 'Account number', 'giga-class-market' ); ?><input type="text" name="settings[payment][methods][<?php echo esc_attr( $name ); ?>][account_no]" value="<?php echo esc_attr( $method['account_no'] ); ?>" /></label>
					<label><?php esc_html_e( 'Instructions', 'giga-class-market' ); ?><textarea name="settings[payment][methods][<?php echo esc_attr( $name ); ?>][instructions]" rows="3"><?php echo esc_textarea( $method['instructions'] ); ?></textarea></label>
				</div>
			<?php endforeach; ?>
		</section>

		<section id="gcm-tab-whatsapp" class="gcm-tab">
			<h2><?php esc_html_e( 'WhatsApp', 'giga-class-market' ); ?></h2>
			<label><?php esc_html_e( 'Business WhatsApp number (sender)', 'giga-class-market' ); ?><input type="text" name="settings[company][whatsapp]" value="<?php echo esc_attr( $settings['company']['whatsapp'] ); ?>" placeholder="+966509136037" /></label>
			<p><?php esc_html_e( 'This is the Giga Class Market number used to message students (credentials, updates, and site WhatsApp buttons). Include country code, e.g. +966509136037. When sending account details, open the chat from this WhatsApp account.', 'giga-class-market' ); ?></p>
		</section>

		<section id="gcm-tab-website" class="gcm-tab">
			<h2><?php esc_html_e( 'Website Theme', 'giga-class-market' ); ?></h2>
			<label><?php esc_html_e( 'Theme color', 'giga-class-market' ); ?><input type="text" name="settings[website][theme_color]" value="<?php echo esc_attr( $settings['website']['theme_color'] ); ?>" /></label>
			<label><?php esc_html_e( 'Accent color', 'giga-class-market' ); ?><input type="text" name="settings[website][accent_color]" value="<?php echo esc_attr( $settings['website']['accent_color'] ); ?>" /></label>
			<label><?php esc_html_e( 'Student page slug', 'giga-class-market' ); ?><input type="text" name="settings[website][student_page_slug]" value="<?php echo esc_attr( $settings['website']['student_page_slug'] ); ?>" /></label>
			<label><?php esc_html_e( 'Community WhatsApp (site-wide)', 'giga-class-market' ); ?><input type="text" name="settings[website][community_whatsapp]" value="<?php echo esc_attr( $settings['website']['community_whatsapp'] ?? '' ); ?>" placeholder="+966509136037 or https://chat.whatsapp.com/…" /></label>
		</section>

		<section id="gcm-tab-course" class="gcm-tab">
			<h2><?php esc_html_e( 'Course Defaults', 'giga-class-market' ); ?></h2>
			<label><?php esc_html_e( 'Featured course count', 'giga-class-market' ); ?><input type="number" min="1" max="3" name="settings[course][featured_count]" value="<?php echo esc_attr( $settings['course']['featured_count'] ); ?>" /></label>
			<label><?php esc_html_e( 'Default duration', 'giga-class-market' ); ?><input type="text" name="settings[course][default_duration]" value="<?php echo esc_attr( $settings['course']['default_duration'] ); ?>" /></label>
			<label><?php esc_html_e( 'Default rating', 'giga-class-market' ); ?><input type="number" step="0.1" min="0" max="5" name="settings[course][default_rating]" value="<?php echo esc_attr( $settings['course']['default_rating'] ); ?>" /></label>
			<label><?php esc_html_e( 'Live class reminder (hours before)', 'giga-class-market' ); ?><input type="number" min="1" max="72" name="settings[course][reminder_hours]" value="<?php echo esc_attr( $settings['course']['reminder_hours'] ?? 2 ); ?>" /></label>
			<label><?php esc_html_e( 'Default student password', 'giga-class-market' ); ?><input type="text" name="settings[security][default_password]" value="<?php echo esc_attr( $settings['security']['default_password'] ); ?>" /></label>
			<label><?php esc_html_e( 'Max upload size (MB)', 'giga-class-market' ); ?><input type="number" min="1" name="settings[security][max_upload_mb]" value="<?php echo esc_attr( $settings['security']['max_upload_mb'] ); ?>" /></label>
		</section>

		<section id="gcm-tab-zoom" class="gcm-tab">
			<h2><?php esc_html_e( 'Zoom (Server-to-Server OAuth)', 'giga-class-market' ); ?></h2>
			<p><?php esc_html_e( 'When a teacher starts a class, GCM creates a Zoom meeting and shows the join link on the student course screen. Leave blank to use a temporary placeholder link until Zoom is configured.', 'giga-class-market' ); ?></p>
			<label><?php esc_html_e( 'Account ID', 'giga-class-market' ); ?><input type="text" name="settings[zoom][account_id]" value="<?php echo esc_attr( $settings['zoom']['account_id'] ?? '' ); ?>" autocomplete="off" /></label>
			<label><?php esc_html_e( 'Client ID', 'giga-class-market' ); ?><input type="text" name="settings[zoom][client_id]" value="<?php echo esc_attr( $settings['zoom']['client_id'] ?? '' ); ?>" autocomplete="off" /></label>
			<label><?php esc_html_e( 'Client Secret', 'giga-class-market' ); ?><input type="password" name="settings[zoom][client_secret]" value="<?php echo esc_attr( $settings['zoom']['client_secret'] ?? '' ); ?>" autocomplete="new-password" /></label>
			<label><?php esc_html_e( 'Host email (Zoom user)', 'giga-class-market' ); ?><input type="email" name="settings[zoom][host_email]" value="<?php echo esc_attr( $settings['zoom']['host_email'] ?? '' ); ?>" placeholder="teacher@example.com" autocomplete="off" /></label>
			<p class="description"><?php esc_html_e( 'If Zoom keys are empty, Start Class opens a working Jitsi Meet room so teachers and students are never sent to a broken page. With Zoom configured, a real Zoom meeting is created automatically.', 'giga-class-market' ); ?></p>
		</section>

		<p>
			<button class="button button-primary" type="submit"><?php esc_html_e( 'Save Settings', 'giga-class-market' ); ?></button>
			<span class="gcm-form-message"></span>
		</p>
	</form>
</div>
