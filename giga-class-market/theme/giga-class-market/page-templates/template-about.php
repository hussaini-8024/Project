<?php
/**
 * Template Name: GCM About
 *
 * @package GigaClassMarket
 */

get_header();
?>
<section class="gcm-page-hero">
	<div class="gcm-container">
		<p class="gcm-eyebrow"><?php esc_html_e( 'About Giga Class Market', 'giga-class-market' ); ?></p>
		<h1><?php esc_html_e( 'A luxury-tech marketplace for ambitious learning', 'giga-class-market' ); ?></h1>
		<p><?php esc_html_e( 'We blend premium design, practical education, and student-first technology into one elevated learning experience.', 'giga-class-market' ); ?></p>
	</div>
</section>

<section class="gcm-section">
	<div class="gcm-container gcm-about-grid">
		<article class="gcm-luxe-card gcm-animate">
			<p class="gcm-eyebrow"><?php esc_html_e( 'Our mission', 'giga-class-market' ); ?></p>
			<h2><?php esc_html_e( 'Make premium education feel clear, useful, and attainable', 'giga-class-market' ); ?></h2>
			<p><?php echo esc_html( gcm_setting( 'about_mission', __( 'Giga Class Market exists to help students build practical skills through refined digital courses, strong mentorship signals, and consistent progress tools.', 'giga-class-market' ) ) ); ?></p>
		</article>
		<article class="gcm-luxe-card gcm-animate">
			<p class="gcm-eyebrow"><?php esc_html_e( 'Our standard', 'giga-class-market' ); ?></p>
			<h2><?php esc_html_e( 'Every course should respect the learner', 'giga-class-market' ); ?></h2>
			<p><?php echo esc_html( gcm_setting( 'about_standard', __( 'We prioritize structure, clarity, accessibility, and business-ready outcomes so every learning journey feels intentional.', 'giga-class-market' ) ) ); ?></p>
		</article>
	</div>
</section>

<section class="gcm-section gcm-section--surface">
	<div class="gcm-container gcm-ceo-grid">
		<div class="gcm-ceo-grid__photo gcm-animate">
			<?php
			$ceo_photo = gcm_setting( 'gcm_ceo_photo' );
			if ( $ceo_photo ) :
				?>
				<img src="<?php echo esc_url( $ceo_photo ); ?>" alt="<?php echo esc_attr( gcm_setting( 'gcm_ceo_name', __( 'CEO of Giga Class Market', 'giga-class-market' ) ) ); ?>">
			<?php else : ?>
				<div class="gcm-ceo-placeholder" aria-hidden="true">GCM</div>
			<?php endif; ?>
		</div>
		<div class="gcm-ceo-grid__message gcm-animate">
			<p class="gcm-eyebrow"><?php esc_html_e( 'CEO message', 'giga-class-market' ); ?></p>
			<h2><?php echo esc_html( gcm_setting( 'gcm_ceo_title', __( 'Learning should look as premium as the future it creates.', 'giga-class-market' ) ) ); ?></h2>
			<p><?php echo esc_html( gcm_setting( 'gcm_ceo_message', __( 'We built Giga Class Market for students who want clarity, elegance, and practical progress. Our promise is simple: every experience should help you move with confidence.', 'giga-class-market' ) ) ); ?></p>
			<strong><?php echo esc_html( gcm_setting( 'gcm_ceo_name', __( 'Giga Class Market Leadership', 'giga-class-market' ) ) ); ?></strong>
		</div>
	</div>
</section>

<?php
get_footer();
