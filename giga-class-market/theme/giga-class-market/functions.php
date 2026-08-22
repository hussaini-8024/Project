<?php
/**
 * Giga Class Market theme functions.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GCM_THEME_VERSION', '1.9.8' );
define( 'GCM_THEME_DIR', get_template_directory() );
define( 'GCM_THEME_URI', get_template_directory_uri() );

if ( ! function_exists( 'gcm_theme_setup' ) ) {
	/**
	 * Register theme supports and menus.
	 */
	function gcm_theme_setup() {
		load_theme_textdomain( 'giga-class-market', GCM_THEME_DIR . '/languages' );

		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'automatic-feed-links' );
		add_theme_support(
			'html5',
			array(
				'comment-form',
				'comment-list',
				'gallery',
				'caption',
				'style',
				'script',
				'search-form',
			)
		);
		add_theme_support(
			'custom-logo',
			array(
				'height'      => 80,
				'width'       => 240,
				'flex-height' => true,
				'flex-width'  => true,
			)
		);

		register_nav_menus(
			array(
				'primary' => __( 'Primary Menu', 'giga-class-market' ),
				'footer'  => __( 'Footer Menu', 'giga-class-market' ),
			)
		);
	}
}
add_action( 'after_setup_theme', 'gcm_theme_setup' );

/**
 * Enqueue theme assets.
 */
function gcm_enqueue_assets() {
	$asset_ver = GCM_THEME_VERSION;
	$bust      = get_option( 'gcm_cache_bust', '' );
	if ( $bust ) {
		$asset_ver .= '.' . preg_replace( '/[^0-9]/', '', (string) $bust );
	}

	wp_enqueue_style(
		'gcm-fonts',
		'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700;9..144,800&family=IBM+Plex+Mono:wght@400;500;600&family=Outfit:wght@500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap',
		array(),
		null
	);

	wp_enqueue_style(
		'gcm-main',
		GCM_THEME_URI . '/assets/css/main.css',
		array( 'gcm-fonts' ),
		$asset_ver
	);

	if ( is_page_template( 'page-templates/template-student-dashboard.php' ) || is_page_template( 'page-templates/template-course-learn.php' ) || is_page_template( 'page-templates/template-teacher-dashboard.php' ) ) {
		wp_enqueue_style(
			'gcm-dashboard',
			GCM_THEME_URI . '/assets/css/dashboard.css',
			array( 'gcm-main' ),
			$asset_ver
		);
	}

	if ( is_singular( 'gcm_portfolio' ) || is_page_template( 'page-templates/template-portfolio.php' ) ) {
		wp_enqueue_style(
			'gcm-portfolio',
			GCM_THEME_URI . '/assets/css/portfolio.css',
			array( 'gcm-main' ),
			$asset_ver
		);
		wp_enqueue_script(
			'gcm-portfolio',
			GCM_THEME_URI . '/assets/js/portfolio.js',
			array(),
			$asset_ver,
			true
		);
	}

	wp_enqueue_script(
		'gcm-slider',
		GCM_THEME_URI . '/assets/js/slider.js',
		array(),
		$asset_ver,
		true
	);

	wp_enqueue_script(
		'gcm-main',
		GCM_THEME_URI . '/assets/js/main.js',
		array( 'gcm-slider' ),
		$asset_ver,
		true
	);

	wp_localize_script(
		'gcm-main',
		'gcmTheme',
		array(
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'contactNonce'  => wp_create_nonce( 'gcm_contact_nonce' ),
			'progressNonce' => wp_create_nonce( 'gcm_progress_nonce' ),
			'i18n'          => array(
				'sending' => __( 'Sending...', 'giga-class-market' ),
				'sent'    => __( 'Thank you. We will contact you shortly.', 'giga-class-market' ),
				'error'   => __( 'Something went wrong. Please try again.', 'giga-class-market' ),
			),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'gcm_enqueue_assets' );

/**
 * Theme customizer for About / CEO / Team content.
 *
 * @param WP_Customize_Manager $wp_customize Customizer.
 */
function gcm_customize_register( $wp_customize ) {
	$wp_customize->add_section(
		'gcm_about_ceo',
		array(
			'title'       => __( 'GCM About / CEO / Team', 'giga-class-market' ),
			'description' => __( 'You can also manage these fields (with media library uploads) under Giga Class Market → Settings → About / Team.', 'giga-class-market' ),
			'priority'    => 35,
		)
	);

	$text_fields = array(
		'gcm_ceo_name'        => array(
			'label' => __( 'CEO name', 'giga-class-market' ),
			'type'  => 'text',
		),
		'gcm_ceo_designation' => array(
			'label' => __( 'CEO designation', 'giga-class-market' ),
			'type'  => 'text',
		),
		'gcm_ceo_title'       => array(
			'label' => __( 'CEO message title', 'giga-class-market' ),
			'type'  => 'text',
		),
		'gcm_ceo_message'     => array(
			'label' => __( 'CEO message', 'giga-class-market' ),
			'type'  => 'textarea',
		),
		'gcm_team_name'       => array(
			'label' => __( 'Team member name', 'giga-class-market' ),
			'type'  => 'text',
		),
		'gcm_team_role'       => array(
			'label' => __( 'Team member role', 'giga-class-market' ),
			'type'  => 'text',
		),
		'gcm_team_bio'        => array(
			'label' => __( 'Team member intro', 'giga-class-market' ),
			'type'  => 'textarea',
		),
		'about_mission'       => array(
			'label' => __( 'Mission text', 'giga-class-market' ),
			'type'  => 'textarea',
		),
		'about_vision'        => array(
			'label' => __( 'Vision text', 'giga-class-market' ),
			'type'  => 'textarea',
		),
	);

	foreach ( $text_fields as $id => $field ) {
		$sanitize = 'textarea' === $field['type'] ? 'sanitize_textarea_field' : 'sanitize_text_field';
		$wp_customize->add_setting(
			$id,
			array(
				'default'           => '',
				'sanitize_callback' => $sanitize,
			)
		);
		$wp_customize->add_control(
			$id,
			array(
				'label'   => $field['label'],
				'section' => 'gcm_about_ceo',
				'type'    => $field['type'],
			)
		);
	}

	$media_fields = array(
		'gcm_ceo_photo_id'  => __( 'CEO photo (media library)', 'giga-class-market' ),
		'gcm_team_photo_id' => __( 'Team member photo (media library)', 'giga-class-market' ),
	);

	foreach ( $media_fields as $id => $label ) {
		$wp_customize->add_setting(
			$id,
			array(
				'default'           => 0,
				'sanitize_callback' => 'absint',
			)
		);
		$wp_customize->add_control(
			new WP_Customize_Media_Control(
				$wp_customize,
				$id,
				array(
					'label'     => $label,
					'section'   => 'gcm_about_ceo',
					'mime_type' => 'image',
				)
			)
		);
	}
}
add_action( 'customize_register', 'gcm_customize_register' );

/**
 * Read plugin settings, if available.
 *
 * @return array
 */
function gcm_get_settings() {
	$settings = array();

	if ( class_exists( 'GCM_Settings_Service' ) ) {
		$raw = GCM_Settings_Service::get_settings();
		if ( is_array( $raw ) ) {
			$settings = $raw;
		}
	} else {
		$option = get_option( 'gcm_settings', array() );
		if ( is_array( $option ) ) {
			$settings = $option;
		}
	}

	// Flatten nested settings for theme convenience.
	$flat = $settings;
	if ( ! empty( $settings['company'] ) && is_array( $settings['company'] ) ) {
		$flat['company_name']     = $settings['company']['name'] ?? '';
		$inbox                    = $settings['company']['inbox_email'] ?? '';
		$from                     = $settings['company']['email'] ?? '';
		$flat['contact_email']    = $inbox ? $inbox : $from;
		$flat['from_email']       = $from;
		$flat['inbox_email']      = $inbox ? $inbox : 'info@gigaclassmarket.com';
		$flat['contact_phone']    = $settings['company']['phone'] ?? '';
		$flat['whatsapp_number']  = $settings['company']['whatsapp'] ?? '';
		$flat['company_address']  = $settings['company']['address'] ?? '';
		$flat['business_hours']   = $settings['company']['hours'] ?? ( $flat['business_hours'] ?? '' );
		$flat['social_facebook']  = $settings['company']['facebook'] ?? ( $flat['social_facebook'] ?? '' );
		$flat['social_instagram'] = $settings['company']['instagram'] ?? ( $flat['social_instagram'] ?? '' );
		$flat['social_linkedin']  = $settings['company']['linkedin'] ?? ( $flat['social_linkedin'] ?? '' );
		$flat['social_youtube']   = $settings['company']['youtube'] ?? ( $flat['social_youtube'] ?? '' );
	}
	if ( ! empty( $settings['website'] ) && is_array( $settings['website'] ) ) {
		$flat['currency_symbol']   = $settings['website']['currency_symbol'] ?? ( $flat['currency_symbol'] ?? 'PKR ' );
		$flat['popup_enabled']     = ! empty( $settings['website']['popup_enabled'] ) ? 1 : 0;
		$flat['popup_image_id']    = absint( $settings['website']['popup_image_id'] ?? 0 );
		$flat['popup_link_url']    = $settings['website']['popup_link_url'] ?? '';
		$flat['website']           = $settings['website'];
	}
	if ( ! empty( $settings['about'] ) && is_array( $settings['about'] ) ) {
		$flat['gcm_ceo_name']        = $settings['about']['ceo_name'] ?? ( $flat['gcm_ceo_name'] ?? '' );
		$flat['gcm_ceo_designation'] = $settings['about']['ceo_designation'] ?? ( $flat['gcm_ceo_designation'] ?? '' );
		$flat['gcm_ceo_title']       = $settings['about']['ceo_title'] ?? ( $flat['gcm_ceo_title'] ?? '' );
		$flat['gcm_ceo_message']     = $settings['about']['ceo_message'] ?? ( $flat['gcm_ceo_message'] ?? '' );
		$flat['gcm_ceo_photo_id']    = absint( $settings['about']['ceo_photo_id'] ?? 0 );
		$flat['gcm_team_name']       = $settings['about']['team_name'] ?? ( $flat['gcm_team_name'] ?? '' );
		$flat['gcm_team_role']       = $settings['about']['team_role'] ?? ( $flat['gcm_team_role'] ?? '' );
		$flat['gcm_team_bio']        = $settings['about']['team_bio'] ?? ( $flat['gcm_team_bio'] ?? '' );
		$flat['gcm_team_photo_id']   = absint( $settings['about']['team_photo_id'] ?? 0 );
		$flat['about']               = $settings['about'];
	}

	return $flat;
}

/**
 * Fetch a theme mod first, then a plugin option.
 *
 * @param string $key Setting key.
 * @param mixed  $default Default value.
 * @return mixed
 */
function gcm_setting( $key, $default = '' ) {
	$mod = get_theme_mod( $key, null );
	if ( null !== $mod && '' !== $mod ) {
		return $mod;
	}

	$settings = gcm_get_settings();
	return isset( $settings[ $key ] ) && '' !== $settings[ $key ] ? $settings[ $key ] : $default;
}

/**
 * Resolve an image URL from a media attachment setting, optional legacy URL, then fallback.
 *
 * @param string $id_key Attachment ID setting key.
 * @param string $url_key Optional legacy URL setting key.
 * @param string $fallback Fallback URL.
 * @return string
 */
function gcm_resolve_image_url( $id_key, $url_key = '', $fallback = '' ) {
	$candidates = array();

	$mod_id = absint( get_theme_mod( $id_key, 0 ) );
	if ( $mod_id ) {
		$candidates[] = $mod_id;
	}

	$settings = gcm_get_settings();
	$opt_id   = absint( $settings[ $id_key ] ?? 0 );
	if ( $opt_id && $opt_id !== $mod_id ) {
		$candidates[] = $opt_id;
	}

	foreach ( $candidates as $id ) {
		$url = wp_get_attachment_image_url( $id, 'large' );
		if ( $url ) {
			return $url;
		}
	}

	if ( $url_key ) {
		$url = (string) gcm_setting( $url_key, '' );
		if ( $url ) {
			return $url;
		}
	}

	return $fallback;
}

/**
 * CEO content for the About page.
 *
 * @return array{name:string,designation:string,title:string,message:string,photo:string}
 */
function gcm_get_about_ceo() {
	return array(
		'name'        => (string) gcm_setting( 'gcm_ceo_name', __( 'Qasim Hussaini', 'giga-class-market' ) ),
		'designation' => (string) gcm_setting( 'gcm_ceo_designation', __( 'CEO, Giga Class Market', 'giga-class-market' ) ),
		'title'       => (string) gcm_setting( 'gcm_ceo_title', __( 'Learning should feel as premium as the future it creates.', 'giga-class-market' ) ),
		'message'     => (string) gcm_setting( 'gcm_ceo_message', __( 'We built Giga Class Market for students who want clarity, elegant study experiences, and real skill progress. Our promise is simple: every course journey should help you move forward with confidence.', 'giga-class-market' ) ),
		'photo'       => gcm_resolve_image_url( 'gcm_ceo_photo_id', 'gcm_ceo_photo', '' ),
	);
}

/**
 * Core team member content for the About page.
 *
 * @return array{name:string,role:string,bio:string,photo:string}
 */
function gcm_get_about_team_member() {
	$default_photo = trailingslashit( GCM_THEME_URI ) . 'assets/images/team/navyan-baig.jpg';

	return array(
		'name'  => (string) gcm_setting( 'gcm_team_name', __( 'Navyan Baig', 'giga-class-market' ) ),
		'role'  => (string) gcm_setting( 'gcm_team_role', __( 'Web Developer', 'giga-class-market' ) ),
		'bio'   => (string) gcm_setting( 'gcm_team_bio', __( 'Navyan builds and maintains the Giga Class Market web platform — from course pages and student dashboards to smooth enrollment and payment flows — so learners get a fast, reliable experience.', 'giga-class-market' ) ),
		'photo' => gcm_resolve_image_url( 'gcm_team_photo_id', '', $default_photo ),
	);
}

/**
 * Initial theme preference from cookie.
 *
 * @return string
 */
function gcm_get_initial_theme() {
	if ( isset( $_COOKIE['gcm_theme'] ) && 'dark' === sanitize_key( wp_unslash( $_COOKIE['gcm_theme'] ) ) ) {
		return 'dark';
	}

	return 'light';
}

/**
 * Add helper body classes.
 *
 * @param array $classes Body classes.
 * @return array
 */
function gcm_body_classes( $classes ) {
	$classes[] = 'gcm-theme';

	if ( 'dark' === gcm_get_initial_theme() ) {
		$classes[] = 'gcm-dark-mode';
	}

	if ( is_page_template( 'page-templates/template-student-dashboard.php' ) || is_page_template( 'page-templates/template-course-learn.php' ) || is_page_template( 'page-templates/template-teacher-dashboard.php' ) ) {
		$classes[] = 'gcm-student-area';
	}

	if ( is_singular( 'gcm_portfolio' ) || is_page_template( 'page-templates/template-portfolio.php' ) ) {
		$classes[] = 'gcm-page-portfolio';
	}

	return $classes;
}
add_filter( 'body_class', 'gcm_body_classes' );

/**
 * Default student review avatars bundled with the theme.
 *
 * @return array[] List of name, role, quote, file, alt.
 */
function gcm_default_student_reviews() {
	$base = GCM_THEME_URI . '/assets/images/testimonials';

	return array(
		array(
			'name'  => __( 'Ayesha Khan', 'giga-class-market' ),
			'role'  => __( 'Networking Student', 'giga-class-market' ),
			'quote' => __( 'The learning experience feels polished, clear, and genuinely premium. I knew exactly what to study next.', 'giga-class-market' ),
			'file'  => 'ayesha-khan.jpg',
			'url'   => $base . '/ayesha-khan.jpg',
		),
		array(
			'name'  => __( 'Omar Farooq', 'giga-class-market' ),
			'role'  => __( 'Cyber Security Learner', 'giga-class-market' ),
			'quote' => __( 'Giga Class Market helped me build practical confidence with elegant lessons and real project direction.', 'giga-class-market' ),
			'file'  => 'omar-farooq.jpg',
			'url'   => $base . '/omar-farooq.jpg',
		),
		array(
			'name'  => __( 'Sara Ali', 'giga-class-market' ),
			'role'  => __( 'Web Development Student', 'giga-class-market' ),
			'quote' => __( 'The course structure made advanced concepts approachable without feeling watered down.', 'giga-class-market' ),
			'file'  => 'sara-ali.jpg',
			'url'   => $base . '/sara-ali.jpg',
		),
	);
}

/**
 * Resolve a student avatar URL for a review card.
 *
 * @param string $name  Student name.
 * @param int    $index Fallback index.
 * @return string
 */
function gcm_student_review_avatar_url( $name = '', $index = 0 ) {
	$reviews = gcm_default_student_reviews();
	$name_l  = strtolower( trim( (string) $name ) );

	foreach ( $reviews as $review ) {
		if ( $name_l && strtolower( $review['name'] ) === $name_l ) {
			return $review['url'];
		}
	}

	$index = absint( $index ) % max( 1, count( $reviews ) );
	return $reviews[ $index ]['url'];
}

/**
 * Sideload a local theme image into the media library.
 *
 * @param string $absolute_path Absolute file path.
 * @param int    $parent_id     Parent post ID.
 * @param string $title         Attachment title.
 * @return int Attachment ID.
 */
function gcm_sideload_theme_image( $absolute_path, $parent_id = 0, $title = '' ) {
	if ( ! file_exists( $absolute_path ) || ! is_readable( $absolute_path ) ) {
		return 0;
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$tmp = wp_tempnam( basename( $absolute_path ) );
	if ( ! $tmp || ! copy( $absolute_path, $tmp ) ) {
		return 0;
	}

	$file_array = array(
		'name'     => basename( $absolute_path ),
		'tmp_name' => $tmp,
	);

	$attachment_id = media_handle_sideload( $file_array, absint( $parent_id ), $title );
	if ( is_wp_error( $attachment_id ) ) {
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return 0;
	}

	return (int) $attachment_id;
}

/**
 * Ensure homepage student reviews exist with realistic photos.
 *
 * @return void
 */
function gcm_maybe_seed_student_reviews() {
	if ( get_option( 'gcm_student_reviews_seeded_v1' ) ) {
		return;
	}

	if ( ! post_type_exists( 'gcm_testimonial' ) ) {
		return;
	}

	$reviews = gcm_default_student_reviews();
	$existing = get_posts(
		array(
			'post_type'      => 'gcm_testimonial',
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => 20,
			'orderby'        => 'date',
			'order'          => 'ASC',
		)
	);

	// If testimonials already exist, only backfill missing featured images.
	if ( ! empty( $existing ) ) {
		foreach ( array_values( $existing ) as $i => $post ) {
			if ( has_post_thumbnail( $post->ID ) ) {
				continue;
			}
			$review = $reviews[ $i % count( $reviews ) ];
			$path   = GCM_THEME_DIR . '/assets/images/testimonials/' . $review['file'];
			$att_id = gcm_sideload_theme_image( $path, $post->ID, $review['name'] );
			if ( $att_id ) {
				set_post_thumbnail( $post->ID, $att_id );
			}
		}
		update_option( 'gcm_student_reviews_seeded_v1', 1, false );
		return;
	}

	foreach ( $reviews as $review ) {
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'gcm_testimonial',
				'post_status'  => 'publish',
				'post_title'   => $review['name'],
				'post_content' => $review['quote'],
				'post_excerpt' => $review['quote'],
			),
			true
		);
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			continue;
		}
		update_post_meta( $post_id, '_gcm_role', $review['role'] );
		update_post_meta( $post_id, '_gcm_rating', 5 );
		$path   = GCM_THEME_DIR . '/assets/images/testimonials/' . $review['file'];
		$att_id = gcm_sideload_theme_image( $path, $post_id, $review['name'] );
		if ( $att_id ) {
			set_post_thumbnail( $post_id, $att_id );
		}
	}

	update_option( 'gcm_student_reviews_seeded_v1', 1, false );
}
add_action( 'admin_init', 'gcm_maybe_seed_student_reviews' );
add_action( 'after_switch_theme', 'gcm_maybe_seed_student_reviews' );

/**
 * Meta lookup with alternate key support.
 *
 * @param int    $post_id Post ID.
 * @param string $key Short key.
 * @param mixed  $default Default value.
 * @return mixed
 */
function gcm_course_meta( $post_id, $key, $default = '' ) {
	$candidates = array(
		'_gcm_' . $key,
		'gcm_' . $key,
		$key,
	);

	foreach ( $candidates as $meta_key ) {
		$value = get_post_meta( $post_id, $meta_key, true );
		if ( '' !== $value && null !== $value ) {
			return $value;
		}
	}

	return $default;
}

/**
 * Course category label.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function gcm_course_category_label( $post_id ) {
	$taxonomies = array( 'gcm_category', 'gcm_course_category', 'category' );

	foreach ( $taxonomies as $taxonomy ) {
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			return $terms[0]->name;
		}
	}

	return __( 'Course', 'giga-class-market' );
}

/**
 * Price formatting.
 *
 * @param mixed $price Price value.
 * @return string
 */
function gcm_format_price( $price ) {
	if ( '' === $price || null === $price ) {
		return __( 'Contact for price', 'giga-class-market' );
	}

	if ( is_numeric( $price ) ) {
		$currency = gcm_setting( 'currency_symbol', '$' );
		return $currency . number_format_i18n( (float) $price, 2 );
	}

	return (string) $price;
}

/**
 * Featured courses query. Exactly the requested count is returned where available.
 *
 * @param int $limit Number of courses.
 * @return WP_Query
 */
function gcm_get_featured_courses_query( $limit = 3 ) {
	$limit = max( 1, min( 3, absint( $limit ) ) );

	if ( class_exists( 'GCM_Course_Service' ) ) {
		$courses = GCM_Course_Service::get_featured( $limit );
		if ( ! empty( $courses ) ) {
			$ids = array();
			foreach ( $courses as $course ) {
				$ids[] = absint( is_array( $course ) ? ( $course['id'] ?? 0 ) : ( $course->ID ?? 0 ) );
			}
			$ids = array_values( array_filter( $ids ) );
			if ( $ids ) {
				return new WP_Query(
					array(
						'post_type'      => 'gcm_course',
						'post__in'       => $ids,
						'orderby'        => 'post__in',
						'posts_per_page' => $limit,
					)
				);
			}
		}
	}

	return new WP_Query(
		array(
			'post_type'      => 'gcm_course',
			'posts_per_page' => $limit,
			'meta_key'       => '_gcm_featured_priority',
			'orderby'        => array(
				'meta_value_num' => 'DESC',
				'date'           => 'DESC',
			),
			'meta_query'     => array(
				array(
					'key'     => '_gcm_featured',
					'value'   => '1',
					'compare' => '=',
				),
			),
		)
	);
}

/**
 * Course purchase URL (WhatsApp gate — bank details page is not shown).
 *
 * @param int $course_id Course ID.
 * @return string
 */
function gcm_course_purchase_url( $course_id ) {
	if ( function_exists( 'gcm_get_course_purchase_url' ) ) {
		$url = gcm_get_course_purchase_url( $course_id );
		if ( $url ) {
			return $url;
		}
	}

	$url = gcm_setting( 'payment_page_url', home_url( '/get-account-details/' ) );
	return add_query_arg( 'course_id', absint( $course_id ), $url );
}

/**
 * Redirect the old bank-details payment page to the WhatsApp gate (do not delete the template).
 *
 * @return void
 */
function gcm_redirect_bank_payment_page() {
	if ( is_admin() || wp_doing_ajax() ) {
		return;
	}

	$is_bank_page = is_page( 'payment' )
		|| is_page_template( 'page-templates/template-payment.php' );

	if ( ! $is_bank_page ) {
		return;
	}

	$course_id = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$target    = home_url( '/get-account-details/' );
	if ( $course_id ) {
		$target = add_query_arg( 'course_id', $course_id, $target );
	}

	wp_safe_redirect( $target, 302 );
	exit;
}
add_action( 'template_redirect', 'gcm_redirect_bank_payment_page', 5 );

/**
 * Student login URL.
 *
 * @return string
 */
function gcm_student_login_url( $redirect = '' ) {
	if ( class_exists( 'GCM_Frontend' ) ) {
		return GCM_Frontend::get_student_login_url( $redirect );
	}
	$url = home_url( '/login/' );
	if ( $redirect ) {
		$url = add_query_arg( 'redirect_to', $redirect, $url );
	}
	return $url;
}

/**
 * Benefits for the homepage, filterable/editable through options.
 *
 * @return array
 */
function gcm_get_benefits() {
	$defaults = array(
		array( 'title' => __( 'Practical Skills', 'giga-class-market' ), 'text' => __( 'Learn high-value skills through concise lessons built for real outcomes.', 'giga-class-market' ) ),
		array( 'title' => __( 'Hands-on Learning', 'giga-class-market' ), 'text' => __( 'Practice as you learn with guided exercises and polished resources.', 'giga-class-market' ) ),
		array( 'title' => __( 'Career Development', 'giga-class-market' ), 'text' => __( 'Sharpen your path with lessons designed around professional growth.', 'giga-class-market' ) ),
	);

	$custom = gcm_setting( 'gcm_benefits', array() );
	if ( is_array( $custom ) && ! empty( $custom ) ) {
		return array_slice( $custom, 0, 3 );
	}

	return $defaults;
}

/**
 * Determine if the current template should not be indexed.
 *
 * @return bool
 */
function gcm_is_noindex_template() {
	return is_page_template(
		array(
			'page-templates/template-student-dashboard.php',
			'page-templates/template-teacher-dashboard.php',
			'page-templates/template-course-learn.php',
			'page-templates/template-payment.php',
			'page-templates/template-payment-whatsapp.php',
			'page-templates/template-payment-verify.php',
			'page-templates/template-login.php',
		)
	) || is_page( array( 'login', 'student-dashboard', 'teacher-dashboard', 'course-learn', 'payment', 'get-account-details', 'payment-verify', 'payment-verification', 'live-class' ) );
}

/**
 * Add robots directives for student/private templates.
 *
 * @param array $robots Robots directives.
 * @return array
 */
function gcm_robots_meta( $robots ) {
	if ( gcm_is_noindex_template() ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
	}

	return $robots;
}
add_filter( 'wp_robots', 'gcm_robots_meta' );

/**
 * Basic Open Graph output for public pages.
 * Skipped when the plugin SEO module is active (avoids duplicate tags).
 */
function gcm_open_graph_meta() {
	if ( class_exists( 'GCM_SEO' ) ) {
		return;
	}

	if ( is_admin() || gcm_is_noindex_template() ) {
		return;
	}

	$title       = wp_get_document_title();
	$description = get_bloginfo( 'description' );
	if ( is_singular() ) {
		$description = has_excerpt() ? get_the_excerpt() : wp_trim_words( wp_strip_all_tags( get_the_content() ), 28 );
	}
	$image = '';
	if ( is_singular() && has_post_thumbnail() ) {
		$image = get_the_post_thumbnail_url( get_the_ID(), 'large' );
	}
	?>
	<meta property="og:site_name" content="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
	<meta property="og:title" content="<?php echo esc_attr( $title ); ?>">
	<meta property="og:description" content="<?php echo esc_attr( $description ); ?>">
	<meta property="og:type" content="<?php echo is_singular() ? 'article' : 'website'; ?>">
	<meta property="og:url" content="<?php echo esc_url( home_url( add_query_arg( array(), $GLOBALS['wp']->request ?? '' ) ) ); ?>">
	<?php if ( $image ) : ?>
		<meta property="og:image" content="<?php echo esc_url( $image ); ?>">
	<?php endif; ?>
	<?php
}
add_action( 'wp_head', 'gcm_open_graph_meta', 5 );

/**
 * Format a MySQL datetime with exact date and time (including seconds).
 *
 * @param string $mysql_datetime MySQL datetime string.
 * @return string
 */
function gcm_format_exact_datetime( $mysql_datetime ) {
	if ( empty( $mysql_datetime ) ) {
		return '';
	}

	$date_format = get_option( 'date_format' );
	if ( ! is_string( $date_format ) || '' === $date_format ) {
		$date_format = 'F j, Y';
	}

	return (string) mysql2date( $date_format . ' H:i:s', $mysql_datetime );
}

/**
 * Safe service method call.
 *
 * @param string $class Class name.
 * @param string $method Method name.
 * @param array  $args Arguments.
 * @param mixed  $fallback Fallback value.
 * @return mixed
 */
function gcm_service_call( $class, $method, $args = array(), $fallback = null ) {
	if ( ! class_exists( $class ) || ! method_exists( $class, $method ) ) {
		return $fallback;
	}

	try {
		return call_user_func_array( array( $class, $method ), $args );
	} catch ( Exception $exception ) {
		return $fallback;
	}
}

/**
 * Access check for course learning pages.
 *
 * @param int $course_id Course ID.
 * @param int $user_id User ID.
 * @return bool
 */
function gcm_user_can_access_course( $course_id, $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	if ( ! $course_id || ! $user_id ) {
		return false;
	}

	$access = gcm_service_call( 'GCM_Enrollment_Service', 'user_has_access', array( $user_id, $course_id ), null );
	if ( null !== $access ) {
		return (bool) $access;
	}

	return (bool) gcm_service_call( 'GCM_Enrollment_Service', 'is_enrolled', array( $user_id, $course_id ), false );
}
