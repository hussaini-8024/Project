<?php
if (!defined('ABSPATH')) {
    exit;
}

class PKC_Progress {
    public static function track($student_id, $course_id, $event, $lesson_id = 0, $material_id = 0, $completed = false) {
        global $wpdb;
        $existing = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . PKC_DB::progress() . ' WHERE student_id = %d AND course_id = %d AND lesson_id = %d AND material_id = %d AND event_type = %s',
            (int) $student_id,
            (int) $course_id,
            (int) $lesson_id,
            (int) $material_id,
            $event
        ));
        $data = array(
            'last_accessed_at' => PKC_DB::now(),
            'completed'        => $completed ? 1 : (int) ($existing->completed ?? 0),
        );
        if ($completed) {
            $data['completed_at'] = PKC_DB::now();
        }
        if ($existing) {
            $wpdb->update(PKC_DB::progress(), $data, array('id' => (int) $existing->id));
        } else {
            $wpdb->insert(PKC_DB::progress(), array_merge($data, array(
                'student_id'  => (int) $student_id,
                'course_id'   => (int) $course_id,
                'lesson_id'   => (int) $lesson_id,
                'material_id' => (int) $material_id,
                'event_type'  => $event,
            )));
        }
    }

    public static function percent($student_id, $course_id) {
        global $wpdb;
        $lessons = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . PKC_DB::lessons() . ' WHERE course_id = %d', (int) $course_id));
        $quizzes = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . PKC_DB::quizzes() . ' WHERE course_id = %d AND status = %s', (int) $course_id, 'published'));
        $total = $lessons + $quizzes;
        if ($total <= 0) {
            return 0;
        }
        $done_lessons = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(DISTINCT lesson_id) FROM ' . PKC_DB::progress() . " WHERE student_id = %d AND course_id = %d AND lesson_id > 0 AND (completed = 1 OR event_type IN ('lesson_completed','lesson_opened'))",
            (int) $student_id,
            (int) $course_id
        ));
        $done_quizzes = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(DISTINCT quiz_id) FROM ' . PKC_DB::quiz_attempts() . " WHERE student_id = %d AND course_id = %d AND status IN ('submitted','expired')",
            (int) $student_id,
            (int) $course_id
        ));
        $done = min($total, $done_lessons + $done_quizzes);
        return (int) round(($done / $total) * 100);
    }

    public static function recent($student_id, $limit = 6) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT p.*, c.title AS course_title, l.title AS lesson_title
             FROM ' . PKC_DB::progress() . ' p
             INNER JOIN ' . PKC_DB::courses() . ' c ON c.id = p.course_id
             LEFT JOIN ' . PKC_DB::lessons() . ' l ON l.id = p.lesson_id
             WHERE p.student_id = %d
             ORDER BY p.last_accessed_at DESC
             LIMIT %d',
            (int) $student_id,
            (int) $limit
        ));
    }
}
