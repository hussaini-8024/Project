<?php
/**
 * Template Name: GCM Terms
 *
 * @package GigaClassMarket
 */

get_header();
?>
<section class="gcm-page-hero">
	<div class="gcm-container">
		<p class="gcm-eyebrow"><?php esc_html_e( 'Legal', 'giga-class-market' ); ?></p>
		<h1><?php esc_html_e( 'Terms and Conditions', 'giga-class-market' ); ?></h1>
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
				<p><?php esc_html_e( 'These terms are placeholders. Update them with your enrollment, refund, intellectual property, acceptable use, payment, and certification policies before launch.', 'giga-class-market' ); ?></p>
				<h2><?php esc_html_e( 'Course access', 'giga-class-market' ); ?></h2>
				<p><?php esc_html_e( 'Course access is provided according to enrollment status and any additional conditions published at the time of purchase.', 'giga-class-market' ); ?></p>
				<h2><?php esc_html_e( 'Learning materials', 'giga-class-market' ); ?></h2>
				<p><?php esc_html_e( 'Course videos, documents, and resources are provided for enrolled student use and may not be redistributed without written permission.', 'giga-class-market' ); ?></p>
				<?php
			}
		endwhile;
		?>
	</div>
</section>
<?php
get_footer();
