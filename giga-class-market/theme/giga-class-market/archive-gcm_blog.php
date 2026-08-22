<?php
/**
 * Blog archive — /blogs/
 *
 * @package GigaClassMarket
 */

get_header();

$featured  = class_exists( 'GCM_Blog_Service' ) ? GCM_Blog_Service::get_featured( 3 ) : array();
$top_reads = class_exists( 'GCM_Blog_Service' ) ? GCM_Blog_Service::get_top_reads( 4 ) : array();
$cats      = class_exists( 'GCM_Blog_Service' ) ? GCM_Blog_Service::get_categories() : array();
$exclude   = array_merge( wp_list_pluck( $featured, 'ID' ), wp_list_pluck( $top_reads, 'ID' ) );
?>
<section class="gcm-page-hero gcm-blog-hero">
	<div class="gcm-container">
		<p class="gcm-eyebrow"><?php esc_html_e( 'Learning Blog', 'giga-class-market' ); ?></p>
		<h1><?php esc_html_e( 'Guides that lead to real skills', 'giga-class-market' ); ?></h1>
		<p><?php esc_html_e( 'Practical articles on exams, networking, cyber security, and careers — written to help you choose the right course next.', 'giga-class-market' ); ?></p>
	</div>
</section>

<?php if ( ! empty( $cats ) ) : ?>
<section class="gcm-section gcm-blog-cats">
	<div class="gcm-container">
		<div class="gcm-blog-cat-chips" role="navigation" aria-label="<?php esc_attr_e( 'Blog categories', 'giga-class-market' ); ?>">
			<a class="gcm-blog-chip is-active" href="<?php echo esc_url( get_post_type_archive_link( 'gcm_blog' ) ); ?>"><?php esc_html_e( 'All', 'giga-class-market' ); ?></a>
			<?php foreach ( $cats as $cat ) : ?>
				<a class="gcm-blog-chip" href="<?php echo esc_url( get_term_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?><?php if ( $cat->count ) : ?> <span><?php echo esc_html( (string) $cat->count ); ?></span><?php endif; ?></a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<?php if ( ! empty( $featured ) ) : ?>
<section class="gcm-section gcm-blog-featured">
	<div class="gcm-container">
		<div class="gcm-section__heading">
			<p class="gcm-eyebrow"><?php esc_html_e( 'Featured', 'giga-class-market' ); ?></p>
			<h2><?php esc_html_e( 'Editor picks', 'giga-class-market' ); ?></h2>
		</div>
		<div class="gcm-blog-featured-grid">
			<?php foreach ( $featured as $index => $post ) : ?>
				<?php
				setup_postdata( $post );
				$mins = class_exists( 'GCM_Blog_Service' ) ? GCM_Blog_Service::reading_minutes( $post->ID ) : 3;
				?>
				<article <?php post_class( 'gcm-blog-card' . ( 0 === $index ? ' gcm-blog-card--hero' : '' ), $post ); ?>>
					<a class="gcm-blog-card__media" href="<?php echo esc_url( get_permalink( $post ) ); ?>">
						<?php if ( has_post_thumbnail( $post ) ) : ?>
							<?php echo get_the_post_thumbnail( $post, 0 === $index ? 'large' : 'medium_large', array( 'alt' => esc_attr( get_the_title( $post ) ) ) ); ?>
						<?php else : ?>
							<span class="gcm-blog-card__placeholder" aria-hidden="true"></span>
						<?php endif; ?>
					</a>
					<div class="gcm-blog-card__body">
						<p class="gcm-eyebrow"><?php echo esc_html( get_the_date( '', $post ) ); ?> · <?php echo esc_html( sprintf( /* translators: %d minutes */ _n( '%d min read', '%d min read', $mins, 'giga-class-market' ), $mins ) ); ?></p>
						<h3><a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></h3>
						<p><?php echo esc_html( get_the_excerpt( $post ) ); ?></p>
					</div>
				</article>
			<?php endforeach; ?>
			<?php wp_reset_postdata(); ?>
		</div>
	</div>
</section>
<?php endif; ?>

<?php if ( ! empty( $top_reads ) ) : ?>
<section class="gcm-section gcm-section--surface gcm-blog-top">
	<div class="gcm-container">
		<div class="gcm-section__heading">
			<p class="gcm-eyebrow"><?php esc_html_e( 'Popular', 'giga-class-market' ); ?></p>
			<h2><?php esc_html_e( 'Top reading', 'giga-class-market' ); ?></h2>
		</div>
		<ol class="gcm-blog-top-list">
			<?php foreach ( $top_reads as $i => $post ) : ?>
				<?php
				$mins  = class_exists( 'GCM_Blog_Service' ) ? GCM_Blog_Service::reading_minutes( $post->ID ) : 3;
				$views = (int) get_post_meta( $post->ID, '_gcm_blog_views', true );
				?>
				<li>
					<span class="gcm-blog-top-list__rank"><?php echo esc_html( (string) ( $i + 1 ) ); ?></span>
					<div>
						<a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><strong><?php echo esc_html( get_the_title( $post ) ); ?></strong></a>
						<p><?php echo esc_html( sprintf( /* translators: 1: minutes, 2: views */ __( '%1$d min · %2$s views', 'giga-class-market' ), $mins, number_format_i18n( $views ) ) ); ?></p>
					</div>
				</li>
			<?php endforeach; ?>
		</ol>
	</div>
</section>
<?php endif; ?>

<section class="gcm-section">
	<div class="gcm-container">
		<div class="gcm-section__heading">
			<p class="gcm-eyebrow"><?php esc_html_e( 'Latest', 'giga-class-market' ); ?></p>
			<h2><?php esc_html_e( 'All articles', 'giga-class-market' ); ?></h2>
		</div>
		<div class="gcm-blog-grid">
			<?php if ( have_posts() ) : ?>
				<?php while ( have_posts() ) : ?>
					<?php
					the_post();
					$mins = class_exists( 'GCM_Blog_Service' ) ? GCM_Blog_Service::reading_minutes( get_the_ID() ) : 3;
					$terms = get_the_terms( get_the_ID(), 'gcm_blog_category' );
					?>
					<article <?php post_class( 'gcm-blog-card gcm-animate' ); ?>>
						<a class="gcm-blog-card__media" href="<?php the_permalink(); ?>">
							<?php if ( has_post_thumbnail() ) : ?>
								<?php the_post_thumbnail( 'medium_large', array( 'alt' => esc_attr( get_the_title() ) ) ); ?>
							<?php else : ?>
								<span class="gcm-blog-card__placeholder" aria-hidden="true"></span>
							<?php endif; ?>
						</a>
						<div class="gcm-blog-card__body">
							<?php if ( $terms && ! is_wp_error( $terms ) ) : ?>
								<p class="gcm-blog-card__cat"><?php echo esc_html( $terms[0]->name ); ?></p>
							<?php endif; ?>
							<p class="gcm-eyebrow"><?php echo esc_html( get_the_date() ); ?> · <?php echo esc_html( sprintf( _n( '%d min read', '%d min read', $mins, 'giga-class-market' ), $mins ) ); ?></p>
							<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
							<p><?php echo esc_html( get_the_excerpt() ); ?></p>
						</div>
					</article>
				<?php endwhile; ?>
			<?php else : ?>
				<div class="gcm-empty-state">
					<h2><?php esc_html_e( 'No blogs yet', 'giga-class-market' ); ?></h2>
					<p><?php esc_html_e( 'New articles will appear here soon.', 'giga-class-market' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
		<div class="gcm-pagination"><?php the_posts_pagination(); ?></div>
	</div>
</section>
<?php
get_footer();
