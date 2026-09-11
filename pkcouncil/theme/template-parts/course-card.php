<?php
$course = $args['course'] ?? null;
if (!$course) {
    return;
}
$cat = $course->category_id ? pkc_category($course->category_id) : null;
$teachers = pkc_course_teachers($course->id);
?>
<article class="pkc-card reveal">
  <a class="pkc-card__media" href="<?php echo esc_url(pkc_url('course/' . $course->slug . '/')); ?>">
    <img src="<?php echo esc_url(pkc_course_image_url($course)); ?>" alt="<?php echo esc_attr($course->title); ?>" loading="lazy" width="800" height="500">
  </a>
  <div class="pkc-card__body">
    <div class="pkc-card__meta">
      <?php if ($cat) : ?><span><?php echo esc_html($cat->name); ?></span><?php endif; ?>
      <strong><?php echo esc_html(pkc_format_price(PKC_Coupons::course_price($course))); ?></strong>
    </div>
    <h3><a href="<?php echo esc_url(pkc_url('course/' . $course->slug . '/')); ?>"><?php echo esc_html($course->title); ?></a></h3>
    <p><?php echo esc_html($course->excerpt); ?></p>
    <?php if ($teachers) : ?>
      <p class="pkc-card__teacher"><?php echo esc_html($teachers[0]->full_name); ?></p>
    <?php endif; ?>
    <div class="pkc-card__actions">
      <a class="pkc-btn pkc-btn--ghost" href="<?php echo esc_url(pkc_url('course/' . $course->slug . '/')); ?>">View details</a>
      <a class="pkc-btn pkc-btn--gold" href="<?php echo esc_url(pkc_url('course/' . $course->slug . '/buy/')); ?>">Buy course</a>
    </div>
  </div>
</article>
