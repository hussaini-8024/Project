<?php get_header(); ?>
<section class="pkc-hero" data-hero>
  <div class="pkc-hero__glow" aria-hidden="true"></div>
  <div class="pkc-shell pkc-hero__grid">
    <div class="pkc-hero__copy">
      <p class="pkc-kicker reveal"><?php echo esc_html(pkc_settings('hero_kicker')); ?></p>
      <h1 class="reveal"><?php echo esc_html(pkc_settings('hero_title')); ?></h1>
      <p class="pkc-lede reveal"><?php echo esc_html(pkc_settings('hero_subtitle')); ?></p>
      <div class="pkc-hero__cta reveal">
        <a class="pkc-btn pkc-btn--gold" href="<?php echo esc_url(home_url('/courses/')); ?>"><?php echo esc_html(pkc_settings('hero_cta_primary')); ?></a>
        <a class="pkc-btn pkc-btn--ghost" href="<?php echo esc_url(home_url('/courses/?sort=featured')); ?>"><?php echo esc_html(pkc_settings('hero_cta_secondary')); ?></a>
      </div>
    </div>
    <div class="pkc-hero__stage" aria-hidden="true">
      <div class="pkc-orbit"></div>
      <div class="pkc-orbit pkc-orbit--2"></div>
      <div class="pkc-book" data-book>
        <div class="pkc-book__cover"></div>
        <div class="pkc-book__page"></div>
        <div class="pkc-book__page pkc-book__page--2"></div>
      </div>
      <div class="pkc-chip pkc-chip--1">MCQs</div>
      <div class="pkc-chip pkc-chip--2">Notes</div>
      <div class="pkc-chip pkc-chip--3">Quizzes</div>
    </div>
  </div>
</section>

<section class="pkc-section" id="top-courses">
  <div class="pkc-shell">
    <div class="pkc-section__head reveal">
      <p class="pkc-kicker">Top courses</p>
      <h2>Selected by the council</h2>
      <p>Homepage courses are chosen in the PKCouncil admin — not a random catalogue dump.</p>
    </div>
    <div class="pkc-grid pkc-grid--3">
      <?php foreach (pkc_courses(array('top' => 1, 'limit' => 6)) as $course) : ?>
        <?php get_template_part('template-parts/course', 'card', array('course' => $course)); ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="pkc-section pkc-section--alt" id="learn">
  <div class="pkc-shell">
    <div class="pkc-section__head reveal">
      <p class="pkc-kicker">The PKCouncil method</p>
      <h2><?php echo esc_html(pkc_settings('learn_heading')); ?></h2>
      <p><?php echo esc_html(pkc_settings('learn_intro')); ?></p>
    </div>
    <div class="pkc-grid pkc-grid--4">
      <?php foreach ((array) pkc_settings('learn_items', array()) as $item) : ?>
        <article class="pkc-tile reveal">
          <h3><?php echo esc_html($item['title'] ?? ''); ?></h3>
          <p><?php echo esc_html($item['text'] ?? ''); ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="pkc-section" id="reviews">
  <div class="pkc-shell">
    <div class="pkc-section__head reveal">
      <p class="pkc-kicker">Student voices</p>
      <h2>Reviews</h2>
    </div>
    <div class="pkc-grid pkc-grid--3">
      <?php foreach (pkc_reviews(array('homepage' => 1, 'limit' => 6)) as $review) : ?>
        <blockquote class="pkc-review reveal">
          <div class="pkc-stars" aria-label="<?php echo (int) $review->rating; ?> out of 5"><?php echo str_repeat('★', (int) $review->rating); ?></div>
          <p><?php echo esc_html($review->content); ?></p>
          <footer><?php echo esc_html($review->author_name); ?></footer>
        </blockquote>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="pkc-section pkc-section--alt" id="why">
  <div class="pkc-shell">
    <div class="pkc-section__head reveal">
      <p class="pkc-kicker">A serious academic home</p>
      <h2><?php echo esc_html(pkc_settings('why_heading')); ?></h2>
      <p><?php echo esc_html(pkc_settings('why_intro')); ?></p>
    </div>
    <div class="pkc-grid pkc-grid--4">
      <?php foreach ((array) pkc_settings('why_items', array()) as $item) : ?>
        <article class="pkc-tile pkc-tile--quiet reveal">
          <h3><?php echo esc_html($item['title'] ?? ''); ?></h3>
          <p><?php echo esc_html($item['text'] ?? ''); ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="pkc-cta">
  <div class="pkc-shell pkc-cta__inner">
    <div>
      <h2><?php echo esc_html(pkc_settings('contact_cta_title')); ?></h2>
      <p><?php echo esc_html(pkc_settings('contact_cta_text')); ?></p>
    </div>
    <div class="pkc-hero__cta">
      <a class="pkc-btn pkc-btn--gold" href="<?php echo esc_url(home_url('/contact-us/')); ?>">Contact Us</a>
      <a class="pkc-btn pkc-btn--ghost" href="<?php echo esc_url(pkc_whatsapp_url('Hello PKCouncil, I have a question about courses.')); ?>" target="_blank" rel="noopener">WhatsApp</a>
    </div>
  </div>
</section>
<?php get_footer(); ?>
