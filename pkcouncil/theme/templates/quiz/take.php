<?php
$acc = pkc_current_account();
$course = pkc_course(get_query_var('pkc_slug'));
$quiz_id = (int) get_query_var('pkc_id');
if (!$course || !PKC_Access::student_has_course($acc->id, $course->id)) {
    wp_die('You do not have access to this quiz.', 'Forbidden', array('response' => 403));
}
global $wpdb;
$quiz = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . PKC_DB::quizzes() . ' WHERE id = %d AND course_id = %d', $quiz_id, $course->id));
if (!$quiz) {
    wp_die('Quiz not found.', 'Not Found', array('response' => 404));
}
get_header();
?>
<div class="pkc-portal">
  <?php get_template_part('template-parts/portal', 'nav'); ?>
  <div class="pkc-portal__main">
    <h1><?php echo esc_html($quiz->title); ?></h1>
    <p><?php echo esc_html($quiz->description); ?></p>
    <p><?php echo (int) $quiz->question_count; ?> questions · <?php echo (int) $quiz->time_limit_minutes; ?> minutes · pass <?php echo esc_html($quiz->passing_percent); ?>%</p>
    <div id="pkc-quiz" data-quiz="<?php echo (int) $quiz->id; ?>">
      <button class="pkc-btn pkc-btn--gold" type="button" data-start>Start quiz</button>
      <div class="pkc-quiz-timer" data-timer hidden>Time remaining: <strong>00:00</strong></div>
      <form data-quiz-form hidden></form>
    </div>
  </div>
</div>
<?php get_footer(); ?>
