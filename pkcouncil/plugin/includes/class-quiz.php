<?php
if (!defined('ABSPATH')) {
    exit;
}

class PKC_Quiz {
    public static function start($quiz_id, $student_id) {
        global $wpdb;
        $quiz = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . PKC_DB::quizzes() . ' WHERE id = %d AND status = %s', (int) $quiz_id, 'published'));
        if (!$quiz) {
            return new WP_Error('pkc_quiz', 'This quiz is not available.');
        }
        if (!PKC_Access::student_has_course($student_id, (int) $quiz->course_id)) {
            return new WP_Error('pkc_quiz', 'You do not have access to this quiz.', array('status' => 403));
        }
        $open = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . PKC_DB::quiz_attempts() . ' WHERE quiz_id = %d AND student_id = %d AND status = %s ORDER BY id DESC LIMIT 1',
            (int) $quiz_id,
            (int) $student_id,
            'in_progress'
        ));
        if ($open) {
            if (strtotime($open->expires_at) <= time()) {
                self::grade($open->id, true);
                $open = null;
            } else {
                return $open;
            }
        }
        if ((int) $quiz->max_attempts > 0) {
            $done = (int) $wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(*) FROM ' . PKC_DB::quiz_attempts() . ' WHERE quiz_id = %d AND student_id = %d AND status IN (%s,%s)',
                (int) $quiz_id,
                (int) $student_id,
                'submitted',
                'expired'
            ));
            if ($done >= (int) $quiz->max_attempts) {
                return new WP_Error('pkc_quiz', 'You have used all allowed attempts for this quiz.');
            }
        }
        $ids = self::select_questions($quiz, $student_id);
        if (!$ids) {
            return new WP_Error('pkc_quiz', 'No eligible questions are available for this quiz yet.');
        }
        $marks = (float) $wpdb->get_var('SELECT COALESCE(SUM(marks),0) FROM ' . PKC_DB::questions() . ' WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')');
        $minutes = max(1, (int) $quiz->time_limit_minutes);
        $wpdb->insert(PKC_DB::quiz_attempts(), array(
            'quiz_id'          => (int) $quiz->id,
            'student_id'       => (int) $student_id,
            'course_id'        => (int) $quiz->course_id,
            'started_at'       => PKC_DB::now(),
            'expires_at'       => gmdate('Y-m-d H:i:s', time() + $minutes * MINUTE_IN_SECONDS),
            'status'           => 'in_progress',
            'question_ids'     => wp_json_encode($ids),
            'total_questions'  => count($ids),
            'total_marks'      => $marks,
            'ip_address'       => PKC_Security::client_ip(),
        ));
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . PKC_DB::quiz_attempts() . ' WHERE id = %d', (int) $wpdb->insert_id));
    }

    public static function select_questions($quiz, $student_id) {
        global $wpdb;
        $needed = max(1, (int) $quiz->question_count);
        $pool = $wpdb->get_results($wpdb->prepare(
            'SELECT id FROM ' . PKC_DB::questions() . ' WHERE status = %s AND (course_id = %d OR course_id = 0)',
            'active',
            (int) $quiz->course_id
        ));
        $ids = array_map(function ($r) { return (int) $r->id; }, $pool);
        if (!$ids) {
            return array();
        }
        $attempted = self::previously_attempted($student_id, (int) $quiz->course_id, $quiz);
        $mode = $quiz->exclude_attempted;
        $fresh = array_values(array_diff($ids, $attempted));
        $chosen = array();
        if ($mode === 'exclude') {
            $source = $fresh;
            if (count($source) < $needed && (int) $quiz->allow_repeat_when_exhausted) {
                $source = $ids;
            }
        } elseif ($mode === 'deprioritize') {
            shuffle($fresh);
            shuffle($attempted);
            $source = array_merge($fresh, $attempted);
        } else {
            $source = $ids;
        }
        if ((int) $quiz->randomize_questions) {
            shuffle($source);
        }
        $chosen = array_slice($source, 0, min($needed, count($source)));
        return $chosen;
    }

    public static function previously_attempted($student_id, $course_id, $quiz) {
        global $wpdb;
        $days = max(0, (int) $quiz->exclude_days);
        $sql = 'SELECT DISTINCT qa.question_id
                FROM ' . PKC_DB::quiz_answers() . ' qa
                INNER JOIN ' . PKC_DB::quiz_attempts() . ' a ON a.id = qa.attempt_id
                WHERE a.student_id = %d AND a.course_id = %d AND a.status IN (%s,%s)';
        $params = array($student_id, $course_id, 'submitted', 'expired');
        if ($quiz->exclude_attempted === 'after_days' && $days > 0) {
            $sql .= ' AND a.submitted_at >= %s';
            $params[] = gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);
        }
        $prepared = $wpdb->prepare($sql, $params);
        return array_map('intval', $wpdb->get_col($prepared));
    }

    public static function attempt_for_student($attempt_id, $student_id) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . PKC_DB::quiz_attempts() . ' WHERE id = %d AND student_id = %d',
            (int) $attempt_id,
            (int) $student_id
        ));
        return $row;
    }

    public static function remaining_seconds($attempt) {
        return max(0, strtotime($attempt->expires_at) - time());
    }

    public static function questions_for_attempt($attempt, $include_correct = false) {
        global $wpdb;
        $ids = pkc_json($attempt->question_ids);
        if (!$ids) {
            return array();
        }
        $in = implode(',', array_map('intval', $ids));
        $questions = $wpdb->get_results('SELECT * FROM ' . PKC_DB::questions() . " WHERE id IN ({$in})");
        $by_id = array();
        foreach ($questions as $q) {
            $by_id[(int) $q->id] = $q;
        }
        $quiz = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . PKC_DB::quizzes() . ' WHERE id = %d', (int) $attempt->quiz_id));
        $out = array();
        foreach ($ids as $id) {
            if (empty($by_id[$id])) {
                continue;
            }
            $q = $by_id[$id];
            $opts = $wpdb->get_results($wpdb->prepare(
                'SELECT id, option_text, sort_order' . ($include_correct ? ', is_correct' : '') . ' FROM ' . PKC_DB::question_options() . ' WHERE question_id = %d ORDER BY sort_order ASC, id ASC',
                (int) $q->id
            ));
            if ($quiz && (int) $quiz->randomize_options && $attempt->status === 'in_progress') {
                shuffle($opts);
            }
            $item = array(
                'id'      => (int) $q->id,
                'question'=> $q->question,
                'marks'   => (float) $q->marks,
                'options' => $opts,
            );
            if ($include_correct) {
                $item['explanation'] = $q->explanation;
            }
            $out[] = $item;
        }
        return $out;
    }

    public static function save_answer($attempt, $question_id, $option_id) {
        if ($attempt->status !== 'in_progress') {
            return new WP_Error('pkc_quiz', 'This quiz has already been submitted.');
        }
        if (strtotime($attempt->expires_at) <= time()) {
            return self::grade((int) $attempt->id, true);
        }
        $ids = pkc_json($attempt->question_ids);
        if (!in_array((int) $question_id, array_map('intval', $ids), true)) {
            return new WP_Error('pkc_quiz', 'Invalid question.', array('status' => 403));
        }
        global $wpdb;
        $opt = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . PKC_DB::question_options() . ' WHERE id = %d AND question_id = %d',
            (int) $option_id,
            (int) $question_id
        ));
        if ($option_id && !$opt) {
            return new WP_Error('pkc_quiz', 'Invalid option.');
        }
        $wpdb->replace(PKC_DB::quiz_answers(), array(
            'attempt_id'         => (int) $attempt->id,
            'question_id'        => (int) $question_id,
            'selected_option_id' => (int) $option_id,
            'is_correct'         => 0,
            'marks_awarded'      => 0,
        ));
        return true;
    }

    public static function grade($attempt_id, $expired = false) {
        global $wpdb;
        $attempt = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . PKC_DB::quiz_attempts() . ' WHERE id = %d', (int) $attempt_id));
        if (!$attempt || $attempt->status !== 'in_progress') {
            return $attempt;
        }
        $ids = pkc_json($attempt->question_ids);
        $answers = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . PKC_DB::quiz_answers() . ' WHERE attempt_id = %d', (int) $attempt_id));
        $by_q = array();
        foreach ($answers as $a) {
            $by_q[(int) $a->question_id] = $a;
        }
        $correct = 0;
        $incorrect = 0;
        $skipped = 0;
        $marks = 0;
        $total_marks = 0;
        foreach ($ids as $qid) {
            $q = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . PKC_DB::questions() . ' WHERE id = %d', (int) $qid));
            if (!$q) {
                continue;
            }
            $total_marks += (float) $q->marks;
            $ans = $by_q[(int) $qid] ?? null;
            if (!$ans || !(int) $ans->selected_option_id) {
                $skipped++;
                continue;
            }
            $opt = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . PKC_DB::question_options() . ' WHERE id = %d', (int) $ans->selected_option_id));
            $is_correct = $opt && (int) $opt->is_correct;
            $awarded = $is_correct ? (float) $q->marks : 0;
            $wpdb->update(PKC_DB::quiz_answers(), array(
                'is_correct'    => $is_correct ? 1 : 0,
                'marks_awarded' => $awarded,
            ), array('id' => (int) $ans->id));
            if ($is_correct) {
                $correct++;
                $marks += $awarded;
            } else {
                $incorrect++;
            }
        }
        $pct = $total_marks > 0 ? round(($marks / $total_marks) * 100, 2) : 0;
        $quiz = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . PKC_DB::quizzes() . ' WHERE id = %d', (int) $attempt->quiz_id));
        $pass = $quiz && $pct >= (float) $quiz->passing_percent;
        $taken = min(time(), strtotime($attempt->expires_at)) - strtotime($attempt->started_at);
        $wpdb->update(PKC_DB::quiz_attempts(), array(
            'submitted_at'     => PKC_DB::now(),
            'status'           => $expired ? 'expired' : 'submitted',
            'attempted'        => $correct + $incorrect,
            'correct_count'    => $correct,
            'incorrect_count'  => $incorrect,
            'skipped'          => $skipped,
            'marks_obtained'   => $marks,
            'total_marks'      => $total_marks,
            'percentage'       => $pct,
            'passed'           => $pass ? 1 : 0,
            'time_taken_seconds' => max(0, $taken),
        ), array('id' => (int) $attempt_id));
        PKC_Notifications::add('student', (int) $attempt->student_id, 'Quiz result ready', 'Your quiz has been graded.', 'quiz', pkc_url('student/quiz-result/' . (int) $attempt_id . '/'));
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . PKC_DB::quiz_attempts() . ' WHERE id = %d', (int) $attempt_id));
    }

    public static function history($student_id, $course_id = 0) {
        global $wpdb;
        $sql = 'SELECT a.*, q.title AS quiz_title, c.title AS course_title
                FROM ' . PKC_DB::quiz_attempts() . ' a
                INNER JOIN ' . PKC_DB::quizzes() . ' q ON q.id = a.quiz_id
                INNER JOIN ' . PKC_DB::courses() . ' c ON c.id = a.course_id
                WHERE a.student_id = %d AND a.status IN (%s,%s)';
        $params = array($student_id, 'submitted', 'expired');
        if ($course_id) {
            $sql .= ' AND a.course_id = %d';
            $params[] = $course_id;
        }
        $sql .= ' ORDER BY a.submitted_at DESC';
        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }
}
