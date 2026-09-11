<?php
/**
 * Template Name: PKCouncil Login
 */
get_header();
$portal = sanitize_text_field($_GET['portal'] ?? 'student');
if ($portal !== 'teacher') {
    $portal = 'student';
}
$error = function_exists('pkc_flash') ? pkc_flash('login_error') : '';
?>
<section class="pkc-auth">
  <div class="pkc-auth__card">
    <p class="pkc-kicker"><?php echo $portal === 'teacher' ? 'Faculty' : 'Students'; ?></p>
    <h1><?php echo $portal === 'teacher' ? 'Teacher login' : 'Student login'; ?></h1>
    <p>PKCouncil accounts are not WordPress users. Sign in here to reach your portal.</p>
    <?php if ($error) : ?><p class="pkc-alert"><?php echo esc_html($error); ?></p><?php endif; ?>
    <form class="pkc-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <?php wp_nonce_field('pkc_login'); ?>
      <input type="hidden" name="action" value="pkc_login">
      <input type="hidden" name="portal" value="<?php echo esc_attr($portal); ?>">
      <label>Username or email<input name="login" required autocomplete="username"></label>
      <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
      <?php if ($portal === 'student') : ?>
        <label class="pkc-check"><input type="checkbox" name="remember" value="1"> Remember me</label>
      <?php endif; ?>
      <button class="pkc-btn pkc-btn--gold" type="submit">Login</button>
    </form>
    <p><a href="<?php echo esc_url(home_url('/forgot-password/?portal=' . $portal)); ?>">Forgot password</a></p>
    <?php if ($portal === 'student') : ?>
      <p class="pkc-switch">Login as <a href="<?php echo esc_url(home_url('/login/?portal=teacher')); ?>">Teacher</a></p>
    <?php else : ?>
      <p class="pkc-switch">Login as <a href="<?php echo esc_url(home_url('/login/')); ?>">Student</a></p>
    <?php endif; ?>
  </div>
</section>
<?php get_footer(); ?>
