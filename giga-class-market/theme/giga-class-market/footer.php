<?php
/**
 * Theme footer.
 *
 * @package GigaClassMarket
 */

$settings = gcm_get_settings();
$socials  = array(
	'facebook'  => gcm_setting( 'facebook_url' ),
	'instagram' => gcm_setting( 'instagram_url' ),
	'linkedin'  => gcm_setting( 'linkedin_url' ),
	'youtube'   => gcm_setting( 'youtube_url' ),
);
?>
</main>

<footer class="gcm-site-footer">
	<div class="gcm-container gcm-footer__grid">
		<div class="gcm-footer__brand">
			<a class="gcm-brand gcm-brand--footer" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<span class="gcm-brand__mark" aria-hidden="true">G</span>
				<span class="gcm-brand__text">Giga Class Market</span>
			</a>
			<p><?php echo esc_html( gcm_setting( 'footer_description', __( 'Premium digital learning experiences for ambitious students and future-ready professionals.', 'giga-class-market' ) ) ); ?></p>
			<?php if ( array_filter( $socials ) ) : ?>
				<ul class="gcm-socials" aria-label="<?php esc_attr_e( 'Social links', 'giga-class-market' ); ?>">
					<?php foreach ( $socials as $name => $url ) : ?>
						<?php if ( $url ) : ?>
							<li><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( ucfirst( $name ) ); ?></a></li>
						<?php endif; ?>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>

		<div class="gcm-footer__nav">
			<h2><?php esc_html_e( 'Quick links', 'giga-class-market' ); ?></h2>
			<ul>
				<li><a href="<?php echo esc_url( get_post_type_archive_link( 'gcm_course' ) ?: home_url( '/courses/' ) ); ?>"><?php esc_html_e( 'Courses', 'giga-class-market' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Contact Us', 'giga-class-market' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>"><?php esc_html_e( 'About Us', 'giga-class-market' ); ?></a></li>
				<li><a href="<?php echo esc_url( gcm_student_login_url() ); ?>"><?php esc_html_e( 'Student Login', 'giga-class-market' ); ?></a></li>
			</ul>
		</div>

		<div class="gcm-footer__contact">
			<h2><?php esc_html_e( 'Contact', 'giga-class-market' ); ?></h2>
			<ul>
				<?php if ( gcm_setting( 'contact_email' ) ) : ?>
					<li><a href="mailto:<?php echo esc_attr( gcm_setting( 'contact_email' ) ); ?>"><?php echo esc_html( gcm_setting( 'contact_email' ) ); ?></a></li>
				<?php endif; ?>
				<?php if ( gcm_setting( 'contact_phone' ) ) : ?>
					<li><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', gcm_setting( 'contact_phone' ) ) ); ?>"><?php echo esc_html( gcm_setting( 'contact_phone' ) ); ?></a></li>
				<?php endif; ?>
				<?php if ( gcm_setting( 'company_address' ) ) : ?>
					<li><?php echo esc_html( gcm_setting( 'company_address' ) ); ?></li>
				<?php endif; ?>
			</ul>
		</div>

		<div class="gcm-footer__legal">
			<h2><?php esc_html_e( 'Support', 'giga-class-market' ); ?></h2>
			<ul>
				<li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Contact Support', 'giga-class-market' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/terms/' ) ); ?>"><?php esc_html_e( 'Terms & Conditions', 'giga-class-market' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/refund-policy/' ) ); ?>"><?php esc_html_e( 'Refund Policy', 'giga-class-market' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>"><?php esc_html_e( 'Privacy Policy', 'giga-class-market' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/verify-certificate/' ) ); ?>"><?php esc_html_e( 'Verify Certificate', 'giga-class-market' ); ?></a></li>
				<li><a href="<?php echo esc_url( function_exists( 'gcm_platform_guide_download_url' ) ? gcm_platform_guide_download_url() : home_url( '/platform-guide.pdf' ) ); ?>" download><?php esc_html_e( 'Platform Guide (PDF)', 'giga-class-market' ); ?></a></li>
			</ul>
		</div>
	</div>

	<div class="gcm-footer__bottom">
		<div class="gcm-container">
			<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php esc_html_e( 'Giga Class Market. All rights reserved.', 'giga-class-market' ); ?></p>
		</div>
	</div>
</footer>

<?php
// Server-rendered popup so guests see it even when admin-ajax is blocked by the host WAF.
// Cache purge on settings/course save keeps this HTML fresh for logged-out visitors.
// Hide on personal portfolio pages (share-only profiles).
$hide_promo_popup = is_singular( 'gcm_portfolio' ) || is_page_template( 'page-templates/template-portfolio.php' );
$popup_enabled    = (int) gcm_setting( 'popup_enabled', 0 );
$popup_image_id   = absint( gcm_setting( 'popup_image_id', 0 ) );
$popup_link       = (string) gcm_setting( 'popup_link_url', '' );
$popup_image      = $popup_image_id ? wp_get_attachment_image_url( $popup_image_id, 'full' ) : '';
if ( ! $hide_promo_popup && $popup_enabled && $popup_image ) :
	?>
	<div class="gcm-promo-popup" id="gcm-promo-popup" hidden data-popup-id="<?php echo esc_attr( (string) $popup_image_id ); ?>" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Promotional banner', 'giga-class-market' ); ?>">
		<div class="gcm-promo-popup__backdrop" data-gcm-popup-close tabindex="-1"></div>
		<div class="gcm-promo-popup__panel">
			<button type="button" class="gcm-promo-popup__close" data-gcm-popup-close aria-label="<?php esc_attr_e( 'Close', 'giga-class-market' ); ?>">
				<span aria-hidden="true">&times;</span>
			</button>
			<?php if ( $popup_link ) : ?>
				<a class="gcm-promo-popup__link" href="<?php echo esc_url( $popup_link ); ?>">
					<img src="<?php echo esc_url( $popup_image ); ?>" alt="<?php esc_attr_e( 'Promotional offer', 'giga-class-market' ); ?>" />
				</a>
			<?php else : ?>
				<img src="<?php echo esc_url( $popup_image ); ?>" alt="<?php esc_attr_e( 'Promotional offer', 'giga-class-market' ); ?>" />
			<?php endif; ?>
		</div>
	</div>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
