<?php
if (!defined('ABSPATH')) {
    exit;
}

class PKC_REST {
    public static function register() {
        $ns = 'pkc/v1';
        register_rest_route($ns, '/auth/login', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'login'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route($ns, '/auth/logout', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'logout'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route($ns, '/auth/forgot', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'forgot'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route($ns, '/auth/reset', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'reset'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route($ns, '/me', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'me'),
            'permission_callback' => array(__CLASS__, 'logged_in'),
        ));
        register_rest_route($ns, '/me', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'update_me'),
            'permission_callback' => array(__CLASS__, 'logged_in_csrf'),
        ));
        register_rest_route($ns, '/me/password', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'change_password'),
            'permission_callback' => array(__CLASS__, 'logged_in_csrf'),
        ));
        register_rest_route($ns, '/coupons/validate', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'validate_coupon'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route($ns, '/contact', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'contact'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route($ns, '/progress', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'progress'),
            'permission_callback' => array(__CLASS__, 'student_csrf'),
        ));
        register_rest_route($ns, '/quiz/start', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'quiz_start'),
            'permission_callback' => array(__CLASS__, 'student_csrf'),
        ));
        register_rest_route($ns, '/quiz/answer', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'quiz_answer'),
            'permission_callback' => array(__CLASS__, 'student_csrf'),
        ));
        register_rest_route($ns, '/quiz/submit', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'quiz_submit'),
            'permission_callback' => array(__CLASS__, 'student_csrf'),
        ));
        register_rest_route($ns, '/quiz/heartbeat', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'quiz_heartbeat'),
            'permission_callback' => array(__CLASS__, 'student_csrf'),
        ));
        register_rest_route($ns, '/chat', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'chat_send'),
            'permission_callback' => array(__CLASS__, 'logged_in_csrf'),
        ));
        register_rest_route($ns, '/reviews', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'review'),
            'permission_callback' => array(__CLASS__, 'student_csrf'),
        ));
    }

    public static function logged_in() {
        return (bool) PKC_Auth::current();
    }

    public static function logged_in_csrf() {
        if (!PKC_Auth::current()) {
            return false;
        }
        $ok = PKC_Security::require_csrf();
        return is_wp_error($ok) ? $ok : true;
    }

    public static function student_csrf() {
        $a = PKC_Auth::current();
        if (!$a || $a->type !== 'student') {
            return false;
        }
        $ok = PKC_Security::require_csrf();
        return is_wp_error($ok) ? $ok : true;
    }

    public static function login(WP_REST_Request $req) {
        $res = PKC_Auth::login(
            sanitize_text_field($req->get_param('login')),
            (string) $req->get_param('password'),
            sanitize_text_field($req->get_param('portal') ?: 'student'),
            (bool) $req->get_param('remember')
        );
        if (is_wp_error($res)) {
            return $res;
        }
        return array('ok' => true, 'type' => $res->type, 'redirect' => $res->redirect);
    }

    public static function logout() {
        PKC_Auth::logout();
        return array('ok' => true, 'redirect' => pkc_login_url());
    }

    public static function forgot(WP_REST_Request $req) {
        PKC_Auth::request_reset(sanitize_text_field($req->get_param('login')), sanitize_text_field($req->get_param('portal') ?: 'student'));
        return array('ok' => true, 'message' => 'If an account exists, a reset link has been sent.');
    }

    public static function reset(WP_REST_Request $req) {
        $r = PKC_Auth::reset_with_token(sanitize_text_field($req->get_param('token')), (string) $req->get_param('password'));
        if (is_wp_error($r)) {
            return $r;
        }
        return array('ok' => true, 'message' => 'Password updated. You can sign in now.', 'redirect' => pkc_login_url());
    }

    public static function me() {
        $a = PKC_Auth::current();
        return array(
            'id' => (int) $a->id,
            'type' => $a->type,
            'username' => $a->username,
            'email' => $a->email,
            'full_name' => $a->full_name,
            'whatsapp' => $a->whatsapp,
            'must_change_password' => (int) $a->must_change_password,
        );
    }

    public static function update_me(WP_REST_Request $req) {
        $a = PKC_Auth::current();
        global $wpdb;
        $table = $a->type === 'teacher' ? PKC_DB::teachers() : PKC_DB::students();
        $email = sanitize_email($req->get_param('email'));
        if (!$email) {
            return new WP_Error('pkc_me', 'Enter a valid email.');
        }
        $other = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE email = %s AND id != %d", $email, (int) $a->id));
        if ($other) {
            return new WP_Error('pkc_me', 'That email is already in use.');
        }
        $data = array(
            'full_name' => sanitize_text_field($req->get_param('full_name')),
            'email'     => $email,
            'whatsapp'  => pkc_sanitize_phone($req->get_param('whatsapp')),
            'updated_at'=> PKC_DB::now(),
        );
        if ($a->type === 'student') {
            $data['address'] = sanitize_textarea_field($req->get_param('address'));
            $data['profile_bio'] = sanitize_textarea_field($req->get_param('profile_bio'));
        } else {
            $data['profile_bio'] = sanitize_textarea_field($req->get_param('profile_bio'));
        }
        $wpdb->update($table, $data, array('id' => (int) $a->id));
        return array('ok' => true);
    }

    public static function change_password(WP_REST_Request $req) {
        $a = PKC_Auth::current();
        $current = (string) $req->get_param('current_password');
        $new = (string) $req->get_param('new_password');
        if (!password_verify($current, $a->password_hash)) {
            return new WP_Error('pkc_pw', 'Current password is incorrect.');
        }
        if (strlen($new) < 8) {
            return new WP_Error('pkc_pw', 'New password must be at least 8 characters.');
        }
        PKC_Auth::set_password($a->type, (int) $a->id, $new, 0);
        PKC_Auth::issue_session($a->type, (int) $a->id, true);
        PKC_Audit::log('password_changed', $a->type, (int) $a->id);
        return array('ok' => true, 'message' => 'Password updated.');
    }

    public static function validate_coupon(WP_REST_Request $req) {
        $course = pkc_course((int) $req->get_param('course_id'));
        if (!$course) {
            return new WP_Error('pkc_coupon', 'Course not found.');
        }
        $q = PKC_Coupons::quote(sanitize_text_field($req->get_param('code')), $course, sanitize_email($req->get_param('email')));
        if (!$q['ok']) {
            return new WP_Error('pkc_coupon', $q['error']);
        }
        return array(
            'ok' => true,
            'discount' => $q['discount'],
            'total' => $q['total'],
            'original' => PKC_Coupons::course_price($course),
            'formatted_total' => pkc_format_price($q['total']),
        );
    }

    public static function contact(WP_REST_Request $req) {
        $name = sanitize_text_field($req->get_param('name'));
        $email = sanitize_email($req->get_param('email'));
        $subject = sanitize_text_field($req->get_param('subject'));
        $message = sanitize_textarea_field($req->get_param('message'));
        if (!$name || !$email || !$subject || !$message) {
            return new WP_Error('pkc_contact', 'Please complete the form.');
        }
        global $wpdb;
        $wpdb->insert(PKC_DB::contacts(), array(
            'name' => $name,
            'email' => $email,
            'whatsapp' => pkc_sanitize_phone($req->get_param('whatsapp')),
            'subject' => $subject,
            'message' => $message,
            'preferred_contact' => sanitize_text_field($req->get_param('preferred_contact')),
            'course_id' => (int) $req->get_param('course_id'),
            'status' => 'new',
            'is_read' => 0,
            'created_at' => PKC_DB::now(),
        ));
        PKC_Notifications::notify_admins('New contact message', $name . ': ' . $subject, 'contact', admin_url('admin.php?page=pkcouncil-contacts'));
        return array('ok' => true, 'message' => 'Message received. The PKCouncil team will reply soon.');
    }

    public static function progress(WP_REST_Request $req) {
        $a = PKC_Auth::current();
        $course_id = (int) $req->get_param('course_id');
        if (!PKC_Access::student_has_course((int) $a->id, $course_id)) {
            return new WP_Error('pkc_progress', 'No access.', array('status' => 403));
        }
        $event = sanitize_key($req->get_param('event'));
        $allowed = array('lesson_opened', 'lesson_completed', 'material_opened', 'pdf_accessed');
        if (!in_array($event, $allowed, true)) {
            return new WP_Error('pkc_progress', 'Invalid event.');
        }
        PKC_Progress::track((int) $a->id, $course_id, $event, (int) $req->get_param('lesson_id'), (int) $req->get_param('material_id'), $event === 'lesson_completed');
        return array('ok' => true, 'percent' => PKC_Progress::percent((int) $a->id, $course_id));
    }

    public static function quiz_start(WP_REST_Request $req) {
        $a = PKC_Auth::current();
        $attempt = PKC_Quiz::start((int) $req->get_param('quiz_id'), (int) $a->id);
        if (is_wp_error($attempt)) {
            return $attempt;
        }
        return array(
            'ok' => true,
            'attempt_id' => (int) $attempt->id,
            'expires_at' => $attempt->expires_at,
            'remaining' => PKC_Quiz::remaining_seconds($attempt),
            'questions' => PKC_Quiz::questions_for_attempt($attempt, false),
        );
    }

    public static function quiz_answer(WP_REST_Request $req) {
        $a = PKC_Auth::current();
        $attempt = PKC_Quiz::attempt_for_student((int) $req->get_param('attempt_id'), (int) $a->id);
        if (!$attempt) {
            return new WP_Error('pkc_quiz', 'Attempt not found.', array('status' => 403));
        }
        $r = PKC_Quiz::save_answer($attempt, (int) $req->get_param('question_id'), (int) $req->get_param('option_id'));
        if (is_wp_error($r)) {
            return $r;
        }
        return array('ok' => true, 'remaining' => PKC_Quiz::remaining_seconds($attempt));
    }

    public static function quiz_submit(WP_REST_Request $req) {
        $a = PKC_Auth::current();
        $attempt = PKC_Quiz::attempt_for_student((int) $req->get_param('attempt_id'), (int) $a->id);
        if (!$attempt) {
            return new WP_Error('pkc_quiz', 'Attempt not found.', array('status' => 403));
        }
        $graded = PKC_Quiz::grade((int) $attempt->id, false);
        return array(
            'ok' => true,
            'redirect' => pkc_url('student/quiz-result/' . (int) $graded->id . '/'),
            'percentage' => (float) $graded->percentage,
            'passed' => (int) $graded->passed,
        );
    }

    public static function quiz_heartbeat(WP_REST_Request $req) {
        $a = PKC_Auth::current();
        $attempt = PKC_Quiz::attempt_for_student((int) $req->get_param('attempt_id'), (int) $a->id);
        if (!$attempt) {
            return new WP_Error('pkc_quiz', 'Attempt not found.', array('status' => 403));
        }
        $remaining = PKC_Quiz::remaining_seconds($attempt);
        if ($remaining <= 0 && $attempt->status === 'in_progress') {
            $graded = PKC_Quiz::grade((int) $attempt->id, true);
            return array('ok' => true, 'remaining' => 0, 'expired' => true, 'redirect' => pkc_url('student/quiz-result/' . (int) $graded->id . '/'));
        }
        return array('ok' => true, 'remaining' => $remaining, 'expired' => false);
    }

    public static function chat_send(WP_REST_Request $req) {
        $a = PKC_Auth::current();
        $course_id = (int) $req->get_param('course_id');
        $body = $req->get_param('body');
        if ($a->type === 'student') {
            $teacher = PKC_Chat::teacher_for_course($course_id);
            if (!$teacher) {
                return new WP_Error('pkc_chat', 'No teacher is assigned to this course yet.');
            }
            $id = PKC_Chat::send($course_id, 'student', (int) $a->id, $body, (int) $a->id, (int) $teacher->id);
        } else {
            $student_id = (int) $req->get_param('student_id');
            $id = PKC_Chat::send($course_id, 'teacher', (int) $a->id, $body, $student_id, (int) $a->id);
        }
        if (is_wp_error($id)) {
            return $id;
        }
        return array('ok' => true, 'id' => $id, 'created_at' => PKC_DB::now());
    }

    public static function review(WP_REST_Request $req) {
        if (!pkc_settings('allow_student_reviews', 1)) {
            return new WP_Error('pkc_review', 'Reviews are closed.');
        }
        $a = PKC_Auth::current();
        $course_id = (int) $req->get_param('course_id');
        if (!PKC_Access::student_has_course((int) $a->id, $course_id)) {
            return new WP_Error('pkc_review', 'You can only review courses you have access to.', array('status' => 403));
        }
        $content = sanitize_textarea_field($req->get_param('content'));
        $rating = max(1, min(5, (int) $req->get_param('rating')));
        if ($content === '') {
            return new WP_Error('pkc_review', 'Write a short review.');
        }
        global $wpdb;
        $wpdb->insert(PKC_DB::reviews(), array(
            'course_id' => $course_id,
            'student_id' => (int) $a->id,
            'author_name' => $a->full_name,
            'rating' => $rating,
            'content' => $content,
            'status' => 'pending',
            'created_at' => PKC_DB::now(),
        ));
        return array('ok' => true, 'message' => 'Thank you. Your review will appear after approval.');
    }
}
