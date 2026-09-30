<?php
/**
 * Main posts index.
 *
 * @package GigaClassMarket
 */

get_header();
?>
<section class="gcm-page-hero">
	<div class="gcm-container">
		<p class="gcm-eyebrow"><?php esc_html_e( 'Insights', 'giga-class-market' ); ?></p>
		<h1><?php echo esc_html( get_the_archive_title() ?: get_bloginfo( 'name' ) ); ?></h1>
	</div>
</section>

<section class="gcm-section">
	<div class="gcm-container gcm-post-grid">
		<?php if ( have_posts() ) : ?>
			<?php while ( have_posts() ) : ?>
				<?php the_post(); ?>
				<article <?php post_class( 'gcm-post-card gcm-animate' ); ?>>
					<?php if ( has_post_thumbnail() ) : ?>
						<a class="gcm-post-card__media" href="<?php the_permalink(); ?>"><?php the_post_thumbnail( 'large', array( 'alt' => esc_attr( get_the_title() ) ) ); ?></a>
					<?php endif; ?>
					<div>
						<p class="gcm-eyebrow"><?php echo esc_html( get_the_date() ); ?></p>
						<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
						<p><?php echo esc_html( get_the_excerpt() ); ?></p>
					</div>
				</article>
			<?php endwhile; ?>
			<div class="gcm-pagination"><?php the_posts_pagination(); ?></div>
		<?php else : ?>
			<div class="gcm-empty-state">
				<h2><?php esc_html_e( 'Nothing published yet', 'giga-class-market' ); ?></h2>
				<p><?php esc_html_e( 'Please check back soon for premium learning insights.', 'giga-class-market' ); ?></p>
			</div>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
