<?php
/**
 * Search results.
 *
 * @package GigaClassMarket
 */

get_header();
?>
<section class="gcm-page-hero">
	<div class="gcm-container">
		<p class="gcm-eyebrow"><?php esc_html_e( 'Search', 'giga-class-market' ); ?></p>
		<h1><?php echo esc_html( sprintf( __( 'Results for "%s"', 'giga-class-market' ), get_search_query() ) ); ?></h1>
	</div>
</section>

<section class="gcm-section">
	<div class="gcm-container">
		<form class="gcm-search-form" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<label class="screen-reader-text" for="gcm-search"><?php esc_html_e( 'Search', 'giga-class-market' ); ?></label>
			<input id="gcm-search" type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'Search Giga Class Market', 'giga-class-market' ); ?>">
			<button class="gcm-button" type="submit"><?php esc_html_e( 'Search', 'giga-class-market' ); ?></button>
		</form>

		<div class="gcm-post-grid">
			<?php if ( have_posts() ) : ?>
				<?php while ( have_posts() ) : ?>
					<?php the_post(); ?>
					<article <?php post_class( 'gcm-post-card gcm-animate' ); ?>>
						<?php if ( has_post_thumbnail() ) : ?>
							<a class="gcm-post-card__media" href="<?php the_permalink(); ?>"><?php the_post_thumbnail( 'large', array( 'alt' => esc_attr( get_the_title() ) ) ); ?></a>
						<?php endif; ?>
						<div>
							<p class="gcm-eyebrow"><?php echo esc_html( get_post_type_object( get_post_type() )->labels->singular_name ?? __( 'Result', 'giga-class-market' ) ); ?></p>
							<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
							<p><?php echo esc_html( get_the_excerpt() ); ?></p>
						</div>
					</article>
				<?php endwhile; ?>
				<div class="gcm-pagination"><?php the_posts_pagination(); ?></div>
			<?php else : ?>
				<div class="gcm-empty-state">
					<h2><?php esc_html_e( 'No results found', 'giga-class-market' ); ?></h2>
					<p><?php esc_html_e( 'Try a different keyword or browse the course marketplace.', 'giga-class-market' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
<?php
get_footer();
