<?php
/**
 * Template Name: GCM Refund Policy
 *
 * @package GigaClassMarket
 */

get_header();
?>
<section class="gcm-page-hero">
	<div class="gcm-container">
		<p class="gcm-eyebrow"><?php esc_html_e( 'Legal', 'giga-class-market' ); ?></p>
		<h1><?php esc_html_e( 'Refund Policy', 'giga-class-market' ); ?></h1>
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
				<p><?php esc_html_e( 'This refund policy is a placeholder. Update it with your payment verification, cancellation, and refund timelines before launch.', 'giga-class-market' ); ?></p>
				<h2><?php esc_html_e( 'Before verification', 'giga-class-market' ); ?></h2>
				<p><?php esc_html_e( 'If a payment submission is rejected during verification, enrollment is not activated. Contact support with your transaction details for assistance.', 'giga-class-market' ); ?></p>
				<h2><?php esc_html_e( 'After enrollment', 'giga-class-market' ); ?></h2>
				<p><?php esc_html_e( 'Once a payment is approved and course access is granted, refunds follow the company policy published here and any course-specific terms shown at purchase.', 'giga-class-market' ); ?></p>
				<h2><?php esc_html_e( 'How to request help', 'giga-class-market' ); ?></h2>
				<p><?php esc_html_e( 'Email or WhatsApp the support contacts listed on the Services page inquiry form with your full name, course name, and transaction ID.', 'giga-class-market' ); ?></p>
				<?php
			}
		endwhile;
		?>
	</div>
</section>
<?php
get_footer();
