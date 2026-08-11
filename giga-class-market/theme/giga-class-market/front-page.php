<?php
/**
 * Front page template.
 *
 * @package GigaClassMarket
 */

get_header();
get_template_part( 'template-parts/home/hero-slider' );
?>

<section class="gcm-section">
	<div class="gcm-container">
		<div class="gcm-section__heading gcm-animate">
			<p class="gcm-eyebrow"><?php esc_html_e( 'Top courses', 'giga-class-market' ); ?></p>
			<h2><?php esc_html_e( 'Featured courses for serious growth', 'giga-class-market' ); ?></h2>
			<p><?php esc_html_e( 'Curated premium courses from Giga Class Market, ranked for impact and learner momentum.', 'giga-class-market' ); ?></p>
		</div>

		<div class="gcm-course-grid gcm-course-grid--three">
			<?php
			$featured_courses = gcm_get_featured_courses_query( 3 );
			if ( $featured_courses->have_posts() ) :
				while ( $featured_courses->have_posts() ) :
					$featured_courses->the_post();
					get_template_part( 'template-parts/course/card' );
				endwhile;
				wp_reset_postdata();
			else :
				?>
				<div class="gcm-empty-state">
					<h3><?php esc_html_e( 'Featured courses coming soon', 'giga-class-market' ); ?></h3>
					<p><?php esc_html_e( 'Activate the course plugin or mark courses as featured to populate this premium marketplace section.', 'giga-class-market' ); ?></p>
				</div>
			<?php endif; ?>
		</div>

		<div class="gcm-section__actions">
			<a class="gcm-button gcm-button--outline" href="<?php echo esc_url( get_post_type_archive_link( 'gcm_course' ) ?: home_url( '/courses/' ) ); ?>"><?php esc_html_e( 'View All Courses', 'giga-class-market' ); ?></a>
		</div>
	</div>
</section>

<section class="gcm-section gcm-section--surface">
	<div class="gcm-container">
		<div class="gcm-section__heading gcm-animate">
			<p class="gcm-eyebrow"><?php esc_html_e( 'What you will learn', 'giga-class-market' ); ?></p>
			<h2><?php esc_html_e( 'A refined path from curiosity to capability', 'giga-class-market' ); ?></h2>
		</div>
		<div class="gcm-benefit-grid">
			<?php foreach ( gcm_get_benefits() as $benefit ) : ?>
				<article class="gcm-benefit-card gcm-animate">
					<span aria-hidden="true"></span>
					<h3><?php echo esc_html( $benefit['title'] ?? '' ); ?></h3>
					<p><?php echo esc_html( $benefit['text'] ?? '' ); ?></p>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="gcm-section">
	<div class="gcm-container">
		<div class="gcm-section__heading gcm-animate">
			<p class="gcm-eyebrow"><?php esc_html_e( 'Student reviews', 'giga-class-market' ); ?></p>
			<h2><?php esc_html_e( 'What learners say after the first breakthrough', 'giga-class-market' ); ?></h2>
		</div>
		<div class="gcm-testimonial-grid">
			<?php
			$testimonials = new WP_Query(
				array(
					'post_type'      => 'gcm_testimonial',
					'posts_per_page' => 3,
					'post_status'    => 'publish',
				)
			);
			if ( $testimonials->have_posts() ) :
				while ( $testimonials->have_posts() ) :
					$testimonials->the_post();
					get_template_part( 'template-parts/shared/testimonial-card' );
				endwhile;
				wp_reset_postdata();
			else :
				$defaults = array(
					__( 'The learning experience feels polished, clear, and genuinely premium. I knew exactly what to study next.', 'giga-class-market' ),
					__( 'Giga Class Market helped me build practical confidence with elegant lessons and real project direction.', 'giga-class-market' ),
					__( 'The course structure made advanced concepts approachable without feeling watered down.', 'giga-class-market' ),
				);
				foreach ( $defaults as $index => $quote ) :
					?>
					<article class="gcm-testimonial-card gcm-animate">
						<div class="gcm-testimonial-card__stars" aria-label="<?php esc_attr_e( 'Five star review', 'giga-class-market' ); ?>">★★★★★</div>
						<blockquote><?php echo esc_html( $quote ); ?></blockquote>
						<div class="gcm-testimonial-card__person">
							<div><strong><?php echo esc_html( sprintf( __( 'Student %d', 'giga-class-market' ), $index + 1 ) ); ?></strong><span><?php esc_html_e( 'GCM Learner', 'giga-class-market' ); ?></span></div>
						</div>
					</article>
					<?php
				endforeach;
			endif;
			?>
		</div>
	</div>
</section>

<section class="gcm-contact-cta">
	<div class="gcm-container gcm-contact-cta__inner gcm-animate">
		<div>
			<p class="gcm-eyebrow"><?php esc_html_e( 'Need guidance?', 'giga-class-market' ); ?></p>
			<h2><?php esc_html_e( 'Have Questions? Contact Us', 'giga-class-market' ); ?></h2>
			<p><?php esc_html_e( 'Tell us your goal and we will help you choose the right premium learning path.', 'giga-class-market' ); ?></p>
		</div>
		<div class="gcm-contact-cta__actions">
			<a class="gcm-button gcm-button--gold" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Contact Us', 'giga-class-market' ); ?></a>
			<?php if ( gcm_setting( 'whatsapp_number' ) ) : ?>
				<a class="gcm-button gcm-button--ghost-dark" href="<?php echo esc_url( 'https://wa.me/' . preg_replace( '/[^0-9]/', '', gcm_setting( 'whatsapp_number' ) ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'WhatsApp', 'giga-class-market' ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</section>

<?php
get_footer();
