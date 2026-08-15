<?php
/**
 * Giga Class Market theme functions.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GCM_THEME_VERSION', '1.5.5' );
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
	wp_enqueue_style(
		'gcm-fonts',
		'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Outfit:wght@400;500;600;700;800&display=swap',
		array(),
		null
	);

	wp_enqueue_style(
		'gcm-main',
		GCM_THEME_URI . '/assets/css/main.css',
		array( 'gcm-fonts' ),
		GCM_THEME_VERSION
	);

	if ( is_page_template( 'page-templates/template-student-dashboard.php' ) || is_page_template( 'page-templates/template-course-learn.php' ) || is_page_template( 'page-templates/template-teacher-dashboard.php' ) ) {
		wp_enqueue_style(
			'gcm-dashboard',
			GCM_THEME_URI . '/assets/css/dashboard.css',
			array( 'gcm-main' ),
			GCM_THEME_VERSION
		);
	}

	wp_enqueue_script(
		'gcm-slider',
		GCM_THEME_URI . '/assets/js/slider.js',
		array(),
		GCM_THEME_VERSION,
		true
	);

	wp_enqueue_script(
		'gcm-main',
		GCM_THEME_URI . '/assets/js/main.js',
		array( 'gcm-slider' ),
		GCM_THEME_VERSION,
		true
	);

	wp_localize_script(
		'gcm-main',
		'gcmTheme',
		array(
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'contactNonce' => wp_create_nonce( 'gcm_contact_nonce' ),
			'progressNonce' => wp_create_nonce( 'gcm_progress_nonce' ),
			'i18n'         => array(
				'sending' => __( 'Sending...', 'giga-class-market' ),
				'sent'    => __( 'Thank you. We will contact you shortly.', 'giga-class-market' ),
				'error'   => __( 'Something went wrong. Please try again.', 'giga-class-market' ),
			),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'gcm_enqueue_assets' );

/**
 * Theme customizer for About / CEO content.
 *
 * @param WP_Customize_Manager $wp_customize Customizer.
 */
function gcm_customize_register( $wp_customize ) {
	$wp_customize->add_section(
		'gcm_about_ceo',
		array(
			'title'    => __( 'GCM About / CEO', 'giga-class-market' ),
			'priority' => 35,
		)
	);

	$fields = array(
		'gcm_ceo_name'        => __( 'CEO name', 'giga-class-market' ),
		'gcm_ceo_designation' => __( 'CEO designation', 'giga-class-market' ),
		'gcm_ceo_title'        => __( 'CEO message title', 'giga-class-market' ),
		'gcm_ceo_message'     => __( 'CEO message', 'giga-class-market' ),
		'gcm_ceo_photo'       => __( 'CEO photo URL', 'giga-class-market' ),
		'about_mission'       => __( 'Mission text', 'giga-class-market' ),
		'about_vision'        => __( 'Vision text', 'giga-class-market' ),
	);

	foreach ( $fields as $id => $label ) {
		$wp_customize->add_setting( $id, array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control(
			$id,
			array(
				'label'   => $label,
				'section' => 'gcm_about_ceo',
				'type'    => false !== strpos( $id, 'message' ) || false !== strpos( $id, 'mission' ) || false !== strpos( $id, 'vision' ) ? 'textarea' : 'text',
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
		$flat['contact_email']    = $settings['company']['email'] ?? '';
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
		$flat['currency_symbol'] = $settings['website']['currency_symbol'] ?? ( $flat['currency_symbol'] ?? 'PKR ' );
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

	return $classes;
}
add_filter( 'body_class', 'gcm_body_classes' );

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
 * Course purchase URL.
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

	$url = gcm_setting( 'payment_page_url', home_url( '/payment/' ) );
	return add_query_arg( 'course_id', absint( $course_id ), $url );
}

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
			'page-templates/template-course-learn.php',
			'page-templates/template-payment.php',
			'page-templates/template-payment-verify.php',
		)
	);
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
 */
function gcm_open_graph_meta() {
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
