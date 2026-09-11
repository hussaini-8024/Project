<?php
get_header();
$token = get_query_var('pkc_token');
?>
<section class="pkc-auth">
  <div class="pkc-auth__card">
    <h1>Reset password</h1>
    <form class="pkc-form" data-reset-form>
      <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
      <label>New password<input type="password" name="password" minlength="8" required></label>
      <button class="pkc-btn pkc-btn--gold" type="submit">Update password</button>
      <p class="pkc-form-msg" data-form-msg hidden></p>
    </form>
  </div>
</section>
<?php get_footer(); ?>
