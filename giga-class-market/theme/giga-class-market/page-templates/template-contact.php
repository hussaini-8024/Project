<?php
/**
 * Template Name: GCM Contact
 *
 * @package GigaClassMarket
 */

get_header();
?>
<section class="gcm-page-hero">
	<div class="gcm-container">
		<p class="gcm-eyebrow"><?php esc_html_e( 'Contact', 'giga-class-market' ); ?></p>
		<h1><?php esc_html_e( 'Speak with Giga Class Market', 'giga-class-market' ); ?></h1>
		<p><?php esc_html_e( 'Questions about courses, enrollment, or partnerships? Send a message and our team will respond.', 'giga-class-market' ); ?></p>
	</div>
</section>

<section class="gcm-section">
	<div class="gcm-container gcm-contact-grid">
		<aside class="gcm-contact-info gcm-animate">
			<p class="gcm-eyebrow"><?php esc_html_e( 'Company information', 'giga-class-market' ); ?></p>
			<h2><?php esc_html_e( 'Premium support for premium learning', 'giga-class-market' ); ?></h2>
			<ul>
				<?php if ( gcm_setting( 'company_address' ) ) : ?>
					<li><strong><?php esc_html_e( 'Address', 'giga-class-market' ); ?></strong><span><?php echo esc_html( gcm_setting( 'company_address' ) ); ?></span></li>
				<?php endif; ?>
				<?php if ( gcm_setting( 'contact_email' ) ) : ?>
					<li><strong><?php esc_html_e( 'Email', 'giga-class-market' ); ?></strong><a href="mailto:<?php echo esc_attr( gcm_setting( 'contact_email' ) ); ?>"><?php echo esc_html( gcm_setting( 'contact_email' ) ); ?></a></li>
				<?php endif; ?>
				<?php if ( gcm_setting( 'contact_phone' ) ) : ?>
					<li><strong><?php esc_html_e( 'Phone', 'giga-class-market' ); ?></strong><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', gcm_setting( 'contact_phone' ) ) ); ?>"><?php echo esc_html( gcm_setting( 'contact_phone' ) ); ?></a></li>
				<?php endif; ?>
				<?php if ( gcm_setting( 'whatsapp_number' ) ) : ?>
					<li><strong><?php esc_html_e( 'WhatsApp', 'giga-class-market' ); ?></strong><a href="<?php echo esc_url( 'https://wa.me/' . preg_replace( '/[^0-9]/', '', gcm_setting( 'whatsapp_number' ) ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Message us on WhatsApp', 'giga-class-market' ); ?></a></li>
				<?php endif; ?>
			</ul>
		</aside>

		<form class="gcm-contact-form gcm-animate" data-gcm-ajax-form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
			<input type="hidden" name="action" value="gcm_contact_submit">
			<?php wp_nonce_field( 'gcm_contact_nonce', 'nonce' ); ?>
			<label>
				<span><?php esc_html_e( 'Full name', 'giga-class-market' ); ?></span>
				<input type="text" name="name" required autocomplete="name">
			</label>
			<label>
				<span><?php esc_html_e( 'Email address', 'giga-class-market' ); ?></span>
				<input type="email" name="email" required autocomplete="email">
			</label>
			<label>
				<span><?php esc_html_e( 'Subject', 'giga-class-market' ); ?></span>
				<input type="text" name="subject" required>
			</label>
			<label>
				<span><?php esc_html_e( 'Message', 'giga-class-market' ); ?></span>
				<textarea name="message" rows="6" required></textarea>
			</label>
			<button class="gcm-button gcm-button--gold" type="submit"><?php esc_html_e( 'Send Message', 'giga-class-market' ); ?></button>
			<p class="gcm-form-status" role="status" aria-live="polite"></p>
		</form>
	</div>
</section>

<?php
get_footer();
