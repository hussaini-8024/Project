<?php
$acc = pkc_current_account();
get_header();
$courses = PKC_Access::teacher_courses($acc->id);
$inbox = PKC_Chat::teacher_inbox($acc->id);
$notes = PKC_Notifications::for_account('teacher', $acc->id, 8);
global $wpdb;
?>
<div class="pkc-portal">
  <?php get_template_part('template-parts/portal', 'nav'); ?>
  <div class="pkc-portal__main">
    <p class="pkc-kicker">Teacher portal</p>
    <h1>Hello, <?php echo esc_html($acc->full_name); ?></h1>
    <p>You only see courses assigned to you, and the students enrolled in those courses.</p>
    <section>
      <h2>Assigned courses</h2>
      <div class="pkc-grid pkc-grid--3">
        <?php foreach ($courses as $c) : ?>
          <article class="pkc-card">
            <img src="<?php echo esc_url(pkc_course_image_url($c)); ?>" alt="">
            <div class="pkc-card__body">
              <h3><?php echo esc_html($c->title); ?></h3>
              <a class="pkc-btn pkc-btn--gold" href="<?php echo esc_url(pkc_url('teacher/course/' . $c->slug . '/')); ?>">Open</a>
            </div>
          </article>
        <?php endforeach; if (!$courses) echo '<p>No courses assigned yet.</p>'; ?>
      </div>
    </section>
    <section class="pkc-panel">
      <h2>Student messages</h2>
      <ul>
        <?php foreach ($inbox as $m) : ?>
          <li><a href="<?php echo esc_url(pkc_url('teacher/chat/' . $m->course_id . '/' . $m->student_id . '/')); ?>"><?php echo esc_html($m->student_name); ?> — <?php echo esc_html($m->course_title); ?> (<?php echo (int) $m->unread; ?> unread)</a></li>
        <?php endforeach; if (!$inbox) echo '<li>No conversations yet.</li>'; ?>
      </ul>
    </section>
    <section class="pkc-panel">
      <h2>Notifications</h2>
      <ul><?php foreach ($notes as $n) : ?><li><?php echo esc_html($n->title); ?></li><?php endforeach; if (!$notes) echo '<li>Quiet for now.</li>'; ?></ul>
    </section>
  </div>
</div>
<?php get_footer(); ?>
