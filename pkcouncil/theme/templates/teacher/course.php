<?php
$acc = pkc_current_account();
$course = pkc_course(get_query_var('pkc_slug'));
if (!$course || !PKC_Access::teacher_has_course($acc->id, $course->id)) {
    wp_die('You do not have access to this course.', 'Forbidden', array('response' => 403));
}
get_header();
$students = PKC_Access::teacher_students($acc->id, $course->id);
global $wpdb;
$quiz_count = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . PKC_DB::questions() . ' WHERE course_id = %d', $course->id));
?>
<div class="pkc-portal">
  <?php get_template_part('template-parts/portal', 'nav'); ?>
  <div class="pkc-portal__main">
    <h1><?php echo esc_html($course->title); ?></h1>
    <p><?php echo count($students); ?> authorised students · <?php echo $quiz_count; ?> MCQs in this course bank</p>
    <section class="pkc-panel">
      <h2>Students</h2>
      <table class="pkc-table">
        <thead><tr><th>Name</th><th>Progress</th><th>Chat</th></tr></thead>
        <tbody>
        <?php foreach ($students as $s) : ?>
          <tr>
            <td><?php echo esc_html($s->full_name); ?></td>
            <td><?php echo (int) PKC_Progress::percent($s->id, $course->id); ?>%</td>
            <td><a href="<?php echo esc_url(pkc_url('teacher/chat/' . $course->id . '/' . $s->id . '/')); ?>">Message</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  </div>
</div>
<?php get_footer(); ?>
