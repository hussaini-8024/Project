<?php
$acc = pkc_current_account();
get_header();
$courses = PKC_Access::student_courses($acc->id);
?>
<div class="pkc-portal">
  <?php get_template_part('template-parts/portal', 'nav'); ?>
  <div class="pkc-portal__main">
    <h1>My courses</h1>
    <div class="pkc-grid pkc-grid--3">
      <?php foreach ($courses as $c) :
        $pct = PKC_Progress::percent($acc->id, $c->id); ?>
        <article class="pkc-card">
          <img src="<?php echo esc_url(pkc_course_image_url($c)); ?>" alt="">
          <div class="pkc-card__body">
            <h3><?php echo esc_html($c->title); ?></h3>
            <div class="pkc-meter"><span style="width:<?php echo (int) $pct; ?>%"></span></div>
            <p><?php echo (int) $pct; ?>% complete</p>
            <div class="pkc-card__actions">
              <a class="pkc-btn pkc-btn--gold" href="<?php echo esc_url(pkc_url('student/course/' . $c->slug . '/')); ?>">Continue</a>
              <a class="pkc-btn pkc-btn--ghost" href="<?php echo esc_url(pkc_url('student/course/' . $c->slug . '/chat/')); ?>">Teacher chat</a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php get_footer(); ?>
