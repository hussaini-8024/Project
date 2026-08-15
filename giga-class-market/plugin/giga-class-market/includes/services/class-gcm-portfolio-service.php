<?php
/**
 * Portfolio profile helpers and seed data for Navyan Baig.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Portfolio settings, categories, and project helpers.
 */
class GCM_Portfolio_Service {

	/**
	 * Project categories shown on the portfolio page.
	 *
	 * @return array<string,string>
	 */
	public static function categories() {
		return array(
			'cyber'      => __( 'Cyber Security', 'giga-class-market' ),
			'networking' => __( 'Networking', 'giga-class-market' ),
			'web'        => __( 'Web Development', 'giga-class-market' ),
			'animation'  => __( 'Animation', 'giga-class-market' ),
		);
	}

	/**
	 * Default editable profile fields.
	 *
	 * @return array
	 */
	public static function profile_defaults() {
		return array(
			'enabled'          => 1,
			'name'             => 'Navyan Baig',
			'tagline'          => 'Cyber Security Student · Builder · Problem Solver',
			'role'             => 'Cyber Security Student & Web Developer',
			'eyebrow'          => 'PORTFOLIO // NAVYAN.BAIG',
			'headline'         => 'Securing systems. Shipping clean web experiences.',
			'intro'            => 'I am Navyan Baig — a cyber security student focused on defensive security, networking fundamentals, and modern web development. I build practical projects that blend security thinking with polished product craft.',
			'bio'              => 'My work spans vulnerability awareness, network labs, full-stack web builds, and motion-led interfaces. I care about clarity, reliability, and interfaces that feel alive without noise.',
			'photo_id'         => 0,
			'location'         => 'Pakistan',
			'email'            => 'Official@gigaclassmarket.com',
			'cta_label'        => 'Start a project',
			'cta_url'          => '/contact/',
			'github_url'       => '',
			'linkedin_url'     => '',
			'status_text'      => 'Open to internships · labs · freelance builds',
			'skills_cyber'     => "Threat modeling\nOWASP awareness\nLinux hardening basics\nSecure coding habits\nIncident response fundamentals",
			'skills_networking'=> "TCP/IP fundamentals\nSubnetting & routing basics\nWireshark analysis\nFirewall concepts\nLAN/WAN troubleshooting",
			'skills_web'       => "WordPress & PHP\nHTML/CSS/JS\nREST APIs\nResponsive UI systems\nLMS & marketplace builds",
			'skills_animation' => "Scroll reveals\nMicro-interactions\nHero motion systems\nCSS/JS timelines\nInterface storytelling",
			'stat_1_label'     => 'Focus areas',
			'stat_1_value'     => '04',
			'stat_2_label'     => 'Build mindset',
			'stat_2_value'     => 'Secure-first',
			'stat_3_label'     => 'Stack energy',
			'stat_3_value'     => 'Web + Labs',
		);
	}

	/**
	 * Get merged portfolio profile.
	 *
	 * @return array
	 */
	public static function get_profile() {
		$settings = GCM_Settings_Service::get_settings();
		$profile  = isset( $settings['portfolio'] ) && is_array( $settings['portfolio'] ) ? $settings['portfolio'] : array();
		return wp_parse_args( $profile, self::profile_defaults() );
	}

	/**
	 * Sanitize portfolio profile settings.
	 *
	 * @param array $raw Raw settings.
	 * @return array
	 */
	public static function sanitize_profile( $raw ) {
		$defaults = self::profile_defaults();
		$raw      = is_array( $raw ) ? $raw : array();

		return array(
			'enabled'           => ! empty( $raw['enabled'] ) ? 1 : 0,
			'name'              => sanitize_text_field( $raw['name'] ?? $defaults['name'] ),
			'tagline'           => sanitize_text_field( $raw['tagline'] ?? $defaults['tagline'] ),
			'role'              => sanitize_text_field( $raw['role'] ?? $defaults['role'] ),
			'eyebrow'           => sanitize_text_field( $raw['eyebrow'] ?? $defaults['eyebrow'] ),
			'headline'          => sanitize_text_field( $raw['headline'] ?? $defaults['headline'] ),
			'intro'             => sanitize_textarea_field( $raw['intro'] ?? $defaults['intro'] ),
			'bio'               => sanitize_textarea_field( $raw['bio'] ?? $defaults['bio'] ),
			'photo_id'          => absint( $raw['photo_id'] ?? 0 ),
			'location'          => sanitize_text_field( $raw['location'] ?? $defaults['location'] ),
			'email'             => sanitize_email( $raw['email'] ?? $defaults['email'] ),
			'cta_label'         => sanitize_text_field( $raw['cta_label'] ?? $defaults['cta_label'] ),
			'cta_url'           => esc_url_raw( $raw['cta_url'] ?? $defaults['cta_url'] ),
			'github_url'        => esc_url_raw( $raw['github_url'] ?? '' ),
			'linkedin_url'      => esc_url_raw( $raw['linkedin_url'] ?? '' ),
			'status_text'       => sanitize_text_field( $raw['status_text'] ?? $defaults['status_text'] ),
			'skills_cyber'      => sanitize_textarea_field( $raw['skills_cyber'] ?? $defaults['skills_cyber'] ),
			'skills_networking' => sanitize_textarea_field( $raw['skills_networking'] ?? $defaults['skills_networking'] ),
			'skills_web'        => sanitize_textarea_field( $raw['skills_web'] ?? $defaults['skills_web'] ),
			'skills_animation'  => sanitize_textarea_field( $raw['skills_animation'] ?? $defaults['skills_animation'] ),
			'stat_1_label'      => sanitize_text_field( $raw['stat_1_label'] ?? $defaults['stat_1_label'] ),
			'stat_1_value'      => sanitize_text_field( $raw['stat_1_value'] ?? $defaults['stat_1_value'] ),
			'stat_2_label'      => sanitize_text_field( $raw['stat_2_label'] ?? $defaults['stat_2_label'] ),
			'stat_2_value'      => sanitize_text_field( $raw['stat_2_value'] ?? $defaults['stat_2_value'] ),
			'stat_3_label'      => sanitize_text_field( $raw['stat_3_label'] ?? $defaults['stat_3_label'] ),
			'stat_3_value'      => sanitize_text_field( $raw['stat_3_value'] ?? $defaults['stat_3_value'] ),
		);
	}

	/**
	 * Lines helper for skill textareas.
	 *
	 * @param string $text Raw textarea.
	 * @return array
	 */
	public static function lines( $text ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $text );
		$lines = array_map( 'trim', $lines );
		return array_values( array_filter( $lines ) );
	}

	/**
	 * Query published portfolio projects.
	 *
	 * @param string $category Optional category slug.
	 * @return array
	 */
	public static function get_projects( $category = '' ) {
		$args = array(
			'post_type'      => 'gcm_portfolio_item',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => array(
				'menu_order' => 'ASC',
				'date'       => 'DESC',
			),
		);

		if ( $category && isset( self::categories()[ $category ] ) ) {
			$args['meta_key']   = '_gcm_portfolio_category';
			$args['meta_value'] = $category;
		}

		$posts = get_posts( $args );
		$items = array();
		foreach ( $posts as $post ) {
			$items[] = self::format_project( $post );
		}
		return $items;
	}

	/**
	 * Format one project.
	 *
	 * @param WP_Post $post Post.
	 * @return array
	 */
	public static function format_project( $post ) {
		$category = (string) get_post_meta( $post->ID, '_gcm_portfolio_category', true );
		if ( ! isset( self::categories()[ $category ] ) ) {
			$category = 'web';
		}

		$tech = (string) get_post_meta( $post->ID, '_gcm_portfolio_tech', true );
		$tech = array_values(
			array_filter(
				array_map(
					'trim',
					preg_split( '/[,|\n]/', $tech )
				)
			)
		);

		return array(
			'id'          => (int) $post->ID,
			'title'       => get_the_title( $post ),
			'excerpt'     => $post->post_excerpt ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( $post->post_content ), 28 ),
			'content'     => apply_filters( 'the_content', $post->post_content ),
			'category'    => $category,
			'category_label' => self::categories()[ $category ],
			'tech'        => $tech,
			'project_url' => (string) get_post_meta( $post->ID, '_gcm_portfolio_url', true ),
			'year'        => (string) get_post_meta( $post->ID, '_gcm_portfolio_year', true ),
			'featured'    => (bool) get_post_meta( $post->ID, '_gcm_portfolio_featured', true ),
			'image'       => get_the_post_thumbnail_url( $post, 'large' ) ?: '',
		);
	}

	/**
	 * Seed default projects once.
	 *
	 * @return void
	 */
	public static function maybe_seed_projects() {
		if ( get_option( 'gcm_portfolio_seeded_v1' ) ) {
			return;
		}
		if ( ! post_type_exists( 'gcm_portfolio_item' ) ) {
			return;
		}

		$existing = get_posts(
			array(
				'post_type'      => 'gcm_portfolio_item',
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		if ( ! empty( $existing ) ) {
			update_option( 'gcm_portfolio_seeded_v1', 1, false );
			return;
		}

		$seeds = array(
			array(
				'title'    => 'Home Lab Threat Surface Map',
				'excerpt'  => 'Mapped attack surfaces across a personal cyber lab and documented hardening priorities.',
				'content'  => 'Built a practical home-lab inventory covering exposed services, weak defaults, and remediation notes for a cyber security learning path.',
				'category' => 'cyber',
				'tech'     => 'Linux, Nmap, OWASP, Documentation',
				'year'     => '2026',
				'featured' => 1,
			),
			array(
				'title'    => 'Secure Login Flow Review',
				'excerpt'  => 'Reviewed authentication UX and session handling patterns for student platforms.',
				'content'  => 'Audited login, session, and redirect flows with a security-first checklist for LMS-style products.',
				'category' => 'cyber',
				'tech'     => 'WordPress, PHP, Session Security',
				'year'     => '2026',
				'featured' => 0,
			),
			array(
				'title'    => 'Packet Path Visual Lab',
				'excerpt'  => 'Networking lab that visualizes routing decisions and common failure points.',
				'content'  => 'Hands-on networking practice covering subnetting, packet capture notes, and troubleshooting playbooks.',
				'category' => 'networking',
				'tech'     => 'TCP/IP, Wireshark, Routing',
				'year'     => '2025',
				'featured' => 1,
			),
			array(
				'title'    => 'LAN Hardening Checklist',
				'excerpt'  => 'A practical networking checklist for small-office and home networks.',
				'content'  => 'Created a repeatable networking hygiene checklist for segmentation, DNS, and firewall defaults.',
				'category' => 'networking',
				'tech'     => 'Firewall, DNS, LAN Design',
				'year'     => '2025',
				'featured' => 0,
			),
			array(
				'title'    => 'Giga Class Market Platform',
				'excerpt'  => 'Marketplace + LMS web platform with enrollment, dashboards, and certificates.',
				'content'  => 'Contributed to a premium WordPress learning marketplace covering courses, payments, student dashboards, and certificate flows.',
				'category' => 'web',
				'tech'     => 'WordPress, PHP, CSS, JS',
				'year'     => '2026',
				'featured' => 1,
				'url'      => home_url( '/' ),
			),
			array(
				'title'    => 'Admin-Managed Content Systems',
				'excerpt'  => 'Built editable site sections so admins can update media and copy without code.',
				'content'  => 'Designed admin-friendly content systems for about, team, SEO, and portfolio management.',
				'category' => 'web',
				'tech'     => 'PHP, Media Library, UX',
				'year'     => '2026',
				'featured' => 0,
			),
			array(
				'title'    => 'Cyber Portfolio Motion System',
				'excerpt'  => 'Scroll reveals, terminal accents, and filter transitions for a cyber portfolio.',
				'content'  => 'Crafted a motion language for cybersecurity storytelling — intentional reveals, staggered cards, and interactive filters.',
				'category' => 'animation',
				'tech'     => 'CSS, JS, Motion Design',
				'year'     => '2026',
				'featured' => 1,
			),
			array(
				'title'    => 'Interface Micro-Interactions',
				'excerpt'  => 'Subtle hover and focus states that keep technical UIs feeling alive.',
				'content'  => 'Designed micro-interactions for cards, CTAs, and status indicators without noisy effects.',
				'category' => 'animation',
				'tech'     => 'CSS Transitions, JS Observers',
				'year'     => '2025',
				'featured' => 0,
			),
		);

		foreach ( $seeds as $index => $seed ) {
			$post_id = wp_insert_post(
				array(
					'post_type'    => 'gcm_portfolio_item',
					'post_status'  => 'publish',
					'post_title'   => $seed['title'],
					'post_excerpt' => $seed['excerpt'],
					'post_content' => $seed['content'],
					'menu_order'   => $index + 1,
				),
				true
			);
			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}
			update_post_meta( $post_id, '_gcm_portfolio_category', $seed['category'] );
			update_post_meta( $post_id, '_gcm_portfolio_tech', $seed['tech'] );
			update_post_meta( $post_id, '_gcm_portfolio_year', $seed['year'] );
			update_post_meta( $post_id, '_gcm_portfolio_featured', ! empty( $seed['featured'] ) ? 1 : 0 );
			if ( ! empty( $seed['url'] ) ) {
				update_post_meta( $post_id, '_gcm_portfolio_url', esc_url_raw( $seed['url'] ) );
			}
		}

		update_option( 'gcm_portfolio_seeded_v1', 1, false );
	}

	/**
	 * Ensure the Portfolio page exists with the GCM template.
	 *
	 * @return void
	 */
	public static function ensure_portfolio_page() {
		$page = get_page_by_path( 'portfolio' );
		if ( ! $page ) {
			$page_id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => __( 'Portfolio', 'giga-class-market' ),
					'post_name'    => 'portfolio',
					'post_content' => '',
				),
				true
			);
			if ( ! is_wp_error( $page_id ) && $page_id ) {
				update_post_meta( $page_id, '_wp_page_template', 'page-templates/template-portfolio.php' );
			}
			return;
		}

		$template = get_post_meta( $page->ID, '_wp_page_template', true );
		if ( 'page-templates/template-portfolio.php' !== $template ) {
			update_post_meta( $page->ID, '_wp_page_template', 'page-templates/template-portfolio.php' );
		}
	}
}
