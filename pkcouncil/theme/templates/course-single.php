<?php
$course = pkc_course(get_query_var('pkc_slug'));
if (!$course) {
    global $wp_query;
    $wp_query->set_404();
    status_header(404);
    get_template_part('404');
    return;
}
get_header();
$teachers = pkc_course_teachers($course->id);
$cat = $course->category_id ? pkc_category($course->category_id) : null;
$learn = pkc_json($course->what_you_learn);
$includes = pkc_json($course->includes);
$faqs = pkc_json($course->faqs);
$modules = pkc_course_modules($course->id);
$reviews = pkc_reviews(array('course_id' => $course->id, 'limit' => 8));
?>
<article class="pkc-course">
  <header class="pkc-course__hero">
    <div class="pkc-shell pkc-course__hero-grid">
      <div>
        <?php if ($cat) : ?><p class="pkc-kicker"><?php echo esc_html($cat->name); ?></p><?php endif; ?>
        <h1><?php echo esc_html($course->title); ?></h1>
        <p class="pkc-lede"><?php echo esc_html($course->excerpt); ?></p>
        <p class="pkc-price"><?php echo esc_html(pkc_format_price(PKC_Coupons::course_price($course))); ?></p>
        <?php if ($teachers) : ?><p>Instructor: <?php echo esc_html($teachers[0]->full_name); ?></p><?php endif; ?>
        <div class="pkc-hero__cta">
          <a class="pkc-btn pkc-btn--gold" href="<?php echo esc_url(pkc_url('course/' . $course->slug . '/buy/')); ?>">Buy course</a>
          <a class="pkc-btn pkc-btn--ghost" href="<?php echo esc_url(pkc_whatsapp_url('I am interested in ' . $course->title)); ?>" target="_blank" rel="noopener">WhatsApp</a>
        </div>
      </div>
      <img class="pkc-course__cover" src="<?php echo esc_url(pkc_course_image_url($course)); ?>" alt="">
    </div>
  </header>
  <div class="pkc-shell pkc-course__body">
    <div>
      <section class="pkc-panel"><h2>Overview</h2><?php echo wp_kses_post(wpautop($course->overview ?: $course->description)); ?></section>
      <section class="pkc-panel">
        <h2>What you will learn</h2>
        <ul class="pkc-list"><?php foreach ($learn as $item) : ?><li><?php echo esc_html($item); ?></li><?php endforeach; ?></ul>
      </section>
      <section class="pkc-panel">
        <h2>Modules</h2>
        <?php foreach ($modules as $mod) : ?>
          <details class="pkc-mod" open>
            <summary><?php echo esc_html($mod->title); ?></summary>
            <ul><?php foreach (pkc_module_lessons($mod->id) as $les) : ?><li><?php echo esc_html($les->title); ?></li><?php endforeach; ?></ul>
          </details>
        <?php endforeach; ?>
      </section>
      <?php if ($faqs) : ?>
      <section class="pkc-panel">
        <h2>FAQs</h2>
        <?php foreach ($faqs as $f) : ?>
          <details><summary><?php echo esc_html($f['q'] ?? ''); ?></summary><p><?php echo esc_html($f['a'] ?? ''); ?></p></details>
        <?php endforeach; ?>
      </section>
      <?php endif; ?>
      <?php if ($reviews) : ?>
      <section class="pkc-panel">
        <h2>Reviews</h2>
        <?php foreach ($reviews as $r) : ?>
          <blockquote class="pkc-review"><p><?php echo esc_html($r->content); ?></p><footer><?php echo esc_html($r->author_name); ?></footer></blockquote>
        <?php endforeach; ?>
      </section>
      <?php endif; ?>
    </div>
    <aside class="pkc-panel pkc-buybox">
      <h2>Included</h2>
      <ul class="pkc-list"><?php foreach ($includes as $inc) : ?><li><?php echo esc_html($inc); ?></li><?php endforeach; ?></ul>
      <p>Practice MCQs, timed quizzes, protected PDFs, and course-specific teacher chat after payment approval.</p>
      <a class="pkc-btn pkc-btn--gold" href="<?php echo esc_url(pkc_url('course/' . $course->slug . '/buy/')); ?>">Buy course</a>
    </aside>
  </div>
</article>
<?php get_footer(); ?>
