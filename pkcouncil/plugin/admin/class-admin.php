<?php
if (!defined('ABSPATH')) {
    exit;
}

class PKC_Admin {
    protected static $instance;

    public static function instance() {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('admin_menu', array($this, 'menu'));
        add_action('admin_enqueue_scripts', array($this, 'assets'));
        add_action('admin_init', array($this, 'handle'));
        add_action('admin_bar_menu', array($this, 'bar'), 80);
    }

    public function bar($bar) {
        $bar->add_node(array(
            'id' => 'pkcouncil',
            'title' => 'PKCouncil',
            'href' => admin_url('admin.php?page=pkcouncil'),
        ));
    }

    public function assets($hook) {
        if (strpos((string) $hook, 'pkcouncil') === false) {
            return;
        }
        wp_enqueue_style('pkc-admin', PKC_URL . 'admin/assets/admin.css', array(), PKC_VERSION);
    }

    public function menu() {
        add_menu_page('PKCouncil', 'PKCouncil', 'manage_options', 'pkcouncil', array($this, 'dashboard'), 'dashicons-welcome-learn-more', 3);
        add_submenu_page('pkcouncil', 'Dashboard', 'Dashboard', 'manage_options', 'pkcouncil', array($this, 'dashboard'));
        add_submenu_page('pkcouncil', 'Website', 'Website', 'manage_options', 'pkcouncil-website', array($this, 'website'));
        add_submenu_page('pkcouncil', 'Courses', 'Courses', 'manage_options', 'pkcouncil-courses', array($this, 'courses'));
        add_submenu_page('pkcouncil', 'Students', 'Students', 'manage_options', 'pkcouncil-students', array($this, 'students'));
        add_submenu_page('pkcouncil', 'Teachers', 'Teachers', 'manage_options', 'pkcouncil-teachers', array($this, 'teachers'));
        add_submenu_page('pkcouncil', 'Payments', 'Payments', 'manage_options', 'pkcouncil-payments', array($this, 'payments'));
        add_submenu_page('pkcouncil', 'Quiz Bank', 'Quiz Bank', 'manage_options', 'pkcouncil-questions', array($this, 'questions'));
        add_submenu_page('pkcouncil', 'Quizzes', 'Quizzes', 'manage_options', 'pkcouncil-quizzes', array($this, 'quizzes'));
        add_submenu_page('pkcouncil', 'Reviews', 'Reviews', 'manage_options', 'pkcouncil-reviews', array($this, 'reviews'));
        add_submenu_page('pkcouncil', 'Coupons', 'Coupons', 'manage_options', 'pkcouncil-coupons', array($this, 'coupons'));
        add_submenu_page('pkcouncil', 'Contacts', 'Contacts', 'manage_options', 'pkcouncil-contacts', array($this, 'contacts'));
        add_submenu_page('pkcouncil', 'Messages', 'Messages', 'manage_options', 'pkcouncil-messages', array($this, 'messages'));
        add_submenu_page('pkcouncil', 'Audit log', 'Audit log', 'manage_options', 'pkcouncil-audit', array($this, 'audit'));
        add_submenu_page('pkcouncil', 'Settings', 'Settings', 'manage_options', 'pkcouncil-settings', array($this, 'settings_page'));
    }

    public function handle() {
        if (!current_user_can('manage_options') || empty($_POST['pkc_admin_action'])) {
            return;
        }
        check_admin_referer('pkc_admin');
        $act = sanitize_key($_POST['pkc_admin_action']);
        global $wpdb;
        $now = PKC_DB::now();

        if ($act === 'save_website' || $act === 'save_settings') {
            $map = array('site_name','tagline','currency_symbol','whatsapp','email','address','phone','facebook','instagram','youtube','footer_about','hero_kicker','hero_title','hero_subtitle','hero_cta_primary','hero_cta_secondary','learn_heading','learn_intro','why_heading','why_intro','contact_cta_title','contact_cta_text','default_student_pass','default_teacher_pass');
            $data = array();
            foreach ($map as $k) {
                if (isset($_POST[$k])) {
                    $data[$k] = wp_unslash($_POST[$k]);
                    if (!in_array($k, array('footer_about','hero_subtitle','learn_intro','why_intro','contact_cta_text'), true)) {
                        $data[$k] = sanitize_text_field($data[$k]);
                    } else {
                        $data[$k] = sanitize_textarea_field($data[$k]);
                    }
                }
            }
            $data['force_password_change'] = empty($_POST['force_password_change']) ? 0 : 1;
            $data['allow_student_reviews'] = empty($_POST['allow_student_reviews']) ? 0 : 1;
            $data['notify_email'] = empty($_POST['notify_email']) ? 0 : 1;
            if (!empty($_POST['learn_items_json'])) {
                $items = json_decode(wp_unslash($_POST['learn_items_json']), true);
                if (is_array($items)) {
                    $data['learn_items'] = $items;
                }
            }
            if (!empty($_POST['why_items_json'])) {
                $items = json_decode(wp_unslash($_POST['why_items_json']), true);
                if (is_array($items)) {
                    $data['why_items'] = $items;
                }
            }
            if (!empty($_POST['about']) && is_array($_POST['about'])) {
                $about = array();
                foreach (wp_unslash($_POST['about']) as $k => $v) {
                    $about[sanitize_key($k)] = sanitize_textarea_field($v);
                }
                $data['about'] = array_merge(pkc_settings('about', array()), $about);
            }
            PKC_Settings::save($data);
            PKC_Audit::log('settings_updated', 'settings', 0);
            $this->notice('Saved.');
        }

        if ($act === 'save_course') {
            $id = (int) ($_POST['id'] ?? 0);
            $title = sanitize_text_field($_POST['title'] ?? '');
            $slug = sanitize_title($_POST['slug'] ?? $title);
            $row = array(
                'title' => $title,
                'slug' => $slug,
                'excerpt' => sanitize_textarea_field($_POST['excerpt'] ?? ''),
                'description' => wp_kses_post(wp_unslash($_POST['description'] ?? '')),
                'overview' => wp_kses_post(wp_unslash($_POST['overview'] ?? '')),
                'category_id' => (int) ($_POST['category_id'] ?? 0),
                'price' => (float) ($_POST['price'] ?? 0),
                'level' => sanitize_text_field($_POST['level'] ?? 'intermediate'),
                'course_type' => sanitize_text_field($_POST['course_type'] ?? 'self-paced'),
                'is_featured' => empty($_POST['is_featured']) ? 0 : 1,
                'is_top' => empty($_POST['is_top']) ? 0 : 1,
                'status' => sanitize_text_field($_POST['status'] ?? 'published'),
                'what_you_learn' => wp_json_encode(array_values(array_filter(array_map('sanitize_text_field', preg_split('/\r\n|\r|\n/', (string) ($_POST['what_you_learn'] ?? '')))))),
                'updated_at' => $now,
            );
            if ($id) {
                $wpdb->update(PKC_DB::courses(), $row, array('id' => $id));
            } else {
                $row['created_at'] = $now;
                $wpdb->insert(PKC_DB::courses(), $row);
                $id = (int) $wpdb->insert_id;
            }
            $wpdb->delete(PKC_DB::teacher_courses(), array('course_id' => $id));
            $tid = (int) ($_POST['teacher_id'] ?? 0);
            if ($tid) {
                PKC_Access::assign_teacher($tid, $id);
            }
            PKC_Audit::log('course_saved', 'course', $id);
            wp_safe_redirect(admin_url('admin.php?page=pkcouncil-courses&edit=' . $id . '&saved=1'));
            exit;
        }

        if ($act === 'delete_course') {
            $id = (int) $_POST['id'];
            $wpdb->delete(PKC_DB::courses(), array('id' => $id));
            PKC_Audit::log('course_deleted', 'course', $id);
            $this->notice('Course deleted.');
        }

        if ($act === 'save_student') {
            $id = (int) ($_POST['id'] ?? 0);
            $row = array(
                'full_name' => sanitize_text_field($_POST['full_name'] ?? ''),
                'email' => sanitize_email($_POST['email'] ?? ''),
                'whatsapp' => pkc_sanitize_phone($_POST['whatsapp'] ?? ''),
                'address' => sanitize_textarea_field($_POST['address'] ?? ''),
                'status' => sanitize_text_field($_POST['status'] ?? 'active'),
                'updated_at' => $now,
            );
            if ($id) {
                $wpdb->update(PKC_DB::students(), $row, array('id' => $id));
            } else {
                $row['username'] = pkc_generate_username($row['full_name'], PKC_DB::students());
                $pass = (string) pkc_settings('default_student_pass', 'Student@PKCouncil');
                $row['password_hash'] = PKC_Auth::hash_password($pass);
                $row['must_change_password'] = 1;
                $row['created_at'] = $now;
                $wpdb->insert(PKC_DB::students(), $row);
                $id = (int) $wpdb->insert_id;
                PKC_Mailer::credentials($row['email'], $row['full_name'], $row['username'], $pass, 'student');
                PKC_Audit::log('student_created', 'student', $id);
            }
            $course_id = (int) ($_POST['grant_course_id'] ?? 0);
            if ($course_id) {
                PKC_Access::grant_course($id, $course_id);
            }
            $this->notice('Student saved.');
        }

        if ($act === 'reset_student_password') {
            $id = (int) $_POST['id'];
            $pass = (string) pkc_settings('default_student_pass', 'Student@PKCouncil');
            PKC_Auth::set_password('student', $id, $pass, 1);
            $s = PKC_Auth::load_account('student', $id);
            if ($s) {
                PKC_Mailer::credentials($s->email, $s->full_name, $s->username, $pass, 'student');
            }
            PKC_Audit::log('password_reset', 'student', $id);
            $this->notice('Password reset and emailed.');
        }

        if ($act === 'revoke_course') {
            PKC_Access::revoke_course((int) $_POST['student_id'], (int) $_POST['course_id']);
            $this->notice('Access revoked.');
        }

        if ($act === 'save_teacher') {
            $id = (int) ($_POST['id'] ?? 0);
            $row = array(
                'full_name' => sanitize_text_field($_POST['full_name'] ?? ''),
                'email' => sanitize_email($_POST['email'] ?? ''),
                'whatsapp' => pkc_sanitize_phone($_POST['whatsapp'] ?? ''),
                'profile_bio' => sanitize_textarea_field($_POST['profile_bio'] ?? ''),
                'status' => sanitize_text_field($_POST['status'] ?? 'active'),
                'updated_at' => $now,
            );
            if ($id) {
                $wpdb->update(PKC_DB::teachers(), $row, array('id' => $id));
            } else {
                $row['username'] = pkc_generate_username($row['full_name'], PKC_DB::teachers());
                $pass = (string) pkc_settings('default_teacher_pass', 'Teacher@PKCouncil');
                $row['password_hash'] = PKC_Auth::hash_password($pass);
                $row['must_change_password'] = 1;
                $row['created_at'] = $now;
                $wpdb->insert(PKC_DB::teachers(), $row);
                $id = (int) $wpdb->insert_id;
                PKC_Mailer::credentials($row['email'], $row['full_name'], $row['username'], $pass, 'teacher');
                PKC_Audit::log('teacher_created', 'teacher', $id);
            }
            $wpdb->delete(PKC_DB::teacher_courses(), array('teacher_id' => $id));
            $cids = array_map('intval', (array) ($_POST['course_ids'] ?? array()));
            foreach ($cids as $cid) {
                if ($cid) {
                    PKC_Access::assign_teacher($id, $cid);
                }
            }
            $this->notice('Teacher saved.');
        }

        if ($act === 'reset_teacher_password') {
            $id = (int) $_POST['id'];
            $pass = (string) pkc_settings('default_teacher_pass', 'Teacher@PKCouncil');
            PKC_Auth::set_password('teacher', $id, $pass, 1);
            $t = PKC_Auth::load_account('teacher', $id);
            if ($t) {
                PKC_Mailer::credentials($t->email, $t->full_name, $t->username, $pass, 'teacher');
            }
            PKC_Audit::log('password_reset', 'teacher', $id);
            $this->notice('Password reset and emailed.');
        }

        if ($act === 'approve_payment') {
            $r = PKC_Payments::approve((int) $_POST['id']);
            $this->notice(is_wp_error($r) ? $r->get_error_message() : 'Payment approved.');
        }
        if ($act === 'reject_payment') {
            $r = PKC_Payments::reject((int) $_POST['id'], sanitize_textarea_field($_POST['reason'] ?? ''));
            $this->notice(is_wp_error($r) ? $r->get_error_message() : 'Payment rejected.');
        }
        if ($act === 'create_account') {
            $r = PKC_Payments::create_student_from_payment((int) $_POST['id']);
            $this->notice(is_wp_error($r) ? $r->get_error_message() : 'Student account created and course access granted.');
        }
        if ($act === 'save_method') {
            $id = (int) ($_POST['id'] ?? 0);
            $row = array(
                'name' => sanitize_text_field($_POST['name'] ?? ''),
                'slug' => sanitize_title($_POST['slug'] ?? $_POST['name'] ?? ''),
                'instructions' => sanitize_textarea_field($_POST['instructions'] ?? ''),
                'account_details' => sanitize_textarea_field($_POST['account_details'] ?? ''),
                'is_active' => empty($_POST['is_active']) ? 0 : 1,
                'sort_order' => (int) ($_POST['sort_order'] ?? 0),
            );
            if ($id) {
                $wpdb->update(PKC_DB::payment_methods(), $row, array('id' => $id));
            } else {
                $wpdb->insert(PKC_DB::payment_methods(), $row);
            }
            $this->notice('Payment method saved.');
        }

        if ($act === 'save_question') {
            $id = (int) ($_POST['id'] ?? 0);
            $row = array(
                'course_id' => (int) ($_POST['course_id'] ?? 0),
                'topic' => sanitize_text_field($_POST['topic'] ?? ''),
                'subject' => sanitize_text_field($_POST['subject'] ?? ''),
                'question' => wp_kses_post(wp_unslash($_POST['question'] ?? '')),
                'explanation' => sanitize_textarea_field($_POST['explanation'] ?? ''),
                'marks' => (float) ($_POST['marks'] ?? 1),
                'difficulty' => sanitize_text_field($_POST['difficulty'] ?? 'medium'),
                'status' => sanitize_text_field($_POST['status'] ?? 'active'),
                'updated_at' => $now,
            );
            if ($id) {
                $wpdb->update(PKC_DB::questions(), $row, array('id' => $id));
                $wpdb->delete(PKC_DB::question_options(), array('question_id' => $id));
            } else {
                $row['created_at'] = $now;
                $wpdb->insert(PKC_DB::questions(), $row);
                $id = (int) $wpdb->insert_id;
            }
            $opts = array_map('sanitize_text_field', (array) ($_POST['options'] ?? array()));
            $correct = (int) ($_POST['correct'] ?? 0);
            foreach ($opts as $i => $ot) {
                if ($ot === '') {
                    continue;
                }
                $wpdb->insert(PKC_DB::question_options(), array(
                    'question_id' => $id, 'option_text' => $ot, 'is_correct' => ($i === $correct) ? 1 : 0, 'sort_order' => $i,
                ));
            }
            PKC_Audit::log('question_saved', 'question', $id);
            wp_safe_redirect(admin_url('admin.php?page=pkcouncil-questions&edit=' . $id . '&saved=1'));
            exit;
        }
        if ($act === 'delete_question') {
            $id = (int) $_POST['id'];
            $wpdb->delete(PKC_DB::questions(), array('id' => $id));
            $wpdb->delete(PKC_DB::question_options(), array('question_id' => $id));
            PKC_Audit::log('question_deleted', 'question', $id);
            $this->notice('Question deleted.');
        }
        if ($act === 'duplicate_question') {
            $id = (int) $_POST['id'];
            $q = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . PKC_DB::questions() . ' WHERE id = %d', $id));
            if ($q) {
                $copy = (array) $q;
                unset($copy['id']);
                $copy['question'] = $q->question . ' (copy)';
                $copy['created_at'] = $now;
                $copy['updated_at'] = $now;
                $wpdb->insert(PKC_DB::questions(), $copy);
                $nid = (int) $wpdb->insert_id;
                $opts = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . PKC_DB::question_options() . ' WHERE question_id = %d', $id));
                foreach ($opts as $o) {
                    $wpdb->insert(PKC_DB::question_options(), array(
                        'question_id' => $nid, 'option_text' => $o->option_text, 'is_correct' => $o->is_correct, 'sort_order' => $o->sort_order,
                    ));
                }
            }
            $this->notice('Question duplicated.');
        }

        if ($act === 'save_quiz') {
            $id = (int) ($_POST['id'] ?? 0);
            $row = array(
                'course_id' => (int) ($_POST['course_id'] ?? 0),
                'title' => sanitize_text_field($_POST['title'] ?? ''),
                'description' => sanitize_textarea_field($_POST['description'] ?? ''),
                'question_count' => (int) ($_POST['question_count'] ?? 10),
                'time_limit_minutes' => (int) ($_POST['time_limit_minutes'] ?? 30),
                'passing_percent' => (float) ($_POST['passing_percent'] ?? 50),
                'max_attempts' => (int) ($_POST['max_attempts'] ?? 0),
                'randomize_questions' => empty($_POST['randomize_questions']) ? 0 : 1,
                'randomize_options' => empty($_POST['randomize_options']) ? 0 : 1,
                'show_explanations' => sanitize_text_field($_POST['show_explanations'] ?? 'after_submit'),
                'exclude_attempted' => sanitize_text_field($_POST['exclude_attempted'] ?? 'deprioritize'),
                'exclude_days' => (int) ($_POST['exclude_days'] ?? 14),
                'allow_repeat_when_exhausted' => empty($_POST['allow_repeat_when_exhausted']) ? 0 : 1,
                'status' => sanitize_text_field($_POST['status'] ?? 'published'),
            );
            if ($id) {
                $wpdb->update(PKC_DB::quizzes(), $row, array('id' => $id));
            } else {
                $row['created_at'] = $now;
                $wpdb->insert(PKC_DB::quizzes(), $row);
            }
            PKC_Audit::log('quiz_saved', 'quiz', $id);
            $this->notice('Quiz saved.');
        }

        if ($act === 'save_review') {
            $id = (int) ($_POST['id'] ?? 0);
            $row = array(
                'author_name' => sanitize_text_field($_POST['author_name'] ?? ''),
                'content' => sanitize_textarea_field($_POST['content'] ?? ''),
                'rating' => max(1, min(5, (int) ($_POST['rating'] ?? 5))),
                'course_id' => (int) ($_POST['course_id'] ?? 0),
                'status' => sanitize_text_field($_POST['status'] ?? 'approved'),
                'is_featured' => empty($_POST['is_featured']) ? 0 : 1,
                'show_on_homepage' => empty($_POST['show_on_homepage']) ? 0 : 1,
            );
            if ($id) {
                $wpdb->update(PKC_DB::reviews(), $row, array('id' => $id));
            } else {
                $row['created_at'] = $now;
                $wpdb->insert(PKC_DB::reviews(), $row);
            }
            $this->notice('Review saved.');
        }
        if ($act === 'delete_review') {
            $wpdb->delete(PKC_DB::reviews(), array('id' => (int) $_POST['id']));
            $this->notice('Review deleted.');
        }

        if ($act === 'save_coupon') {
            $id = (int) ($_POST['id'] ?? 0);
            $row = array(
                'code' => strtoupper(sanitize_text_field($_POST['code'] ?? '')),
                'discount_type' => sanitize_text_field($_POST['discount_type'] ?? 'percent'),
                'discount_value' => (float) ($_POST['discount_value'] ?? 0),
                'max_uses' => (int) ($_POST['max_uses'] ?? 0),
                'max_per_user' => (int) ($_POST['max_per_user'] ?? 1),
                'min_amount' => (float) ($_POST['min_amount'] ?? 0),
                'expires_at' => sanitize_text_field($_POST['expires_at'] ?? '') ?: null,
                'is_active' => empty($_POST['is_active']) ? 0 : 1,
            );
            if ($id) {
                $wpdb->update(PKC_DB::coupons(), $row, array('id' => $id));
            } else {
                $row['created_at'] = $now;
                $wpdb->insert(PKC_DB::coupons(), $row);
                $id = (int) $wpdb->insert_id;
                PKC_Audit::log('coupon_created', 'coupon', $id);
            }
            $wpdb->delete(PKC_DB::coupon_courses(), array('coupon_id' => $id));
            foreach (array_map('intval', (array) ($_POST['course_ids'] ?? array())) as $cid) {
                if ($cid) {
                    $wpdb->insert(PKC_DB::coupon_courses(), array('coupon_id' => $id, 'course_id' => $cid));
                }
            }
            $this->notice('Coupon saved.');
        }
        if ($act === 'delete_coupon') {
            $wpdb->delete(PKC_DB::coupons(), array('id' => (int) $_POST['id']));
            $this->notice('Coupon deleted.');
        }

        if ($act === 'mark_contact') {
            $wpdb->update(PKC_DB::contacts(), array(
                'is_read' => 1,
                'status' => sanitize_text_field($_POST['status'] ?? 'read'),
            ), array('id' => (int) $_POST['id']));
            $this->notice('Updated.');
        }

        if ($act === 'save_category') {
            $wpdb->insert(PKC_DB::categories(), array(
                'name' => sanitize_text_field($_POST['name'] ?? ''),
                'slug' => sanitize_title($_POST['name'] ?? ''),
                'description' => sanitize_text_field($_POST['description'] ?? ''),
                'sort_order' => 99,
                'created_at' => $now,
            ));
            $this->notice('Category added.');
        }
    }

    protected function notice($msg) {
        set_transient('pkc_admin_notice_' . get_current_user_id(), $msg, 30);
    }

    protected function show_notice() {
        $m = get_transient('pkc_admin_notice_' . get_current_user_id());
        if ($m) {
            delete_transient('pkc_admin_notice_' . get_current_user_id());
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($m) . '</p></div>';
        }
        if (!empty($_GET['saved'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Saved.</p></div>';
        }
    }

    protected function form_start($action) {
        echo '<form method="post" class="pkc-admin-form">';
        wp_nonce_field('pkc_admin');
        echo '<input type="hidden" name="pkc_admin_action" value="' . esc_attr($action) . '">';
    }

    public function dashboard() {
        global $wpdb;
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        echo '<div class="wrap pkc-wrap"><h1>PKCouncil</h1>';
        $this->show_notice();
        $students = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . PKC_DB::students());
        $teachers = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . PKC_DB::teachers());
        $courses = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . PKC_DB::courses());
        $pending = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . PKC_DB::payments() . ' WHERE status = %s', 'pending'));
        $questions = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . PKC_DB::questions());
        $contacts = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . PKC_DB::contacts() . ' WHERE is_read = 0'));
        echo '<div class="pkc-stats">';
        foreach (array('Students' => $students, 'Teachers' => $teachers, 'Courses' => $courses, 'Pending payments' => $pending, 'MCQs' => $questions, 'Unread contacts' => $contacts) as $l => $v) {
            echo '<div class="pkc-stat"><strong>' . (int) $v . '</strong><span>' . esc_html($l) . '</span></div>';
        }
        echo '</div><p class="description">Student and teacher accounts are independent of WordPress users. Only administrators use this dashboard.</p></div>';
    }

    public function website() {
        $s = PKC_Settings::all();
        echo '<div class="wrap pkc-wrap"><h1>Website content</h1>';
        $this->show_notice();
        $this->form_start('save_website');
        echo '<h2>Hero</h2>';
        $this->field('hero_kicker', 'Kicker', $s['hero_kicker']);
        $this->field('hero_title', 'Title', $s['hero_title']);
        $this->area('hero_subtitle', 'Subtitle', $s['hero_subtitle']);
        $this->field('hero_cta_primary', 'Primary CTA', $s['hero_cta_primary']);
        $this->field('hero_cta_secondary', 'Secondary CTA', $s['hero_cta_secondary']);
        echo '<h2>What you will learn</h2>';
        $this->field('learn_heading', 'Heading', $s['learn_heading']);
        $this->area('learn_intro', 'Intro', $s['learn_intro']);
        echo '<p class="description">Items are stored as JSON array of {title,text}.</p>';
        echo '<textarea name="learn_items_json" rows="10" class="large-text code">' . esc_textarea(wp_json_encode($s['learn_items'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</textarea>';
        echo '<h2>Why PKCouncil</h2>';
        $this->field('why_heading', 'Heading', $s['why_heading']);
        $this->area('why_intro', 'Intro', $s['why_intro']);
        echo '<textarea name="why_items_json" rows="10" class="large-text code">' . esc_textarea(wp_json_encode($s['why_items'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</textarea>';
        echo '<h2>Contact CTA</h2>';
        $this->field('contact_cta_title', 'Title', $s['contact_cta_title']);
        $this->area('contact_cta_text', 'Text', $s['contact_cta_text']);
        echo '<h2>About Us</h2>';
        foreach ((array) $s['about'] as $k => $v) {
            echo '<p><label>' . esc_html($k) . '<br><textarea name="about[' . esc_attr($k) . ']" rows="3" class="large-text">' . esc_textarea($v) . '</textarea></label></p>';
        }
        echo '<h2>Footer & contact</h2>';
        $this->field('site_name', 'Site name', $s['site_name']);
        $this->area('footer_about', 'Footer about', $s['footer_about']);
        $this->field('whatsapp', 'WhatsApp (digits, country code)', $s['whatsapp']);
        $this->field('email', 'Email', $s['email']);
        $this->field('address', 'Address', $s['address']);
        $this->field('phone', 'Phone', $s['phone']);
        submit_button('Save website');
        echo '</form></div>';
    }

    public function courses() {
        global $wpdb;
        echo '<div class="wrap pkc-wrap"><h1>Courses</h1>';
        $this->show_notice();
        if (!empty($_GET['edit']) || isset($_GET['new'])) {
            $id = (int) ($_GET['edit'] ?? 0);
            $c = $id ? pkc_course($id) : null;
            $teachers = $wpdb->get_results('SELECT * FROM ' . PKC_DB::teachers() . ' ORDER BY full_name');
            $assigned = $c ? pkc_course_teachers($c->id) : array();
            $aid = $assigned ? (int) $assigned[0]->id : 0;
            $this->form_start('save_course');
            echo '<input type="hidden" name="id" value="' . (int) $id . '">';
            $this->field('title', 'Title', $c->title ?? '');
            $this->field('slug', 'Slug', $c->slug ?? '');
            $this->area('excerpt', 'Short description', $c->excerpt ?? '');
            $this->area('description', 'Description', $c->description ?? '');
            $this->area('overview', 'Overview', $c->overview ?? '');
            echo '<p><label>Category<br><select name="category_id">';
            foreach (pkc_categories() as $cat) {
                echo '<option value="' . (int) $cat->id . '" ' . selected($c->category_id ?? 0, $cat->id, false) . '>' . esc_html($cat->name) . '</option>';
            }
            echo '</select></label></p>';
            $this->field('price', 'Price', $c->price ?? '0');
            echo '<p><label>Level<br><select name="level">';
            foreach (array('beginner','intermediate','advanced') as $lv) {
                echo '<option ' . selected($c->level ?? '', $lv, false) . '>' . esc_html($lv) . '</option>';
            }
            echo '</select></label></p>';
            echo '<p><label>Type<br><input name="course_type" value="' . esc_attr($c->course_type ?? 'self-paced') . '"></label></p>';
            echo '<p><label>Teacher<br><select name="teacher_id"><option value="0">—</option>';
            foreach ($teachers as $t) {
                echo '<option value="' . (int) $t->id . '" ' . selected($aid, $t->id, false) . '>' . esc_html($t->full_name) . '</option>';
            }
            echo '</select></label></p>';
            $learn = implode("\n", pkc_json($c->what_you_learn ?? '[]'));
            $this->area('what_you_learn', 'What students will learn (one per line)', $learn);
            echo '<p><label><input type="checkbox" name="is_featured" value="1" ' . checked($c->is_featured ?? 0, 1, false) . '> Featured</label></p>';
            echo '<p><label><input type="checkbox" name="is_top" value="1" ' . checked($c->is_top ?? 0, 1, false) . '> Show on homepage (Top courses)</label></p>';
            echo '<p><label>Status<br><select name="status"><option value="published" ' . selected($c->status ?? '', 'published', false) . '>published</option><option value="draft" ' . selected($c->status ?? '', 'draft', false) . '>draft</option></select></label></p>';
            submit_button('Save course');
            echo '</form><p><a href="' . esc_url(admin_url('admin.php?page=pkcouncil-courses')) . '">Back to list</a></p></div>';
            return;
        }
        echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=pkcouncil-courses&new=1')) . '">Add course</a></p>';
        echo '<table class="widefat striped"><thead><tr><th>Title</th><th>Price</th><th>Top</th><th>Status</th><th></th></tr></thead><tbody>';
        foreach ($wpdb->get_results('SELECT * FROM ' . PKC_DB::courses() . ' ORDER BY created_at DESC') as $c) {
            echo '<tr><td>' . esc_html($c->title) . '</td><td>' . esc_html(pkc_format_price($c->price)) . '</td><td>' . ((int) $c->is_top ? 'Yes' : '') . '</td><td>' . esc_html($c->status) . '</td><td><a href="' . esc_url(admin_url('admin.php?page=pkcouncil-courses&edit=' . $c->id)) . '">Edit</a>';
            $this->tiny_action('delete_course', array('id' => $c->id), 'Delete');
            echo '</td></tr>';
        }
        echo '</tbody></table>';
        echo '<h2>Categories</h2>';
        $this->form_start('save_category');
        echo '<input name="name" placeholder="Name"> <input name="description" placeholder="Description"> ';
        submit_button('Add category', 'secondary', 'submit', false);
        echo '</form></div>';
    }

    public function students() {
        global $wpdb;
        echo '<div class="wrap pkc-wrap"><h1>Students</h1>';
        $this->show_notice();
        $id = (int) ($_GET['edit'] ?? 0);
        $s = $id ? PKC_Auth::load_account('student', $id) : null;
        echo '<h2>' . ($s ? 'Edit student' : 'Create student') . '</h2>';
        $this->form_start('save_student');
        echo '<input type="hidden" name="id" value="' . (int) $id . '">';
        $this->field('full_name', 'Full name', $s->full_name ?? '');
        $this->field('email', 'Email', $s->email ?? '');
        $this->field('whatsapp', 'WhatsApp', $s->whatsapp ?? '');
        $this->area('address', 'Address', $s->address ?? '');
        echo '<p><label>Status <select name="status"><option value="active">active</option><option value="disabled" ' . selected($s->status ?? '', 'disabled', false) . '>disabled</option></select></label></p>';
        echo '<p><label>Grant course <select name="grant_course_id"><option value="0">—</option>';
        foreach ($wpdb->get_results('SELECT id,title FROM ' . PKC_DB::courses()) as $c) {
            echo '<option value="' . (int) $c->id . '">' . esc_html($c->title) . '</option>';
        }
        echo '</select></label></p>';
        submit_button($s ? 'Update' : 'Create');
        echo '</form>';
        if ($s) {
            $this->form_start('reset_student_password');
            echo '<input type="hidden" name="id" value="' . (int) $s->id . '">';
            submit_button('Reset password', 'secondary');
            echo '</form><h3>Courses</h3><ul>';
            foreach (PKC_Access::student_courses($s->id) as $c) {
                echo '<li>' . esc_html($c->title) . ' — ' . (int) PKC_Progress::percent($s->id, $c->id) . '% ';
                $this->tiny_action('revoke_course', array('student_id' => $s->id, 'course_id' => $c->id), 'Revoke');
                echo '</li>';
            }
            echo '</ul><h3>Quiz history</h3><ul>';
            foreach (PKC_Quiz::history($s->id) as $h) {
                echo '<li>' . esc_html($h->quiz_title) . ' — ' . esc_html($h->percentage) . '% on ' . esc_html($h->submitted_at) . '</li>';
            }
            echo '</ul>';
        }
        echo '<h2>All students</h2><table class="widefat striped"><thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Status</th></tr></thead><tbody>';
        foreach ($wpdb->get_results('SELECT * FROM ' . PKC_DB::students() . ' ORDER BY created_at DESC') as $row) {
            echo '<tr><td><a href="' . esc_url(admin_url('admin.php?page=pkcouncil-students&edit=' . $row->id)) . '">' . esc_html($row->full_name) . '</a></td><td>' . esc_html($row->username) . '</td><td>' . esc_html($row->email) . '</td><td>' . esc_html($row->status) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public function teachers() {
        global $wpdb;
        echo '<div class="wrap pkc-wrap"><h1>Teachers</h1>';
        $this->show_notice();
        $id = (int) ($_GET['edit'] ?? 0);
        $t = $id ? PKC_Auth::load_account('teacher', $id) : null;
        $assigned = $t ? array_map(function ($c) { return (int) $c->id; }, PKC_Access::teacher_courses($t->id)) : array();
        $this->form_start('save_teacher');
        echo '<input type="hidden" name="id" value="' . (int) $id . '">';
        $this->field('full_name', 'Full name', $t->full_name ?? '');
        $this->field('email', 'Email', $t->email ?? '');
        $this->field('whatsapp', 'WhatsApp', $t->whatsapp ?? '');
        $this->area('profile_bio', 'Profile', $t->profile_bio ?? '');
        echo '<p><label>Status <select name="status"><option>active</option><option value="disabled" ' . selected($t->status ?? '', 'disabled', false) . '>disabled</option></select></label></p>';
        echo '<p>Assigned courses<br>';
        foreach ($wpdb->get_results('SELECT id,title FROM ' . PKC_DB::courses()) as $c) {
            echo '<label style="display:block"><input type="checkbox" name="course_ids[]" value="' . (int) $c->id . '" ' . checked(in_array((int) $c->id, $assigned, true), true, false) . '> ' . esc_html($c->title) . '</label>';
        }
        echo '</p>';
        submit_button('Save teacher');
        echo '</form>';
        if ($t) {
            $this->form_start('reset_teacher_password');
            echo '<input type="hidden" name="id" value="' . (int) $t->id . '">';
            submit_button('Reset password', 'secondary');
            echo '</form>';
        }
        echo '<table class="widefat striped"><thead><tr><th>Name</th><th>Username</th><th>Email</th></tr></thead><tbody>';
        foreach ($wpdb->get_results('SELECT * FROM ' . PKC_DB::teachers() . ' ORDER BY full_name') as $row) {
            echo '<tr><td><a href="' . esc_url(admin_url('admin.php?page=pkcouncil-teachers&edit=' . $row->id)) . '">' . esc_html($row->full_name) . '</a></td><td>' . esc_html($row->username) . '</td><td>' . esc_html($row->email) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public function payments() {
        global $wpdb;
        echo '<div class="wrap pkc-wrap"><h1>Payments</h1>';
        $this->show_notice();
        $id = (int) ($_GET['id'] ?? 0);
        if ($id) {
            $p = PKC_Payments::get($id);
            if (!$p) {
                echo '<p>Not found.</p></div>';
                return;
            }
            $course = pkc_course($p->course_id);
            $method = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . PKC_DB::payment_methods() . ' WHERE id = %d', $p->payment_method_id));
            echo '<table class="widefat"><tbody>';
            foreach (array('Name' => $p->full_name, 'Email' => $p->email, 'WhatsApp' => $p->whatsapp, 'Address' => $p->address, 'Course' => $course->title ?? '', 'Method' => $method->name ?? '', 'Txn' => $p->transaction_id, 'Original' => pkc_format_price($p->original_amount), 'Discount' => pkc_format_price($p->discount_amount), 'Amount' => pkc_format_price($p->amount), 'Status' => $p->status, 'Submitted' => $p->submitted_at) as $k => $v) {
                echo '<tr><th>' . esc_html($k) . '</th><td>' . esc_html($v) . '</td></tr>';
            }
            echo '</tbody></table>';
            if ($p->receipt_path) {
                echo '<p><a class="button" href="' . esc_url(pkc_url('pkc-receipt/' . $p->id . '/')) . '" target="_blank">View receipt</a></p>';
            }
            if ($p->status === 'pending') {
                $this->form_start('approve_payment');
                echo '<input type="hidden" name="id" value="' . (int) $p->id . '">';
                submit_button('Approve', 'primary', 'submit', false);
                echo '</form> ';
                $this->form_start('reject_payment');
                echo '<input type="hidden" name="id" value="' . (int) $p->id . '">';
                echo '<input name="reason" placeholder="Reason (optional)"> ';
                submit_button('Reject', 'secondary', 'submit', false);
                echo '</form>';
            }
            if ($p->status === 'approved' && !(int) $p->created_account_id) {
                $this->form_start('create_account');
                echo '<input type="hidden" name="id" value="' . (int) $p->id . '">';
                submit_button('Create account');
                echo '</form>';
            }
            echo '</div>';
            return;
        }
        $filter = sanitize_text_field($_GET['status'] ?? '');
        $sql = 'SELECT p.*, c.title AS course_title FROM ' . PKC_DB::payments() . ' p LEFT JOIN ' . PKC_DB::courses() . ' c ON c.id = p.course_id';
        if (in_array($filter, array('pending','approved','rejected'), true)) {
            $sql .= $wpdb->prepare(' WHERE p.status = %s', $filter);
        }
        $sql .= ' ORDER BY p.submitted_at DESC';
        echo '<p><a href="' . esc_url(admin_url('admin.php?page=pkcouncil-payments')) . '">All</a> · <a href="?page=pkcouncil-payments&status=pending">Pending</a> · <a href="?page=pkcouncil-payments&status=approved">Approved</a> · <a href="?page=pkcouncil-payments&status=rejected">Rejected</a></p>';
        echo '<table class="widefat striped"><thead><tr><th>When</th><th>Name</th><th>Course</th><th>Amount</th><th>Status</th></tr></thead><tbody>';
        foreach ($wpdb->get_results($sql) as $p) {
            echo '<tr><td>' . esc_html($p->submitted_at) . '</td><td><a href="' . esc_url(admin_url('admin.php?page=pkcouncil-payments&id=' . $p->id)) . '">' . esc_html($p->full_name) . '</a></td><td>' . esc_html($p->course_title) . '</td><td>' . esc_html(pkc_format_price($p->amount)) . '</td><td>' . esc_html($p->status) . '</td></tr>';
        }
        echo '</tbody></table><h2>Payment methods</h2>';
        foreach (pkc_payment_methods(false) as $m) {
            $this->form_start('save_method');
            echo '<input type="hidden" name="id" value="' . (int) $m->id . '">';
            echo '<p><input name="name" value="' . esc_attr($m->name) . '"> <input name="slug" value="' . esc_attr($m->slug) . '"> <label><input type="checkbox" name="is_active" ' . checked($m->is_active, 1, false) . '> active</label></p>';
            echo '<textarea name="instructions" class="large-text">' . esc_textarea($m->instructions) . '</textarea>';
            echo '<textarea name="account_details" class="large-text">' . esc_textarea($m->account_details) . '</textarea>';
            submit_button('Save method', 'secondary');
            echo '</form><hr>';
        }
        $this->form_start('save_method');
        echo '<h3>Add method</h3><input name="name" placeholder="Name"> <input name="slug" placeholder="slug">';
        echo '<textarea name="instructions" class="large-text" placeholder="Instructions"></textarea>';
        echo '<textarea name="account_details" class="large-text" placeholder="Account details"></textarea>';
        echo '<label><input type="checkbox" name="is_active" checked> active</label>';
        submit_button('Add method');
        echo '</form></div>';
    }

    public function questions() {
        global $wpdb;
        echo '<div class="wrap pkc-wrap"><h1>Quiz Bank</h1>';
        $this->show_notice();
        $id = (int) ($_GET['edit'] ?? 0);
        $q = $id ? $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . PKC_DB::questions() . ' WHERE id = %d', $id)) : null;
        $opts = $q ? $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . PKC_DB::question_options() . ' WHERE question_id = %d ORDER BY sort_order', $q->id)) : array();
        echo '<h2>' . ($q ? 'Edit MCQ' : 'Add MCQ') . '</h2>';
        $this->form_start('save_question');
        echo '<input type="hidden" name="id" value="' . (int) $id . '">';
        echo '<p><label>Course <select name="course_id"><option value="0">General bank</option>';
        foreach ($wpdb->get_results('SELECT id,title FROM ' . PKC_DB::courses()) as $c) {
            echo '<option value="' . (int) $c->id . '" ' . selected($q->course_id ?? 0, $c->id, false) . '>' . esc_html($c->title) . '</option>';
        }
        echo '</select></label></p>';
        $this->area('question', 'Question', $q->question ?? '');
        for ($i = 0; $i < 4; $i++) {
            $ot = $opts[$i]->option_text ?? '';
            $chk = isset($opts[$i]) && (int) $opts[$i]->is_correct ? 'checked' : '';
            echo '<p><label><input type="radio" name="correct" value="' . $i . '" ' . $chk . '> Correct</label> <input name="options[]" class="regular-text" value="' . esc_attr($ot) . '" placeholder="Option ' . ($i + 1) . '"></p>';
        }
        $this->area('explanation', 'Explanation', $q->explanation ?? '');
        $this->field('topic', 'Topic', $q->topic ?? '');
        $this->field('subject', 'Subject', $q->subject ?? '');
        $this->field('marks', 'Marks', $q->marks ?? '1');
        echo '<p><label>Difficulty <select name="difficulty">';
        foreach (array('easy','medium','hard') as $d) {
            echo '<option ' . selected($q->difficulty ?? 'medium', $d, false) . '>' . $d . '</option>';
        }
        echo '</select></label></p>';
        echo '<p><label>Status <select name="status"><option>active</option><option value="draft" ' . selected($q->status ?? '', 'draft', false) . '>draft</option></select></label></p>';
        submit_button('Save question');
        echo '</form>';
        $search = sanitize_text_field($_GET['s'] ?? '');
        $sql = 'SELECT * FROM ' . PKC_DB::questions() . ' WHERE 1=1';
        if ($search) {
            $sql .= $wpdb->prepare(' AND question LIKE %s', '%' . $wpdb->esc_like($search) . '%');
        }
        $sql .= ' ORDER BY id DESC LIMIT 100';
        echo '<form method="get"><input type="hidden" name="page" value="pkcouncil-questions"><input name="s" value="' . esc_attr($search) . '"> <button class="button">Search</button></form>';
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Question</th><th></th></tr></thead><tbody>';
        foreach ($wpdb->get_results($sql) as $row) {
            echo '<tr><td>' . (int) $row->id . '</td><td>' . esc_html(wp_trim_words(wp_strip_all_tags($row->question), 16)) . '</td><td><a href="?page=pkcouncil-questions&edit=' . (int) $row->id . '">Edit</a> ';
            $this->tiny_action('duplicate_question', array('id' => $row->id), 'Duplicate');
            $this->tiny_action('delete_question', array('id' => $row->id), 'Delete');
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public function quizzes() {
        global $wpdb;
        echo '<div class="wrap pkc-wrap"><h1>Quizzes</h1>';
        $this->show_notice();
        $id = (int) ($_GET['edit'] ?? 0);
        $q = $id ? $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . PKC_DB::quizzes() . ' WHERE id = %d', $id)) : null;
        $this->form_start('save_quiz');
        echo '<input type="hidden" name="id" value="' . (int) $id . '">';
        echo '<p><label>Course <select name="course_id">';
        foreach ($wpdb->get_results('SELECT id,title FROM ' . PKC_DB::courses()) as $c) {
            echo '<option value="' . (int) $c->id . '" ' . selected($q->course_id ?? 0, $c->id, false) . '>' . esc_html($c->title) . '</option>';
        }
        echo '</select></label></p>';
        $this->field('title', 'Title', $q->title ?? '');
        $this->area('description', 'Description', $q->description ?? '');
        $this->field('question_count', 'Number of questions', $q->question_count ?? 10);
        $this->field('time_limit_minutes', 'Time limit (minutes)', $q->time_limit_minutes ?? 30);
        $this->field('passing_percent', 'Passing %', $q->passing_percent ?? 50);
        $this->field('max_attempts', 'Max attempts (0 = unlimited)', $q->max_attempts ?? 0);
        echo '<p><label><input type="checkbox" name="randomize_questions" ' . checked($q->randomize_questions ?? 1, 1, false) . '> Randomize questions</label></p>';
        echo '<p><label><input type="checkbox" name="randomize_options" ' . checked($q->randomize_options ?? 1, 1, false) . '> Randomize options</label></p>';
        echo '<p><label>Previously attempted <select name="exclude_attempted">';
        foreach (array('off' => 'Allow repeats', 'deprioritize' => 'Deprioritize', 'exclude' => 'Exclude', 'after_days' => 'Exclude for N days') as $k => $l) {
            echo '<option value="' . esc_attr($k) . '" ' . selected($q->exclude_attempted ?? '', $k, false) . '>' . esc_html($l) . '</option>';
        }
        echo '</select></label></p>';
        $this->field('exclude_days', 'Exclude days', $q->exclude_days ?? 14);
        echo '<p><label><input type="checkbox" name="allow_repeat_when_exhausted" ' . checked($q->allow_repeat_when_exhausted ?? 1, 1, false) . '> Allow repeats if the pool is exhausted</label></p>';
        echo '<p><label>Explanations <select name="show_explanations"><option value="after_submit">After submit</option><option value="never" ' . selected($q->show_explanations ?? '', 'never', false) . '>Never</option></select></label></p>';
        echo '<p><label>Status <select name="status"><option>published</option><option value="draft" ' . selected($q->status ?? '', 'draft', false) . '>draft</option></select></label></p>';
        submit_button('Save quiz');
        echo '</form><table class="widefat striped"><thead><tr><th>Quiz</th><th>Questions</th><th>Time</th></tr></thead><tbody>';
        foreach ($wpdb->get_results('SELECT * FROM ' . PKC_DB::quizzes() . ' ORDER BY id DESC') as $row) {
            echo '<tr><td><a href="?page=pkcouncil-quizzes&edit=' . (int) $row->id . '">' . esc_html($row->title) . '</a></td><td>' . (int) $row->question_count . '</td><td>' . (int) $row->time_limit_minutes . ' min</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public function reviews() {
        global $wpdb;
        echo '<div class="wrap pkc-wrap"><h1>Reviews</h1>';
        $this->show_notice();
        $this->form_start('save_review');
        echo '<input type="hidden" name="id" value="' . (int) ($_GET['edit'] ?? 0) . '">';
        $r = !empty($_GET['edit']) ? $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . PKC_DB::reviews() . ' WHERE id = %d', (int) $_GET['edit'])) : null;
        $this->field('author_name', 'Author', $r->author_name ?? '');
        $this->area('content', 'Review', $r->content ?? '');
        $this->field('rating', 'Rating 1-5', $r->rating ?? 5);
        echo '<p><label>Course <select name="course_id"><option value="0">General</option>';
        foreach ($wpdb->get_results('SELECT id,title FROM ' . PKC_DB::courses()) as $c) {
            echo '<option value="' . (int) $c->id . '" ' . selected($r->course_id ?? 0, $c->id, false) . '>' . esc_html($c->title) . '</option>';
        }
        echo '</select></label></p>';
        echo '<p>Status <select name="status"><option>approved</option><option>pending</option><option>rejected</option></select></p>';
        echo '<p><label><input type="checkbox" name="is_featured" ' . checked($r->is_featured ?? 0, 1, false) . '> Featured</label></p>';
        echo '<p><label><input type="checkbox" name="show_on_homepage" ' . checked($r->show_on_homepage ?? 1, 1, false) . '> Homepage</label></p>';
        submit_button('Save review');
        echo '</form><table class="widefat striped">';
        foreach ($wpdb->get_results('SELECT * FROM ' . PKC_DB::reviews() . ' ORDER BY created_at DESC') as $row) {
            echo '<tr><td>' . esc_html($row->author_name) . '</td><td>' . esc_html(wp_trim_words($row->content, 20)) . '</td><td>' . esc_html($row->status) . '</td><td><a href="?page=pkcouncil-reviews&edit=' . (int) $row->id . '">Edit</a> ';
            $this->tiny_action('delete_review', array('id' => $row->id), 'Delete');
            echo '</td></tr>';
        }
        echo '</table></div>';
    }

    public function coupons() {
        global $wpdb;
        echo '<div class="wrap pkc-wrap"><h1>Coupons</h1>';
        $this->show_notice();
        $id = (int) ($_GET['edit'] ?? 0);
        $c = $id ? $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . PKC_DB::coupons() . ' WHERE id = %d', $id)) : null;
        $assigned = $c ? PKC_Coupons::allowed_course_ids($c->id) : array();
        $this->form_start('save_coupon');
        echo '<input type="hidden" name="id" value="' . (int) $id . '">';
        $this->field('code', 'Code', $c->code ?? '');
        echo '<p><label>Type <select name="discount_type"><option value="percent">percent</option><option value="fixed" ' . selected($c->discount_type ?? '', 'fixed', false) . '>fixed</option></select></label></p>';
        $this->field('discount_value', 'Value', $c->discount_value ?? 10);
        $this->field('max_uses', 'Max uses (0 unlimited)', $c->max_uses ?? 0);
        $this->field('max_per_user', 'Max per user', $c->max_per_user ?? 1);
        $this->field('min_amount', 'Min amount', $c->min_amount ?? 0);
        $this->field('expires_at', 'Expires (YYYY-MM-DD HH:MM:SS)', $c->expires_at ?? '');
        echo '<p><label><input type="checkbox" name="is_active" ' . checked($c->is_active ?? 1, 1, false) . '> Active</label></p>';
        echo '<p>Restrict to courses (none = all)<br>';
        foreach ($wpdb->get_results('SELECT id,title FROM ' . PKC_DB::courses()) as $course) {
            echo '<label style="display:block"><input type="checkbox" name="course_ids[]" value="' . (int) $course->id . '" ' . checked(in_array((int) $course->id, $assigned, true), true, false) . '> ' . esc_html($course->title) . '</label>';
        }
        echo '</p>';
        submit_button('Save coupon');
        echo '</form><table class="widefat striped">';
        foreach ($wpdb->get_results('SELECT * FROM ' . PKC_DB::coupons()) as $row) {
            echo '<tr><td>' . esc_html($row->code) . '</td><td>' . esc_html($row->discount_type) . ' ' . esc_html($row->discount_value) . '</td><td>used ' . (int) $row->used_count . '</td><td><a href="?page=pkcouncil-coupons&edit=' . (int) $row->id . '">Edit</a> ';
            $this->tiny_action('delete_coupon', array('id' => $row->id), 'Delete');
            echo '</td></tr>';
        }
        echo '</table></div>';
    }

    public function contacts() {
        global $wpdb;
        echo '<div class="wrap pkc-wrap"><h1>Contact messages</h1>';
        $this->show_notice();
        echo '<table class="widefat striped"><thead><tr><th>When</th><th>From</th><th>Subject</th><th>Message</th><th></th></tr></thead><tbody>';
        foreach ($wpdb->get_results('SELECT * FROM ' . PKC_DB::contacts() . ' ORDER BY created_at DESC') as $row) {
            echo '<tr><td>' . esc_html($row->created_at) . '</td><td>' . esc_html($row->name) . '<br>' . esc_html($row->email) . '<br>' . esc_html($row->whatsapp) . '</td><td>' . esc_html($row->subject) . '</td><td>' . esc_html($row->message) . '</td><td>' . ((int) $row->is_read ? 'Read' : 'Unread');
            $this->tiny_action('mark_contact', array('id' => $row->id, 'status' => 'read'), 'Mark read');
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public function messages() {
        global $wpdb;
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        echo '<div class="wrap pkc-wrap"><h1>Course conversations</h1>';
        $rows = $wpdb->get_results('SELECT m.course_id, m.student_id, m.teacher_id, MAX(m.created_at) last_at, c.title course_title, s.full_name student_name, t.full_name teacher_name
            FROM ' . PKC_DB::messages() . ' m
            JOIN ' . PKC_DB::courses() . ' c ON c.id = m.course_id
            JOIN ' . PKC_DB::students() . ' s ON s.id = m.student_id
            JOIN ' . PKC_DB::teachers() . ' t ON t.id = m.teacher_id
            GROUP BY m.course_id, m.student_id, m.teacher_id ORDER BY last_at DESC');
        echo '<table class="widefat striped"><thead><tr><th>Course</th><th>Student</th><th>Teacher</th><th>Last</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr><td>' . esc_html($r->course_title) . '</td><td>' . esc_html($r->student_name) . '</td><td>' . esc_html($r->teacher_name) . '</td><td>' . esc_html($r->last_at) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public function audit() {
        global $wpdb;
        echo '<div class="wrap pkc-wrap"><h1>Audit log</h1><table class="widefat striped">';
        foreach ($wpdb->get_results('SELECT * FROM ' . PKC_DB::audit_logs() . ' ORDER BY id DESC LIMIT 200') as $r) {
            echo '<tr><td>' . esc_html($r->created_at) . '</td><td>' . esc_html($r->actor_type) . ':' . (int) $r->actor_id . '</td><td>' . esc_html($r->action) . '</td><td>' . esc_html($r->object_type) . ' ' . (int) $r->object_id . '</td><td><code>' . esc_html($r->details) . '</code></td></tr>';
        }
        echo '</table></div>';
    }

    public function settings_page() {
        $s = PKC_Settings::all();
        echo '<div class="wrap pkc-wrap"><h1>PKCouncil settings</h1>';
        $this->show_notice();
        $this->form_start('save_settings');
        $this->field('currency_symbol', 'Currency symbol', $s['currency_symbol']);
        $this->field('default_student_pass', 'Default student password (hashed on save, never stored in student records as plaintext)', $s['default_student_pass']);
        $this->field('default_teacher_pass', 'Default teacher password', $s['default_teacher_pass']);
        echo '<p><label><input type="checkbox" name="force_password_change" ' . checked($s['force_password_change'], 1, false) . '> Force password change after first login</label></p>';
        echo '<p><label><input type="checkbox" name="allow_student_reviews" ' . checked($s['allow_student_reviews'], 1, false) . '> Allow student reviews</label></p>';
        echo '<p><label><input type="checkbox" name="notify_email" ' . checked($s['notify_email'], 1, false) . '> Send email notifications</label></p>';
        submit_button('Save settings');
        echo '</form></div>';
    }

    protected function field($name, $label, $value) {
        echo '<p><label>' . esc_html($label) . '<br><input class="regular-text" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"></label></p>';
    }

    protected function area($name, $label, $value) {
        echo '<p><label>' . esc_html($label) . '<br><textarea class="large-text" rows="4" name="' . esc_attr($name) . '">' . esc_textarea($value) . '</textarea></label></p>';
    }

    protected function tiny_action($action, $fields, $label) {
        echo '<form method="post" style="display:inline">';
        wp_nonce_field('pkc_admin');
        echo '<input type="hidden" name="pkc_admin_action" value="' . esc_attr($action) . '">';
        foreach ($fields as $k => $v) {
            echo '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr($v) . '">';
        }
        echo '<button class="button-link" onclick="return confirm(\'Are you sure?\')">' . esc_html($label) . '</button></form> ';
    }
}
