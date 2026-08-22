<?php
/**
 * Single blog post.
 *
 * @package GigaClassMarket
 */

get_header();

while ( have_posts() ) :
	the_post();
	if ( class_exists( 'GCM_Blog_Service' ) ) {
		GCM_Blog_Service::track_view( get_the_ID() );
	}
	$mins        = class_exists( 'GCM_Blog_Service' ) ? GCM_Blog_Service::reading_minutes( get_the_ID() ) : 3;
	$terms       = get_the_terms( get_the_ID(), 'gcm_blog_category' );
	$course_id   = class_exists( 'GCM_Blog_Service' ) ? GCM_Blog_Service::get_related_course_id( get_the_ID() ) : 0;
	$cta_label   = (string) get_post_meta( get_the_ID(), '_gcm_blog_cta_label', true );
	$related     = class_exists( 'GCM_Blog_Service' ) ? GCM_Blog_Service::get_related( get_the_ID(), 3 ) : array();
	if ( ! $cta_label ) {
		$cta_label = __( 'Explore this course', 'giga-class-market' );
	}
	?>
	<article <?php post_class( 'gcm-blog-single' ); ?>>
		<header class="gcm-page-hero gcm-blog-single__hero">
			<div class="gcm-container gcm-blog-single__intro">
				<p class="gcm-eyebrow">
					<a href="<?php echo esc_url( get_post_type_archive_link( 'gcm_blog' ) ); ?>"><?php esc_html_e( 'Blog', 'giga-class-market' ); ?></a>
					<?php if ( $terms && ! is_wp_error( $terms ) ) : ?>
						· <a href="<?php echo esc_url( get_term_link( $terms[0] ) ); ?>"><?php echo esc_html( $terms[0]->name ); ?></a>
					<?php endif; ?>
				</p>
				<h1><?php the_title(); ?></h1>
				<p class="gcm-blog-single__meta">
					<?php echo esc_html( get_the_date() ); ?>
					· <?php echo esc_html( sprintf( _n( '%d min read', '%d min read', $mins, 'giga-class-market' ), $mins ) ); ?>
				</p>
			</div>
		</header>

		<?php if ( has_post_thumbnail() ) : ?>
			<div class="gcm-container gcm-blog-single__cover">
				<?php the_post_thumbnail( 'large', array( 'alt' => esc_attr( get_the_title() ) ) ); ?>
			</div>
		<?php endif; ?>

		<div class="gcm-container gcm-blog-single__layout">
			<div class="gcm-blog-single__content entry-content">
				<?php the_content(); ?>
			</div>

			<aside class="gcm-blog-single__aside">
				<?php if ( $course_id && get_post( $course_id ) ) : ?>
					<div class="gcm-blog-course-cta">
						<p class="gcm-eyebrow"><?php esc_html_e( 'Next step', 'giga-class-market' ); ?></p>
						<h2><?php echo esc_html( get_the_title( $course_id ) ); ?></h2>
						<p><?php echo esc_html( get_the_excerpt( $course_id ) ?: __( 'Ready to go deeper? Enroll in the related course on Giga Class Market.', 'giga-class-market' ) ); ?></p>
						<a class="gcm-button gcm-button--gold" href="<?php echo esc_url( get_permalink( $course_id ) ); ?>"><?php echo esc_html( $cta_label ); ?></a>
					</div>
				<?php else : ?>
					<div class="gcm-blog-course-cta">
						<p class="gcm-eyebrow"><?php esc_html_e( 'Learn with us', 'giga-class-market' ); ?></p>
						<h2><?php esc_html_e( 'Browse premium courses', 'giga-class-market' ); ?></h2>
						<p><?php esc_html_e( 'Turn this reading into skills with expert-led online courses.', 'giga-class-market' ); ?></p>
						<a class="gcm-button gcm-button--gold" href="<?php echo esc_url( get_post_type_archive_link( 'gcm_course' ) ?: home_url( '/courses/' ) ); ?>"><?php esc_html_e( 'View courses', 'giga-class-market' ); ?></a>
					</div>
				<?php endif; ?>
			</aside>
		</div>

		<?php if ( ! empty( $related ) ) : ?>
			<section class="gcm-section gcm-section--surface">
				<div class="gcm-container">
					<div class="gcm-section__heading">
						<p class="gcm-eyebrow"><?php esc_html_e( 'Keep reading', 'giga-class-market' ); ?></p>
						<h2><?php esc_html_e( 'Related articles', 'giga-class-market' ); ?></h2>
					</div>
					<div class="gcm-blog-grid">
						<?php foreach ( $related as $post ) : ?>
							<?php
							setup_postdata( $post );
							$rm = class_exists( 'GCM_Blog_Service' ) ? GCM_Blog_Service::reading_minutes( $post->ID ) : 3;
							?>
							<article class="gcm-blog-card">
								<a class="gcm-blog-card__media" href="<?php echo esc_url( get_permalink( $post ) ); ?>">
									<?php if ( has_post_thumbnail( $post ) ) : ?>
										<?php echo get_the_post_thumbnail( $post, 'medium_large', array( 'alt' => esc_attr( get_the_title( $post ) ) ) ); ?>
									<?php else : ?>
										<span class="gcm-blog-card__placeholder" aria-hidden="true"></span>
									<?php endif; ?>
								</a>
								<div class="gcm-blog-card__body">
									<p class="gcm-eyebrow"><?php echo esc_html( get_the_date( '', $post ) ); ?> · <?php echo esc_html( sprintf( _n( '%d min read', '%d min read', $rm, 'giga-class-market' ), $rm ) ); ?></p>
									<h3><a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></h3>
								</div>
							</article>
						<?php endforeach; ?>
						<?php wp_reset_postdata(); ?>
					</div>
				</div>
			</section>
		<?php endif; ?>
	</article>
	<?php
endwhile;

get_footer();
