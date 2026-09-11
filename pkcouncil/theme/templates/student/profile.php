<?php
$acc = pkc_current_account();
get_header();
?>
<div class="pkc-portal">
  <?php get_template_part('template-parts/portal', 'nav'); ?>
  <div class="pkc-portal__main">
    <h1>Profile & settings</h1>
    <?php if ((int) $acc->must_change_password) : ?>
      <div class="pkc-alert">You must change the initial password before the account is fully at rest.</div>
    <?php endif; ?>
    <form class="pkc-panel pkc-form" data-profile-form>
      <label>Name<input name="full_name" value="<?php echo esc_attr($acc->full_name); ?>" required></label>
      <label>Email<input type="email" name="email" value="<?php echo esc_attr($acc->email); ?>" required></label>
      <label>WhatsApp<input name="whatsapp" value="<?php echo esc_attr($acc->whatsapp); ?>"></label>
      <label class="pkc-span">Address<textarea name="address"><?php echo esc_textarea($acc->address); ?></textarea></label>
      <label class="pkc-span">About<textarea name="profile_bio"><?php echo esc_textarea($acc->profile_bio); ?></textarea></label>
      <button class="pkc-btn pkc-btn--gold" type="submit">Save profile</button>
      <p class="pkc-form-msg pkc-span" data-form-msg hidden></p>
    </form>
    <form class="pkc-panel pkc-form" data-password-form>
      <h2>Change password</h2>
      <label>Current password<input type="password" name="current_password" required></label>
      <label>New password<input type="password" name="new_password" minlength="8" required></label>
      <button class="pkc-btn pkc-btn--ghost" type="submit">Update password</button>
      <p class="pkc-form-msg pkc-span" data-form-msg hidden></p>
    </form>
  </div>
</div>
<?php get_footer(); ?>
