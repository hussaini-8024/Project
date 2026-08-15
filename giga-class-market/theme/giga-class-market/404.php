<?php
/**
 * 404 template.
 *
 * @package GigaClassMarket
 */

get_header();
?>
<section class="gcm-error-page">
	<div class="gcm-container gcm-error-page__inner">
		<p class="gcm-eyebrow"><?php esc_html_e( '404', 'giga-class-market' ); ?></p>
		<h1><?php esc_html_e( 'This lesson path is unavailable', 'giga-class-market' ); ?></h1>
		<p><?php esc_html_e( 'The page may have moved, but the marketplace is ready whenever you are.', 'giga-class-market' ); ?></p>
		<div class="gcm-hero__actions">
			<a class="gcm-button gcm-button--gold" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Return Home', 'giga-class-market' ); ?></a>
			<a class="gcm-button gcm-button--outline" href="<?php echo esc_url( get_post_type_archive_link( 'gcm_course' ) ?: home_url( '/courses/' ) ); ?>"><?php esc_html_e( 'Explore Courses', 'giga-class-market' ); ?></a>
		</div>
	</div>
</section>
<?php
get_footer();
