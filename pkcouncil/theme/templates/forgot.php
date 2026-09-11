<?php
get_header();
$portal = sanitize_text_field($_GET['portal'] ?? 'student');
?>
<section class="pkc-auth">
  <div class="pkc-auth__card">
    <h1>Forgot password</h1>
    <form class="pkc-form" data-forgot-form>
      <input type="hidden" name="portal" value="<?php echo esc_attr($portal); ?>">
      <label>Username or email<input name="login" required></label>
      <button class="pkc-btn pkc-btn--gold" type="submit">Send reset link</button>
      <p class="pkc-form-msg" data-form-msg hidden></p>
    </form>
    <p><a href="<?php echo esc_url(home_url('/login/?portal=' . $portal)); ?>">Back to login</a></p>
  </div>
</section>
<?php get_footer(); ?>
