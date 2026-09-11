<?php
$acc = pkc_current_account();
$course = pkc_course(get_query_var('pkc_slug'));
if (!$course || !PKC_Access::student_has_course($acc->id, $course->id)) {
    wp_die('You do not have access to this course.', 'Forbidden', array('response' => 403));
}
get_header();
global $wpdb;
$pct = PKC_Progress::percent($acc->id, $course->id);
$modules = pkc_course_modules($course->id);
$materials = pkc_course_materials($course->id);
$quizzes = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . PKC_DB::quizzes() . ' WHERE course_id = %d AND status = %s', $course->id, 'published'));
$teacher = PKC_Chat::teacher_for_course($course->id);
?>
<div class="pkc-portal">
  <?php get_template_part('template-parts/portal', 'nav'); ?>
  <div class="pkc-portal__main">
    <p class="pkc-kicker"><?php echo esc_html($course->title); ?> — <?php echo (int) $pct; ?>% complete</p>
    <h1>Learning area</h1>
    <div class="pkc-meter pkc-meter--lg"><span style="width:<?php echo (int) $pct; ?>%"></span></div>
    <div class="pkc-learn">
      <div>
        <?php foreach ($modules as $mod) : ?>
          <section class="pkc-panel">
            <h2><?php echo esc_html($mod->title); ?></h2>
            <?php foreach (pkc_module_lessons($mod->id) as $les) : ?>
              <article class="pkc-lesson" data-progress data-course="<?php echo (int) $course->id; ?>" data-lesson="<?php echo (int) $les->id; ?>">
                <h3><?php echo esc_html($les->title); ?></h3>
                <div class="pkc-prose"><?php echo wp_kses_post($les->content); ?></div>
                <button class="pkc-btn pkc-btn--ghost" type="button" data-complete>Mark complete</button>
              </article>
            <?php endforeach; ?>
          </section>
        <?php endforeach; ?>
      </div>
      <aside>
        <section class="pkc-panel">
          <h2>Materials</h2>
          <ul>
            <?php foreach ($materials as $m) : ?>
              <li><a href="<?php echo esc_url(pkc_url('pkc-file/' . $m->id . '/')); ?>"><?php echo esc_html($m->title); ?></a></li>
            <?php endforeach; ?>
          </ul>
        </section>
        <section class="pkc-panel">
          <h2>Quizzes</h2>
          <ul>
            <?php foreach ($quizzes as $q) : ?>
              <li><a class="pkc-btn pkc-btn--gold" href="<?php echo esc_url(pkc_url('student/course/' . $course->slug . '/quiz/' . $q->id . '/')); ?>"><?php echo esc_html($q->title); ?></a></li>
            <?php endforeach; ?>
          </ul>
        </section>
        <section class="pkc-panel">
          <h2>Teacher</h2>
          <p><?php echo $teacher ? esc_html($teacher->full_name) : 'No teacher assigned yet.'; ?></p>
          <a class="pkc-btn pkc-btn--ghost" href="<?php echo esc_url(pkc_url('student/course/' . $course->slug . '/chat/')); ?>">Chat with teacher</a>
        </section>
      </aside>
    </div>
  </div>
</div>
<?php get_footer(); ?>
