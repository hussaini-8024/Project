<?php
/**
 * Homepage hero slider.
 *
 * @package GigaClassMarket
 */

$slides_query = new WP_Query(
	array(
		'post_type'      => 'gcm_slide',
		'posts_per_page' => 5,
		'post_status'    => 'publish',
		'orderby'        => 'menu_order date',
		'order'          => 'ASC',
	)
);

$slides = array();
if ( $slides_query->have_posts() ) {
	while ( $slides_query->have_posts() ) {
		$slides_query->the_post();
		$slides[] = array(
			'eyebrow' => __( 'Giga Class Market', 'giga-class-market' ),
			'title'   => get_the_title(),
			'text'    => has_excerpt() ? get_the_excerpt() : wp_trim_words( wp_strip_all_tags( get_the_content() ), 28 ),
		);
	}
	wp_reset_postdata();
}

if ( empty( $slides ) ) {
	$slides = gcm_setting(
		'hero_slides',
		array(
			array(
				'eyebrow' => __( 'Luxury-tech learning marketplace', 'giga-class-market' ),
				'title'   => __( 'Master premium skills with Giga Class Market', 'giga-class-market' ),
				'text'    => __( 'Discover polished courses built for ambitious learners, creative professionals, and future-ready teams.', 'giga-class-market' ),
			),
			array(
				'eyebrow' => __( 'Structured paths. Real momentum.', 'giga-class-market' ),
				'title'   => __( 'Learn from focused courses that move careers forward', 'giga-class-market' ),
				'text'    => __( 'Every track blends expert insight, applied projects, and elegant study experiences.', 'giga-class-market' ),
			),
			array(
				'eyebrow' => __( 'From first lesson to certification', 'giga-class-market' ),
				'title'   => __( 'Build confidence with a marketplace made for growth', 'giga-class-market' ),
				'text'    => __( 'Choose your course, continue at your pace, and showcase measurable progress.', 'giga-class-market' ),
			),
		)
	);
}

if ( ! is_array( $slides ) || empty( $slides ) ) {
	return;
}
?>
<section class="gcm-hero" aria-label="<?php esc_attr_e( 'Featured learning experiences', 'giga-class-market' ); ?>" data-gcm-slider>
	<div class="gcm-hero__viewport">
		<?php foreach ( array_values( $slides ) as $index => $slide ) : ?>
			<article class="gcm-hero__slide <?php echo 0 === $index ? 'is-active' : ''; ?>" data-gcm-slide>
				<div class="gcm-container gcm-hero__content">
					<div class="gcm-hero__copy gcm-animate">
						<p class="gcm-eyebrow"><?php echo esc_html( $slide['eyebrow'] ?? __( 'Giga Class Market', 'giga-class-market' ) ); ?></p>
						<h1><?php echo esc_html( $slide['title'] ?? __( 'Giga Class Market', 'giga-class-market' ) ); ?></h1>
						<p class="gcm-hero__lead"><?php echo esc_html( $slide['text'] ?? '' ); ?></p>
						<div class="gcm-hero__actions">
							<a class="gcm-button gcm-button--gold" href="<?php echo esc_url( get_post_type_archive_link( 'gcm_course' ) ?: home_url( '/courses/' ) ); ?>"><?php esc_html_e( 'Explore Courses', 'giga-class-market' ); ?></a>
							<a class="gcm-button gcm-button--ghost" href="<?php echo esc_url( gcm_student_login_url() ); ?>"><?php esc_html_e( 'Start Learning', 'giga-class-market' ); ?></a>
						</div>
					</div>
					<div class="gcm-hero__visual" aria-hidden="true">
						<div class="gcm-orbit gcm-orbit--one"></div>
						<div class="gcm-orbit gcm-orbit--two"></div>
						<div class="gcm-hero-card">
							<span><?php esc_html_e( 'Premium Tracks', 'giga-class-market' ); ?></span>
							<strong><?php esc_html_e( 'Education, elevated', 'giga-class-market' ); ?></strong>
						</div>
					</div>
				</div>
			</article>
		<?php endforeach; ?>
	</div>

	<div class="gcm-container gcm-hero__controls">
		<button type="button" class="gcm-slider-btn" data-gcm-slider-prev aria-label="<?php esc_attr_e( 'Previous slide', 'giga-class-market' ); ?>">&larr;</button>
		<div class="gcm-slider-dots" role="tablist" aria-label="<?php esc_attr_e( 'Hero slides', 'giga-class-market' ); ?>">
			<?php foreach ( array_values( $slides ) as $index => $slide ) : ?>
				<button type="button" class="<?php echo 0 === $index ? 'is-active' : ''; ?>" data-gcm-slider-dot="<?php echo esc_attr( $index ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Go to slide %d', 'giga-class-market' ), $index + 1 ) ); ?>"></button>
			<?php endforeach; ?>
		</div>
		<button type="button" class="gcm-slider-btn" data-gcm-slider-next aria-label="<?php esc_attr_e( 'Next slide', 'giga-class-market' ); ?>">&rarr;</button>
	</div>
</section>
