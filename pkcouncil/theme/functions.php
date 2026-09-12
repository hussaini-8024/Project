<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('pkc_url')) {
    function pkc_url($path = '') {
        $path = ltrim((string) $path, '/');
        $q = '';
        if (strpos($path, '?') !== false) {
            list($path, $q) = explode('?', $path, 2);
            $q = '?' . $q;
        }
        $path = untrailingslashit($path);
        global $wp_rewrite;
        if ($wp_rewrite instanceof WP_Rewrite && $wp_rewrite->using_index_permalinks()) {
            $base = trim($wp_rewrite->root, '/');
            $url = $path === '' ? home_url('/' . $base . '/') : home_url(user_trailingslashit($base . '/' . $path));
            return $url . $q;
        }
        $url = $path === '' ? home_url('/') : home_url(user_trailingslashit($path));
        return $url . $q;
    }
}

if (!function_exists('pkc_page_url')) {
    function pkc_page_url($slug) {
        $slug = sanitize_title($slug);
        $pages = get_option('pkc_pages', array());
        if (!empty($pages[$slug])) {
            $link = get_permalink((int) $pages[$slug]);
            if ($link) {
                return $link;
            }
        }
        $page = get_page_by_path($slug);
        if ($page) {
            return get_permalink($page);
        }
        return pkc_url($slug . '/');
    }
}

if (!function_exists('pkc_login_url')) {
    function pkc_login_url($portal = 'student') {
        $url = pkc_page_url('login');
        if ($portal === 'teacher') {
            return add_query_arg('portal', 'teacher', $url);
        }
        return $url;
    }
}

add_action('init', function () {
    remove_action('template_redirect', 'wp_redirect_admin_locations', 1000);
}, 0);

add_action('template_redirect', function () {
    if (is_admin() || wp_doing_ajax() || wp_doing_cron() || is_preview()) {
        return;
    }
    $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $path = '/' . trim($path, '/');
    if ($path === '/' || strpos($path, '/index.php') !== false || strpos($path, '/wp-admin') !== false || strpos($path, '/wp-json') !== false) {
        return;
    }
    $slug = trim($path, '/');
    $known = array('courses', 'about-us', 'contact-us', 'login');
    if (in_array($slug, $known, true) && !is_page($slug)) {
        $dest = pkc_page_url($slug);
        $dest_path = (string) parse_url($dest, PHP_URL_PATH);
        if ($dest && untrailingslashit($dest_path) !== untrailingslashit($path)) {
            nocache_headers();
            wp_safe_redirect($dest . (empty($_SERVER['QUERY_STRING']) ? '' : '?' . $_SERVER['QUERY_STRING']), 302);
            exit;
        }
    }
    global $wp_rewrite;
    if (is_404() && $wp_rewrite instanceof WP_Rewrite && $wp_rewrite->using_index_permalinks()) {
        nocache_headers();
        wp_safe_redirect(home_url('/index.php' . user_trailingslashit($path)), 302);
        exit;
    }
}, 0);

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
    return array(
        home_url('/') => 'Home',
        pkc_page_url('courses') => 'Courses',
        pkc_page_url('about-us') => 'About Us',
        pkc_page_url('contact-us') => 'Contact Us',
    );
}
