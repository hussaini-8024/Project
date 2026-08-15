<?php
/**
 * Multi-portfolio helpers: profiles at /{slug}/ and linked projects.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Portfolio profiles + project helpers.
 */
class GCM_Portfolio_Service {

	/**
	 * Project categories.
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
	 * Profile field keys stored as post meta on gcm_portfolio.
	 *
	 * @return array<string,string>
	 */
	public static function profile_meta_map() {
		return array(
			'tagline'           => '_gcm_pf_tagline',
			'role'              => '_gcm_pf_role',
			'eyebrow'           => '_gcm_pf_eyebrow',
			'headline'          => '_gcm_pf_headline',
			'intro'             => '_gcm_pf_intro',
			'bio'               => '_gcm_pf_bio',
			'location'          => '_gcm_pf_location',
			'email'             => '_gcm_pf_email',
			'cta_label'         => '_gcm_pf_cta_label',
			'cta_url'           => '_gcm_pf_cta_url',
			'github_url'        => '_gcm_pf_github_url',
			'linkedin_url'      => '_gcm_pf_linkedin_url',
			'status_text'       => '_gcm_pf_status_text',
			'skills_cyber'      => '_gcm_pf_skills_cyber',
			'skills_networking' => '_gcm_pf_skills_networking',
			'skills_web'        => '_gcm_pf_skills_web',
			'skills_animation'  => '_gcm_pf_skills_animation',
			'stat_1_label'      => '_gcm_pf_stat_1_label',
			'stat_1_value'      => '_gcm_pf_stat_1_value',
			'stat_2_label'      => '_gcm_pf_stat_2_label',
			'stat_2_value'      => '_gcm_pf_stat_2_value',
			'stat_3_label'      => '_gcm_pf_stat_3_label',
			'stat_3_value'      => '_gcm_pf_stat_3_value',
		);
	}

	/**
	 * Defaults used when seeding / empty meta.
	 *
	 * @return array
	 */
	public static function profile_defaults() {
		return array(
			'tagline'           => 'Cyber Security Student · Builder · Problem Solver',
			'role'              => 'Cyber Security Student & Web Developer',
			'eyebrow'           => 'PORTFOLIO // NAVYAN.BAIG',
			'headline'          => 'Securing systems. Shipping clean web experiences.',
			'intro'             => 'I am Navyan Baig — a cyber security student focused on defensive security, networking fundamentals, and modern web development. I build practical projects that blend security thinking with polished product craft.',
			'bio'               => 'My work spans vulnerability awareness, network labs, full-stack web builds, and motion-led interfaces. I care about clarity, reliability, and interfaces that feel alive without noise.',
			'location'          => 'Pakistan',
			'email'             => 'Official@gigaclassmarket.com',
			'cta_label'         => 'Start a project',
			'cta_url'           => '/contact/',
			'github_url'        => '',
			'linkedin_url'      => '',
			'status_text'       => 'Open to internships · labs · freelance builds',
			'skills_cyber'      => "Threat modeling\nOWASP awareness\nLinux hardening basics\nSecure coding habits\nIncident response fundamentals",
			'skills_networking' => "TCP/IP fundamentals\nSubnetting & routing basics\nWireshark analysis\nFirewall concepts\nLAN/WAN troubleshooting",
			'skills_web'        => "WordPress & PHP\nHTML/CSS/JS\nREST APIs\nResponsive UI systems\nLMS & marketplace builds",
			'skills_animation'  => "Scroll reveals\nMicro-interactions\nHero motion systems\nCSS/JS timelines\nInterface storytelling",
			'stat_1_label'      => 'Focus areas',
			'stat_1_value'      => '04',
			'stat_2_label'      => 'Build mindset',
			'stat_2_value'      => 'Secure-first',
			'stat_3_label'      => 'Stack energy',
			'stat_3_value'      => 'Web + Labs',
		);
	}

	/**
	 * Portfolio URLs are root-level /{slug}/ via post_type_link + request mapping.
	 * No catch-all rewrite (would steal real pages like /about/).
	 *
	 * @return void
	 */
	public static function register_rewrites() {
		// Intentionally empty — see map_portfolio_request().
	}

	/**
	 * Map /{slug}/ to a portfolio when a published profile exists.
	 * Prefer real pages/posts when they collide with a portfolio slug.
	 *
	 * @param array $query_vars Query vars.
	 * @return array
	 */
	public static function map_portfolio_request( $query_vars ) {
		if ( ! empty( $query_vars['gcm_portfolio'] ) ) {
			$slug = sanitize_title( $query_vars['gcm_portfolio'] );
			if ( self::get_published_by_slug( $slug ) ) {
				return array(
					'post_type'     => 'gcm_portfolio',
					'name'          => $slug,
					'gcm_portfolio' => $slug,
				);
			}
			unset( $query_vars['gcm_portfolio'] );
			if ( empty( $query_vars['pagename'] ) && empty( $query_vars['name'] ) ) {
				$query_vars['pagename'] = $slug;
			}
			return $query_vars;
		}

		$slug = '';
		if ( ! empty( $query_vars['pagename'] ) && false === strpos( (string) $query_vars['pagename'], '/' ) ) {
			$slug = sanitize_title( $query_vars['pagename'] );
		} elseif ( ! empty( $query_vars['name'] ) && empty( $query_vars['post_type'] ) && empty( $query_vars['page_id'] ) ) {
			$slug = sanitize_title( $query_vars['name'] );
		}

		if ( '' === $slug ) {
			return $query_vars;
		}

		// Real WP pages always win over portfolio profiles.
		if ( get_page_by_path( $slug ) ) {
			return $query_vars;
		}

		if ( ! self::get_published_by_slug( $slug ) ) {
			return $query_vars;
		}

		return array(
			'post_type'     => 'gcm_portfolio',
			'name'          => $slug,
			'gcm_portfolio' => $slug,
		);
	}

	/**
	 * Published portfolio by slug.
	 *
	 * @param string $slug Slug.
	 * @return WP_Post|null
	 */
	public static function get_published_by_slug( $slug ) {
		$slug = sanitize_title( $slug );
		if ( '' === $slug ) {
			return null;
		}
		$posts = get_posts(
			array(
				'name'             => $slug,
				'post_type'        => 'gcm_portfolio',
				'post_status'      => 'publish',
				'posts_per_page'   => 1,
				'suppress_filters' => true,
			)
		);
		return $posts ? $posts[0] : null;
	}

	/**
	 * Pretty permalink: https://site.com/navyan/
	 *
	 * @param string  $permalink Permalink.
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public static function filter_portfolio_link( $permalink, $post ) {
		if ( ! ( $post instanceof WP_Post ) || 'gcm_portfolio' !== $post->post_type || empty( $post->post_name ) ) {
			return $permalink;
		}
		return home_url( user_trailingslashit( $post->post_name ) );
	}

	/**
	 * Format profile array from a portfolio post.
	 *
	 * @param WP_Post|int $post Post or ID.
	 * @return array
	 */
	public static function get_profile_from_post( $post ) {
		$post = get_post( $post );
		if ( ! $post || 'gcm_portfolio' !== $post->post_type ) {
			return self::profile_defaults();
		}

		$defaults = self::profile_defaults();
		$profile  = array(
			'name'     => get_the_title( $post ),
			'slug'     => $post->post_name,
			'photo_id' => (int) get_post_thumbnail_id( $post ),
			'url'      => get_permalink( $post ),
		);

		foreach ( self::profile_meta_map() as $key => $meta_key ) {
			$val = get_post_meta( $post->ID, $meta_key, true );
			$profile[ $key ] = ( '' === $val || null === $val ) ? ( $defaults[ $key ] ?? '' ) : $val;
		}

		return $profile;
	}

	/**
	 * Save profile fields from admin POST.
	 *
	 * @param int $post_id Portfolio ID.
	 * @return void
	 */
	public static function save_profile_meta_from_request( $post_id ) {
		$map = self::profile_meta_map();
		foreach ( $map as $key => $meta_key ) {
			$field = 'gcm_pf_' . $key;
			if ( ! isset( $_POST[ $field ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				continue;
			}
			$raw = wp_unslash( $_POST[ $field ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( in_array( $key, array( 'intro', 'bio', 'skills_cyber', 'skills_networking', 'skills_web', 'skills_animation' ), true ) ) {
				$value = sanitize_textarea_field( $raw );
			} elseif ( in_array( $key, array( 'cta_url', 'github_url', 'linkedin_url' ), true ) ) {
				$value = esc_url_raw( $raw );
			} elseif ( 'email' === $key ) {
				$value = sanitize_email( $raw );
			} else {
				$value = sanitize_text_field( $raw );
			}
			update_post_meta( $post_id, $meta_key, $value );
		}
	}

	/**
	 * Lines helper.
	 *
	 * @param string $text Textarea.
	 * @return array
	 */
	public static function lines( $text ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $text );
		$lines = array_map( 'trim', $lines );
		return array_values( array_filter( $lines ) );
	}

	/**
	 * Projects for one portfolio.
	 *
	 * @param int $portfolio_id Portfolio post ID.
	 * @return array
	 */
	public static function get_projects( $portfolio_id = 0 ) {
		$args = array(
			'post_type'      => 'gcm_portfolio_item',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => array(
				'menu_order' => 'ASC',
				'date'       => 'DESC',
			),
		);

		$portfolio_id = absint( $portfolio_id );
		if ( $portfolio_id ) {
			$args['meta_key']   = '_gcm_portfolio_id';
			$args['meta_value'] = (string) $portfolio_id;
		}

		$items = array();
		foreach ( get_posts( $args ) as $post ) {
			$items[] = self::format_project( $post );
		}
		return $items;
	}

	/**
	 * Format project.
	 *
	 * @param WP_Post $post Post.
	 * @return array
	 */
	public static function format_project( $post ) {
		$category = (string) get_post_meta( $post->ID, '_gcm_portfolio_category', true );
		if ( ! isset( self::categories()[ $category ] ) ) {
			$category = 'web';
		}
		$tech = array_values(
			array_filter(
				array_map(
					'trim',
					preg_split( '/[,|\n]/', (string) get_post_meta( $post->ID, '_gcm_portfolio_tech', true ) )
				)
			)
		);

		return array(
			'id'             => (int) $post->ID,
			'title'          => get_the_title( $post ),
			'excerpt'        => $post->post_excerpt ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( $post->post_content ), 28 ),
			'category'       => $category,
			'category_label' => self::categories()[ $category ],
			'tech'           => $tech,
			'project_url'    => (string) get_post_meta( $post->ID, '_gcm_portfolio_url', true ),
			'year'           => (string) get_post_meta( $post->ID, '_gcm_portfolio_year', true ),
			'featured'       => (bool) get_post_meta( $post->ID, '_gcm_portfolio_featured', true ),
			'image'          => get_the_post_thumbnail_url( $post, 'large' ) ?: '',
			'portfolio_id'   => absint( get_post_meta( $post->ID, '_gcm_portfolio_id', true ) ),
		);
	}

	/**
	 * All portfolio profiles for admin dropdowns.
	 *
	 * @return array<int,string>
	 */
	public static function list_portfolios() {
		$posts = get_posts(
			array(
				'post_type'      => 'gcm_portfolio',
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$out = array();
		foreach ( $posts as $post ) {
			$out[ (int) $post->ID ] = $post->post_title . ' (/' . $post->post_name . '/)';
		}
		return $out;
	}

	/**
	 * Hide the old shared /portfolio/ page from public menus; keep CPT URLs only.
	 *
	 * @return void
	 */
	public static function cleanup_shared_portfolio_page() {
		$page = get_page_by_path( 'portfolio' );
		if ( $page && 'publish' === $page->post_status ) {
			wp_update_post(
				array(
					'ID'          => $page->ID,
					'post_status' => 'draft',
				)
			);
		}
	}

	/**
	 * Seed Navyan portfolio + projects once (v2 multi-portfolio).
	 *
	 * @return void
	 */
	public static function maybe_seed_projects() {
		if ( get_option( 'gcm_portfolio_seeded_v2' ) ) {
			self::cleanup_shared_portfolio_page();
			return;
		}
		if ( ! post_type_exists( 'gcm_portfolio' ) || ! post_type_exists( 'gcm_portfolio_item' ) ) {
			return;
		}

		$navyan = get_page_by_path( 'navyan', OBJECT, 'gcm_portfolio' );
		if ( ! $navyan ) {
			$existing = get_posts(
				array(
					'post_type'      => 'gcm_portfolio',
					'name'           => 'navyan',
					'posts_per_page' => 1,
					'post_status'    => array( 'publish', 'draft' ),
				)
			);
			$navyan = $existing ? $existing[0] : null;
		}

		if ( ! $navyan ) {
			$portfolio_id = wp_insert_post(
				array(
					'post_type'   => 'gcm_portfolio',
					'post_status' => 'publish',
					'post_title'  => 'Navyan Baig',
					'post_name'   => 'navyan',
					'post_content'=> '',
				),
				true
			);
			if ( is_wp_error( $portfolio_id ) || ! $portfolio_id ) {
				return;
			}
			$defaults = self::profile_defaults();
			foreach ( self::profile_meta_map() as $key => $meta_key ) {
				if ( isset( $defaults[ $key ] ) ) {
					update_post_meta( $portfolio_id, $meta_key, $defaults[ $key ] );
				}
			}
			// Prefer existing team photo attachment if present in media by filename search — optional skip.
		} else {
			$portfolio_id = (int) $navyan->ID;
			if ( 'publish' !== $navyan->post_status ) {
				wp_update_post(
					array(
						'ID'          => $portfolio_id,
						'post_status' => 'publish',
						'post_name'   => 'navyan',
					)
				);
			}
		}

		// Attach existing unassigned projects, or seed new ones.
		$existing_items = get_posts(
			array(
				'post_type'      => 'gcm_portfolio_item',
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 50,
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $existing_items ) ) {
			foreach ( $existing_items as $item_id ) {
				if ( ! get_post_meta( $item_id, '_gcm_portfolio_id', true ) ) {
					update_post_meta( $item_id, '_gcm_portfolio_id', $portfolio_id );
				}
			}
		} else {
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
				update_post_meta( $post_id, '_gcm_portfolio_id', $portfolio_id );
				update_post_meta( $post_id, '_gcm_portfolio_category', $seed['category'] );
				update_post_meta( $post_id, '_gcm_portfolio_tech', $seed['tech'] );
				update_post_meta( $post_id, '_gcm_portfolio_year', $seed['year'] );
				update_post_meta( $post_id, '_gcm_portfolio_featured', ! empty( $seed['featured'] ) ? 1 : 0 );
				if ( ! empty( $seed['url'] ) ) {
					update_post_meta( $post_id, '_gcm_portfolio_url', esc_url_raw( $seed['url'] ) );
				}
			}
		}

		self::cleanup_shared_portfolio_page();
		update_option( 'gcm_portfolio_seeded_v2', 1, false );
		update_option( 'gcm_flush_rewrite_rules', 1, false );
	}

	/**
	 * Flush rewrite rules once after portfolio URL structure changes.
	 *
	 * @return void
	 */
	public static function maybe_flush_rewrites() {
		$ver = get_option( 'gcm_portfolio_rewrite_ver' );
		if ( $ver !== GCM_VERSION || get_option( 'gcm_flush_rewrite_rules' ) ) {
			flush_rewrite_rules( false );
			update_option( 'gcm_portfolio_rewrite_ver', GCM_VERSION, false );
			delete_option( 'gcm_flush_rewrite_rules' );
		}
	}
}
