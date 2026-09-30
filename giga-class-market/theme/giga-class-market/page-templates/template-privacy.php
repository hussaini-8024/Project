<?php
/**
 * Template Name: GCM Privacy Policy
 *
 * @package GigaClassMarket
 */

get_header();
?>
<section class="gcm-page-hero">
	<div class="gcm-container">
		<p class="gcm-eyebrow"><?php esc_html_e( 'Legal', 'giga-class-market' ); ?></p>
		<h1><?php esc_html_e( 'Privacy Policy', 'giga-class-market' ); ?></h1>
	</div>
</section>

<section class="gcm-section">
	<div class="gcm-container gcm-content">
		<?php
		while ( have_posts() ) :
			the_post();
			if ( trim( get_the_content() ) ) {
				the_content();
			} else {
				?>
				<p><?php esc_html_e( 'This privacy policy is a placeholder. Update it with your data collection, payment, analytics, communication, retention, and student account practices before launch.', 'giga-class-market' ); ?></p>
				<h2><?php esc_html_e( 'Information we collect', 'giga-class-market' ); ?></h2>
				<p><?php esc_html_e( 'We may collect account details, enrollment data, contact submissions, and payment verification information to provide course access and support.', 'giga-class-market' ); ?></p>
				<h2><?php esc_html_e( 'How we use information', 'giga-class-market' ); ?></h2>
				<p><?php esc_html_e( 'Information is used to operate Giga Class Market, improve learning experiences, support students, and comply with applicable obligations.', 'giga-class-market' ); ?></p>
				<?php
			}
		endwhile;
		?>
	</div>
</section>
<?php
get_footer();
