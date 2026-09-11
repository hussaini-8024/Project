<?php
if (!defined('ABSPATH')) {
    exit;
}

class PKC_Chat {
    public static function teacher_for_course($course_id) {
        $teachers = pkc_course_teachers($course_id);
        return $teachers ? $teachers[0] : null;
    }

    public static function send($course_id, $sender_type, $sender_id, $body, $student_id, $teacher_id) {
        $body = trim(wp_kses_post($body));
        if ($body === '') {
            return new WP_Error('pkc_chat', 'Message cannot be empty.');
        }
        if ($sender_type === 'student') {
            if (!PKC_Access::student_has_course($sender_id, $course_id) || (int) $student_id !== (int) $sender_id) {
                return new WP_Error('pkc_chat', 'You cannot message this course.', array('status' => 403));
            }
        } elseif ($sender_type === 'teacher') {
            if (!PKC_Access::teacher_has_course($sender_id, $course_id) || (int) $teacher_id !== (int) $sender_id) {
                return new WP_Error('pkc_chat', 'You cannot message this student.', array('status' => 403));
            }
            if (!PKC_Access::student_has_course($student_id, $course_id)) {
                return new WP_Error('pkc_chat', 'This student is not in your course.', array('status' => 403));
            }
        } else {
            return new WP_Error('pkc_chat', 'Invalid sender.', array('status' => 403));
        }
        global $wpdb;
        $wpdb->insert(PKC_DB::messages(), array(
            'course_id'   => (int) $course_id,
            'student_id'  => (int) $student_id,
            'teacher_id'  => (int) $teacher_id,
            'sender_type' => $sender_type,
            'sender_id'   => (int) $sender_id,
            'body'        => $body,
            'is_read'     => 0,
            'created_at'  => PKC_DB::now(),
        ));
        $id = (int) $wpdb->insert_id;
        if ($sender_type === 'student') {
            PKC_Notifications::add('teacher', $teacher_id, 'New student message', wp_trim_words(wp_strip_all_tags($body), 16), 'chat', pkc_url('teacher/chat/' . (int) $course_id . '/' . (int) $student_id . '/'));
        } else {
            PKC_Notifications::add('student', $student_id, 'New teacher message', wp_trim_words(wp_strip_all_tags($body), 16), 'chat', pkc_url('student/course/' . (pkc_course($course_id)->slug ?? '') . '/chat/'));
        }
        return $id;
    }

    public static function thread($course_id, $student_id, $teacher_id, $limit = 100) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . PKC_DB::messages() . ' WHERE course_id = %d AND student_id = %d AND teacher_id = %d ORDER BY created_at ASC LIMIT %d',
            (int) $course_id,
            (int) $student_id,
            (int) $teacher_id,
            (int) $limit
        ));
        return $rows;
    }

    public static function mark_read($course_id, $student_id, $teacher_id, $reader_type) {
        global $wpdb;
        $other = $reader_type === 'student' ? 'teacher' : 'student';
        $wpdb->query($wpdb->prepare(
            'UPDATE ' . PKC_DB::messages() . ' SET is_read = 1 WHERE course_id = %d AND student_id = %d AND teacher_id = %d AND sender_type = %s',
            (int) $course_id,
            (int) $student_id,
            (int) $teacher_id,
            $other
        ));
    }

    public static function teacher_inbox($teacher_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT m.course_id, m.student_id, MAX(m.created_at) AS last_at,
                    SUM(CASE WHEN m.is_read = 0 AND m.sender_type = %s THEN 1 ELSE 0 END) AS unread,
                    s.full_name AS student_name, c.title AS course_title, c.slug AS course_slug
             FROM ' . PKC_DB::messages() . ' m
             INNER JOIN ' . PKC_DB::students() . ' s ON s.id = m.student_id
             INNER JOIN ' . PKC_DB::courses() . ' c ON c.id = m.course_id
             WHERE m.teacher_id = %d
             GROUP BY m.course_id, m.student_id
             ORDER BY last_at DESC',
            'student',
            (int) $teacher_id
        ));
    }
}
