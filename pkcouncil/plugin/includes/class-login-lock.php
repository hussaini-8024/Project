<?php
if (!defined('ABSPATH')) {
    exit;
}

class PKC_Login_Lock {
    public static function init() {
        add_action('init', array(__CLASS__, 'disable_core_login_hijack'), 0);
        add_action('init', array(__CLASS__, 'block_wp_login'), 1);
        add_action('admin_init', array(__CLASS__, 'block_admin'));
        add_filter('wp_headers', array(__CLASS__, 'no_cache_auth_pages'));
        add_filter('wp_sitemaps_add_provider', array(__CLASS__, 'sitemaps'), 10, 2);
        add_filter('robots_txt', array(__CLASS__, 'robots'), 10, 2);
    }

    public static function disable_core_login_hijack() {
        remove_action('template_redirect', 'wp_redirect_admin_locations', 1000);
    }

    public static function block_wp_login() {
        global $pagenow;
        if ($pagenow !== 'wp-login.php') {
            return;
        }
        $action = $_REQUEST['action'] ?? 'login';
        if ($action === 'logout') {
            return;
        }
        if (is_user_logged_in() && current_user_can('manage_options')) {
            return;
        }
        wp_safe_redirect(pkc_page_url('login'));
        exit;
    }

    public static function block_admin() {
        if (wp_doing_ajax()) {
            return;
        }
        global $pagenow;
        // Public form posts (login, payment verification) use admin-post.php.
        if ($pagenow === 'admin-post.php') {
            return;
        }
        if (current_user_can('manage_options')) {
            return;
        }
        wp_safe_redirect(pkc_page_url('login'));
        exit;
    }

    public static function no_cache_auth_pages($headers) {
        $route = get_query_var('pkc_route');
        if (is_page('login') || in_array($route, array('forgot', 'reset', 'logout'), true)) {
            $headers['Cache-Control'] = 'private, no-store, no-cache, must-revalidate, max-age=0';
            $headers['Pragma'] = 'no-cache';
            $headers['Expires'] = 'Wed, 11 Jan 1984 05:00:00 GMT';
        }
        return $headers;
    }

    public static function sitemaps($provider, $name) {
        return $provider;
    }

    public static function robots($output, $public) {
        $output .= "\nDisallow: /student/\nDisallow: /teacher/\nDisallow: /login/\nDisallow: /pkc-file/\nDisallow: /wp-admin/\nDisallow: /wp-login.php\n";
        return $output;
    }
}
