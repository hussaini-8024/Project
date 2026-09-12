<?php
if (!defined('ABSPATH')) {
    exit;
}

class PKC_Rewrites {
    public static function init() {
        add_rewrite_tag('%pkc_route%', '([^&]+)');
        add_rewrite_tag('%pkc_slug%', '([^&]+)');
        add_rewrite_tag('%pkc_id%', '([0-9]+)');
        add_rewrite_tag('%pkc_id2%', '([0-9]+)');
        add_rewrite_tag('%pkc_token%', '([^&]+)');

        add_rewrite_rule('^course/([^/]+)/buy/?$', 'index.php?pkc_route=checkout&pkc_slug=$matches[1]', 'top');
        add_rewrite_rule('^course/([^/]+)/?$', 'index.php?pkc_route=course&pkc_slug=$matches[1]', 'top');
        add_rewrite_rule('^logout/?$', 'index.php?pkc_route=logout', 'top');
        add_rewrite_rule('^forgot-password/?$', 'index.php?pkc_route=forgot', 'top');
        add_rewrite_rule('^reset-password/([^/]+)/?$', 'index.php?pkc_route=reset&pkc_token=$matches[1]', 'top');
        add_rewrite_rule('^pkc-file/([0-9]+)/?$', 'index.php?pkc_route=file&pkc_id=$matches[1]', 'top');
        add_rewrite_rule('^pkc-receipt/([0-9]+)/?$', 'index.php?pkc_route=receipt&pkc_id=$matches[1]', 'top');

        add_rewrite_rule('^student/course/([^/]+)/quiz/([0-9]+)/?$', 'index.php?pkc_route=student_quiz&pkc_slug=$matches[1]&pkc_id=$matches[2]', 'top');
        add_rewrite_rule('^student/course/([^/]+)/chat/?$', 'index.php?pkc_route=student_chat&pkc_slug=$matches[1]', 'top');
        add_rewrite_rule('^student/course/([^/]+)/?$', 'index.php?pkc_route=student_course&pkc_slug=$matches[1]', 'top');
        add_rewrite_rule('^student/quiz-result/([0-9]+)/?$', 'index.php?pkc_route=student_result&pkc_id=$matches[1]', 'top');
        add_rewrite_rule('^student/profile/?$', 'index.php?pkc_route=student_profile', 'top');
        add_rewrite_rule('^student/courses/?$', 'index.php?pkc_route=student_courses', 'top');
        add_rewrite_rule('^student/?$', 'index.php?pkc_route=student', 'top');

        add_rewrite_rule('^teacher/chat/([0-9]+)/([0-9]+)/?$', 'index.php?pkc_route=teacher_chat&pkc_id=$matches[1]&pkc_id2=$matches[2]', 'top');
        add_rewrite_rule('^teacher/course/([^/]+)/?$', 'index.php?pkc_route=teacher_course&pkc_slug=$matches[1]', 'top');
        add_rewrite_rule('^teacher/profile/?$', 'index.php?pkc_route=teacher_profile', 'top');
        add_rewrite_rule('^teacher/?$', 'index.php?pkc_route=teacher', 'top');
    }

    /**
     * Hosts without Apache rewrite need /index.php/... URLs.
     * WordPress core also sends /login/ to wp-login.php — send visitors to PKCouncil login instead.
     */
    public static function compat_redirects() {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = '/' . trim($path, '/');
        if ($path === '/') {
            return;
        }
        if (strpos($path, '/index.php') !== false || strpos($path, '/wp-admin') !== false || strpos($path, '/wp-content') !== false || strpos($path, '/wp-json') !== false) {
            return;
        }
        $slug = trim($path, '/');
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        $with_qs = static function ($url) use ($qs) {
            if ($qs === '' || strpos($url, '?') !== false) {
                return $url;
            }
            return $url . '?' . $qs;
        };

        if ($slug === 'login' && !is_page('login')) {
            $dest = pkc_page_url('login');
            $dest_path = (string) parse_url($dest, PHP_URL_PATH);
            if (untrailingslashit($dest_path) !== untrailingslashit($path)) {
                nocache_headers();
                wp_safe_redirect($with_qs($dest), 302);
                exit;
            }
        }

        global $wp_rewrite;
        if (!($wp_rewrite instanceof WP_Rewrite) || !$wp_rewrite->using_index_permalinks()) {
            return;
        }

        if (in_array($slug, array('courses', 'about-us', 'contact-us'), true) && !is_page($slug)) {
            $dest = pkc_page_url($slug);
            $dest_path = (string) parse_url($dest, PHP_URL_PATH);
            if (untrailingslashit($dest_path) !== untrailingslashit($path)) {
                nocache_headers();
                wp_safe_redirect($with_qs($dest), 302);
                exit;
            }
        }

        if (is_404()) {
            nocache_headers();
            wp_safe_redirect($with_qs(home_url('/index.php' . user_trailingslashit($path))), 302);
            exit;
        }
    }

    public static function query_vars($vars) {
        array_push($vars, 'pkc_route', 'pkc_slug', 'pkc_id', 'pkc_id2', 'pkc_token');
        return $vars;
    }

    public static function template($template) {
        $route = get_query_var('pkc_route');
        if (!$route) {
            return $template;
        }
        if ($route === 'file') {
            PKC_Materials::serve((int) get_query_var('pkc_id'));
        }
        if ($route === 'receipt') {
            PKC_Materials::serve_receipt((int) get_query_var('pkc_id'));
        }
        if ($route === 'logout') {
            PKC_Auth::logout();
            wp_safe_redirect(pkc_login_url());
            exit;
        }

        $map = array(
            'course'           => 'templates/course-single.php',
            'checkout'         => 'templates/checkout.php',
            'forgot'           => 'templates/forgot.php',
            'reset'            => 'templates/reset.php',
            'student'          => 'templates/student/dashboard.php',
            'student_courses'  => 'templates/student/courses.php',
            'student_course'   => 'templates/student/learn.php',
            'student_quiz'     => 'templates/quiz/take.php',
            'student_result'   => 'templates/quiz/result.php',
            'student_chat'     => 'templates/student/chat.php',
            'student_profile'  => 'templates/student/profile.php',
            'teacher'          => 'templates/teacher/dashboard.php',
            'teacher_course'   => 'templates/teacher/course.php',
            'teacher_chat'     => 'templates/teacher/chat.php',
            'teacher_profile'  => 'templates/teacher/profile.php',
        );
        if (empty($map[$route])) {
            return $template;
        }
        $private = in_array($route, array('student', 'student_courses', 'student_course', 'student_quiz', 'student_result', 'student_chat', 'student_profile'), true);
        $tprivate = in_array($route, array('teacher', 'teacher_course', 'teacher_chat', 'teacher_profile'), true);
        if ($private) {
            PKC_Auth::require_student();
        }
        if ($tprivate) {
            PKC_Auth::require_teacher();
        }
        add_action('wp_head', 'pkc_noindex', 1);
        $theme = locate_template($map[$route]);
        if ($theme) {
            return $theme;
        }
        $plugin = PKC_DIR . $map[$route];
        if (file_exists($plugin)) {
            return $plugin;
        }
        return $template;
    }

    public static function title($title) {
        $route = get_query_var('pkc_route');
        if (!$route) {
            return $title;
        }
        $names = array(
            'course' => 'Course',
            'checkout' => 'Complete payment',
            'forgot' => 'Forgot password',
            'reset' => 'Reset password',
            'student' => 'Student portal',
            'teacher' => 'Teacher portal',
            'student_quiz' => 'Quiz',
        );
        if (isset($names[$route])) {
            $title['title'] = $names[$route];
        }
        if ($route === 'course' || $route === 'checkout' || $route === 'student_course') {
            $c = pkc_course(get_query_var('pkc_slug'));
            if ($c) {
                $title['title'] = $c->title;
            }
        }
        return $title;
    }
}
