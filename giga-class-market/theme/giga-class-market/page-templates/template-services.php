<?php
/**
 * Template Name: GCM Services
 *
 * @package GigaClassMarket
 */

get_header();

$contact_url = home_url( '/contact/' );

$services = array(
	array(
		'slug'  => 'web-development',
		'code'  => '01',
		'title' => __( 'Web Development', 'giga-class-market' ),
		'lead'  => __( 'Custom websites, WordPress builds, and learning platforms designed for speed, clarity, and conversions.', 'giga-class-market' ),
		'points'=> array(
			__( 'Business & course marketplace websites', 'giga-class-market' ),
			__( 'WordPress theme & plugin development', 'giga-class-market' ),
			__( 'Responsive UI and performance tuning', 'giga-class-market' ),
		),
	),
	array(
		'slug'  => 'digital-marketing',
		'code'  => '02',
		'title' => __( 'Digital Marketing', 'giga-class-market' ),
		'lead'  => __( 'Practical campaigns that bring the right audience to your offers — without noisy tactics.', 'giga-class-market' ),
		'points'=> array(
			__( 'Campaign planning & funnel setup', 'giga-class-market' ),
			__( 'Landing page messaging', 'giga-class-market' ),
			__( 'Lead generation for courses & services', 'giga-class-market' ),
		),
	),
	array(
		'slug'  => 'seo',
		'code'  => '03',
		'title' => __( 'SEO', 'giga-class-market' ),
		'lead'  => __( 'Search-ready pages, technical SEO, and content structure so people can find you on Google.', 'giga-class-market' ),
		'points'=> array(
			__( 'On-page SEO & meta strategy', 'giga-class-market' ),
			__( 'Technical SEO improvements', 'giga-class-market' ),
			__( 'Local & course-page ranking support', 'giga-class-market' ),
		),
	),
	array(
		'slug'  => 'social-media-marketing',
		'code'  => '04',
		'title' => __( 'Social Media Marketing', 'giga-class-market' ),
		'lead'  => __( 'Consistent presence on Instagram and other channels with clear offers and call-to-action links.', 'giga-class-market' ),
		'points'=> array(
			__( 'Profile & content direction', 'giga-class-market' ),
			__( 'Bio / landing-page linking', 'giga-class-market' ),
			__( 'Organic growth guidance', 'giga-class-market' ),
		),
	),
	array(
		'slug'  => 'branding-ui',
		'code'  => '05',
		'title' => __( 'Branding & UI Design', 'giga-class-market' ),
		'lead'  => __( 'Visual identity and interface systems that make your brand feel premium and trustworthy.', 'giga-class-market' ),
		'points'=> array(
			__( 'Logo & brand style direction', 'giga-class-market' ),
			__( 'UI layouts for web products', 'giga-class-market' ),
			__( 'Design systems for scalable pages', 'giga-class-market' ),
		),
	),
	array(
		'slug'  => 'lms-setup',
		'code'  => '06',
		'title' => __( 'LMS & E-Learning Setup', 'giga-class-market' ),
		'lead'  => __( 'Course platforms, student dashboards, and enrollment flows tailored for training businesses.', 'giga-class-market' ),
		'points'=> array(
			__( 'Course marketplace setup', 'giga-class-market' ),
			__( 'Student / teacher workflows', 'giga-class-market' ),
			__( 'Certificates & live-class systems', 'giga-class-market' ),
		),
	),
);
?>
<section class="gcm-page-hero">
	<div class="gcm-container">
		<p class="gcm-eyebrow"><?php esc_html_e( 'Services', 'giga-class-market' ); ?></p>
		<h1><?php esc_html_e( 'Professional services for growing brands', 'giga-class-market' ); ?></h1>
		<p><?php esc_html_e( 'Beyond courses, Giga Class Market helps businesses and creators with web development, digital marketing, SEO, and related digital services.', 'giga-class-market' ); ?></p>
		<p class="gcm-services-hero-actions">
			<a class="gcm-button gcm-button--gold" href="<?php echo esc_url( $contact_url ); ?>"><?php esc_html_e( 'Contact Us', 'giga-class-market' ); ?></a>
		</p>
	</div>
</section>

<section class="gcm-section">
	<div class="gcm-container">
		<header class="gcm-section__heading gcm-animate">
			<p class="gcm-eyebrow"><?php esc_html_e( 'What we offer', 'giga-class-market' ); ?></p>
			<h2><?php esc_html_e( 'Choose a service. Tell us what you need.', 'giga-class-market' ); ?></h2>
			<p><?php esc_html_e( 'Each Contact Us button opens the contact form so you can send your project details directly to our team.', 'giga-class-market' ); ?></p>
		</header>

		<div class="gcm-services-grid">
			<?php foreach ( $services as $service ) : ?>
				<?php
				$service_contact = add_query_arg(
					array(
						'service' => $service['slug'],
						'subject' => sprintf( __( 'Service inquiry: %s', 'giga-class-market' ), $service['title'] ),
					),
					$contact_url
				);
				?>
				<article class="gcm-service-card gcm-animate">
					<span class="gcm-service-card__code" aria-hidden="true"><?php echo esc_html( $service['code'] ); ?></span>
					<h3><?php echo esc_html( $service['title'] ); ?></h3>
					<p><?php echo esc_html( $service['lead'] ); ?></p>
					<ul>
						<?php foreach ( $service['points'] as $point ) : ?>
							<li><?php echo esc_html( $point ); ?></li>
						<?php endforeach; ?>
					</ul>
					<a class="gcm-button gcm-button--outline" href="<?php echo esc_url( $service_contact ); ?>">
						<?php esc_html_e( 'Contact Us', 'giga-class-market' ); ?>
					</a>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="gcm-section gcm-section--surface">
	<div class="gcm-container gcm-services-cta gcm-animate">
		<p class="gcm-eyebrow"><?php esc_html_e( 'Start a project', 'giga-class-market' ); ?></p>
		<h2><?php esc_html_e( 'Ready to discuss your idea?', 'giga-class-market' ); ?></h2>
		<p><?php esc_html_e( 'Tell us about your website, marketing, SEO, or learning-platform goals. We will reply with next steps.', 'giga-class-market' ); ?></p>
		<a class="gcm-button gcm-button--gold" href="<?php echo esc_url( $contact_url ); ?>"><?php esc_html_e( 'Contact Us', 'giga-class-market' ); ?></a>
	</div>
</section>
<?php
get_footer();
