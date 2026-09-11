<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Central table-name helper for PKCouncil custom tables.
 */
class PKC_DB {
    public static function t($name) {
        global $wpdb;
        return $wpdb->prefix . 'pkc_' . $name;
    }

    public static function students() { return self::t('students'); }
    public static function teachers() { return self::t('teachers'); }
    public static function categories() { return self::t('categories'); }
    public static function courses() { return self::t('courses'); }
    public static function modules() { return self::t('modules'); }
    public static function lessons() { return self::t('lessons'); }
    public static function materials() { return self::t('materials'); }
    public static function student_courses() { return self::t('student_courses'); }
    public static function teacher_courses() { return self::t('teacher_courses'); }
    public static function payment_methods() { return self::t('payment_methods'); }
    public static function payments() { return self::t('payments'); }
    public static function coupons() { return self::t('coupons'); }
    public static function coupon_courses() { return self::t('coupon_courses'); }
    public static function coupon_usage() { return self::t('coupon_usage'); }
    public static function questions() { return self::t('questions'); }
    public static function question_options() { return self::t('question_options'); }
    public static function quizzes() { return self::t('quizzes'); }
    public static function quiz_attempts() { return self::t('quiz_attempts'); }
    public static function quiz_answers() { return self::t('quiz_answers'); }
    public static function progress() { return self::t('progress'); }
    public static function messages() { return self::t('messages'); }
    public static function reviews() { return self::t('reviews'); }
    public static function contacts() { return self::t('contacts'); }
    public static function notifications() { return self::t('notifications'); }
    public static function audit_logs() { return self::t('audit_logs'); }
    public static function sessions() { return self::t('sessions'); }
    public static function login_attempts() { return self::t('login_attempts'); }
    public static function password_resets() { return self::t('password_resets'); }

    public static function now() {
        return current_time('mysql');
    }
}
