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
			<p><?php echo wp_kses( __( 'Curated <strong>premium courses</strong> from Giga Class Market, ranked for impact and <strong>learner momentum</strong>.', 'giga-class-market' ), array( 'strong' => array() ) ); ?></p>
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
			<?php
			$benefit_icons = array( '01', '02', '03' );
			foreach ( array_values( gcm_get_benefits() ) as $index => $benefit ) :
				?>
				<article class="gcm-benefit-card gcm-animate">
					<span class="gcm-benefit-card__icon" aria-hidden="true"><?php echo esc_html( $benefit_icons[ $index ] ?? '•' ); ?></span>
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
				$defaults = function_exists( 'gcm_default_student_reviews' ) ? gcm_default_student_reviews() : array();
				foreach ( $defaults as $item ) :
					?>
					<article class="gcm-testimonial-card gcm-animate">
						<div class="gcm-testimonial-card__stars" aria-label="<?php esc_attr_e( 'Five star review', 'giga-class-market' ); ?>">★★★★★</div>
						<blockquote><?php echo esc_html( $item['quote'] ); ?></blockquote>
						<div class="gcm-testimonial-card__person">
							<img src="<?php echo esc_url( $item['url'] ); ?>" alt="<?php echo esc_attr( $item['name'] ); ?>" width="72" height="72" loading="lazy" decoding="async" />
							<div>
								<strong><?php echo esc_html( $item['name'] ); ?></strong>
								<span><?php echo esc_html( $item['role'] ); ?></span>
							</div>
						</div>
					</article>
					<?php
				endforeach;
			endif;
			?>
		</div>
	</div>
</section>

<section class="gcm-section gcm-section--surface gcm-home-seo">
	<div class="gcm-container">
		<div class="gcm-section__heading gcm-animate">
			<p class="gcm-eyebrow"><?php esc_html_e( 'Why Giga Class Market', 'giga-class-market' ); ?></p>
			<h2><?php esc_html_e( 'Online courses built for real careers in Pakistan and beyond', 'giga-class-market' ); ?></h2>
			<p><?php esc_html_e( 'Giga Class Market is a premium digital learning marketplace for students who want practical skills, clear structure, and verified progress — not random video dumps.', 'giga-class-market' ); ?></p>
		</div>
		<div class="gcm-benefit-grid gcm-home-seo__grid">
			<article class="gcm-benefit-card gcm-animate">
				<h3><?php esc_html_e( 'FPSC preparation online', 'giga-class-market' ); ?></h3>
				<p><?php esc_html_e( 'Prepare for FPSC exams in Pakistan with a focused study path: MCQs, past-paper strategy, English, Pakistan Affairs, and current affairs — designed for busy learners.', 'giga-class-market' ); ?></p>
				<p><a href="<?php echo esc_url( home_url( '/courses/fpsc-success-mastery/' ) ); ?>"><?php esc_html_e( 'Explore FPSC Success Mastery', 'giga-class-market' ); ?></a></p>
			</article>
			<article class="gcm-benefit-card gcm-animate">
				<h3><?php esc_html_e( 'CCNA networking skills', 'giga-class-market' ); ?></h3>
				<p><?php esc_html_e( 'Build job-ready networking foundations from beginner to professional — routing, switching, labs mindset, and a clear CCNA-oriented learning path.', 'giga-class-market' ); ?></p>
				<p><a href="<?php echo esc_url( home_url( '/courses/ccna-level-from-beginner-to-professional/' ) ); ?>"><?php esc_html_e( 'Explore the CCNA course', 'giga-class-market' ); ?></a></p>
			</article>
			<article class="gcm-benefit-card gcm-animate">
				<h3><?php esc_html_e( 'Ethical hacking foundations', 'giga-class-market' ); ?></h3>
				<p><?php esc_html_e( 'Start cybersecurity the right way: legal practice habits, networking + Linux basics, and a structured ethical hacking path from entry level toward stronger skills.', 'giga-class-market' ); ?></p>
				<p><a href="<?php echo esc_url( home_url( '/courses/ethical-hacking-entry-level-to-pro/' ) ); ?>"><?php esc_html_e( 'Explore Ethical Hacking', 'giga-class-market' ); ?></a></p>
			</article>
		</div>
		<p class="gcm-home-seo__more gcm-animate">
			<?php
			echo wp_kses(
				sprintf(
					/* translators: 1: courses URL, 2: blogs URL */
					__( 'Browse all <a href="%1$s">online courses</a> or read our <a href="%2$s">FPSC, CCNA, and ethical hacking guides</a> to choose the right next step.', 'giga-class-market' ),
					esc_url( get_post_type_archive_link( 'gcm_course' ) ?: home_url( '/courses/' ) ),
					esc_url( get_post_type_archive_link( 'gcm_blog' ) ?: home_url( '/blogs/' ) )
				),
				array( 'a' => array( 'href' => array() ) )
			);
			?>
		</p>
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
