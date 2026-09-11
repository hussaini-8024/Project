<?php
$acc = pkc_current_account();
$attempt = PKC_Quiz::attempt_for_student((int) get_query_var('pkc_id'), $acc->id);
if (!$attempt || !in_array($attempt->status, array('submitted', 'expired'), true)) {
    wp_die('Result not available.', 'Forbidden', array('response' => 403));
}
global $wpdb;
$quiz = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . PKC_DB::quizzes() . ' WHERE id = %d', $attempt->quiz_id));
$questions = PKC_Quiz::questions_for_attempt($attempt, $quiz && $quiz->show_explanations === 'after_submit');
$answers = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . PKC_DB::quiz_answers() . ' WHERE attempt_id = %d', $attempt->id));
$map = array();
foreach ($answers as $a) {
    $map[(int) $a->question_id] = $a;
}
get_header();
?>
<div class="pkc-portal">
  <?php get_template_part('template-parts/portal', 'nav'); ?>
  <div class="pkc-portal__main">
    <h1>Quiz result</h1>
    <div class="pkc-stats">
      <div class="pkc-stat"><strong><?php echo esc_html($attempt->percentage); ?>%</strong><span>Score</span></div>
      <div class="pkc-stat"><strong><?php echo (int) $attempt->correct_count; ?></strong><span>Correct</span></div>
      <div class="pkc-stat"><strong><?php echo (int) $attempt->incorrect_count; ?></strong><span>Incorrect</span></div>
      <div class="pkc-stat"><strong><?php echo (int) $attempt->skipped; ?></strong><span>Skipped</span></div>
      <div class="pkc-stat"><strong><?php echo (int) $attempt->passed ? 'Pass' : 'Fail'; ?></strong><span>Result</span></div>
    </div>
    <p>Time taken: <?php echo (int) floor($attempt->time_taken_seconds / 60); ?>m <?php echo (int) ($attempt->time_taken_seconds % 60); ?>s
      <?php if ($attempt->status === 'expired') echo ' · submitted automatically when time expired'; ?></p>
    <?php if ($quiz && $quiz->show_explanations === 'after_submit') : foreach ($questions as $q) :
      $ans = $map[$q['id']] ?? null; ?>
      <section class="pkc-panel">
        <h2><?php echo wp_kses_post($q['question']); ?></h2>
        <ul>
          <?php foreach ($q['options'] as $o) : ?>
            <li><?php echo esc_html($o->option_text); ?><?php if (!empty($o->is_correct)) echo ' ✓'; ?></li>
          <?php endforeach; ?>
        </ul>
        <?php if (!empty($q['explanation'])) : ?><p><?php echo esc_html($q['explanation']); ?></p><?php endif; ?>
      </section>
    <?php endforeach; endif; ?>
  </div>
</div>
<?php get_footer(); ?>
