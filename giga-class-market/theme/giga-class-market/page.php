<?php
/**
 * Default page template.
 *
 * @package GigaClassMarket
 */

get_header();
?>
<section class="gcm-page-hero">
	<div class="gcm-container">
		<p class="gcm-eyebrow"><?php esc_html_e( 'Giga Class Market', 'giga-class-market' ); ?></p>
		<h1><?php the_title(); ?></h1>
	</div>
</section>

<section class="gcm-section">
	<div class="gcm-container gcm-content">
		<?php
		while ( have_posts() ) :
			the_post();
			the_content();
			wp_link_pages();
		endwhile;
		?>
	</div>
</section>
<?php
get_footer();
