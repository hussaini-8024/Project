<!DOCTYPE html>
<html <?php language_attributes(); ?> data-theme="light">
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
<script>
(function(){try{var t=localStorage.getItem('pkc-theme');if(t==='dark'||(!t&&window.matchMedia('(prefers-color-scheme:dark)').matches)){document.documentElement.setAttribute('data-theme','dark');}}catch(e){}})();
</script>
</head>
<body <?php body_class('pkc-body'); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#content">Skip to content</a>
<header class="pkc-header" data-header>
  <div class="pkc-shell pkc-header__inner">
    <a class="pkc-logo" href="<?php echo esc_url(home_url('/')); ?>" aria-label="PKCouncil home">
      <span class="pkc-logo__mark" aria-hidden="true">
        <svg viewBox="0 0 48 48" width="32" height="32"><rect width="48" height="48" rx="12" fill="currentColor" opacity=".12"/><path d="M12 34V14h12.5c4.6 0 7.5 2.6 7.5 6.4 0 2.6-1.5 4.7-4 5.6L36 34h-6.2l-7.2-7.6H18V34H12zm6-13.2h5.6c2.2 0 3.5-1.1 3.5-2.8S25.8 15.2 23.6 15.2H18v5.6z" fill="currentColor"/></svg>
      </span>
      <span class="pkc-logo__word">PKCouncil</span>
    </a>
    <nav class="pkc-nav" data-nav aria-label="Primary">
      <?php foreach (pkc_nav_items() as $url => $label) : ?>
        <a href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="pkc-header__actions">
      <button class="pkc-theme" type="button" data-theme-toggle aria-label="Toggle dark mode">
        <span class="pkc-theme__sun" aria-hidden="true">☀</span>
        <span class="pkc-theme__moon" aria-hidden="true">☾</span>
      </button>
      <?php
      $acc = function_exists('pkc_current_account') ? pkc_current_account() : null;
      if ($acc && $acc->type === 'student') :
      ?>
        <a class="pkc-btn pkc-btn--gold" href="<?php echo esc_url(function_exists('pkc_url') ? pkc_url('student/') : home_url('/')); ?>">Portal</a>
      <?php elseif ($acc && $acc->type === 'teacher') : ?>
        <a class="pkc-btn pkc-btn--gold" href="<?php echo esc_url(function_exists('pkc_url') ? pkc_url('teacher/') : home_url('/')); ?>">Portal</a>
      <?php elseif (function_exists('pkc_is_admin_user') && pkc_is_admin_user()) : ?>
        <a class="pkc-btn pkc-btn--gold" href="<?php echo esc_url(admin_url('admin.php?page=pkcouncil')); ?>">Admin</a>
      <?php else : ?>
        <a class="pkc-btn pkc-btn--gold" href="<?php echo esc_url(function_exists('pkc_login_url') ? pkc_login_url() : home_url('/')); ?>">Login</a>
      <?php endif; ?>
      <button class="pkc-burger" type="button" data-menu-toggle aria-expanded="false" aria-label="Open menu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</header>
<div class="pkc-mobile-nav" data-mobile-nav hidden>
  <?php foreach (pkc_nav_items() as $url => $label) : ?>
    <a href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a>
  <?php endforeach; ?>
  <a href="<?php echo esc_url(function_exists('pkc_login_url') ? pkc_login_url() : home_url('/')); ?>">Login</a>
</div>
<main id="content" class="pkc-main">
