<?php
/**
 * Inquiry form used on the Services page.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$company_name = gcm_setting( 'company_name', 'Giga Class Market' );
$hours        = gcm_setting( 'business_hours', __( 'Mon–Sat, 9:00 AM – 6:00 PM', 'giga-class-market' ) );
$service_q    = isset( $_GET['service'] ) ? sanitize_text_field( wp_unslash( $_GET['service'] ) ) : '';

$services = array(
	'website'     => __( 'Website development', 'giga-class-market' ),
	'lms'         => __( 'LMS / student portal', 'giga-class-market' ),
	'school'      => __( 'School or college management portal', 'giga-class-market' ),
	'hotel'       => __( 'Hotel or restaurant website + booking', 'giga-class-market' ),
	'hospital'    => __( 'Hospital or clinic appointment portal', 'giga-class-market' ),
	'law'         => __( 'Law firm website + client inquiry', 'giga-class-market' ),
	'enrollment'  => __( 'Course enrollment (Giga Class Market)', 'giga-class-market' ),
	'other'       => __( 'Other', 'giga-class-market' ),
);
?>
<div class="gcm-container gcm-contact-grid">
	<aside class="gcm-contact-info gcm-animate">
		<p class="gcm-eyebrow"><?php esc_html_e( 'Send an inquiry', 'giga-class-market' ); ?></p>
		<h2><?php echo esc_html( $company_name ); ?></h2>
		<p><?php esc_html_e( 'Tell us which service you need. We will reply on WhatsApp or email.', 'giga-class-market' ); ?></p>
		<ul>
			<?php if ( gcm_setting( 'company_address' ) ) : ?>
				<li><strong><?php esc_html_e( 'Address', 'giga-class-market' ); ?></strong><span><?php echo esc_html( gcm_setting( 'company_address' ) ); ?></span></li>
			<?php endif; ?>
			<?php if ( gcm_setting( 'contact_phone' ) ) : ?>
				<li><strong><?php esc_html_e( 'Phone', 'giga-class-market' ); ?></strong><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', gcm_setting( 'contact_phone' ) ) ); ?>"><?php echo esc_html( gcm_setting( 'contact_phone' ) ); ?></a></li>
			<?php endif; ?>
			<?php if ( gcm_setting( 'whatsapp_number' ) ) : ?>
				<li><strong><?php esc_html_e( 'WhatsApp', 'giga-class-market' ); ?></strong><a href="<?php echo esc_url( 'https://wa.me/' . preg_replace( '/[^0-9]/', '', gcm_setting( 'whatsapp_number' ) ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( gcm_setting( 'whatsapp_number' ) ); ?></a></li>
			<?php endif; ?>
			<?php if ( gcm_setting( 'contact_email' ) ) : ?>
				<li><strong><?php esc_html_e( 'Email', 'giga-class-market' ); ?></strong><a href="mailto:<?php echo esc_attr( gcm_setting( 'contact_email' ) ); ?>"><?php echo esc_html( gcm_setting( 'contact_email' ) ); ?></a></li>
			<?php endif; ?>
			<li><strong><?php esc_html_e( 'Business hours', 'giga-class-market' ); ?></strong><span><?php echo esc_html( $hours ); ?></span></li>
		</ul>
	</aside>

	<form id="inquiry" class="gcm-contact-form gcm-inquiry-anchor gcm-animate" data-gcm-ajax-form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
		<input type="hidden" name="action" value="gcm_contact_submit">
		<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'gcm_ajax_nonce' ) ); ?>">
		<label>
			<span><?php esc_html_e( 'Full Name', 'giga-class-market' ); ?></span>
			<input type="text" name="full_name" required autocomplete="name">
		</label>
		<label>
			<span><?php esc_html_e( 'Email', 'giga-class-market' ); ?></span>
			<input type="email" name="email" required autocomplete="email">
		</label>
		<label>
			<span><?php esc_html_e( 'Phone / WhatsApp', 'giga-class-market' ); ?></span>
			<input type="text" name="whatsapp" required autocomplete="tel">
		</label>
		<label>
			<span><?php esc_html_e( 'Service you need', 'giga-class-market' ); ?></span>
			<select name="subject" required>
				<option value=""><?php esc_html_e( 'Select a service', 'giga-class-market' ); ?></option>
				<?php foreach ( $services as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( $label ); ?>" <?php selected( $service_q, $slug ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label>
			<span><?php esc_html_e( 'Message', 'giga-class-market' ); ?></span>
			<textarea name="message" rows="6" required placeholder="<?php esc_attr_e( 'Business name, city, and what you want built…', 'giga-class-market' ); ?>"></textarea>
		</label>
		<button class="gcm-button gcm-button--gold" type="submit"><?php esc_html_e( 'Send inquiry', 'giga-class-market' ); ?></button>
		<p class="gcm-form-status" role="status" aria-live="polite"></p>
	</form>
</div>
