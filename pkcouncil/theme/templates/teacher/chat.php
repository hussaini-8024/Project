<?php
$acc = pkc_current_account();
$course_id = (int) get_query_var('pkc_id');
$student_id = (int) get_query_var('pkc_id2');
if (!PKC_Access::teacher_has_course($acc->id, $course_id) || !PKC_Access::student_has_course($student_id, $course_id)) {
    wp_die('You do not have access to this conversation.', 'Forbidden', array('response' => 403));
}
$course = pkc_course($course_id);
$student = PKC_Auth::load_account('student', $student_id);
get_header();
$thread = PKC_Chat::thread($course_id, $student_id, $acc->id);
PKC_Chat::mark_read($course_id, $student_id, $acc->id, 'teacher');
?>
<div class="pkc-portal">
  <?php get_template_part('template-parts/portal', 'nav'); ?>
  <div class="pkc-portal__main">
    <h1>Chat with <?php echo esc_html($student->full_name ?? 'student'); ?></h1>
    <p><?php echo esc_html($course->title ?? ''); ?></p>
    <div class="pkc-chat" data-chat data-course="<?php echo (int) $course_id; ?>" data-student="<?php echo (int) $student_id; ?>">
      <div class="pkc-chat__log" data-chat-log>
        <?php foreach ($thread as $m) : ?>
          <div class="pkc-bubble pkc-bubble--<?php echo esc_attr($m->sender_type); ?>">
            <p><?php echo esc_html($m->body); ?></p>
            <time><?php echo esc_html($m->created_at); ?></time>
          </div>
        <?php endforeach; ?>
      </div>
      <form class="pkc-chat__form" data-chat-form>
        <textarea name="body" required></textarea>
        <button class="pkc-btn pkc-btn--gold" type="submit">Send</button>
      </form>
    </div>
  </div>
</div>
<?php get_footer(); ?>
