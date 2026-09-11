<?php
$acc = pkc_current_account();
$course = pkc_course(get_query_var('pkc_slug'));
if (!$course || !PKC_Access::student_has_course($acc->id, $course->id)) {
    wp_die('You do not have access to this conversation.', 'Forbidden', array('response' => 403));
}
$teacher = PKC_Chat::teacher_for_course($course->id);
get_header();
$thread = $teacher ? PKC_Chat::thread($course->id, $acc->id, $teacher->id) : array();
if ($teacher) {
    PKC_Chat::mark_read($course->id, $acc->id, $teacher->id, 'student');
}
?>
<div class="pkc-portal">
  <?php get_template_part('template-parts/portal', 'nav'); ?>
  <div class="pkc-portal__main">
    <h1>Chat — <?php echo esc_html($course->title); ?></h1>
    <?php if (!$teacher) : ?>
      <p>No teacher is assigned to this course yet.</p>
    <?php else : ?>
      <p>Instructor: <?php echo esc_html($teacher->full_name); ?></p>
      <div class="pkc-chat" data-chat data-course="<?php echo (int) $course->id; ?>">
        <div class="pkc-chat__log" data-chat-log>
          <?php foreach ($thread as $m) : ?>
            <div class="pkc-bubble pkc-bubble--<?php echo esc_attr($m->sender_type); ?>">
              <p><?php echo esc_html($m->body); ?></p>
              <time><?php echo esc_html($m->created_at); ?></time>
            </div>
          <?php endforeach; ?>
        </div>
        <form class="pkc-chat__form" data-chat-form>
          <textarea name="body" required placeholder="Write to your teacher"></textarea>
          <button class="pkc-btn pkc-btn--gold" type="submit">Send</button>
        </form>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php get_footer(); ?>
