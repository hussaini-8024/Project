<?php
if (!defined('ABSPATH')) {
    exit;
}

class PKC_Plugin {
    protected static $instance;

    public static function instance() {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        PKC_Activator::maybe_upgrade();
        add_action('init', array($this, 'boot_session'), 0);
        add_action('init', array('PKC_Rewrites', 'init'));
        add_filter('query_vars', array('PKC_Rewrites', 'query_vars'));
        add_filter('template_include', array('PKC_Rewrites', 'template'), 99);
        add_filter('document_title_parts', array('PKC_Rewrites', 'title'));
        add_action('rest_api_init', array('PKC_REST', 'register'));
        add_action('admin_post_nopriv_pkc_payment', array($this, 'handle_payment'));
        add_action('admin_post_pkc_payment', array($this, 'handle_payment'));
        add_action('admin_post_nopriv_pkc_login', array($this, 'handle_login'));
        add_action('admin_post_pkc_login', array($this, 'handle_login'));
        add_action('wp_enqueue_scripts', array($this, 'boot_js'), 20);
        add_action('wp_head', array($this, 'seo_course'), 20);
        PKC_Login_Lock::init();
        if (is_admin()) {
            PKC_Admin::instance();
        }
    }

    public function boot_session() {
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            session_start();
        }
    }

    public function handle_login() {
        check_admin_referer('pkc_login');
        $res = PKC_Auth::login(
            sanitize_text_field($_POST['login'] ?? ''),
            (string) ($_POST['password'] ?? ''),
            sanitize_text_field($_POST['portal'] ?? 'student'),
            !empty($_POST['remember'])
        );
        if (is_wp_error($res)) {
            pkc_flash('login_error', $res->get_error_message());
            wp_safe_redirect(pkc_url('login/?portal=' . sanitize_text_field($_POST['portal'] ?? 'student')));
            exit;
        }
        wp_safe_redirect($res->redirect);
        exit;
    }

    public function handle_payment() {
        check_admin_referer('pkc_payment');
        $id = PKC_Payments::submit($_POST, $_FILES['receipt'] ?? null);
        $slug = sanitize_title($_POST['course_slug'] ?? '');
        if (is_wp_error($id)) {
            pkc_flash('pay_error', $id->get_error_message());
            wp_safe_redirect(pkc_url('course/' . $slug . '/buy/'));
            exit;
        }
        pkc_flash('pay_ok', 'Payment submitted. PKCouncil will verify it shortly. You will receive access after approval.');
        wp_safe_redirect(pkc_url('course/' . $slug . '/buy/?submitted=1'));
        exit;
    }

    public function boot_js() {
        $acc = PKC_Auth::current();
        wp_register_script('pkc-boot', false, array(), PKC_VERSION, true);
        wp_enqueue_script('pkc-boot');
        wp_add_inline_script('pkc-boot', 'window.pkcData=' . wp_json_encode(array(
            'rest' => esc_url_raw(rest_url('pkc/v1/')),
            'nonce' => $acc->csrf ?? '',
            'home' => home_url('/'),
            'loggedIn' => (bool) $acc,
            'role' => $acc->type ?? '',
            'mustChange' => $acc ? (int) $acc->must_change_password : 0,
        )) . ';', 'before');
    }

    public function seo_course() {
        if (get_query_var('pkc_route') !== 'course') {
            return;
        }
        $c = pkc_course(get_query_var('pkc_slug'));
        if (!$c) {
            return;
        }
        $data = array(
            '@context' => 'https://schema.org',
            '@type' => 'Course',
            'name' => $c->title,
            'description' => wp_strip_all_tags($c->excerpt ?: $c->description),
            'provider' => array(
                '@type' => 'Organization',
                'name' => 'PKCouncil',
                'url' => home_url('/'),
            ),
            'offers' => array(
                '@type' => 'Offer',
                'price' => PKC_Coupons::course_price($c),
                'priceCurrency' => 'PKR',
                'url' => pkc_url('course/' . $c->slug . '/'),
            ),
        );
        echo '<script type="application/ld+json">' . wp_json_encode($data) . '</script>';
    }
}
