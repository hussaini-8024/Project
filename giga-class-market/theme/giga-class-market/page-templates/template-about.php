<?php
/**
 * Template Name: GCM About
 *
 * @package GigaClassMarket
 */

get_header();

$about_cards = array(
	array(
		'eyebrow' => __( 'Mission', 'giga-class-market' ),
		'title'   => __( 'Make premium education clear and attainable', 'giga-class-market' ),
		'text'    => gcm_setting( 'about_mission', __( 'Giga Class Market helps students build practical skills through structured digital courses, verified enrollment, and consistent progress tracking.', 'giga-class-market' ) ),
		'tone'    => 'gold',
		'icon'    => '01',
	),
	array(
		'eyebrow' => __( 'Vision', 'giga-class-market' ),
		'title'   => __( 'A trusted marketplace for technical growth', 'giga-class-market' ),
		'text'    => gcm_setting( 'about_vision', __( 'We envision a learning platform where every course, payment, and student journey is managed with professionalism and transparency.', 'giga-class-market' ) ),
		'tone'    => 'navy',
		'icon'    => '02',
	),
	array(
		'eyebrow' => __( 'What makes us different', 'giga-class-market' ),
		'title'   => __( 'Marketplace + LMS, designed as one system', 'giga-class-market' ),
		'text'    => gcm_setting( 'about_difference', __( 'Unlike generic course pages, Giga Class Market connects browsing, payment verification, secure student accounts, and lesson progress in one connected workflow.', 'giga-class-market' ) ),
		'tone'    => 'blue',
		'icon'    => '03',
	),
	array(
		'eyebrow' => __( 'Learning philosophy', 'giga-class-market' ),
		'title'   => __( 'Practice first. Progress always.', 'giga-class-market' ),
		'text'    => gcm_setting( 'about_philosophy', __( 'We focus on practical skills, industry knowledge, and measurable lesson completion so learners always know where they stand.', 'giga-class-market' ) ),
		'tone'    => 'emerald',
		'icon'    => '04',
	),
);

$team_uri = trailingslashit( GCM_THEME_URI ) . 'assets/images/team/';
$core_team = array(
	array(
		'name'  => __( 'Manzoor Ahmad', 'giga-class-market' ),
		'role'  => __( 'Lecturer', 'giga-class-market' ),
		'bio'   => __( 'An experienced lecturer focused on clear teaching, practical skills, and helping students build confidence through structured live classes.', 'giga-class-market' ),
		'photo' => $team_uri . 'manzoor-ahmad.jpg',
	),
	array(
		'name'  => __( 'Navyan Baig', 'giga-class-market' ),
		'role'  => __( 'Web Developer', 'giga-class-market' ),
		'bio'   => __( 'Web developer building and refining the Giga Class Market platform so students, teachers, and admins enjoy a smooth, reliable learning experience.', 'giga-class-market' ),
		'photo' => $team_uri . 'navyan-baig.jpg',
	),
);
?>
<section class="gcm-page-hero gcm-page-hero--about">
	<div class="gcm-container">
		<p class="gcm-eyebrow gcm-animate"><?php esc_html_e( 'About Us', 'giga-class-market' ); ?></p>
		<h1 class="gcm-animate"><?php esc_html_e( 'Premium education for ambitious learners', 'giga-class-market' ); ?></h1>
		<p class="gcm-animate"><?php echo wp_kses( __( 'We built a modern learning marketplace that feels <strong>trustworthy</strong>, polished, and ready for <strong>real careers</strong>.', 'giga-class-market' ), array( 'strong' => array() ) ); ?></p>
	</div>
</section>

<section class="gcm-section">
	<div class="gcm-container gcm-about-grid">
		<?php foreach ( $about_cards as $index => $card ) : ?>
			<article class="gcm-about-card gcm-about-card--<?php echo esc_attr( $card['tone'] ); ?> gcm-animate" style="--gcm-delay: <?php echo esc_attr( ( $index + 1 ) * 0.08 ); ?>s">
				<div class="gcm-about-card__top">
					<span class="gcm-about-card__icon" aria-hidden="true"><?php echo esc_html( $card['icon'] ); ?></span>
					<p class="gcm-eyebrow"><?php echo esc_html( $card['eyebrow'] ); ?></p>
				</div>
				<h2><?php echo esc_html( $card['title'] ); ?></h2>
				<p><?php echo esc_html( $card['text'] ); ?></p>
			</article>
		<?php endforeach; ?>
	</div>
</section>

<section class="gcm-section gcm-section--surface">
	<div class="gcm-container gcm-ceo-panel gcm-animate">
		<div class="gcm-ceo-panel__photo">
			<?php
			$ceo_photo = gcm_setting( 'gcm_ceo_photo' );
			if ( $ceo_photo ) :
				?>
				<img src="<?php echo esc_url( $ceo_photo ); ?>" alt="<?php echo esc_attr( gcm_setting( 'gcm_ceo_name', __( 'CEO of Giga Class Market', 'giga-class-market' ) ) ); ?>">
			<?php else : ?>
				<div class="gcm-ceo-placeholder" aria-hidden="true">GCM</div>
			<?php endif; ?>
		</div>
		<div class="gcm-ceo-panel__message">
			<p class="gcm-eyebrow"><?php esc_html_e( 'CEO Message', 'giga-class-market' ); ?></p>
			<h2><?php echo esc_html( gcm_setting( 'gcm_ceo_title', __( 'Learning should feel as premium as the future it creates.', 'giga-class-market' ) ) ); ?></h2>
			<p><?php echo esc_html( gcm_setting( 'gcm_ceo_message', __( 'We built Giga Class Market for students who want clarity, elegant study experiences, and real skill progress. Our promise is simple: every course journey should help you move forward with confidence.', 'giga-class-market' ) ) ); ?></p>
			<div class="gcm-ceo-panel__identity">
				<strong><?php echo esc_html( gcm_setting( 'gcm_ceo_name', __( 'Qasim Hussaini', 'giga-class-market' ) ) ); ?></strong>
				<span><?php echo esc_html( gcm_setting( 'gcm_ceo_designation', __( 'CEO, Giga Class Market', 'giga-class-market' ) ) ); ?></span>
			</div>
		</div>
	</div>
</section>

<section class="gcm-section gcm-section--team" aria-labelledby="gcm-core-team-heading">
	<div class="gcm-container">
		<header class="gcm-section__header gcm-section__header--center gcm-animate">
			<p class="gcm-eyebrow"><?php esc_html_e( 'Our people', 'giga-class-market' ); ?></p>
			<h2 id="gcm-core-team-heading"><?php esc_html_e( 'Meet Our Core Team', 'giga-class-market' ); ?></h2>
			<p><?php esc_html_e( 'The educators and builders guiding Giga Class Market every day.', 'giga-class-market' ); ?></p>
		</header>
		<div class="gcm-team-grid">
			<?php foreach ( $core_team as $index => $member ) : ?>
				<article class="gcm-team-card gcm-animate" style="--gcm-delay: <?php echo esc_attr( ( $index + 1 ) * 0.1 ); ?>s">
					<div class="gcm-team-card__photo">
						<img
							src="<?php echo esc_url( $member['photo'] ); ?>"
							alt="<?php echo esc_attr( $member['name'] ); ?>"
							width="480"
							height="640"
							loading="lazy"
							decoding="async"
						>
					</div>
					<div class="gcm-team-card__body">
						<h3><?php echo esc_html( $member['name'] ); ?></h3>
						<p class="gcm-team-card__role"><?php echo esc_html( $member['role'] ); ?></p>
						<p class="gcm-team-card__bio"><?php echo esc_html( $member['bio'] ); ?></p>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php
get_footer();
