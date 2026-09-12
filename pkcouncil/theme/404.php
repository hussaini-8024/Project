<?php get_header(); ?>
<div class="pkc-shell pkc-page">
  <h1>Page not found</h1>
  <p>That page is not part of the PKCouncil library. Return home or browse courses.</p>
  <a class="pkc-btn pkc-btn--gold" href="<?php echo esc_url(home_url('/')); ?>">Home</a>
  <?php if (function_exists('pkc_page_url')) : ?>
    <a class="pkc-btn pkc-btn--ghost" href="<?php echo esc_url(pkc_page_url('courses')); ?>">Browse courses</a>
  <?php endif; ?>
</div>
<?php get_footer(); ?>
