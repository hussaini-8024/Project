<?php
/**
 * Template Name: GCM Portfolio
 *
 * Cybersecurity-themed portfolio for Navyan Baig — fully admin-editable.
 *
 * @package GigaClassMarket
 */

get_header();

$profile   = class_exists( 'GCM_Portfolio_Service' ) ? GCM_Portfolio_Service::get_profile() : array();
$projects  = class_exists( 'GCM_Portfolio_Service' ) ? GCM_Portfolio_Service::get_projects() : array();
$cats      = class_exists( 'GCM_Portfolio_Service' ) ? GCM_Portfolio_Service::categories() : array();
$photo_id  = absint( $profile['photo_id'] ?? 0 );
$photo_url = $photo_id ? wp_get_attachment_image_url( $photo_id, 'large' ) : '';
if ( ! $photo_url && defined( 'GCM_THEME_URI' ) ) {
	$fallback = trailingslashit( GCM_THEME_URI ) . 'assets/images/team/navyan-baig.jpg';
	$photo_url = $fallback;
}

$cta_url = $profile['cta_url'] ?? '/contact/';
if ( $cta_url && 0 === strpos( $cta_url, '/' ) ) {
	$cta_url = home_url( $cta_url );
}

$skill_blocks = array(
	'cyber'      => array(
		'label' => __( 'Cyber Security', 'giga-class-market' ),
		'code'  => 'SEC',
		'items' => class_exists( 'GCM_Portfolio_Service' ) ? GCM_Portfolio_Service::lines( $profile['skills_cyber'] ?? '' ) : array(),
	),
	'networking' => array(
		'label' => __( 'Networking', 'giga-class-market' ),
		'code'  => 'NET',
		'items' => class_exists( 'GCM_Portfolio_Service' ) ? GCM_Portfolio_Service::lines( $profile['skills_networking'] ?? '' ) : array(),
	),
	'web'        => array(
		'label' => __( 'Web Development', 'giga-class-market' ),
		'code'  => 'WEB',
		'items' => class_exists( 'GCM_Portfolio_Service' ) ? GCM_Portfolio_Service::lines( $profile['skills_web'] ?? '' ) : array(),
	),
	'animation'  => array(
		'label' => __( 'Animation', 'giga-class-market' ),
		'code'  => 'MOT',
		'items' => class_exists( 'GCM_Portfolio_Service' ) ? GCM_Portfolio_Service::lines( $profile['skills_animation'] ?? '' ) : array(),
	),
);
?>
<div class="gcm-folio" data-gcm-folio>
	<div class="gcm-folio__grid-bg" aria-hidden="true"></div>

	<section class="gcm-folio-hero">
		<div class="gcm-container gcm-folio-hero__inner">
			<div class="gcm-folio-hero__copy">
				<p class="gcm-folio-eyebrow gcm-folio-reveal"><?php echo esc_html( $profile['eyebrow'] ?? '' ); ?></p>
				<p class="gcm-folio-status gcm-folio-reveal"><?php echo esc_html( $profile['status_text'] ?? '' ); ?></p>
				<h1 class="gcm-folio-name gcm-folio-reveal" data-gcm-typewriter><?php echo esc_html( $profile['name'] ?? 'Navyan Baig' ); ?></h1>
				<p class="gcm-folio-role gcm-folio-reveal"><?php echo esc_html( $profile['role'] ?? '' ); ?></p>
				<p class="gcm-folio-headline gcm-folio-reveal"><?php echo esc_html( $profile['headline'] ?? '' ); ?></p>
				<p class="gcm-folio-intro gcm-folio-reveal"><?php echo esc_html( $profile['intro'] ?? '' ); ?></p>
				<div class="gcm-folio-actions gcm-folio-reveal">
					<?php if ( ! empty( $profile['cta_label'] ) && $cta_url ) : ?>
						<a class="gcm-folio-btn gcm-folio-btn--primary" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $profile['cta_label'] ); ?></a>
					<?php endif; ?>
					<a class="gcm-folio-btn gcm-folio-btn--ghost" href="#gcm-folio-work"><?php esc_html_e( 'View projects', 'giga-class-market' ); ?></a>
				</div>
				<ul class="gcm-folio-meta gcm-folio-reveal">
					<?php if ( ! empty( $profile['location'] ) ) : ?>
						<li><span>LOC</span><?php echo esc_html( $profile['location'] ); ?></li>
					<?php endif; ?>
					<?php if ( ! empty( $profile['email'] ) ) : ?>
						<li><span>MAIL</span><a href="mailto:<?php echo esc_attr( $profile['email'] ); ?>"><?php echo esc_html( $profile['email'] ); ?></a></li>
					<?php endif; ?>
				</ul>
			</div>
			<div class="gcm-folio-hero__visual gcm-folio-reveal">
				<div class="gcm-folio-portrait">
					<?php if ( $photo_url ) : ?>
						<img src="<?php echo esc_url( $photo_url ); ?>" alt="<?php echo esc_attr( $profile['name'] ?? 'Navyan Baig' ); ?>" width="640" height="800" loading="eager" decoding="async">
					<?php endif; ?>
					<div class="gcm-folio-portrait__scan" aria-hidden="true"></div>
					<div class="gcm-folio-portrait__frame" aria-hidden="true"></div>
				</div>
				<div class="gcm-folio-stats">
					<div><strong><?php echo esc_html( $profile['stat_1_value'] ?? '' ); ?></strong><span><?php echo esc_html( $profile['stat_1_label'] ?? '' ); ?></span></div>
					<div><strong><?php echo esc_html( $profile['stat_2_value'] ?? '' ); ?></strong><span><?php echo esc_html( $profile['stat_2_label'] ?? '' ); ?></span></div>
					<div><strong><?php echo esc_html( $profile['stat_3_value'] ?? '' ); ?></strong><span><?php echo esc_html( $profile['stat_3_label'] ?? '' ); ?></span></div>
				</div>
			</div>
		</div>
	</section>

	<section class="gcm-folio-about gcm-folio-section">
		<div class="gcm-container">
			<header class="gcm-folio-section__head gcm-folio-reveal">
				<p class="gcm-folio-eyebrow"><?php esc_html_e( 'PROFILE', 'giga-class-market' ); ?></p>
				<h2><?php esc_html_e( 'About the operator', 'giga-class-market' ); ?></h2>
			</header>
			<p class="gcm-folio-about__text gcm-folio-reveal"><?php echo esc_html( $profile['bio'] ?? '' ); ?></p>
			<?php if ( ! empty( $profile['github_url'] ) || ! empty( $profile['linkedin_url'] ) ) : ?>
				<div class="gcm-folio-social gcm-folio-reveal">
					<?php if ( ! empty( $profile['github_url'] ) ) : ?>
						<a href="<?php echo esc_url( $profile['github_url'] ); ?>" target="_blank" rel="noopener noreferrer">GitHub</a>
					<?php endif; ?>
					<?php if ( ! empty( $profile['linkedin_url'] ) ) : ?>
						<a href="<?php echo esc_url( $profile['linkedin_url'] ); ?>" target="_blank" rel="noopener noreferrer">LinkedIn</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<section class="gcm-folio-skills gcm-folio-section" id="gcm-folio-skills">
		<div class="gcm-container">
			<header class="gcm-folio-section__head gcm-folio-reveal">
				<p class="gcm-folio-eyebrow"><?php esc_html_e( 'CAPABILITIES', 'giga-class-market' ); ?></p>
				<h2><?php esc_html_e( 'Cyber · Network · Web · Motion', 'giga-class-market' ); ?></h2>
			</header>
			<div class="gcm-folio-skills__grid">
				<?php foreach ( $skill_blocks as $key => $block ) : ?>
					<article class="gcm-folio-skill gcm-folio-reveal" data-skill="<?php echo esc_attr( $key ); ?>">
						<div class="gcm-folio-skill__top">
							<span class="gcm-folio-skill__code"><?php echo esc_html( $block['code'] ); ?></span>
							<h3><?php echo esc_html( $block['label'] ); ?></h3>
						</div>
						<ul>
							<?php foreach ( $block['items'] as $item ) : ?>
								<li><?php echo esc_html( $item ); ?></li>
							<?php endforeach; ?>
						</ul>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="gcm-folio-work gcm-folio-section" id="gcm-folio-work">
		<div class="gcm-container">
			<header class="gcm-folio-section__head gcm-folio-reveal">
				<p class="gcm-folio-eyebrow"><?php esc_html_e( 'SELECTED WORK', 'giga-class-market' ); ?></p>
				<h2><?php esc_html_e( 'Projects & labs', 'giga-class-market' ); ?></h2>
			</header>

			<div class="gcm-folio-filters gcm-folio-reveal" role="tablist" aria-label="<?php esc_attr_e( 'Filter projects', 'giga-class-market' ); ?>">
				<button type="button" class="is-active" data-filter="all" role="tab" aria-selected="true"><?php esc_html_e( 'All', 'giga-class-market' ); ?></button>
				<?php foreach ( $cats as $slug => $label ) : ?>
					<button type="button" data-filter="<?php echo esc_attr( $slug ); ?>" role="tab" aria-selected="false"><?php echo esc_html( $label ); ?></button>
				<?php endforeach; ?>
			</div>

			<div class="gcm-folio-projects">
				<?php if ( empty( $projects ) ) : ?>
					<p class="gcm-folio-empty gcm-folio-reveal"><?php esc_html_e( 'Projects will appear here once added in Giga Class Market → Portfolio.', 'giga-class-market' ); ?></p>
				<?php else : ?>
					<?php foreach ( $projects as $index => $project ) : ?>
						<article
							class="gcm-folio-card gcm-folio-reveal<?php echo ! empty( $project['featured'] ) ? ' is-featured' : ''; ?>"
							data-category="<?php echo esc_attr( $project['category'] ); ?>"
							style="--gcm-folio-i: <?php echo esc_attr( (string) $index ); ?>"
						>
							<?php if ( ! empty( $project['image'] ) ) : ?>
								<div class="gcm-folio-card__media">
									<img src="<?php echo esc_url( $project['image'] ); ?>" alt="<?php echo esc_attr( $project['title'] ); ?>" loading="lazy" decoding="async">
								</div>
							<?php else : ?>
								<div class="gcm-folio-card__media gcm-folio-card__media--empty" aria-hidden="true">
									<span><?php echo esc_html( strtoupper( $project['category'] ) ); ?></span>
								</div>
							<?php endif; ?>
							<div class="gcm-folio-card__body">
								<div class="gcm-folio-card__meta">
									<span class="gcm-folio-chip"><?php echo esc_html( $project['category_label'] ); ?></span>
									<?php if ( ! empty( $project['year'] ) ) : ?>
										<span class="gcm-folio-year"><?php echo esc_html( $project['year'] ); ?></span>
									<?php endif; ?>
								</div>
								<h3><?php echo esc_html( $project['title'] ); ?></h3>
								<p><?php echo esc_html( $project['excerpt'] ); ?></p>
								<?php if ( ! empty( $project['tech'] ) ) : ?>
									<ul class="gcm-folio-tech">
										<?php foreach ( $project['tech'] as $tech ) : ?>
											<li><?php echo esc_html( $tech ); ?></li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
								<?php if ( ! empty( $project['project_url'] ) ) : ?>
									<a class="gcm-folio-card__link" href="<?php echo esc_url( $project['project_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open project', 'giga-class-market' ); ?></a>
								<?php endif; ?>
							</div>
						</article>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<section class="gcm-folio-cta gcm-folio-section">
		<div class="gcm-container gcm-folio-cta__inner gcm-folio-reveal">
			<p class="gcm-folio-eyebrow"><?php esc_html_e( 'NEXT SIGNAL', 'giga-class-market' ); ?></p>
			<h2><?php echo esc_html( sprintf( __( 'Work with %s', 'giga-class-market' ), $profile['name'] ?? 'Navyan Baig' ) ); ?></h2>
			<p><?php echo esc_html( $profile['tagline'] ?? '' ); ?></p>
			<?php if ( ! empty( $profile['cta_label'] ) && $cta_url ) : ?>
				<a class="gcm-folio-btn gcm-folio-btn--primary" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $profile['cta_label'] ); ?></a>
			<?php endif; ?>
		</div>
	</section>
</div>
<?php
get_footer();
