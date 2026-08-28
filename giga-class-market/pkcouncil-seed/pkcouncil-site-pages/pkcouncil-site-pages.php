<?php
/**
 * Plugin Name: PK Council Site Pages
 * Description: Creates Home, About, Services, Contact, Privacy, Terms, and Refund pages modeled on Giga Class Market structure, branded for Pakistan Council Organization.
 * Version:     1.0.0
 * Author:      Pakistan Council
 * Text Domain: pkcouncil-site-pages
 *
 * @package PKCouncilSitePages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PKC_PAGES_VERSION', '1.0.0' );
define( 'PKC_PAGES_OPTION', 'pkc_site_pages_seed_rev' );
define( 'PKC_PAGES_REV', '1.0.0-a' );

/**
 * Run seeder on activation and once on admin load when revision is behind.
 */
register_activation_hook( __FILE__, 'pkc_pages_activate' );
add_action( 'admin_init', 'pkc_pages_maybe_seed' );

/**
 * Activation entry.
 *
 * @return void
 */
function pkc_pages_activate() {
	pkc_pages_seed_all( true );
	flush_rewrite_rules();
}

/**
 * Versioned seed on admin.
 *
 * @return void
 */
function pkc_pages_maybe_seed() {
	if ( get_option( PKC_PAGES_OPTION ) === PKC_PAGES_REV ) {
		return;
	}
	pkc_pages_seed_all( true );
}

/**
 * Create/update all public pages and set Home as front page.
 *
 * @param bool $force Force content refresh.
 * @return void
 */
function pkc_pages_seed_all( $force = false ) {
	$map = array();
	foreach ( pkc_pages_pack() as $pack ) {
		$id = pkc_pages_upsert( $pack, $force );
		if ( $id ) {
			$map[ $pack['slug'] ] = $id;
		}
	}

	if ( ! empty( $map['home'] ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', (int) $map['home'] );
	}

	update_option( 'blogname', 'Pakistan Council Organization' );
	update_option( 'blogdescription', 'Professional education, training, and digital learning for Pakistan' );
	update_option( PKC_PAGES_OPTION, PKC_PAGES_REV, false );
}

/**
 * Insert or update one page.
 *
 * @param array $pack Page pack.
 * @param bool  $force Force update.
 * @return int
 */
function pkc_pages_upsert( $pack, $force ) {
	$existing = get_page_by_path( $pack['slug'] );
	$postarr  = array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => $pack['title'],
		'post_name'    => $pack['slug'],
		'post_content' => $pack['content'],
		'post_excerpt' => $pack['excerpt'],
	);

	if ( $existing ) {
		if ( ! $force && get_post_meta( $existing->ID, '_pkc_seeded', true ) ) {
			return (int) $existing->ID;
		}
		$postarr['ID'] = (int) $existing->ID;
		$post_id       = wp_update_post( $postarr, true );
	} else {
		$post_id = wp_insert_post( $postarr, true );
	}

	if ( is_wp_error( $post_id ) || ! $post_id ) {
		return 0;
	}

	update_post_meta( (int) $post_id, '_pkc_seeded', 1 );
	update_post_meta( (int) $post_id, '_pkc_seed_rev', PKC_PAGES_REV );

	if ( ! empty( $pack['seo_title'] ) ) {
		update_post_meta( (int) $post_id, '_pkc_seo_title', $pack['seo_title'] );
	}
	if ( ! empty( $pack['seo_description'] ) ) {
		update_post_meta( (int) $post_id, '_pkc_seo_description', $pack['seo_description'] );
	}

	return (int) $post_id;
}

/**
 * Page content packs (GCM structure → Pakistan Council brand).
 *
 * @return array
 */
function pkc_pages_pack() {
	$brand = 'Pakistan Council Organization';
	$home  = home_url( '/' );
	$about = home_url( '/about/' );
	$svc   = home_url( '/services/' );
	$con   = home_url( '/contact/' );

	return array(
		array(
			'slug'            => 'home',
			'title'           => 'Home',
			'excerpt'         => 'Pakistan Council Organization — education, training, and digital learning.',
			'seo_title'       => $brand . ' | Education, Training & Digital Learning',
			'seo_description' => 'Pakistan Council Organization provides structured education, professional training, and digital learning paths for ambitious students across Pakistan.',
			'content'         => pkc_html(
				array(
					'<!-- wp:heading {"level":1} -->',
					'<h1>Premium education for ambitious learners</h1>',
					'<!-- /wp:heading -->',
					'<!-- wp:paragraph -->',
					'<p><strong>Pakistan Council Organization</strong> helps students and professionals build practical skills through clear training paths, trusted guidance, and modern digital learning.</p>',
					'<!-- /wp:paragraph -->',
					'<!-- wp:heading {"level":2} -->',
					'<h2>What we offer</h2>',
					'<!-- /wp:heading -->',
					'<!-- wp:list -->',
					'<ul><li><strong>FPSC preparation</strong> — structured study guidance for competitive exams in Pakistan</li><li><strong>IT &amp; networking training</strong> — practical skills for CCNA-oriented careers</li><li><strong>Cybersecurity foundations</strong> — ethical hacking and security awareness paths</li><li><strong>Digital services</strong> — web, SEO, and marketing support for growing brands</li></ul>',
					'<!-- /wp:list -->',
					'<!-- wp:paragraph -->',
					'<p><a href="' . esc_url( $about ) . '">About us</a> · <a href="' . esc_url( $svc ) . '">Services</a> · <a href="' . esc_url( $con ) . '">Contact</a></p>',
					'<!-- /wp:paragraph -->',
				)
			),
		),
		array(
			'slug'            => 'about',
			'title'           => 'About',
			'excerpt'         => 'Learn about Pakistan Council Organization — mission, vision, and learning philosophy.',
			'seo_title'       => 'About Us | ' . $brand,
			'seo_description' => 'About Pakistan Council Organization — premium education, practical training, and a trusted path for ambitious learners in Pakistan.',
			'content'         => pkc_html(
				array(
					'<h1>Premium education for ambitious learners</h1>',
					'<p>We built a modern learning experience that feels <strong>trustworthy</strong>, polished, and ready for <strong>real careers</strong>.</p>',
					'<h2>Mission</h2>',
					'<p>Make premium education clear and attainable. ' . esc_html( $brand ) . ' helps students build practical skills through structured learning, verified enrollment workflows, and consistent progress.</p>',
					'<h2>Vision</h2>',
					'<p>A trusted organization for technical and professional growth — where every course path, payment step, and student journey is handled with professionalism and transparency.</p>',
					'<h2>What makes us different</h2>',
					'<p>Unlike generic course pages, our approach connects browsing, enrollment, support, and learning progress into one connected workflow.</p>',
					'<h2>Learning philosophy</h2>',
					'<p><strong>Practice first. Progress always.</strong> We focus on practical skills, industry knowledge, and measurable completion so learners always know where they stand.</p>',
				)
			),
		),
		array(
			'slug'            => 'services',
			'title'           => 'Services',
			'excerpt'         => 'Professional digital services — web development, marketing, SEO, and LMS support.',
			'seo_title'       => 'Digital Services | Web, Marketing & SEO | ' . $brand,
			'seo_description' => 'Hire Pakistan Council Organization for web development, digital marketing, SEO, branding, and learning-platform support.',
			'content'         => pkc_html(
				array(
					'<h1>Professional services for growing brands</h1>',
					'<p>Beyond training, ' . esc_html( $brand ) . ' helps businesses and creators with web development, digital marketing, SEO, and related digital services.</p>',
					'<h2>What we offer</h2>',
					'<ul><li>Website design &amp; development</li><li>Digital marketing &amp; social media</li><li>SEO &amp; content strategy</li><li>Branding &amp; creative assets</li><li>LMS / training portal setup support</li></ul>',
					'<h2>How to start</h2>',
					'<p>Tell us your goal and timeline. We will recommend a clear next step. <a href="' . esc_url( $con ) . '">Contact us</a>.</p>',
				)
			),
		),
		array(
			'slug'            => 'contact',
			'title'           => 'Contact',
			'excerpt'         => 'Contact Pakistan Council Organization for training, enrollment, and services.',
			'seo_title'       => 'Contact Us | ' . $brand,
			'seo_description' => 'Contact Pakistan Council Organization for course support, enrollment help, professional services, and partnership questions.',
			'content'         => pkc_html(
				array(
					'<h1>Speak with Pakistan Council Organization</h1>',
					'<p>Questions about training, enrollment, partnerships, or professional services? Send a message and our team will respond.</p>',
					'<h2>Company information</h2>',
					'<ul><li><strong>Organization:</strong> Pakistan Council Organization</li><li><strong>Website:</strong> <a href="' . esc_url( $home ) . '">pkcouncil.org</a></li></ul>',
					'<p><em>Update phone, WhatsApp, and email in this page after publish (WP Admin → Pages → Contact).</em></p>',
				)
			),
		),
		array(
			'slug'            => 'privacy-policy',
			'title'           => 'Privacy Policy',
			'excerpt'         => 'Privacy practices for Pakistan Council Organization.',
			'seo_title'       => 'Privacy Policy | ' . $brand,
			'seo_description' => 'Privacy Policy for Pakistan Council Organization — data collection, payments, communications, and account practices.',
			'content'         => pkc_html(
				array(
					'<h1>Privacy Policy</h1>',
					'<p>This privacy policy explains how ' . esc_html( $brand ) . ' collects and uses information when you browse our website, enroll in training, or contact our team.</p>',
					'<h2>Information we collect</h2>',
					'<ul><li>Contact details you submit (name, email, phone)</li><li>Enrollment and payment verification details</li><li>Basic technical data (browser, device, IP) for security</li></ul>',
					'<h2>How we use information</h2>',
					'<p>We use information to provide training access, verify payments, improve services, and respond to support requests.</p>',
					'<h2>Contact</h2>',
					'<p>For privacy questions, use our <a href="' . esc_url( $con ) . '">Contact</a> page.</p>',
				)
			),
		),
		array(
			'slug'            => 'terms',
			'title'           => 'Terms and Conditions',
			'excerpt'         => 'Terms of use for Pakistan Council Organization.',
			'seo_title'       => 'Terms & Conditions | ' . $brand,
			'seo_description' => 'Terms and Conditions for Pakistan Council Organization — enrollment, acceptable use, payments, and certifications.',
			'content'         => pkc_html(
				array(
					'<h1>Terms and Conditions</h1>',
					'<p>By using ' . esc_html( $brand ) . ' websites and services, you agree to these terms.</p>',
					'<h2>Training access</h2>',
					'<p>Access is granted after successful enrollment / payment verification according to the published process.</p>',
					'<h2>Acceptable use</h2>',
					'<p>You may not share account credentials, misuse content, or attempt unauthorized access to systems.</p>',
					'<h2>Intellectual property</h2>',
					'<p>Course materials and site content remain the property of ' . esc_html( $brand ) . ' unless stated otherwise.</p>',
				)
			),
		),
		array(
			'slug'            => 'refund-policy',
			'title'           => 'Refund Policy',
			'excerpt'         => 'Refund and cancellation policy for Pakistan Council Organization.',
			'seo_title'       => 'Refund Policy | ' . $brand,
			'seo_description' => 'Refund Policy for Pakistan Council Organization — cancellation and refund timelines.',
			'content'         => pkc_html(
				array(
					'<h1>Refund Policy</h1>',
					'<p>This policy explains when refunds may apply for training enrollments with ' . esc_html( $brand ) . '.</p>',
					'<h2>Before verification</h2>',
					'<p>If a payment submission is rejected during verification, access is not granted and the case is reviewed by our team.</p>',
					'<h2>After access is granted</h2>',
					'<p>Once learning access is activated, refunds are limited and handled case-by-case according to published rules.</p>',
					'<h2>How to request</h2>',
					'<p>Contact us via the <a href="' . esc_url( $con ) . '">Contact</a> page with your enrollment details.</p>',
				)
			),
		),
	);
}

/**
 * Join HTML parts.
 *
 * @param array $parts Parts.
 * @return string
 */
function pkc_html( $parts ) {
	return implode( "\n\n", $parts );
}

/**
 * Lightweight SEO title/description for seeded pages.
 */
add_filter( 'pre_get_document_title', 'pkc_pages_document_title', 20 );
add_action( 'wp_head', 'pkc_pages_meta_description', 1 );

/**
 * Use seeded SEO title on matching pages.
 *
 * @param string $title Title.
 * @return string
 */
function pkc_pages_document_title( $title ) {
	if ( ! is_singular( 'page' ) ) {
		return $title;
	}
	$custom = (string) get_post_meta( get_the_ID(), '_pkc_seo_title', true );
	return $custom ? $custom : $title;
}

/**
 * Print meta description from seeded SEO field.
 *
 * @return void
 */
function pkc_pages_meta_description() {
	if ( ! is_singular( 'page' ) ) {
		return;
	}
	$desc = (string) get_post_meta( get_the_ID(), '_pkc_seo_description', true );
	if ( ! $desc ) {
		return;
	}
	echo '<meta name="description" content="' . esc_attr( wp_strip_all_tags( $desc ) ) . '" />' . "\n";
}
