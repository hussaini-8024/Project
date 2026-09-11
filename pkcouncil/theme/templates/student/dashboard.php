<?php
$acc = pkc_current_account();
get_header();
$courses = PKC_Access::student_courses($acc->id);
$recent = PKC_Progress::recent($acc->id);
$history = PKC_Quiz::history($acc->id);
$notes = PKC_Notifications::for_account('student', $acc->id, 8);
?>
<div class="pkc-portal">
  <?php get_template_part('template-parts/portal', 'nav'); ?>
  <div class="pkc-portal__main">
    <?php if ((int) $acc->must_change_password) : ?>
      <div class="pkc-alert">Please change your initial password in Profile before you continue.</div>
    <?php endif; ?>
    <header class="pkc-welcome">
      <p class="pkc-kicker">Student portal</p>
      <h1>Welcome, <?php echo esc_html(explode(' ', $acc->full_name)[0]); ?></h1>
      <p>Your PKCouncil courses, progress, quizzes, and teacher conversations live here.</p>
    </header>
    <section>
      <h2>My courses</h2>
      <div class="pkc-grid pkc-grid--3">
        <?php foreach ($courses as $c) :
          $pct = PKC_Progress::percent($acc->id, $c->id); ?>
          <article class="pkc-card">
            <img src="<?php echo esc_url(pkc_course_image_url($c)); ?>" alt="">
            <div class="pkc-card__body">
              <h3><?php echo esc_html($c->title); ?></h3>
              <div class="pkc-meter" aria-label="<?php echo (int) $pct; ?>% complete"><span style="width:<?php echo (int) $pct; ?>%"></span></div>
              <p><?php echo (int) $pct; ?>% complete</p>
              <a class="pkc-btn pkc-btn--gold" href="<?php echo esc_url(pkc_url('student/course/' . $c->slug . '/')); ?>">Continue learning</a>
            </div>
          </article>
        <?php endforeach; if (!$courses) : ?>
          <p>No courses yet. After a payment is approved, they appear here.</p>
        <?php endif; ?>
      </div>
    </section>
    <div class="pkc-grid pkc-grid--2">
      <section class="pkc-panel">
        <h2>Recently accessed</h2>
        <ul><?php foreach ($recent as $r) : ?>
          <li><?php echo esc_html($r->lesson_title ?: $r->event_type); ?> — <?php echo esc_html($r->course_title); ?></li>
        <?php endforeach; if (!$recent) echo '<li>Nothing yet.</li>'; ?></ul>
      </section>
      <section class="pkc-panel">
        <h2>Quiz statistics</h2>
        <?php
        $n = count($history);
        $avg = $n ? round(array_sum(array_map(function ($h) { return (float) $h->percentage; }, $history)) / $n, 1) : 0;
        ?>
        <p>Attempts: <?php echo (int) $n; ?></p>
        <p>Average score: <?php echo esc_html($avg); ?>%</p>
        <ul>
          <?php foreach (array_slice($history, 0, 5) as $h) : ?>
            <li><a href="<?php echo esc_url(pkc_url('student/quiz-result/' . $h->id . '/')); ?>"><?php echo esc_html($h->quiz_title); ?> — <?php echo esc_html($h->percentage); ?>%</a></li>
          <?php endforeach; ?>
        </ul>
      </section>
    </div>
    <section class="pkc-panel">
      <h2>Notifications</h2>
      <ul><?php foreach ($notes as $n) : ?>
        <li><?php echo esc_html($n->title); ?> — <?php echo esc_html($n->body); ?></li>
      <?php endforeach; if (!$notes) echo '<li>You are up to date.</li>'; ?></ul>
    </section>
  </div>
</div>
<?php get_footer(); ?>
