<?php
/**
 * On-page SEO: titles, meta, Open Graph, schema, robots, sitemaps.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Search-engine optimization for public GCM pages.
 */
class GCM_SEO {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'document_title_parts', array( $this, 'filter_title_parts' ) );
		add_action( 'wp_head', array( $this, 'print_meta_tags' ), 1 );
		add_action( 'wp_head', array( $this, 'print_social_meta' ), 2 );
		add_action( 'wp_head', array( $this, 'print_schema' ), 3 );
		add_filter( 'wp_robots', array( $this, 'filter_robots' ), 20 );
		add_filter( 'robots_txt', array( $this, 'filter_robots_txt' ), 10, 2 );
		add_filter( 'wp_sitemaps_posts_query_args', array( $this, 'filter_sitemap_posts_query' ), 10, 2 );
		add_filter( 'wp_sitemaps_add_provider', array( $this, 'filter_sitemap_providers' ), 10, 2 );
	}

	/**
	 * SEO settings section with defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		$brand = 'Giga Class Market';

		return array(
			'title_separator'         => '|',
			'home_title'              => $brand . ' | Premium Online Courses & Digital Learning',
			'home_description'        => 'Learn practical skills with premium online courses from Giga Class Market. Browse courses, enroll securely, track progress, and earn verified certificates.',
			'about_title'             => 'About Us | ' . $brand,
			'about_description'       => 'Learn about Giga Class Market — a premium education marketplace built for ambitious students, with verified enrollment, structured courses, and career-ready skills.',
			'contact_title'           => 'Contact Us | ' . $brand,
			'contact_description'     => 'Contact Giga Class Market for course support, enrollment help, and partnership questions. Reach us by phone, WhatsApp, or email.',
			'courses_title'           => 'Explore Premium Courses | ' . $brand,
			'courses_description'     => 'Browse premium digital courses on Giga Class Market. Compare prices, enroll online, and build skills with structured lessons and verified certificates.',
			'default_og_image_id'     => 0,
			'organization_description'=> 'Giga Class Market is a premium online learning marketplace offering structured digital courses, secure enrollment, student dashboards, and verified certificates.',
			'google_site_verification'=> '',
			'bing_site_verification'  => '',
		);
	}

	/**
	 * Get SEO settings.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$settings = GCM_Settings_Service::get_settings();
		$seo      = isset( $settings['seo'] ) && is_array( $settings['seo'] ) ? $settings['seo'] : array();
		return wp_parse_args( $seo, self::defaults() );
	}

	/**
	 * Whether the current request should stay out of search indexes.
	 *
	 * @return bool
	 */
	public function is_private_view() {
		if ( is_admin() ) {
			return true;
		}

		if ( is_feed() || is_trackback() || is_search() ) {
			return false;
		}

		$slugs = array(
			'student-dashboard',
			'teacher-dashboard',
			'course-learn',
			'payment',
			'payment-verify',
			'login',
			'live-class',
		);

		if ( is_page( $slugs ) ) {
			return true;
		}

		if ( function_exists( 'gcm_is_noindex_template' ) && gcm_is_noindex_template() ) {
			return true;
		}

		if ( is_page_template(
			array(
				'page-templates/template-student-dashboard.php',
				'page-templates/template-teacher-dashboard.php',
				'page-templates/template-course-learn.php',
				'page-templates/template-payment.php',
				'page-templates/template-payment-verify.php',
				'page-templates/template-login.php',
			)
		) ) {
			return true;
		}

		return false;
	}

	/**
	 * Improve document titles for key public templates.
	 *
	 * @param array $parts Title parts.
	 * @return array
	 */
	public function filter_title_parts( $parts ) {
		if ( $this->is_private_view() ) {
			return $parts;
		}

		$seo  = self::get_settings();
		$sep  = trim( (string) $seo['title_separator'] ) ?: '|';
		$site = get_bloginfo( 'name', 'display' );

		if ( is_front_page() && ! empty( $seo['home_title'] ) ) {
			return array( 'title' => $seo['home_title'] );
		}

		if ( is_post_type_archive( 'gcm_course' ) && ! empty( $seo['courses_title'] ) ) {
			return array( 'title' => $seo['courses_title'] );
		}

		if ( is_page( 'about' ) && ! empty( $seo['about_title'] ) ) {
			return array( 'title' => $seo['about_title'] );
		}

		if ( is_page( 'contact' ) && ! empty( $seo['contact_title'] ) ) {
			return array( 'title' => $seo['contact_title'] );
		}

		if ( is_singular( 'gcm_course' ) ) {
			$custom = (string) get_post_meta( get_the_ID(), '_gcm_seo_title', true );
			$title  = $custom ? $custom : get_the_title();
			return array(
				'title' => $title,
				'site'  => $site,
			);
		}

		if ( is_singular() && ! empty( $parts['title'] ) && empty( $parts['site'] ) ) {
			$parts['site'] = $site;
		}

		unset( $sep );
		return $parts;
	}

	/**
	 * Meta description + verification tags.
	 *
	 * @return void
	 */
	public function print_meta_tags() {
		if ( $this->is_private_view() ) {
			return;
		}

		$seo         = self::get_settings();
		$description = $this->get_meta_description();

		if ( $description ) {
			echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
		}

		if ( ! empty( $seo['google_site_verification'] ) ) {
			echo '<meta name="google-site-verification" content="' . esc_attr( $seo['google_site_verification'] ) . '" />' . "\n";
		}

		if ( ! empty( $seo['bing_site_verification'] ) ) {
			echo '<meta name="msvalidate.01" content="' . esc_attr( $seo['bing_site_verification'] ) . '" />' . "\n";
		}

		$canonical = $this->get_canonical_url();
		if ( $canonical && ! is_singular() ) {
			// Core prints singular canonicals; fill gaps for home/archives/pages that miss it.
			echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
		}
	}

	/**
	 * Open Graph + Twitter Card tags.
	 *
	 * @return void
	 */
	public function print_social_meta() {
		if ( $this->is_private_view() ) {
			return;
		}

		$title       = wp_get_document_title();
		$description = $this->get_meta_description();
		$url         = $this->get_canonical_url();
		$image       = $this->get_share_image();
		$type        = is_singular( 'gcm_course' ) ? 'product' : ( is_singular() ? 'article' : 'website' );
		$site_name   = get_bloginfo( 'name', 'display' );

		echo '<meta property="og:locale" content="' . esc_attr( str_replace( '-', '_', get_locale() ) ) . '" />' . "\n";
		echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '" />' . "\n";
		echo '<meta property="og:type" content="' . esc_attr( $type ) . '" />' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
		if ( $description ) {
			echo '<meta property="og:description" content="' . esc_attr( $description ) . '" />' . "\n";
		}
		if ( $url ) {
			echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
		}
		if ( $image ) {
			echo '<meta property="og:image" content="' . esc_url( $image ) . '" />' . "\n";
			echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
			echo '<meta name="twitter:image" content="' . esc_url( $image ) . '" />' . "\n";
		} else {
			echo '<meta name="twitter:card" content="summary" />' . "\n";
		}
		echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '" />' . "\n";
		if ( $description ) {
			echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '" />' . "\n";
		}
	}

	/**
	 * JSON-LD graphs for Organization, WebSite, Course, FAQ, Breadcrumbs.
	 *
	 * @return void
	 */
	public function print_schema() {
		if ( $this->is_private_view() ) {
			return;
		}

		$graphs = array();

		if ( is_front_page() || is_home() ) {
			$graphs[] = $this->organization_schema();
			$graphs[] = $this->website_schema();
		}

		if ( is_post_type_archive( 'gcm_course' ) ) {
			$graphs[] = $this->organization_schema();
			$graphs[] = $this->breadcrumb_schema(
				array(
					array(
						'name' => __( 'Home', 'giga-class-market' ),
						'url'  => home_url( '/' ),
					),
					array(
						'name' => __( 'Courses', 'giga-class-market' ),
						'url'  => get_post_type_archive_link( 'gcm_course' ) ?: home_url( '/courses/' ),
					),
				)
			);
		}

		if ( is_page( 'about' ) || is_page( 'contact' ) ) {
			$graphs[] = $this->organization_schema();
			$graphs[] = $this->breadcrumb_schema(
				array(
					array(
						'name' => __( 'Home', 'giga-class-market' ),
						'url'  => home_url( '/' ),
					),
					array(
						'name' => get_the_title(),
						'url'  => get_permalink(),
					),
				)
			);
		}

		if ( is_singular( 'gcm_course' ) ) {
			$course_graph = $this->course_schema( get_the_ID() );
			if ( $course_graph ) {
				$graphs[] = $course_graph;
			}
			$faq = $this->faq_schema( get_the_ID() );
			if ( $faq ) {
				$graphs[] = $faq;
			}
			$graphs[] = $this->breadcrumb_schema(
				array(
					array(
						'name' => __( 'Home', 'giga-class-market' ),
						'url'  => home_url( '/' ),
					),
					array(
						'name' => __( 'Courses', 'giga-class-market' ),
						'url'  => get_post_type_archive_link( 'gcm_course' ) ?: home_url( '/courses/' ),
					),
					array(
						'name' => get_the_title(),
						'url'  => get_permalink(),
					),
				)
			);
		}

		$graphs = array_values( array_filter( $graphs ) );
		if ( empty( $graphs ) ) {
			return;
		}

		$payload = array(
			'@context' => 'https://schema.org',
			'@graph'   => $graphs,
		);

		echo '<script type="application/ld+json">' . wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}

	/**
	 * Expand noindex coverage for private LMS surfaces.
	 *
	 * @param array $robots Robots directives.
	 * @return array
	 */
	public function filter_robots( $robots ) {
		if ( $this->is_private_view() ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}

		return $robots;
	}

	/**
	 * Helpful robots.txt hints (keep public courses crawlable).
	 *
	 * @param string $output Robots.txt body.
	 * @param bool   $public Blog public.
	 * @return string
	 */
	public function filter_robots_txt( $output, $public ) {
		if ( ! $public ) {
			return $output;
		}

		$extra  = "\n# Giga Class Market SEO\n";
		$extra .= "Allow: /courses/\n";
		$extra .= "Allow: /about/\n";
		$extra .= "Allow: /contact/\n";
		$extra .= "Disallow: /student-dashboard/\n";
		$extra .= "Disallow: /teacher-dashboard/\n";
		$extra .= "Disallow: /course-learn/\n";
		$extra .= "Disallow: /payment/\n";
		$extra .= "Disallow: /payment-verify/\n";
		$extra .= "Disallow: /login/\n";
		$extra .= 'Sitemap: ' . esc_url( home_url( '/wp-sitemap.xml' ) ) . "\n";

		return $output . $extra;
	}

	/**
	 * Keep private page templates out of the posts sitemap.
	 *
	 * @param array  $args Query args.
	 * @param string $post_type Post type.
	 * @return array
	 */
	public function filter_sitemap_posts_query( $args, $post_type ) {
		if ( 'page' !== $post_type ) {
			return $args;
		}

		$exclude = array();
		foreach ( array( 'student-dashboard', 'teacher-dashboard', 'course-learn', 'payment', 'payment-verify', 'login', 'live-class' ) as $slug ) {
			$page = get_page_by_path( $slug );
			if ( $page ) {
				$exclude[] = (int) $page->ID;
			}
		}

		if ( $exclude ) {
			$args['post__not_in'] = isset( $args['post__not_in'] ) ? array_merge( (array) $args['post__not_in'], $exclude ) : $exclude;
		}

		return $args;
	}

	/**
	 * Ensure users sitemap is not advertised (students/teachers are private).
	 *
	 * @param WP_Sitemaps_Provider $provider Provider.
	 * @param string               $name Name.
	 * @return WP_Sitemaps_Provider|false
	 */
	public function filter_sitemap_providers( $provider, $name ) {
		if ( 'users' === $name ) {
			return false;
		}
		return $provider;
	}

	/**
	 * Resolve meta description for the current view.
	 *
	 * @return string
	 */
	private function get_meta_description() {
		$seo = self::get_settings();

		if ( is_front_page() ) {
			return $this->trim_description( $seo['home_description'] );
		}

		if ( is_post_type_archive( 'gcm_course' ) ) {
			return $this->trim_description( $seo['courses_description'] );
		}

		if ( is_page( 'about' ) ) {
			return $this->trim_description( $seo['about_description'] );
		}

		if ( is_page( 'contact' ) ) {
			return $this->trim_description( $seo['contact_description'] );
		}

		if ( is_singular( 'gcm_course' ) ) {
			$custom = (string) get_post_meta( get_the_ID(), '_gcm_seo_description', true );
			if ( $custom ) {
				return $this->trim_description( $custom );
			}
			$course = class_exists( 'GCM_Course_Service' ) ? GCM_Course_Service::get( get_the_ID() ) : null;
			if ( $course ) {
				$text = $course['excerpt'] ? $course['excerpt'] : wp_trim_words( wp_strip_all_tags( $course['content'] ), 35 );
				return $this->trim_description( $text );
			}
		}

		if ( is_singular() ) {
			if ( has_excerpt() ) {
				return $this->trim_description( get_the_excerpt() );
			}
			return $this->trim_description( wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', get_the_ID() ) ), 35 ) );
		}

		$tagline = get_bloginfo( 'description', 'display' );
		return $tagline ? $this->trim_description( $tagline ) : $this->trim_description( $seo['home_description'] );
	}

	/**
	 * Canonical URL for the current view.
	 *
	 * @return string
	 */
	private function get_canonical_url() {
		if ( is_singular() ) {
			return (string) get_permalink();
		}
		if ( is_front_page() ) {
			return home_url( '/' );
		}
		if ( is_post_type_archive( 'gcm_course' ) ) {
			return (string) ( get_post_type_archive_link( 'gcm_course' ) ?: home_url( '/courses/' ) );
		}
		if ( is_home() ) {
			$page_for_posts = (int) get_option( 'page_for_posts' );
			return $page_for_posts ? (string) get_permalink( $page_for_posts ) : home_url( '/' );
		}

		return home_url( add_query_arg( array(), $GLOBALS['wp']->request ? '/' . $GLOBALS['wp']->request . '/' : '/' ) );
	}

	/**
	 * Best share/OG image.
	 *
	 * @return string
	 */
	private function get_share_image() {
		if ( is_singular() && has_post_thumbnail() ) {
			$url = get_the_post_thumbnail_url( get_the_ID(), 'large' );
			if ( $url ) {
				return $url;
			}
		}

		$seo = self::get_settings();
		$id  = absint( $seo['default_og_image_id'] );
		if ( $id ) {
			$url = wp_get_attachment_image_url( $id, 'large' );
			if ( $url ) {
				return $url;
			}
		}

		$logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( $logo_id ) {
			$url = wp_get_attachment_image_url( $logo_id, 'full' );
			if ( $url ) {
				return $url;
			}
		}

		return '';
	}

	/**
	 * Organization schema node.
	 *
	 * @return array
	 */
	private function organization_schema() {
		$settings = GCM_Settings_Service::get_settings();
		$company  = $settings['company'] ?? array();
		$seo      = self::get_settings();
		$same_as  = array_values(
			array_filter(
				array(
					$company['facebook'] ?? '',
					$company['instagram'] ?? '',
					$company['linkedin'] ?? '',
					$company['youtube'] ?? '',
				)
			)
		);

		$org = array(
			'@type' => 'Organization',
			'@id'   => home_url( '/#organization' ),
			'name'  => $company['name'] ?? get_bloginfo( 'name' ),
			'url'   => home_url( '/' ),
			'description' => $seo['organization_description'],
		);

		$logo_id = absint( $seo['default_og_image_id'] ) ?: (int) get_theme_mod( 'custom_logo' );
		if ( $logo_id ) {
			$logo = wp_get_attachment_image_url( $logo_id, 'full' );
			if ( $logo ) {
				$org['logo'] = $logo;
			}
		}

		if ( ! empty( $company['email'] ) ) {
			$org['email'] = $company['email'];
		}
		if ( ! empty( $company['phone'] ) ) {
			$org['telephone'] = $company['phone'];
		}
		if ( ! empty( $company['address'] ) ) {
			$org['address'] = array(
				'@type'         => 'PostalAddress',
				'streetAddress' => $company['address'],
			);
		}
		if ( $same_as ) {
			$org['sameAs'] = $same_as;
		}

		return $org;
	}

	/**
	 * WebSite schema with course search action.
	 *
	 * @return array
	 */
	private function website_schema() {
		return array(
			'@type'           => 'WebSite',
			'@id'             => home_url( '/#website' ),
			'url'             => home_url( '/' ),
			'name'            => get_bloginfo( 'name' ),
			'description'     => self::get_settings()['home_description'],
			'publisher'       => array( '@id' => home_url( '/#organization' ) ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => home_url( '/?s={search_term_string}' ),
				),
				'query-input' => 'required name=search_term_string',
			),
		);
	}

	/**
	 * Rich Course schema.
	 *
	 * @param int $course_id Course ID.
	 * @return array|null
	 */
	private function course_schema( $course_id ) {
		$course = GCM_Course_Service::get( $course_id );
		if ( ! $course ) {
			return null;
		}

		$settings = GCM_Settings_Service::get_settings();
		$company  = $settings['company']['name'] ?? get_bloginfo( 'name' );
		$price    = $course['discount_price'] > 0 ? $course['discount_price'] : $course['price'];
		$desc     = (string) get_post_meta( $course_id, '_gcm_seo_description', true );
		if ( ! $desc ) {
			$desc = $course['excerpt'] ? $course['excerpt'] : wp_trim_words( wp_strip_all_tags( $course['content'] ), 40 );
		}

		$schema = array(
			'@type'       => 'Course',
			'@id'         => trailingslashit( $course['permalink'] ) . '#course',
			'name'        => $course['title'],
			'description' => wp_strip_all_tags( $desc ),
			'url'         => $course['permalink'],
			'provider'    => array(
				'@type' => 'Organization',
				'name'  => $company,
				'url'   => home_url( '/' ),
			),
			'offers'      => array(
				'@type'         => 'Offer',
				'category'      => 'Paid',
				'price'         => (string) $price,
				'priceCurrency' => 'PKR',
				'availability'  => 'https://schema.org/InStock',
				'url'           => $course['permalink'],
			),
		);

		if ( ! empty( $course['thumbnail'] ) ) {
			$schema['image'] = $course['thumbnail'];
		}
		if ( ! empty( $course['instructor'] ) ) {
			$schema['instructor'] = array(
				'@type' => 'Person',
				'name'  => $course['instructor'],
			);
		}
		if ( ! empty( $course['duration'] ) ) {
			$schema['timeRequired'] = $course['duration'];
		}

		return $schema;
	}

	/**
	 * FAQ schema from course FAQ meta.
	 *
	 * @param int $course_id Course ID.
	 * @return array|null
	 */
	private function faq_schema( $course_id ) {
		$raw = (string) get_post_meta( $course_id, '_gcm_faq', true );
		if ( '' === trim( $raw ) ) {
			return null;
		}

		$entities = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || false === strpos( $line, '|' ) ) {
				continue;
			}
			list( $question, $answer ) = array_map( 'trim', explode( '|', $line, 2 ) );
			if ( '' === $question || '' === $answer ) {
				continue;
			}
			$entities[] = array(
				'@type'          => 'Question',
				'name'           => wp_strip_all_tags( $question ),
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => wp_strip_all_tags( $answer ),
				),
			);
		}

		if ( empty( $entities ) ) {
			return null;
		}

		return array(
			'@type'      => 'FAQPage',
			'mainEntity' => $entities,
		);
	}

	/**
	 * BreadcrumbList schema.
	 *
	 * @param array $crumbs Crumb rows with name/url.
	 * @return array
	 */
	private function breadcrumb_schema( $crumbs ) {
		$items = array();
		foreach ( array_values( $crumbs ) as $index => $crumb ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $index + 1,
				'name'     => $crumb['name'],
				'item'     => $crumb['url'],
			);
		}

		return array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $items,
		);
	}

	/**
	 * Keep descriptions within a practical SERP length.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	private function trim_description( $text ) {
		$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $text ) ) );
		if ( strlen( $text ) <= 160 ) {
			return $text;
		}
		return rtrim( substr( $text, 0, 157 ) ) . '…';
	}
}
