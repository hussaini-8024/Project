<?php
/**
 * Single course template.
 *
 * @package GigaClassMarket
 */

get_header();
?>
<?php while ( have_posts() ) : ?>
	<?php the_post(); ?>
	<?php
	$course_id   = get_the_ID();
	$duration    = gcm_course_meta( $course_id, 'duration', __( 'Self-paced', 'giga-class-market' ) );
	$level       = gcm_course_meta( $course_id, 'level', __( 'All levels', 'giga-class-market' ) );
	$lessons     = gcm_course_meta( $course_id, 'lessons', '' );
	$price       = gcm_course_meta( $course_id, 'price', '' );
	$rating      = gcm_course_meta( $course_id, 'rating', '4.9' );
	$instructor  = gcm_course_meta( $course_id, 'instructor', __( 'Giga Class Market Faculty', 'giga-class-market' ) );
	$curriculum  = gcm_course_meta( $course_id, 'curriculum', array() );
	if ( is_string( $curriculum ) ) {
		$curriculum = array_filter( array_map( 'trim', explode( "\n", $curriculum ) ) );
	}
	?>
	<article <?php post_class( 'gcm-course-single' ); ?>>
		<section class="gcm-course-hero">
			<div class="gcm-container gcm-course-hero__grid">
				<div class="gcm-course-hero__copy">
					<p class="gcm-eyebrow"><?php echo esc_html( gcm_course_category_label( $course_id ) ); ?></p>
					<h1><?php the_title(); ?></h1>
					<p><?php echo esc_html( has_excerpt() ? get_the_excerpt() : wp_trim_words( wp_strip_all_tags( get_the_content() ), 28 ) ); ?></p>
					<div class="gcm-course-hero__facts">
						<span><?php echo esc_html( $duration ); ?></span>
						<span><?php echo esc_html( $level ); ?></span>
						<span>★ <?php echo esc_html( $rating ); ?></span>
					</div>
				</div>
				<aside class="gcm-course-buy-card" aria-label="<?php esc_attr_e( 'Course enrollment', 'giga-class-market' ); ?>">
					<?php if ( has_post_thumbnail() ) : ?>
						<?php the_post_thumbnail( 'large', array( 'alt' => esc_attr( get_the_title() ) ) ); ?>
					<?php endif; ?>
					<div class="gcm-course-buy-card__body">
						<strong><?php echo esc_html( gcm_format_price( $price ) ); ?></strong>
						<a class="gcm-button gcm-button--gold gcm-button--full" href="<?php echo esc_url( gcm_course_purchase_url( $course_id ) ); ?>"><?php esc_html_e( 'Buy Now', 'giga-class-market' ); ?></a>
						<ul>
							<li><?php echo esc_html( sprintf( __( 'Instructor: %s', 'giga-class-market' ), $instructor ) ); ?></li>
							<li><?php echo esc_html( $lessons ? sprintf( __( '%s lessons', 'giga-class-market' ), $lessons ) : __( 'Structured curriculum', 'giga-class-market' ) ); ?></li>
							<li><?php esc_html_e( 'Certificate-ready learning path', 'giga-class-market' ); ?></li>
						</ul>
					</div>
				</aside>
			</div>
		</section>

		<section class="gcm-section">
			<div class="gcm-container gcm-course-detail-grid">
				<div class="gcm-content">
					<h2><?php esc_html_e( 'Course overview', 'giga-class-market' ); ?></h2>
					<?php the_content(); ?>
				</div>
				<aside class="gcm-curriculum-card">
					<h2><?php esc_html_e( 'Curriculum', 'giga-class-market' ); ?></h2>
					<?php if ( ! empty( $curriculum ) ) : ?>
						<ol>
							<?php foreach ( $curriculum as $item ) : ?>
								<li><?php echo esc_html( is_array( $item ) ? ( $item['title'] ?? '' ) : $item ); ?></li>
							<?php endforeach; ?>
						</ol>
					<?php else : ?>
						<p><?php esc_html_e( 'Curriculum details will be published soon.', 'giga-class-market' ); ?></p>
					<?php endif; ?>
				</aside>
			</div>
		</section>
	</article>
<?php endwhile; ?>
<?php
get_footer();
