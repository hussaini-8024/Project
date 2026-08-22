<?php
/**
 * Blog category archive.
 *
 * @package GigaClassMarket
 */

get_header();

$term = get_queried_object();
$cats = class_exists( 'GCM_Blog_Service' ) ? GCM_Blog_Service::get_categories() : array();
?>
<section class="gcm-page-hero gcm-blog-hero">
	<div class="gcm-container">
		<p class="gcm-eyebrow"><a href="<?php echo esc_url( get_post_type_archive_link( 'gcm_blog' ) ); ?>"><?php esc_html_e( 'Blog', 'giga-class-market' ); ?></a></p>
		<h1><?php echo esc_html( $term && isset( $term->name ) ? $term->name : __( 'Category', 'giga-class-market' ) ); ?></h1>
		<?php if ( $term && ! empty( $term->description ) ) : ?>
			<p><?php echo esc_html( $term->description ); ?></p>
		<?php else : ?>
			<p><?php esc_html_e( 'Articles in this category — curated to help you learn and enroll with confidence.', 'giga-class-market' ); ?></p>
		<?php endif; ?>
	</div>
</section>

<?php if ( ! empty( $cats ) ) : ?>
<section class="gcm-section gcm-blog-cats">
	<div class="gcm-container">
		<div class="gcm-blog-cat-chips">
			<a class="gcm-blog-chip" href="<?php echo esc_url( get_post_type_archive_link( 'gcm_blog' ) ); ?>"><?php esc_html_e( 'All', 'giga-class-market' ); ?></a>
			<?php foreach ( $cats as $cat ) : ?>
				<a class="gcm-blog-chip<?php echo ( $term && (int) $term->term_id === (int) $cat->term_id ) ? ' is-active' : ''; ?>" href="<?php echo esc_url( get_term_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?></a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<section class="gcm-section">
	<div class="gcm-container gcm-blog-grid">
		<?php if ( have_posts() ) : ?>
			<?php while ( have_posts() ) : ?>
				<?php
				the_post();
				$mins = class_exists( 'GCM_Blog_Service' ) ? GCM_Blog_Service::reading_minutes( get_the_ID() ) : 3;
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
						<p class="gcm-eyebrow"><?php echo esc_html( get_the_date() ); ?> · <?php echo esc_html( sprintf( _n( '%d min read', '%d min read', $mins, 'giga-class-market' ), $mins ) ); ?></p>
						<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
						<p><?php echo esc_html( get_the_excerpt() ); ?></p>
					</div>
				</article>
			<?php endwhile; ?>
			<div class="gcm-pagination"><?php the_posts_pagination(); ?></div>
		<?php else : ?>
			<div class="gcm-empty-state">
				<h2><?php esc_html_e( 'No posts in this category yet', 'giga-class-market' ); ?></h2>
			</div>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
