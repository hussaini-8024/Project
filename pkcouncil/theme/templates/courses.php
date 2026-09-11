<?php
/**
 * Template Name: PKCouncil Courses
 */
get_header();
$args = array(
    'search'   => sanitize_text_field($_GET['q'] ?? ''),
    'category' => (int) ($_GET['category'] ?? 0),
    'level'    => sanitize_text_field($_GET['level'] ?? ''),
    'type'     => sanitize_text_field($_GET['type'] ?? ''),
    'min_price'=> $_GET['min_price'] ?? '',
    'max_price'=> $_GET['max_price'] ?? '',
    'sort'     => sanitize_text_field($_GET['sort'] ?? 'newest'),
    'limit'    => 24,
);
$courses = pkc_courses($args);
?>
<section class="pkc-page-hero">
  <div class="pkc-shell">
    <p class="pkc-kicker">Marketplace</p>
    <h1>Courses</h1>
    <p class="pkc-lede">Search the PKCouncil catalogue. Every course is self-paced: notes, MCQs, quizzes, and teacher support.</p>
  </div>
</section>
<div class="pkc-shell">
  <form class="pkc-filters" method="get">
    <input type="search" name="q" placeholder="Search courses" value="<?php echo esc_attr($args['search']); ?>">
    <select name="category">
      <option value="0">All categories</option>
      <?php foreach (pkc_categories() as $cat) : ?>
        <option value="<?php echo (int) $cat->id; ?>" <?php selected($args['category'], $cat->id); ?>><?php echo esc_html($cat->name); ?></option>
      <?php endforeach; ?>
    </select>
    <select name="level">
      <option value="">All levels</option>
      <?php foreach (array('beginner','intermediate','advanced') as $lv) : ?>
        <option value="<?php echo esc_attr($lv); ?>" <?php selected($args['level'], $lv); ?>><?php echo esc_html(ucfirst($lv)); ?></option>
      <?php endforeach; ?>
    </select>
    <select name="type">
      <option value="">All types</option>
      <option value="self-paced" <?php selected($args['type'], 'self-paced'); ?>>Self-paced</option>
    </select>
    <input type="number" name="min_price" placeholder="Min price" value="<?php echo esc_attr($args['min_price']); ?>">
    <input type="number" name="max_price" placeholder="Max price" value="<?php echo esc_attr($args['max_price']); ?>">
    <select name="sort">
      <option value="newest" <?php selected($args['sort'], 'newest'); ?>>Newest</option>
      <option value="oldest" <?php selected($args['sort'], 'oldest'); ?>>Oldest</option>
      <option value="price_asc" <?php selected($args['sort'], 'price_asc'); ?>>Price: low to high</option>
      <option value="price_desc" <?php selected($args['sort'], 'price_desc'); ?>>Price: high to low</option>
      <option value="popular" <?php selected($args['sort'], 'popular'); ?>>Popular</option>
      <option value="featured" <?php selected($args['sort'], 'featured'); ?>>Featured</option>
    </select>
    <button class="pkc-btn pkc-btn--gold" type="submit">Filter</button>
  </form>
  <div class="pkc-grid pkc-grid--3">
    <?php if (!$courses) : ?>
      <p>No courses match those filters.</p>
    <?php else : foreach ($courses as $course) : ?>
      <?php get_template_part('template-parts/course', 'card', array('course' => $course)); ?>
    <?php endforeach; endif; ?>
  </div>
</div>
<?php get_footer(); ?>
