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
		<h1><?php esc_html_e( 'Premium education for ambitious learners', 'giga-class-market' ); ?></h1>
		<p><?php esc_html_e( 'We built a modern learning marketplace that feels trustworthy, polished, and ready for real careers.', 'giga-class-market' ); ?></p>
	</div>
</section>

<section class="gcm-section">
	<div class="gcm-container gcm-about-grid">
		<article class="gcm-luxe-card gcm-animate">
			<p class="gcm-eyebrow"><?php esc_html_e( 'Mission', 'giga-class-market' ); ?></p>
			<h2><?php esc_html_e( 'Make premium education clear and attainable', 'giga-class-market' ); ?></h2>
			<p><?php echo esc_html( gcm_setting( 'about_mission', __( 'Giga Class Market helps students build practical skills through structured digital courses, verified enrollment, and consistent progress tracking.', 'giga-class-market' ) ) ); ?></p>
		</article>
		<article class="gcm-luxe-card gcm-animate">
			<p class="gcm-eyebrow"><?php esc_html_e( 'Vision', 'giga-class-market' ); ?></p>
			<h2><?php esc_html_e( 'A trusted marketplace for technical growth', 'giga-class-market' ); ?></h2>
			<p><?php echo esc_html( gcm_setting( 'about_vision', __( 'We envision a learning platform where every course, payment, and student journey is managed with professionalism and transparency.', 'giga-class-market' ) ) ); ?></p>
		</article>
		<article class="gcm-luxe-card gcm-animate">
			<p class="gcm-eyebrow"><?php esc_html_e( 'What makes us different', 'giga-class-market' ); ?></p>
			<h2><?php esc_html_e( 'Marketplace + LMS, designed as one system', 'giga-class-market' ); ?></h2>
			<p><?php echo esc_html( gcm_setting( 'about_difference', __( 'Unlike generic course pages, Giga Class Market connects browsing, payment verification, secure student accounts, and lesson progress in one connected workflow.', 'giga-class-market' ) ) ); ?></p>
		</article>
		<article class="gcm-luxe-card gcm-animate">
			<p class="gcm-eyebrow"><?php esc_html_e( 'Learning philosophy', 'giga-class-market' ); ?></p>
			<h2><?php esc_html_e( 'Practice first. Progress always.', 'giga-class-market' ); ?></h2>
			<p><?php echo esc_html( gcm_setting( 'about_philosophy', __( 'We focus on practical skills, industry knowledge, and measurable lesson completion so learners always know where they stand.', 'giga-class-market' ) ) ); ?></p>
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
			<p class="gcm-eyebrow"><?php esc_html_e( 'CEO Message', 'giga-class-market' ); ?></p>
			<h2><?php echo esc_html( gcm_setting( 'gcm_ceo_title', __( 'Learning should feel as premium as the future it creates.', 'giga-class-market' ) ) ); ?></h2>
			<p><?php echo esc_html( gcm_setting( 'gcm_ceo_message', __( 'We built Giga Class Market for students who want clarity, elegant study experiences, and real skill progress. Our promise is simple: every course journey should help you move forward with confidence.', 'giga-class-market' ) ) ); ?></p>
			<strong><?php echo esc_html( gcm_setting( 'gcm_ceo_name', __( 'Qasim Hussaini', 'giga-class-market' ) ) ); ?></strong>
			<span><?php echo esc_html( gcm_setting( 'gcm_ceo_designation', __( 'CEO, Giga Class Market', 'giga-class-market' ) ) ); ?></span>
		</div>
	</div>
</section>
<?php
get_footer();
