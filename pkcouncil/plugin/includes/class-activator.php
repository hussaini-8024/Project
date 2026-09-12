<?php
if (!defined('ABSPATH')) {
    exit;
}

class PKC_Activator {
    public static function activate() {
        self::create_tables();
        self::ensure_protected_dir();
        PKC_Settings::defaults();
        self::create_pages();
        PKC_Seed::run();
        flush_rewrite_rules();
        update_option('pkc_db_version', PKC_VERSION);
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    public static function maybe_upgrade() {
        $v = get_option('pkc_db_version');
        if ($v !== PKC_VERSION) {
            self::create_tables();
            self::create_pages();
            flush_rewrite_rules(false);
            update_option('pkc_db_version', PKC_VERSION);
        }
    }

    public static function ensure_protected_dir() {
        $dir = self::protected_dir();
        wp_mkdir_p($dir);
        if (!file_exists($dir . '/index.php')) {
            file_put_contents($dir . '/index.php', "<?php\n// Silence is golden.\n");
        }
        if (!file_exists($dir . '/.htaccess')) {
            file_put_contents($dir . "/.htaccess", "Require all denied\nDeny from all\n");
        }
        $web = $dir . '/web.config';
        if (!file_exists($web)) {
            file_put_contents($web, "<?xml version=\"1.0\"?><configuration><system.webServer><security><authorization><deny users=\"*\" /></authorization></security></system.webServer></configuration>");
        }
    }

    public static function protected_dir() {
        return WP_CONTENT_DIR . '/uploads/pkc-protected';
    }

    public static function create_pages() {
        $pages = array(
            'about-us'    => array('title' => 'About Us', 'template' => 'templates/about.php'),
            'contact-us'  => array('title' => 'Contact Us', 'template' => 'templates/contact.php'),
            'courses'     => array('title' => 'Courses', 'template' => 'templates/courses.php'),
            'login'       => array('title' => 'Login', 'template' => 'templates/login.php'),
        );
        $created = get_option('pkc_pages', array());
        foreach ($pages as $slug => $info) {
            if (!empty($created[$slug]) && get_post($created[$slug])) {
                continue;
            }
            $existing = get_page_by_path($slug);
            if ($existing) {
                $created[$slug] = $existing->ID;
                update_post_meta($existing->ID, '_wp_page_template', $info['template']);
                continue;
            }
            $id = wp_insert_post(array(
                'post_title'   => $info['title'],
                'post_name'    => $slug,
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_content' => '',
            ));
            if (!is_wp_error($id)) {
                $created[$slug] = $id;
                update_post_meta($id, '_wp_page_template', $info['template']);
            }
        }
        update_option('pkc_pages', $created);

        $front = get_option('page_on_front');
        if (!$front) {
            $home = get_page_by_path('home');
            if (!$home) {
                $home_id = wp_insert_post(array(
                    'post_title'   => 'Home',
                    'post_name'    => 'home',
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                    'post_content' => '',
                ));
            } else {
                $home_id = $home->ID;
            }
            if (!empty($home_id) && !is_wp_error($home_id)) {
                update_option('show_on_front', 'page');
                update_option('page_on_front', $home_id);
            }
        }
    }

    public static function create_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $c = $wpdb->get_charset_collate();

        $sql = array();

        $sql[] = "CREATE TABLE " . PKC_DB::students() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            username varchar(60) NOT NULL,
            email varchar(190) NOT NULL,
            password_hash varchar(255) NOT NULL,
            full_name varchar(190) NOT NULL,
            whatsapp varchar(40) DEFAULT '',
            address text NULL,
            profile_bio text NULL,
            avatar_id bigint(20) unsigned DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'active',
            must_change_password tinyint(1) NOT NULL DEFAULT 1,
            last_login datetime DEFAULT NULL,
            last_login_ip varchar(64) DEFAULT '',
            failed_logins int(11) NOT NULL DEFAULT 0,
            locked_until datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY username (username),
            UNIQUE KEY email (email),
            KEY status (status)
        ) $c;";

        $sql[] = "CREATE TABLE " . PKC_DB::teachers() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            username varchar(60) NOT NULL,
            email varchar(190) NOT NULL,
            password_hash varchar(255) NOT NULL,
            full_name varchar(190) NOT NULL,
            whatsapp varchar(40) DEFAULT '',
            profile_bio text NULL,
            avatar_id bigint(20) unsigned DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'active',
            must_change_password tinyint(1) NOT NULL DEFAULT 1,
            permissions longtext NULL,
            last_login datetime DEFAULT NULL,
            last_login_ip varchar(64) DEFAULT '',
            failed_logins int(11) NOT NULL DEFAULT 0,
            locked_until datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY username (username),
            UNIQUE KEY email (email),
            KEY status (status)
        ) $c;";

        $sql[] = "CREATE TABLE " . PKC_DB::categories() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(120) NOT NULL,
            slug varchar(120) NOT NULL,
            description text NULL,
            parent_id bigint(20) unsigned DEFAULT 0,
            sort_order int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug)
        ) $c;";

        $sql[] = "CREATE TABLE " . PKC_DB::courses() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            slug varchar(190) NOT NULL,
            excerpt text NULL,
            description longtext NULL,
            overview longtext NULL,
            image_id bigint(20) unsigned DEFAULT 0,
            image_url varchar(500) DEFAULT '',
            category_id bigint(20) unsigned DEFAULT 0,
            price decimal(10,2) NOT NULL DEFAULT 0.00,
            sale_price decimal(10,2) DEFAULT NULL,
            level varchar(40) DEFAULT 'intermediate',
            course_type varchar(40) DEFAULT 'self-paced',
            is_featured tinyint(1) NOT NULL DEFAULT 0,
            is_top tinyint(1) NOT NULL DEFAULT 0,
            visibility varchar(20) NOT NULL DEFAULT 'public',
            what_you_learn longtext NULL,
            includes longtext NULL,
            faqs longtext NULL,
            status varchar(20) NOT NULL DEFAULT 'published',
            enrolled_count int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY category_id (category_id),
            KEY status (status),
            KEY is_top (is_top),
            KEY price (price)
        ) $c;";

        $sql[] = "CREATE TABLE " . PKC_DB::modules() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            course_id bigint(20) unsigned NOT NULL,
            title varchar(255) NOT NULL,
            description text NULL,
            sort_order int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY course_id (course_id)
        ) $c;";

        $sql[] = "CREATE TABLE " . PKC_DB::lessons() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            module_id bigint(20) unsigned NOT NULL,
            course_id bigint(20) unsigned NOT NULL,
            title varchar(255) NOT NULL,
            content longtext NULL,
            lesson_type varchar(30) NOT NULL DEFAULT 'text',
            sort_order int(11) NOT NULL DEFAULT 0,
            duration_minutes int(11) DEFAULT 0,
            PRIMARY KEY  (id),
            KEY module_id (module_id),
            KEY course_id (course_id)
        ) $c;";

        $sql[] = "CREATE TABLE " . PKC_DB::materials() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            course_id bigint(20) unsigned NOT NULL,
            lesson_id bigint(20) unsigned DEFAULT 0,
            title varchar(255) NOT NULL,
            file_path varchar(500) NOT NULL,
            file_type varchar(40) DEFAULT '',
            file_size bigint(20) DEFAULT 0,
            is_downloadable tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY course_id (course_id),
            KEY lesson_id (lesson_id)
        ) $c;";

        $sql[] = "CREATE TABLE " . PKC_DB::student_courses() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            student_id bigint(20) unsigned NOT NULL,
            course_id bigint(20) unsigned NOT NULL,
            payment_id bigint(20) unsigned DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'active',
            granted_at datetime NOT NULL,
            revoked_at datetime DEFAULT NULL,
            expires_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY student_course (student_id,course_id),
            KEY course_id (course_id),
            KEY status (status)
        ) $c;";

        $sql[] = "CREATE TABLE " . PKC_DB::teacher_courses() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            teacher_id bigint(20) unsigned NOT NULL,
            course_id bigint(20) unsigned NOT NULL,
            role varchar(40) NOT NULL DEFAULT 'instructor',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY teacher_course (teacher_id,course_id),
            KEY course_id (course_id)
        ) $c;";

        $sql[] = "CREATE TABLE " . PKC_DB::payment_methods() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(120) NOT NULL,
            slug varchar(80) NOT NULL,
            instructions longtext NULL,
            account_details longtext NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            sort_order int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug)
        ) $c;";

        $sql[] = "CREATE TABLE " . PKC_DB::payments() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            course_id bigint(20) unsigned NOT NULL,
            student_id bigint(20) unsigned DEFAULT 0,
            full_name varchar(190) NOT NULL,
            email varchar(190) NOT NULL,
            whatsapp varchar(40) DEFAULT '',
            address text NULL,
            payment_method_id bigint(20) unsigned NOT NULL,
            transaction_id varchar(190) NOT NULL,
            receipt_path varchar(500) DEFAULT '',
            coupon_id bigint(20) unsigned DEFAULT 0,
            original_amount decimal(10,2) NOT NULL DEFAULT 0.00,
            discount_amount decimal(10,2) NOT NULL DEFAULT 0.00,
            amount decimal(10,2) NOT NULL DEFAULT 0.00,
            status varchar(20) NOT NULL DEFAULT 'pending',
            rejection_reason text NULL,
            submitted_at datetime NOT NULL,
            reviewed_at datetime DEFAULT NULL,
            reviewed_by bigint(20) unsigned DEFAULT 0,
            created_account_id bigint(20) unsigned DEFAULT 0,
            PRIMARY KEY  (id),
            KEY course_id (course_id),
            KEY student_id (student_id),
            KEY status (status),
            KEY email (email),
            KEY transaction_id (transaction_id)
        ) $c;";

        $sql[] = "CREATE TABLE " . PKC_DB::coupons() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code varchar(60) NOT NULL,
            discount_type varchar(20) NOT NULL DEFAULT 'percent',
            discount_value decimal(10,2) NOT NULL DEFAULT 0.00,
            max_uses int(11) DEFAULT 0,
            max_per_user int(11) DEFAULT 1,
            used_count int(11) NOT NULL DEFAULT 0,
            min_amount decimal(10,2) DEFAULT 0.00,
            starts_at datetime DEFAULT NULL,
            expires_at datetime DEFAULT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY code (code)
        ) $c;";

        $sql[] = "CREATE TABLE " . PKC_DB::coupon_courses() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            coupon_id bigint(20) unsigned NOT NULL,
            course_id bigint(20) unsigned NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY coupon_course (coupon_id,course_id)
        ) $c;";

        $sql[] = "CREATE TABLE " . PKC_DB::coupon_usage() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            coupon_id bigint(20) unsigned NOT NULL,
            payment_id bigint(20) unsigned NOT NULL,
            student_email varchar(190) DEFAULT '',
            used_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY coupon_id (coupon_id),
            KEY student_email (student_email)
        ) $c;";

        $sql[] = "CREATE TABLE " . PKC_DB::questions() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            course_id bigint(20) unsigned DEFAULT 0,
            topic varchar(190) DEFAULT '',
            subject varchar(190) DEFAULT '',
            category varchar(120) DEFAULT '',
            question longtext NOT NULL,
            explanation longtext NULL,
            marks decimal(8,2) NOT NULL DEFAULT 1.00,
            difficulty varchar(20) DEFAULT 'medium',
            status varchar(20) NOT NULL DEFAULT 'active',
            time_seconds int(11) DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY course_id (course_id),
            KEY subject (subject),
            KEY difficulty (difficulty),
            KEY status (status)
        ) $c;";

        $sql[] = "CREATE TABLE " . PKC_DB::question_options() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            question_id bigint(20) unsigned NOT NULL,
            option_text text NOT NULL,
            is_correct tinyint(1) NOT NULL DEFAULT 0,
            sort_order int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY question_id (question_id)
        ) $c;";

        $sql[] = "CREATE TABLE " . PKC_DB::quizzes() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            course_id bigint(20) unsigned NOT NULL,
            title varchar(255) NOT NULL,
            description text NULL,
            question_count int(11) NOT NULL DEFAULT 10,
            total_marks decimal(10,2) DEFAULT 0.00,
            time_limit_minutes int(11) NOT NULL DEFAULT 30,
            passing_percent decimal(5,2) NOT NULL DEFAULT 50.00,
            max_attempts int(11) NOT NULL DEFAULT 0,
            randomize_questions tinyint(1) NOT NULL DEFAULT 1,
            randomize_options tinyint(1) NOT NULL DEFAULT 1,
            show_explanations varchar(30) NOT NULL DEFAULT 'after_submit',
            exclude_attempted varchar(30) NOT NULL DEFAULT 'deprioritize',
            exclude_days int(11) NOT NULL DEFAULT 14,
            allow_repeat_when_exhausted tinyint(1) NOT NULL DEFAULT 1,
            result_visibility varchar(30) NOT NULL DEFAULT 'immediate',
            status varchar(20) NOT NULL DEFAULT 'published',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY course_id (course_id)
        ) $c;";

        $sql[] = "CREATE TABLE " . PKC_DB::quiz_attempts() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            quiz_id bigint(20) unsigned NOT NULL,
            student_id bigint(20) unsigned NOT NULL,
            course_id bigint(20) unsigned NOT NULL,
            started_at datetime NOT NULL,
            submitted_at datetime DEFAULT NULL,
            expires_at datetime NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'in_progress',
            question_ids longtext NULL,
            total_questions int(11) DEFAULT 0,
            attempted int(11) DEFAULT 0,
            correct_count int(11) DEFAULT 0,
            incorrect_count int(11) DEFAULT 0,
            skipped int(11) DEFAULT 0,
            marks_obtained decimal(10,2) DEFAULT 0.00,
            total_marks decimal(10,2) DEFAULT 0.00,
            percentage decimal(6,2) DEFAULT 0.00,
            passed tinyint(1) DEFAULT 0,
            time_taken_seconds int(11) DEFAULT 0,
            ip_address varchar(64) DEFAULT '',
            PRIMARY KEY  (id),
            KEY quiz_student (quiz_id,student_id),
            KEY student_id (student_id),
            KEY status (status)
        ) $c;";

        $sql[] = "CREATE TABLE " . PKC_DB::quiz_answers() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            attempt_id bigint(20) unsigned NOT NULL,
            question_id bigint(20) unsigned NOT NULL,
            selected_option_id bigint(20) unsigned DEFAULT 0,
            is_correct tinyint(1) DEFAULT 0,
            marks_awarded decimal(8,2) DEFAULT 0.00,
            PRIMARY KEY  (id),
            UNIQUE KEY attempt_question (attempt_id,question_id),
            KEY question_id (question_id)
        ) $c;";

        $sql[] = "CREATE TABLE " . PKC_DB::progress() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            student_id bigint(20) unsigned NOT NULL,
            course_id bigint(20) unsigned NOT NULL,
            lesson_id bigint(20) unsigned DEFAULT 0,
            material_id bigint(20) unsigned DEFAULT 0,
            event_type varchar(40) NOT NULL,
            completed tinyint(1) NOT NULL DEFAULT 0,
            last_accessed_at datetime NOT NULL,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_progress (student_id,course_id,lesson_id,material_id,event_type),
            KEY student_course (student_id,course_id)
        ) $c;";

        $sql[] = "CREATE TABLE " . PKC_DB::messages() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            course_id bigint(20) unsigned NOT NULL,
            student_id bigint(20) unsigned NOT NULL,
            teacher_id bigint(20) unsigned NOT NULL,
            sender_type varchar(20) NOT NULL,
            sender_id bigint(20) unsigned NOT NULL,
            body longtext NOT NULL,
            attachment_path varchar(500) DEFAULT '',
            is_read tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY conversation (course_id,student_id,teacher_id),
            KEY is_read (is_read)
        ) $c;";

        $sql[] = "CREATE TABLE " . PKC_DB::reviews() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            course_id bigint(20) unsigned DEFAULT 0,
            student_id bigint(20) unsigned DEFAULT 0,
            author_name varchar(190) NOT NULL,
            rating tinyint(1) NOT NULL DEFAULT 5,
            content text NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            is_featured tinyint(1) NOT NULL DEFAULT 0,
            show_on_homepage tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY course_id (course_id),
            KEY show_on_homepage (show_on_homepage)
        ) $c;";

        $sql[] = "CREATE TABLE " . PKC_DB::contacts() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(190) NOT NULL,
            email varchar(190) NOT NULL,
            whatsapp varchar(40) DEFAULT '',
            subject varchar(255) NOT NULL,
            message longtext NOT NULL,
            preferred_contact varchar(40) DEFAULT '',
            course_id bigint(20) unsigned DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'new',
            is_read tinyint(1) NOT NULL DEFAULT 0,
            replied_at datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY is_read (is_read)
        ) $c;";

        $sql[] = "CREATE TABLE " . PKC_DB::notifications() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            recipient_type varchar(20) NOT NULL,
            recipient_id bigint(20) unsigned NOT NULL,
            title varchar(255) NOT NULL,
            body text NULL,
            type varchar(40) DEFAULT 'info',
            is_read tinyint(1) NOT NULL DEFAULT 0,
            link varchar(500) DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY recipient (recipient_type,recipient_id,is_read)
        ) $c;";

        $sql[] = "CREATE TABLE " . PKC_DB::audit_logs() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            actor_type varchar(20) NOT NULL,
            actor_id bigint(20) unsigned DEFAULT 0,
            action varchar(80) NOT NULL,
            object_type varchar(40) DEFAULT '',
            object_id bigint(20) unsigned DEFAULT 0,
            details longtext NULL,
            ip varchar(64) DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY action (action),
            KEY created_at (created_at)
        ) $c;";

        $sql[] = "CREATE TABLE " . PKC_DB::sessions() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            account_type varchar(20) NOT NULL,
            account_id bigint(20) unsigned NOT NULL,
            selector varchar(32) NOT NULL,
            token_hash varchar(64) NOT NULL,
            csrf_token varchar(64) NOT NULL,
            expires_at datetime NOT NULL,
            remember tinyint(1) NOT NULL DEFAULT 0,
            ip varchar(64) DEFAULT '',
            user_agent varchar(255) DEFAULT '',
            created_at datetime NOT NULL,
            last_seen datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY selector (selector),
            KEY account (account_type,account_id),
            KEY expires_at (expires_at)
        ) $c;";

        $sql[] = "CREATE TABLE " . PKC_DB::login_attempts() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            ip varchar(64) NOT NULL,
            login varchar(190) NOT NULL,
            success tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY ip_time (ip,created_at)
        ) $c;";

        $sql[] = "CREATE TABLE " . PKC_DB::password_resets() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            account_type varchar(20) NOT NULL,
            account_id bigint(20) unsigned NOT NULL,
            token_hash varchar(64) NOT NULL,
            expires_at datetime NOT NULL,
            used_at datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY account (account_type,account_id)
        ) $c;";

        foreach ($sql as $statement) {
            dbDelta($statement);
        }
    }
}
