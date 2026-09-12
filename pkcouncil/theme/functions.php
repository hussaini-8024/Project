<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', array('search-form', 'comment-form', 'gallery', 'caption', 'style', 'script'));
    register_nav_menus(array('primary' => 'Primary'));
});

add_action('wp_enqueue_scripts', function () {
    $ver = defined('PKC_VERSION') ? PKC_VERSION : '1.0.0';
    wp_enqueue_style('pkc-fonts', 'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,650;9..144,700&family=Outfit:wght@400;500;600;700&display=swap', array(), null);
    wp_enqueue_style('pkc-main', get_template_directory_uri() . '/assets/css/main.css', array('pkc-fonts'), $ver);
    $route = function_exists('get_query_var') ? get_query_var('pkc_route') : '';
    $portal = (strpos((string) $route, 'student') === 0 || strpos((string) $route, 'teacher') === 0 || $route === 'student_quiz' || $route === 'student_result');
    if ($portal) {
        wp_enqueue_style('pkc-portal', get_template_directory_uri() . '/assets/css/portal.css', array('pkc-main'), $ver);
        wp_enqueue_script('pkc-portal', get_template_directory_uri() . '/assets/js/portal.js', array(), $ver, true);
    }
    if ($route === 'student_quiz') {
        wp_enqueue_script('pkc-quiz', get_template_directory_uri() . '/assets/js/quiz.js', array('pkc-portal'), $ver, true);
    }
    wp_enqueue_script('pkc-main', get_template_directory_uri() . '/assets/js/main.js', array(), $ver, true);
});

add_action('wp_head', function () {
    if (!defined('PKC_VERSION')) {
        return;
    }
    echo '<meta name="theme-color" content="#0f3d42">';
    if (is_front_page()) {
        echo '<meta name="description" content="' . esc_attr(pkc_settings('tagline')) . '">';
    }
}, 1);

function pkc_nav_items() {
    if (!function_exists('pkc_page_url')) {
        return array(
            home_url('/') => 'Home',
        );
    }
    return array(
        home_url('/') => 'Home',
        pkc_page_url('courses') => 'Courses',
        pkc_page_url('about-us') => 'About Us',
        pkc_page_url('contact-us') => 'Contact Us',
    );
}
