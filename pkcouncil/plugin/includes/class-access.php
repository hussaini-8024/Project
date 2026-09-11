<?php
if (!defined('ABSPATH')) {
    exit;
}

class PKC_Access {
    public static function student_has_course($student_id, $course_id) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . PKC_DB::student_courses() . ' WHERE student_id = %d AND course_id = %d',
            (int) $student_id,
            (int) $course_id
        ));
        if (!$row || $row->status !== 'active') {
            return false;
        }
        if (!empty($row->expires_at) && strtotime($row->expires_at) < time()) {
            return false;
        }
        return true;
    }

    public static function teacher_has_course($teacher_id, $course_id) {
        global $wpdb;
        $id = $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . PKC_DB::teacher_courses() . ' WHERE teacher_id = %d AND course_id = %d',
            (int) $teacher_id,
            (int) $course_id
        ));
        return (bool) $id;
    }

    public static function student_courses($student_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT c.*, sc.status AS access_status, sc.granted_at
             FROM ' . PKC_DB::student_courses() . ' sc
             INNER JOIN ' . PKC_DB::courses() . ' c ON c.id = sc.course_id
             WHERE sc.student_id = %d AND sc.status = %s
             ORDER BY sc.granted_at DESC',
            (int) $student_id,
            'active'
        ));
    }

    public static function teacher_courses($teacher_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT c.*
             FROM ' . PKC_DB::teacher_courses() . ' tc
             INNER JOIN ' . PKC_DB::courses() . ' c ON c.id = tc.course_id
             WHERE tc.teacher_id = %d
             ORDER BY c.title ASC',
            (int) $teacher_id
        ));
    }

    public static function grant_course($student_id, $course_id, $payment_id = 0) {
        global $wpdb;
        $existing = $wpdb->get_row($wpdb->prepare(
            'SELECT id FROM ' . PKC_DB::student_courses() . ' WHERE student_id = %d AND course_id = %d',
            (int) $student_id,
            (int) $course_id
        ));
        if ($existing) {
            $wpdb->update(PKC_DB::student_courses(), array(
                'status'     => 'active',
                'payment_id' => (int) $payment_id,
                'granted_at' => PKC_DB::now(),
                'revoked_at' => null,
            ), array('id' => (int) $existing->id));
        } else {
            $wpdb->insert(PKC_DB::student_courses(), array(
                'student_id' => (int) $student_id,
                'course_id'  => (int) $course_id,
                'payment_id' => (int) $payment_id,
                'status'     => 'active',
                'granted_at' => PKC_DB::now(),
            ));
            $wpdb->query($wpdb->prepare(
                'UPDATE ' . PKC_DB::courses() . ' SET enrolled_count = enrolled_count + 1 WHERE id = %d',
                (int) $course_id
            ));
        }
        PKC_Audit::log('course_access_granted', 'course', (int) $course_id, array('student_id' => (int) $student_id));
    }

    public static function revoke_course($student_id, $course_id) {
        global $wpdb;
        $wpdb->update(PKC_DB::student_courses(), array(
            'status'     => 'revoked',
            'revoked_at' => PKC_DB::now(),
        ), array(
            'student_id' => (int) $student_id,
            'course_id'  => (int) $course_id,
        ));
        PKC_Audit::log('course_access_revoked', 'course', (int) $course_id, array('student_id' => (int) $student_id));
    }

    public static function assign_teacher($teacher_id, $course_id, $role = 'instructor') {
        global $wpdb;
        $wpdb->replace(PKC_DB::teacher_courses(), array(
            'teacher_id' => (int) $teacher_id,
            'course_id'  => (int) $course_id,
            'role'       => sanitize_text_field($role),
            'created_at' => PKC_DB::now(),
        ));
    }

    public static function teacher_students($teacher_id, $course_id = 0) {
        global $wpdb;
        $courses = self::teacher_courses($teacher_id);
        $ids = array_map(function ($c) { return (int) $c->id; }, $courses);
        if ($course_id) {
            if (!in_array((int) $course_id, $ids, true)) {
                return array();
            }
            $ids = array((int) $course_id);
        }
        if (!$ids) {
            return array();
        }
        $in = implode(',', array_map('intval', $ids));
        return $wpdb->get_results(
            'SELECT DISTINCT s.*, sc.course_id
             FROM ' . PKC_DB::students() . ' s
             INNER JOIN ' . PKC_DB::student_courses() . " sc ON sc.student_id = s.id
             WHERE sc.status = 'active' AND sc.course_id IN ({$in})
             ORDER BY s.full_name ASC"
        );
    }
}
