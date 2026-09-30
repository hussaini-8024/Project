<?php
/**
 * Singular fallback template.
 *
 * @package GigaClassMarket
 */

get_header();
?>
<?php while ( have_posts() ) : ?>
	<?php the_post(); ?>
	<article <?php post_class( 'gcm-singular' ); ?>>
		<section class="gcm-page-hero">
			<div class="gcm-container">
				<p class="gcm-eyebrow"><?php echo esc_html( get_post_type_object( get_post_type() )->labels->singular_name ?? __( 'Article', 'giga-class-market' ) ); ?></p>
				<h1><?php the_title(); ?></h1>
			</div>
		</section>
		<section class="gcm-section">
			<div class="gcm-container gcm-content">
				<?php if ( has_post_thumbnail() ) : ?>
					<figure class="gcm-featured-image"><?php the_post_thumbnail( 'large', array( 'alt' => esc_attr( get_the_title() ) ) ); ?></figure>
				<?php endif; ?>
				<?php the_content(); ?>
				<?php wp_link_pages(); ?>
			</div>
		</section>
	</article>
<?php endwhile; ?>
<?php
get_footer();
