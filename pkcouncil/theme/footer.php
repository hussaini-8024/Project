</main>
<footer class="pkc-footer">
  <div class="pkc-shell pkc-footer__grid">
    <div>
      <a class="pkc-logo pkc-logo--foot" href="<?php echo esc_url(home_url('/')); ?>">
        <span class="pkc-logo__word">PKCouncil</span>
      </a>
      <p><?php echo esc_html(function_exists('pkc_settings') ? pkc_settings('footer_about') : 'Premium self-paced learning.'); ?></p>
    </div>
    <div>
      <h2>Quick links</h2>
      <a href="<?php echo esc_url(pkc_page_url('courses')); ?>">Courses</a>
      <a href="<?php echo esc_url(pkc_page_url('about-us')); ?>">About Us</a>
      <a href="<?php echo esc_url(pkc_page_url('contact-us')); ?>">Contact Us</a>
      <a href="<?php echo esc_url(pkc_login_url()); ?>">Login</a>
    </div>
    <div>
      <h2>Contact</h2>
      <?php if (function_exists('pkc_whatsapp_url')) : ?>
        <a href="<?php echo esc_url(pkc_whatsapp_url('Hello PKCouncil')); ?>" target="_blank" rel="noopener">WhatsApp</a>
      <?php endif; ?>
      <a href="mailto:<?php echo esc_attr(function_exists('pkc_settings') ? pkc_settings('email') : 'hello@pkcouncil.org'); ?>">Email</a>
      <p><?php echo esc_html(function_exists('pkc_settings') ? pkc_settings('address') : ''); ?></p>
    </div>
  </div>
  <div class="pkc-shell pkc-footer__base">
    <p>© <?php echo esc_html(gmdate('Y')); ?> PKCouncil. All rights reserved.</p>
    <p><a href="<?php echo esc_url(pkc_page_url('about-us')); ?>">Academic integrity</a></p>
  </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
