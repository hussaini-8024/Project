<?php
/**
 * Template Name: GCM Services
 *
 * Public services page. The website contact / inquiry form lives here.
 *
 * @package GigaClassMarket
 */

get_header();

$offerings = array(
	array(
		'slug'  => 'website',
		'title' => __( 'Websites', 'giga-class-market' ),
		'text'  => __( 'Business websites for hotels, restaurants, shops, and professional offices — with WhatsApp and Google-ready pages.', 'giga-class-market' ),
	),
	array(
		'slug'  => 'lms',
		'title' => __( 'LMS & e-learning', 'giga-class-market' ),
		'text'  => __( 'Giga Class Market learning portals: courses, student login, payments, and progress tracking.', 'giga-class-market' ),
	),
	array(
		'slug'  => 'school',
		'title' => __( 'School & college portals', 'giga-class-market' ),
		'text'  => __( 'Admissions, fees, attendance, homework, and parent/student login for schools and academies.', 'giga-class-market' ),
	),
	array(
		'slug'  => 'hotel',
		'title' => __( 'Hotel & restaurant booking', 'giga-class-market' ),
		'text'  => __( 'Direct room or table booking so guests skip commission-heavy listing sites.', 'giga-class-market' ),
	),
	array(
		'slug'  => 'hospital',
		'title' => __( 'Hospital & clinic portals', 'giga-class-market' ),
		'text'  => __( 'Doctor profiles, online appointments, and a simple patient inquiry flow.', 'giga-class-market' ),
	),
	array(
		'slug'  => 'law',
		'title' => __( 'Law firm websites', 'giga-class-market' ),
		'text'  => __( 'Professional chamber sites with case inquiry forms and client contact.', 'giga-class-market' ),
	),
);
?>
<section class="gcm-page-hero">
	<div class="gcm-container">
		<p class="gcm-eyebrow"><?php esc_html_e( 'Services', 'giga-class-market' ); ?></p>
		<h1><?php esc_html_e( 'Websites, LMS, and business portals', 'giga-class-market' ); ?></h1>
		<p><?php esc_html_e( 'Giga Developers and Giga Class Market build and manage the digital side of your business. Pick a service, then send the form below.', 'giga-class-market' ); ?></p>
		<p>
			<a class="gcm-button gcm-button--gold" href="#inquiry"><?php esc_html_e( 'Request a quote', 'giga-class-market' ); ?></a>
		</p>
	</div>
</section>

<section class="gcm-section">
	<div class="gcm-container">
		<div class="gcm-section__heading gcm-animate">
			<p class="gcm-eyebrow"><?php esc_html_e( 'What we build', 'giga-class-market' ); ?></p>
			<h2><?php esc_html_e( 'Choose the work you need', 'giga-class-market' ); ?></h2>
		</div>
		<div class="gcm-service-grid">
			<?php foreach ( $offerings as $index => $offer ) : ?>
				<article class="gcm-service-card gcm-animate" style="--gcm-delay: <?php echo esc_attr( ( $index + 1 ) * 0.06 ); ?>s">
					<h3><?php echo esc_html( $offer['title'] ); ?></h3>
					<p><?php echo esc_html( $offer['text'] ); ?></p>
					<a class="gcm-service-card__link" href="<?php echo esc_url( add_query_arg( 'service', $offer['slug'], home_url( '/services/' ) ) . '#inquiry' ); ?>"><?php esc_html_e( 'Inquire about this', 'giga-class-market' ); ?></a>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="gcm-section gcm-section--surface" aria-labelledby="gcm-inquiry-heading">
	<div class="gcm-container">
		<div class="gcm-section__heading gcm-animate">
			<p class="gcm-eyebrow"><?php esc_html_e( 'Contact form', 'giga-class-market' ); ?></p>
			<h2 id="gcm-inquiry-heading"><?php esc_html_e( 'Tell us what to build', 'giga-class-market' ); ?></h2>
			<p><?php esc_html_e( 'This is the website inquiry form. No separate contact page — send your request here.', 'giga-class-market' ); ?></p>
		</div>
	</div>
	<?php get_template_part( 'template-parts/shared/contact-form' ); ?>
</section>

<?php
get_footer();
