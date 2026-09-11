<?php
/**
 * Template Name: PKCouncil About
 */
get_header();
$a = pkc_settings('about', array());
?>
<section class="pkc-page-hero">
  <div class="pkc-shell">
    <p class="pkc-kicker">About Us</p>
    <h1><?php echo esc_html($a['org_name'] ?? 'PKCouncil'); ?></h1>
    <p class="pkc-lede"><?php echo esc_html($a['introduction'] ?? ''); ?></p>
  </div>
</section>
<div class="pkc-shell pkc-prose">
  <section class="pkc-panel reveal">
    <h2>A message from the founder</h2>
    <p><?php echo esc_html($a['founder_message'] ?? ''); ?></p>
    <p><strong><?php echo esc_html($a['founder_name'] ?? ''); ?></strong> — <?php echo esc_html($a['founder_title'] ?? ''); ?></p>
    <p><?php echo esc_html($a['founder_info'] ?? ''); ?></p>
  </section>
  <div class="pkc-grid pkc-grid--2">
    <section class="pkc-panel reveal">
      <h2>Academic manager</h2>
      <p><strong><?php echo esc_html($a['manager_name'] ?? ''); ?></strong></p>
      <p><?php echo esc_html($a['manager_info'] ?? ''); ?></p>
    </section>
    <section class="pkc-panel reveal">
      <h2>Why PKCouncil exists</h2>
      <p><?php echo esc_html($a['why_exists'] ?? ''); ?></p>
    </section>
  </div>
  <div class="pkc-grid pkc-grid--3">
    <section class="pkc-panel reveal"><h2>Mission</h2><p><?php echo esc_html($a['mission'] ?? ''); ?></p></section>
    <section class="pkc-panel reveal"><h2>Vision</h2><p><?php echo esc_html($a['vision'] ?? ''); ?></p></section>
    <section class="pkc-panel reveal"><h2>Educational philosophy</h2><p><?php echo esc_html($a['philosophy'] ?? ''); ?></p></section>
  </div>
</div>
<?php get_footer(); ?>
