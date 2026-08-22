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
		add_action( 'init', array( $this, 'ensure_google_verification_file' ), 5 );
		add_action( 'template_redirect', array( $this, 'serve_google_verification_file' ), 0 );
		add_action( 'save_post_gcm_course', array( $this, 'on_course_saved' ), 40, 2 );
		add_action( 'save_post_gcm_blog', array( $this, 'on_blog_saved' ), 40, 2 );
	}

	/**
	 * Keep course SEO meta filled whenever a course is saved/published.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public function on_course_saved( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! $post || 'publish' !== $post->post_status ) {
			return;
		}
		if ( class_exists( 'GCM_Course_SEO' ) ) {
			GCM_Course_SEO::ensure_course( (int) $post_id );
		}
	}

	/**
	 * Keep blog SEO meta filled on publish.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public function on_blog_saved( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! $post || 'publish' !== $post->post_status ) {
			return;
		}
		if ( class_exists( 'GCM_Blog_SEO' ) ) {
			GCM_Blog_SEO::ensure_blog( (int) $post_id );
		}
	}

	/**
	 * Google Search Console HTML verification filename.
	 *
	 * @return string
	 */
	public static function google_verification_filename() {
		return 'googleba1261e92aab8d99.html';
	}

	/**
	 * Exact body Google expects for HTML-file verification.
	 *
	 * @return string
	 */
	public static function google_verification_body() {
		return 'google-site-verification: googleba1261e92aab8d99.html';
	}

	/**
	 * Write the verification HTML into the WordPress root when possible.
	 *
	 * @return void
	 */
	public function ensure_google_verification_file() {
		$filename = self::google_verification_filename();
		$target   = trailingslashit( ABSPATH ) . $filename;
		$body     = self::google_verification_body();

		if ( file_exists( $target ) ) {
			$current = (string) file_get_contents( $target );
			if ( false !== strpos( $current, 'google-site-verification' ) ) {
				return;
			}
		}

		// Prefer copying the packaged file when present.
		$packaged = trailingslashit( GCM_PLUGIN_DIR ) . 'public/seo/' . $filename;
		if ( file_exists( $packaged ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
			@copy( $packaged, $target );
		}

		if ( ! file_exists( $target ) || false === strpos( (string) file_get_contents( $target ), 'google-site-verification' ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@file_put_contents( $target, $body );
		}
	}

	/**
	 * Fallback: serve the verification file through WordPress if the root file is missing.
	 *
	 * @return void
	 */
	public function serve_google_verification_file() {
		$filename = self::google_verification_filename();
		$request  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path     = wp_parse_url( $request, PHP_URL_PATH );
		$path     = is_string( $path ) ? untrailingslashit( $path ) : '';

		if ( '/' . $filename !== $path && $filename !== ltrim( $path, '/' ) ) {
			return;
		}

		nocache_headers();
		status_header( 200 );
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		echo self::google_verification_body(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
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
			'services_title'          => 'Digital Services | Web Development, Marketing & SEO | ' . $brand,
			'services_description'    => 'Hire Giga Class Market for web development, digital marketing, SEO, social media, branding, and LMS setup. Contact us to start your project.',
			'contact_title'           => 'Contact Us | ' . $brand,
			'contact_description'     => 'Contact Giga Class Market for course support, enrollment help, professional services, and partnership questions. Reach us by phone, WhatsApp, or email.',
			'courses_title'           => 'Online Courses | FPSC, CCNA, Ethical Hacking & AI Coding | ' . $brand,
			'courses_description'     => 'Enroll in premium online courses at Giga Class Market — FPSC preparation (Pakistan), CCNA networking, ethical hacking, AI coding, and more. Practical lessons, expert instructors, and verified certificates.',
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
			'get-account-details',
			'payment-verify',
			'payment-verification',
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
				'page-templates/template-payment-whatsapp.php',
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

		if ( is_page( 'services' ) && ! empty( $seo['services_title'] ) ) {
			return array( 'title' => $seo['services_title'] );
		}

		if ( is_page( 'contact' ) && ! empty( $seo['contact_title'] ) ) {
			return array( 'title' => $seo['contact_title'] );
		}

		if ( is_singular( 'gcm_course' ) ) {
			$course_id = get_the_ID();
			$title     = class_exists( 'GCM_Course_SEO' )
				? GCM_Course_SEO::resolve_seo_title( $course_id )
				: get_the_title( $course_id );
			// Full SERP title already includes brand — avoid "title - domain" duplicates.
			return array( 'title' => $title );
		}

		if ( is_post_type_archive( 'gcm_blog' ) ) {
			return array( 'title' => __( 'Blog | FPSC, CCNA & Ethical Hacking Guides | Giga Class Market', 'giga-class-market' ) );
		}

		if ( is_tax( 'gcm_blog_category' ) ) {
			$term = get_queried_object();
			$name = ( $term && ! empty( $term->name ) ) ? $term->name : __( 'Blog', 'giga-class-market' );
			return array(
				'title' => sprintf(
					/* translators: %s: category name */
					__( '%s Articles | Giga Class Market Blog', 'giga-class-market' ),
					$name
				),
			);
		}

		if ( is_singular( 'gcm_blog' ) ) {
			$blog_id = get_the_ID();
			$title   = class_exists( 'GCM_Blog_SEO' )
				? GCM_Blog_SEO::resolve_seo_title( $blog_id )
				: get_the_title( $blog_id );
			return array( 'title' => $title );
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

		if ( is_singular( 'gcm_course' ) && class_exists( 'GCM_Course_SEO' ) ) {
			$keyword = GCM_Course_SEO::resolve_keywords_csv( get_the_ID() );
			if ( $keyword ) {
				echo '<meta name="keywords" content="' . esc_attr( $keyword ) . '" />' . "\n";
			}
		}

		if ( is_singular( 'gcm_blog' ) && class_exists( 'GCM_Blog_SEO' ) ) {
			$keyword = GCM_Blog_SEO::resolve_focus_keyword( get_the_ID() );
			if ( $keyword ) {
				echo '<meta name="keywords" content="' . esc_attr( $keyword ) . '" />' . "\n";
			}
		}

		if ( ! empty( $seo['google_site_verification'] ) ) {
			echo '<meta name="google-site-verification" content="' . esc_attr( $seo['google_site_verification'] ) . '" />' . "\n";
		}

		if ( ! empty( $seo['bing_site_verification'] ) ) {
			echo '<meta name="msvalidate.01" content="' . esc_attr( $seo['bing_site_verification'] ) . '" />' . "\n";
		}

		$canonical = $this->get_canonical_url();
		if ( $canonical && ( ! is_singular() || is_post_type_archive( 'gcm_course' ) || is_post_type_archive( 'gcm_blog' ) ) ) {
			// Core prints singular canonicals; force clean archive canonicals too.
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
		$type        = is_singular( 'gcm_course' ) ? 'website' : ( is_singular() ? 'article' : 'website' );
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

		if ( is_singular( 'gcm_course' ) && class_exists( 'GCM_Course_Service' ) ) {
			$course = GCM_Course_Service::get( get_the_ID() );
			if ( $course ) {
				$price = $course['discount_price'] > 0 ? $course['discount_price'] : $course['price'];
				echo '<meta property="product:price:amount" content="' . esc_attr( (string) $price ) . '" />' . "\n";
				echo '<meta property="product:price:currency" content="PKR" />' . "\n";
			}
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
			$item_list = $this->course_item_list_schema();
			if ( $item_list ) {
				$graphs[] = $item_list;
			}
		}

		if ( is_page( 'about' ) || is_page( 'services' ) || is_page( 'contact' ) ) {
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

		if ( is_post_type_archive( 'gcm_blog' ) || is_tax( 'gcm_blog_category' ) ) {
			$graphs[] = $this->organization_schema();
			$crumbs   = array(
				array(
					'name' => __( 'Home', 'giga-class-market' ),
					'url'  => home_url( '/' ),
				),
				array(
					'name' => __( 'Blog', 'giga-class-market' ),
					'url'  => get_post_type_archive_link( 'gcm_blog' ) ?: home_url( '/blogs/' ),
				),
			);
			if ( is_tax( 'gcm_blog_category' ) ) {
				$term = get_queried_object();
				if ( $term && ! empty( $term->name ) ) {
					$crumbs[] = array(
						'name' => $term->name,
						'url'  => get_term_link( $term ),
					);
				}
			}
			$graphs[] = $this->breadcrumb_schema( $crumbs );
		}

		if ( is_singular( 'gcm_blog' ) ) {
			$blog_graph = $this->blog_post_schema( get_the_ID() );
			if ( $blog_graph ) {
				$graphs[] = $blog_graph;
			}
			$graphs[] = $this->breadcrumb_schema(
				array(
					array(
						'name' => __( 'Home', 'giga-class-market' ),
						'url'  => home_url( '/' ),
					),
					array(
						'name' => __( 'Blog', 'giga-class-market' ),
						'url'  => get_post_type_archive_link( 'gcm_blog' ) ?: home_url( '/blogs/' ),
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
			return $robots;
		}

		// Avoid indexing filtered/search marketplace URLs (canonical stays clean /courses/).
		if ( is_post_type_archive( 'gcm_course' ) ) {
			$has_filter = ! empty( $_GET['course_search'] ) || ! empty( $_GET['course_category'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$sort       = isset( $_GET['sort'] ) ? sanitize_key( wp_unslash( $_GET['sort'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $has_filter || ( $sort && 'newest' !== $sort ) ) {
				$robots['noindex'] = true;
				$robots['follow']  = true;
			}
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
		$extra .= "Allow: /services/\n";
		$extra .= "Allow: /contact/\n";
		$extra .= "Disallow: /student-dashboard/\n";
		$extra .= "Disallow: /teacher-dashboard/\n";
		$extra .= "Disallow: /course-learn/\n";
		$extra .= "Disallow: /payment/\n";
		$extra .= "Disallow: /get-account-details/\n";
		$extra .= "Disallow: /payment-verify/\n";
		$extra .= "Disallow: /payment-verification/\n";
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
		foreach ( array( 'student-dashboard', 'teacher-dashboard', 'course-learn', 'payment', 'get-account-details', 'payment-verify', 'payment-verification', 'login', 'live-class' ) as $slug ) {
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

		if ( is_page( 'services' ) ) {
			return $this->trim_description( $seo['services_description'] );
		}

		if ( is_page( 'contact' ) ) {
			return $this->trim_description( $seo['contact_description'] );
		}

		if ( is_singular( 'gcm_course' ) ) {
			$course_id = get_the_ID();
			if ( class_exists( 'GCM_Course_SEO' ) ) {
				return $this->trim_description( GCM_Course_SEO::resolve_seo_description( $course_id ) );
			}
			$custom = (string) get_post_meta( $course_id, '_gcm_seo_description', true );
			if ( $custom ) {
				return $this->trim_description( $custom );
			}
			$course = class_exists( 'GCM_Course_Service' ) ? GCM_Course_Service::get( $course_id ) : null;
			if ( $course ) {
				$text = $course['excerpt'] ? $course['excerpt'] : wp_trim_words( wp_strip_all_tags( $course['content'] ), 35 );
				return $this->trim_description( $text );
			}
		}

		if ( is_post_type_archive( 'gcm_blog' ) ) {
			return $this->trim_description(
				__( 'Top reading guides for FPSC preparation Pakistan, CCNA networking, and ethical hacking — practical SEO blogs that help you choose and succeed in Giga Class Market courses.', 'giga-class-market' )
			);
		}

		if ( is_tax( 'gcm_blog_category' ) ) {
			$term = get_queried_object();
			if ( $term && ! empty( $term->description ) ) {
				return $this->trim_description( $term->description );
			}
			$name = ( $term && ! empty( $term->name ) ) ? $term->name : __( 'this topic', 'giga-class-market' );
			return $this->trim_description(
				sprintf(
					/* translators: %s: category name */
					__( 'Browse %s articles on Giga Class Market — practical tips that connect learning with the right online course.', 'giga-class-market' ),
					$name
				)
			);
		}

		if ( is_singular( 'gcm_blog' ) ) {
			$blog_id = get_the_ID();
			if ( class_exists( 'GCM_Blog_SEO' ) ) {
				return $this->trim_description( GCM_Blog_SEO::resolve_seo_description( $blog_id ) );
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
		if ( is_post_type_archive( 'gcm_blog' ) ) {
			return (string) ( get_post_type_archive_link( 'gcm_blog' ) ?: home_url( '/blogs/' ) );
		}
		if ( is_tax( 'gcm_blog_category' ) ) {
			$link = get_term_link( get_queried_object() );
			return is_wp_error( $link ) ? home_url( '/blogs/' ) : (string) $link;
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

		if ( ! empty( $company['email'] ) || ! empty( $company['inbox_email'] ) ) {
			$org['email'] = ! empty( $company['inbox_email'] ) ? $company['inbox_email'] : $company['email'];
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

		$org['areaServed'] = array(
			'@type' => 'Country',
			'name'  => 'Pakistan',
		);

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
					'urlTemplate' => home_url( '/courses/?course_search={search_term_string}' ),
				),
				'query-input' => 'required name=search_term_string',
			),
		);
	}

	/**
	 * Course list carousel schema for /courses/ (Google Course list eligibility).
	 *
	 * @return array|null
	 */
	private function course_item_list_schema() {
		$query = new WP_Query(
			array(
				'post_type'              => 'gcm_course',
				'post_status'            => 'publish',
				'posts_per_page'         => 50,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);

		if ( ! $query->have_posts() ) {
			return null;
		}

		$elements = array();
		$position = 0;
		foreach ( $query->posts as $post ) {
			++$position;
			$course_node = $this->course_schema( (int) $post->ID );
			if ( ! $course_node ) {
				continue;
			}
			$elements[] = array(
				'@type'    => 'ListItem',
				'position' => $position,
				'item'     => $course_node,
			);
		}
		wp_reset_postdata();

		if ( count( $elements ) < 1 ) {
			return null;
		}

		return array(
			'@type'           => 'ItemList',
			'@id'             => trailingslashit( get_post_type_archive_link( 'gcm_course' ) ?: home_url( '/courses/' ) ) . '#course-list',
			'name'            => __( 'Online Courses at Giga Class Market', 'giga-class-market' ),
			'itemListElement' => $elements,
		);
	}

	/**
	 * BlogPosting schema for a single blog.
	 *
	 * @param int $blog_id Blog ID.
	 * @return array|null
	 */
	private function blog_post_schema( $blog_id ) {
		$blog_id = absint( $blog_id );
		$post    = get_post( $blog_id );
		if ( ! $post || 'gcm_blog' !== $post->post_type ) {
			return null;
		}

		$desc = class_exists( 'GCM_Blog_SEO' )
			? GCM_Blog_SEO::resolve_seo_description( $blog_id )
			: wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 );
		$keyword = class_exists( 'GCM_Blog_SEO' ) ? GCM_Blog_SEO::resolve_focus_keyword( $blog_id ) : '';
		$image   = get_the_post_thumbnail_url( $blog_id, 'full' );
		$author  = get_the_author_meta( 'display_name', (int) $post->post_author );

		$schema = array(
			'@type'            => 'BlogPosting',
			'@id'              => trailingslashit( get_permalink( $blog_id ) ) . '#blogposting',
			'headline'         => get_the_title( $blog_id ),
			'description'      => wp_strip_all_tags( $desc ),
			'datePublished'    => get_the_date( 'c', $blog_id ),
			'dateModified'     => get_the_modified_date( 'c', $blog_id ),
			'mainEntityOfPage' => get_permalink( $blog_id ),
			'inLanguage'       => 'en',
			'isPartOf'         => array(
				'@type' => 'Blog',
				'name'  => __( 'Giga Class Market Blog', 'giga-class-market' ),
				'url'   => get_post_type_archive_link( 'gcm_blog' ) ?: home_url( '/blogs/' ),
			),
			'publisher'        => array(
				'@type' => 'Organization',
				'name'  => get_bloginfo( 'name' ),
				'url'   => home_url( '/' ),
				'@id'   => home_url( '/#organization' ),
			),
			'author'           => array(
				'@type' => 'Person',
				'name'  => $author ? $author : get_bloginfo( 'name' ),
			),
		);

		if ( $keyword ) {
			$schema['keywords'] = $keyword;
		}
		if ( $image ) {
			$schema['image'] = $image;
		}

		$course_id = class_exists( 'GCM_Blog_Service' ) ? GCM_Blog_Service::get_related_course_id( $blog_id ) : 0;
		if ( $course_id && get_post( $course_id ) ) {
			$schema['about'] = array(
				'@type' => 'Course',
				'name'  => get_the_title( $course_id ),
				'url'   => get_permalink( $course_id ),
			);
		}

		return $schema;
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
		$is_free  = (float) $price <= 0;
		$desc     = class_exists( 'GCM_Course_SEO' )
			? GCM_Course_SEO::resolve_seo_description( $course_id )
			: ( (string) get_post_meta( $course_id, '_gcm_seo_description', true ) );
		if ( ! $desc ) {
			$desc = $course['excerpt'] ? $course['excerpt'] : wp_trim_words( wp_strip_all_tags( $course['content'] ), 40 );
		}

		$provider = array(
			'@type' => 'Organization',
			'name'  => $company,
			'url'   => home_url( '/' ),
			'@id'   => home_url( '/#organization' ),
		);
		$same_as  = array_values(
			array_filter(
				array(
					$settings['company']['facebook'] ?? '',
					$settings['company']['instagram'] ?? '',
					$settings['company']['linkedin'] ?? '',
					$settings['company']['youtube'] ?? '',
				)
			)
		);
		if ( $same_as ) {
			$provider['sameAs'] = $same_as;
		}

		$schema = array(
			'@type'            => 'Course',
			'@id'              => trailingslashit( $course['permalink'] ) . '#course',
			'name'             => $course['title'],
			'description'      => wp_strip_all_tags( $desc ),
			'url'              => $course['permalink'],
			'inLanguage'       => 'en',
			'isAccessibleForFree' => $is_free,
			'provider'         => $provider,
			'offers'           => array(
				'@type'         => 'Offer',
				'category'      => $is_free ? 'Free' : 'Paid',
				'price'         => (string) $price,
				'priceCurrency' => 'PKR',
				'availability'  => 'https://schema.org/InStock',
				'url'           => $course['permalink'],
			),
			'hasCourseInstance'=> array(
				'@type'      => 'CourseInstance',
				'courseMode' => 'Online',
				'location'   => array(
					'@type' => 'VirtualLocation',
					'url'   => $course['permalink'],
				),
			),
		);

		if ( class_exists( 'GCM_Course_SEO' ) ) {
			$keyword = GCM_Course_SEO::resolve_keywords_csv( $course_id );
			if ( $keyword ) {
				$schema['keywords'] = $keyword;
			}
			$extras = GCM_Course_SEO::schema_extras( $course_id );
			foreach ( $extras as $key => $value ) {
				if ( '' === $key || null === $value || '' === $value ) {
					continue;
				}
				// Merge "about" with category names when both exist.
				if ( 'about' === $key && ! empty( $schema['about'] ) && is_array( $value ) ) {
					$schema['about'] = array_values( array_unique( array_merge( (array) $schema['about'], $value ) ) );
					continue;
				}
				$schema[ $key ] = $value;
			}
		}

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
			$schema['hasCourseInstance']['courseWorkload'] = $course['duration'];
		}
		if ( ! empty( $course['categories'] ) && is_array( $course['categories'] ) ) {
			$schema['about'] = array_values( $course['categories'] );
		}
		if ( ! empty( $course['what_you_learn'] ) ) {
			$skills = array_values(
				array_filter(
					array_map(
						'trim',
						preg_split( '/\r\n|\r|\n/', (string) $course['what_you_learn'] )
					)
				)
			);
			if ( $skills ) {
				$schema['teaches'] = $skills;
			}
		}

		$rating_value = 0.0;
		$rating_count = 0;
		if ( class_exists( 'GCM_Review_Service' ) ) {
			$rating_value = (float) GCM_Review_Service::get_average( $course_id );
			$reviews      = GCM_Review_Service::get_for_course( $course_id, 'approved' );
			$rating_count = is_array( $reviews ) ? count( $reviews ) : 0;
		}
		if ( $rating_count < 1 && ! empty( $course['rating'] ) ) {
			$rating_value = (float) $course['rating'];
			$rating_count = 1;
		}
		if ( $rating_value > 0 && $rating_count > 0 ) {
			$schema['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => (string) $rating_value,
				'bestRating'  => '5',
				'worstRating' => '1',
				'ratingCount' => (string) $rating_count,
			);
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
		if ( '' === trim( $raw ) && class_exists( 'GCM_Course_SEO' ) ) {
			$raw = GCM_Course_SEO::build_default_faq( $course_id );
		}
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

