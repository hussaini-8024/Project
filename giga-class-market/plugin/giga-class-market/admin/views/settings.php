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
			<a href="#gcm-tab-about"><?php esc_html_e( 'About / Team', 'giga-class-market' ); ?></a>
			<a href="#gcm-tab-seo"><?php esc_html_e( 'SEO', 'giga-class-market' ); ?></a>
			<a href="#gcm-tab-course"><?php esc_html_e( 'Course', 'giga-class-market' ); ?></a>
			<a href="#gcm-tab-zoom"><?php esc_html_e( 'Zoom', 'giga-class-market' ); ?></a>
		</nav>

		<section id="gcm-tab-company" class="gcm-tab active">
			<h2><?php esc_html_e( 'Company Information', 'giga-class-market' ); ?></h2>
			<label><?php esc_html_e( 'Company name', 'giga-class-market' ); ?><input type="text" name="settings[company][name]" value="<?php echo esc_attr( $settings['company']['name'] ); ?>" /></label>
			<label><?php esc_html_e( 'Email (outgoing From address)', 'giga-class-market' ); ?><input type="email" name="settings[company][email]" value="<?php echo esc_attr( $settings['company']['email'] ); ?>" placeholder="Official@gigaclassmarket.com" /></label>
			<p class="description"><?php esc_html_e( 'Used only to SEND emails (From address) — students, certificates, reminders. Recommended: Official@gigaclassmarket.com', 'giga-class-market' ); ?></p>
			<label><?php esc_html_e( 'Inbox email (receive contact messages)', 'giga-class-market' ); ?><input type="email" name="settings[company][inbox_email]" value="<?php echo esc_attr( $settings['company']['inbox_email'] ?? 'info@gigaclassmarket.com' ); ?>" placeholder="info@gigaclassmarket.com" /></label>
			<p class="description"><?php esc_html_e( 'Contact form messages and the public Contact/Footer email go here. Recommended: info@gigaclassmarket.com', 'giga-class-market' ); ?></p>
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

			<h2><?php esc_html_e( 'Promo Popup Banner', 'giga-class-market' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Shown as a newsletter-style overlay when visitors open the site. Upload a banner image from the media library.', 'giga-class-market' ); ?></p>
			<label class="gcm-checkbox-label">
				<input type="checkbox" name="settings[website][popup_enabled]" value="1" <?php checked( ! empty( $settings['website']['popup_enabled'] ) ); ?> />
				<?php esc_html_e( 'Enable promo popup', 'giga-class-market' ); ?>
			</label>
			<?php
			$popup_image_id  = absint( $settings['website']['popup_image_id'] ?? 0 );
			$popup_image_url = $popup_image_id ? wp_get_attachment_image_url( $popup_image_id, 'large' ) : '';
			?>
			<div class="gcm-media-field">
				<input type="hidden" name="settings[website][popup_image_id]" id="gcm_popup_image_id" value="<?php echo esc_attr( (string) $popup_image_id ); ?>" />
				<div id="gcm_popup_image_preview" class="gcm-media-preview<?php echo $popup_image_url ? ' has-image' : ''; ?>">
					<?php if ( $popup_image_url ) : ?>
						<img src="<?php echo esc_url( $popup_image_url ); ?>" alt="" />
					<?php else : ?>
						<span><?php esc_html_e( 'No banner selected', 'giga-class-market' ); ?></span>
					<?php endif; ?>
				</div>
				<p>
					<button type="button" class="button gcm-media-upload" data-target="#gcm_popup_image_id" data-preview="#gcm_popup_image_preview" data-title="<?php echo esc_attr__( 'Select banner image', 'giga-class-market' ); ?>" data-empty="<?php echo esc_attr__( 'No banner selected', 'giga-class-market' ); ?>"><?php esc_html_e( 'Upload / select banner', 'giga-class-market' ); ?></button>
					<button type="button" class="button gcm-media-clear" data-target="#gcm_popup_image_id" data-preview="#gcm_popup_image_preview" data-empty="<?php echo esc_attr__( 'No banner selected', 'giga-class-market' ); ?>"><?php esc_html_e( 'Remove', 'giga-class-market' ); ?></button>
				</p>
			</div>
			<label><?php esc_html_e( 'Optional banner link URL', 'giga-class-market' ); ?><input type="url" name="settings[website][popup_link_url]" value="<?php echo esc_attr( $settings['website']['popup_link_url'] ?? '' ); ?>" placeholder="https://" /></label>
		</section>

		<section id="gcm-tab-about" class="gcm-tab">
			<h2><?php esc_html_e( 'CEO Section', 'giga-class-market' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Shown on the About page. Upload the CEO portrait from the WordPress media library.', 'giga-class-market' ); ?></p>
			<?php
			$about           = isset( $settings['about'] ) && is_array( $settings['about'] ) ? $settings['about'] : array();
			$ceo_photo_id    = absint( $about['ceo_photo_id'] ?? 0 );
			$ceo_photo_url   = $ceo_photo_id ? wp_get_attachment_image_url( $ceo_photo_id, 'medium' ) : '';
			$team_photo_id   = absint( $about['team_photo_id'] ?? 0 );
			$team_photo_url  = $team_photo_id ? wp_get_attachment_image_url( $team_photo_id, 'medium' ) : '';
			?>
			<label><?php esc_html_e( 'CEO name', 'giga-class-market' ); ?><input type="text" name="settings[about][ceo_name]" value="<?php echo esc_attr( $about['ceo_name'] ?? '' ); ?>" /></label>
			<label><?php esc_html_e( 'CEO designation', 'giga-class-market' ); ?><input type="text" name="settings[about][ceo_designation]" value="<?php echo esc_attr( $about['ceo_designation'] ?? '' ); ?>" /></label>
			<label><?php esc_html_e( 'CEO message title', 'giga-class-market' ); ?><input type="text" name="settings[about][ceo_title]" value="<?php echo esc_attr( $about['ceo_title'] ?? '' ); ?>" /></label>
			<label><?php esc_html_e( 'CEO message', 'giga-class-market' ); ?><textarea name="settings[about][ceo_message]" rows="4"><?php echo esc_textarea( $about['ceo_message'] ?? '' ); ?></textarea></label>
			<div class="gcm-media-field">
				<p><strong><?php esc_html_e( 'CEO photo', 'giga-class-market' ); ?></strong></p>
				<input type="hidden" name="settings[about][ceo_photo_id]" id="gcm_ceo_photo_id" value="<?php echo esc_attr( (string) $ceo_photo_id ); ?>" />
				<div id="gcm_ceo_photo_preview" class="gcm-media-preview<?php echo $ceo_photo_url ? ' has-image' : ''; ?>">
					<?php if ( $ceo_photo_url ) : ?>
						<img src="<?php echo esc_url( $ceo_photo_url ); ?>" alt="" />
					<?php else : ?>
						<span><?php esc_html_e( 'No photo selected', 'giga-class-market' ); ?></span>
					<?php endif; ?>
				</div>
				<p>
					<button type="button" class="button gcm-media-upload" data-target="#gcm_ceo_photo_id" data-preview="#gcm_ceo_photo_preview" data-title="<?php echo esc_attr__( 'Select CEO photo', 'giga-class-market' ); ?>" data-empty="<?php echo esc_attr__( 'No photo selected', 'giga-class-market' ); ?>"><?php esc_html_e( 'Upload / select from media library', 'giga-class-market' ); ?></button>
					<button type="button" class="button gcm-media-clear" data-target="#gcm_ceo_photo_id" data-preview="#gcm_ceo_photo_preview" data-empty="<?php echo esc_attr__( 'No photo selected', 'giga-class-market' ); ?>"><?php esc_html_e( 'Remove', 'giga-class-market' ); ?></button>
				</p>
			</div>

			<h2><?php esc_html_e( 'Core Team Member', 'giga-class-market' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Shown under the CEO section. Name, role, intro, and photo are editable and saved with your settings.', 'giga-class-market' ); ?></p>
			<label><?php esc_html_e( 'Name', 'giga-class-market' ); ?><input type="text" name="settings[about][team_name]" value="<?php echo esc_attr( $about['team_name'] ?? '' ); ?>" /></label>
			<label><?php esc_html_e( 'Role', 'giga-class-market' ); ?><input type="text" name="settings[about][team_role]" value="<?php echo esc_attr( $about['team_role'] ?? '' ); ?>" placeholder="Web Developer" /></label>
			<label><?php esc_html_e( 'Short intro', 'giga-class-market' ); ?><textarea name="settings[about][team_bio]" rows="4"><?php echo esc_textarea( $about['team_bio'] ?? '' ); ?></textarea></label>
			<div class="gcm-media-field">
				<p><strong><?php esc_html_e( 'Team member photo', 'giga-class-market' ); ?></strong></p>
				<input type="hidden" name="settings[about][team_photo_id]" id="gcm_team_photo_id" value="<?php echo esc_attr( (string) $team_photo_id ); ?>" />
				<div id="gcm_team_photo_preview" class="gcm-media-preview<?php echo $team_photo_url ? ' has-image' : ''; ?>">
					<?php if ( $team_photo_url ) : ?>
						<img src="<?php echo esc_url( $team_photo_url ); ?>" alt="" />
					<?php else : ?>
						<span><?php esc_html_e( 'No photo selected — theme default will be used', 'giga-class-market' ); ?></span>
					<?php endif; ?>
				</div>
				<p>
					<button type="button" class="button gcm-media-upload" data-target="#gcm_team_photo_id" data-preview="#gcm_team_photo_preview" data-title="<?php echo esc_attr__( 'Select team member photo', 'giga-class-market' ); ?>" data-empty="<?php echo esc_attr__( 'No photo selected — theme default will be used', 'giga-class-market' ); ?>"><?php esc_html_e( 'Upload / select from media library', 'giga-class-market' ); ?></button>
					<button type="button" class="button gcm-media-clear" data-target="#gcm_team_photo_id" data-preview="#gcm_team_photo_preview" data-empty="<?php echo esc_attr__( 'No photo selected — theme default will be used', 'giga-class-market' ); ?>"><?php esc_html_e( 'Remove', 'giga-class-market' ); ?></button>
				</p>
			</div>
		</section>

		<section id="gcm-tab-seo" class="gcm-tab">
			<?php
			$seo             = isset( $settings['seo'] ) && is_array( $settings['seo'] ) ? $settings['seo'] : ( class_exists( 'GCM_SEO' ) ? GCM_SEO::defaults() : array() );
			$og_image_id     = absint( $seo['default_og_image_id'] ?? 0 );
			$og_image_url    = $og_image_id ? wp_get_attachment_image_url( $og_image_id, 'medium' ) : '';
			?>
			<h2><?php esc_html_e( 'Search Engine Optimization', 'giga-class-market' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Titles, meta descriptions, social share image, and verification codes help Google and other search engines understand and rank your site. After saving, submit https://gigaclassmarket.com/wp-sitemap.xml in Google Search Console.', 'giga-class-market' ); ?></p>

			<label><?php esc_html_e( 'Title separator', 'giga-class-market' ); ?><input type="text" name="settings[seo][title_separator]" value="<?php echo esc_attr( $seo['title_separator'] ?? '|' ); ?>" maxlength="5" /></label>

			<h3><?php esc_html_e( 'Homepage', 'giga-class-market' ); ?></h3>
			<label><?php esc_html_e( 'SEO title', 'giga-class-market' ); ?><input type="text" name="settings[seo][home_title]" value="<?php echo esc_attr( $seo['home_title'] ?? '' ); ?>" /></label>
			<label><?php esc_html_e( 'Meta description', 'giga-class-market' ); ?><textarea name="settings[seo][home_description]" rows="3"><?php echo esc_textarea( $seo['home_description'] ?? '' ); ?></textarea></label>

			<h3><?php esc_html_e( 'Courses archive', 'giga-class-market' ); ?></h3>
			<label><?php esc_html_e( 'SEO title', 'giga-class-market' ); ?><input type="text" name="settings[seo][courses_title]" value="<?php echo esc_attr( $seo['courses_title'] ?? '' ); ?>" /></label>
			<label><?php esc_html_e( 'Meta description', 'giga-class-market' ); ?><textarea name="settings[seo][courses_description]" rows="3"><?php echo esc_textarea( $seo['courses_description'] ?? '' ); ?></textarea></label>

			<h3><?php esc_html_e( 'About page', 'giga-class-market' ); ?></h3>
			<label><?php esc_html_e( 'SEO title', 'giga-class-market' ); ?><input type="text" name="settings[seo][about_title]" value="<?php echo esc_attr( $seo['about_title'] ?? '' ); ?>" /></label>
			<label><?php esc_html_e( 'Meta description', 'giga-class-market' ); ?><textarea name="settings[seo][about_description]" rows="3"><?php echo esc_textarea( $seo['about_description'] ?? '' ); ?></textarea></label>

			<h3><?php esc_html_e( 'Services page', 'giga-class-market' ); ?></h3>
			<label><?php esc_html_e( 'SEO title', 'giga-class-market' ); ?><input type="text" name="settings[seo][services_title]" value="<?php echo esc_attr( $seo['services_title'] ?? '' ); ?>" /></label>
			<label><?php esc_html_e( 'Meta description', 'giga-class-market' ); ?><textarea name="settings[seo][services_description]" rows="3"><?php echo esc_textarea( $seo['services_description'] ?? '' ); ?></textarea></label>

			<h3><?php esc_html_e( 'Contact page', 'giga-class-market' ); ?></h3>
			<label><?php esc_html_e( 'SEO title', 'giga-class-market' ); ?><input type="text" name="settings[seo][contact_title]" value="<?php echo esc_attr( $seo['contact_title'] ?? '' ); ?>" /></label>
			<label><?php esc_html_e( 'Meta description', 'giga-class-market' ); ?><textarea name="settings[seo][contact_description]" rows="3"><?php echo esc_textarea( $seo['contact_description'] ?? '' ); ?></textarea></label>

			<h3><?php esc_html_e( 'Social / Open Graph default image', 'giga-class-market' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Used when a page has no featured image. Recommended size: 1200×630px.', 'giga-class-market' ); ?></p>
			<div class="gcm-media-field">
				<input type="hidden" name="settings[seo][default_og_image_id]" id="gcm_seo_og_image_id" value="<?php echo esc_attr( (string) $og_image_id ); ?>" />
				<div id="gcm_seo_og_image_preview" class="gcm-media-preview<?php echo $og_image_url ? ' has-image' : ''; ?>">
					<?php if ( $og_image_url ) : ?>
						<img src="<?php echo esc_url( $og_image_url ); ?>" alt="" />
					<?php else : ?>
						<span><?php esc_html_e( 'No default share image selected', 'giga-class-market' ); ?></span>
					<?php endif; ?>
				</div>
				<p>
					<button type="button" class="button gcm-media-upload" data-target="#gcm_seo_og_image_id" data-preview="#gcm_seo_og_image_preview" data-title="<?php echo esc_attr__( 'Select default Open Graph image', 'giga-class-market' ); ?>" data-empty="<?php echo esc_attr__( 'No default share image selected', 'giga-class-market' ); ?>"><?php esc_html_e( 'Upload / select from media library', 'giga-class-market' ); ?></button>
					<button type="button" class="button gcm-media-clear" data-target="#gcm_seo_og_image_id" data-preview="#gcm_seo_og_image_preview" data-empty="<?php echo esc_attr__( 'No default share image selected', 'giga-class-market' ); ?>"><?php esc_html_e( 'Remove', 'giga-class-market' ); ?></button>
				</p>
			</div>

			<label><?php esc_html_e( 'Organization description (schema)', 'giga-class-market' ); ?><textarea name="settings[seo][organization_description]" rows="3"><?php echo esc_textarea( $seo['organization_description'] ?? '' ); ?></textarea></label>
			<label><?php esc_html_e( 'Google Search Console verification code', 'giga-class-market' ); ?><input type="text" name="settings[seo][google_site_verification]" value="<?php echo esc_attr( $seo['google_site_verification'] ?? '' ); ?>" placeholder="paste content value only" /></label>
			<label><?php esc_html_e( 'Bing Webmaster verification code', 'giga-class-market' ); ?><input type="text" name="settings[seo][bing_site_verification]" value="<?php echo esc_attr( $seo['bing_site_verification'] ?? '' ); ?>" placeholder="paste content value only" /></label>
			<p class="description"><?php echo esc_html( sprintf( __( 'XML sitemap: %s', 'giga-class-market' ), home_url( '/wp-sitemap.xml' ) ) ); ?></p>
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
