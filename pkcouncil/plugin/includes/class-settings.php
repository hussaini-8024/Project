<?php
if (!defined('ABSPATH')) {
    exit;
}

class PKC_Settings {
    const OPTION = 'pkc_settings';

    public static function defaults_array() {
        return array(
            'site_name'            => 'PKCouncil',
            'tagline'              => 'Precision knowledge. A modern council for academic excellence.',
            'currency_symbol'      => 'Rs',
            'whatsapp'             => '923001234567',
            'email'                => 'hello@pkcouncil.org',
            'address'              => 'Lahore, Pakistan',
            'phone'                => '+92 300 1234567',
            'facebook'             => '',
            'instagram'            => '',
            'youtube'              => '',
            'footer_about'         => 'PKCouncil is a premium self-paced learning platform offering structured courses, protected study materials, practice MCQs, and teacher support — designed for serious students.',
            'default_student_pass' => 'Student@PKCouncil',
            'default_teacher_pass' => 'Teacher@PKCouncil',
            'force_password_change'=> 1,
            'allow_student_reviews'=> 1,
            'notify_email'         => 1,
            'whatsapp_api'         => '',
            'hero_kicker'          => 'The academic council for ambitious students',
            'hero_title'           => 'Learn with structure. Practice with purpose. Rise with PKCouncil.',
            'hero_subtitle'        => 'Self-paced courses, protected notes, adaptive MCQ banks, and course-specific teacher support — built as a complete learning ecosystem, not a generic classroom.',
            'hero_cta_primary'     => 'Explore Courses',
            'hero_cta_secondary'   => 'Start Learning',
            'learn_heading'        => 'What you will learn',
            'learn_intro'          => 'Every PKCouncil course is designed as a complete study system: materials, practice, assessment, and human support.',
            'learn_items'          => array(
                array('title' => 'Structured course materials', 'text' => 'Modules and topics sequenced the way serious exam preparation actually works.'),
                array('title' => 'Protected PDF notes', 'text' => 'Downloadable only after purchase, delivered through secure access control.'),
                array('title' => 'Practice MCQs', 'text' => 'A living question bank that grows with every course.'),
                array('title' => 'Timed quizzes', 'text' => 'Exam-style papers generated from the bank, graded automatically.'),
                array('title' => 'Self-paced learning', 'text' => 'Study on your schedule. Progress is saved and visible.'),
                array('title' => 'Progress tracking', 'text' => 'See exactly how far you have come in each course.'),
                array('title' => 'Teacher support', 'text' => 'Course-specific chat with the instructor assigned to your subject.'),
                array('title' => 'Course-specific communication', 'text' => 'Biology questions go to the Biology teacher — never a generic inbox.'),
            ),
            'why_heading'          => 'Why choose PKCouncil',
            'why_intro'            => 'Built for students who want academic depth, not entertainment dressed as education.',
            'why_items'            => array(
                array('title' => 'Quality educational material', 'text' => 'Notes and modules written for exam clarity, not filler.'),
                array('title' => 'Experienced teachers', 'text' => 'Instructors assigned per course, with access limited to their subjects.'),
                array('title' => 'Self-paced by design', 'text' => 'No live-class calendar. Your hours, your progress.'),
                array('title' => 'Practice questions', 'text' => 'MCQs with explanations, marks, and intelligent repetition rules.'),
                array('title' => 'Structured courses', 'text' => 'Modules, topics, materials, quizzes — one coherent path.'),
                array('title' => 'Student support', 'text' => 'Chat inside the course you purchased. Nothing extra, nothing missing.'),
                array('title' => 'Secure learning portal', 'text' => 'Custom student accounts, hashed passwords, protected files.'),
                array('title' => 'Affordable learning', 'text' => 'Manual payment verification with EasyPaisa, JazzCash, and bank transfer.'),
            ),
            'about'                => array(
                'org_name'         => 'PKCouncil',
                'introduction'     => 'PKCouncil exists to give ambitious students a serious, self-paced academic home — structured courses, protected materials, rigorous practice, and teachers who know their subject.',
                'founder_name'     => 'Founder, PKCouncil',
                'founder_title'    => 'Founder & Academic Lead',
                'founder_message'  => 'We built PKCouncil because students deserve more than scattered PDFs and noisy group chats. They deserve a council: a place where material is curated, practice is measured, and support is accountable.',
                'founder_info'     => 'The founding team comes from classroom teaching and exam coaching. PKCouncil is the institution we wished existed when students asked for a complete, trustworthy digital path.',
                'manager_name'     => 'Academic Manager',
                'manager_info'     => 'Our academic manager oversees course quality, teacher assignments, and the integrity of the quiz bank so every enrolled student meets the same standard.',
                'mission'          => 'To deliver premium, structured, self-paced education with practice, assessment, and human support — without turning learning into a live-stream spectacle.',
                'vision'           => 'A trusted academic council where every serious student in Pakistan can prepare with clarity, discipline, and access to excellent teachers.',
                'philosophy'       => 'Understanding before speed. Practice before performance. Access only to what you have earned. Teachers who own their subject, not the entire catalogue.',
                'why_exists'       => 'PKCouncil exists because exam preparation is too important to leave to generic LMS templates. Students need materials that stay protected, quizzes that behave like real papers, and teachers who can actually see their course — and only their course.',
            ),
            'contact_cta_title'    => 'Have a question before you enrol?',
            'contact_cta_text'     => 'Write to the PKCouncil team or message us on WhatsApp. We will help you choose the right course.',
        );
    }

    public static function defaults() {
        $existing = get_option(self::OPTION);
        if (!is_array($existing)) {
            update_option(self::OPTION, self::defaults_array());
            return;
        }
        update_option(self::OPTION, array_replace_recursive(self::defaults_array(), $existing));
    }

    public static function all() {
        $all = get_option(self::OPTION, array());
        if (!is_array($all)) {
            $all = array();
        }
        return array_replace_recursive(self::defaults_array(), $all);
    }

    public static function get($key = null, $default = null) {
        $all = self::all();
        if ($key === null) {
            return $all;
        }
        if (strpos($key, '.') !== false) {
            $parts = explode('.', $key);
            $cursor = $all;
            foreach ($parts as $p) {
                if (!is_array($cursor) || !array_key_exists($p, $cursor)) {
                    return $default;
                }
                $cursor = $cursor[$p];
            }
            return $cursor;
        }
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function update($key, $value) {
        $all = self::all();
        $all[$key] = $value;
        update_option(self::OPTION, $all);
    }

    public static function save($data) {
        update_option(self::OPTION, array_replace_recursive(self::all(), $data));
    }
}
