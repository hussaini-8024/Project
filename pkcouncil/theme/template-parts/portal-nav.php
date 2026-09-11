<?php
$acc = pkc_current_account();
$unread = PKC_Notifications::unread_count($acc->type, $acc->id);
$home = $acc->type === 'teacher' ? pkc_url('teacher/') : pkc_url('student/');
?>
<aside class="pkc-side">
  <a class="pkc-logo pkc-logo--foot" href="<?php echo esc_url($home); ?>">PKCouncil</a>
  <p class="pkc-side__name"><?php echo esc_html($acc->full_name); ?></p>
  <nav>
    <?php if ($acc->type === 'student') : ?>
      <a href="<?php echo esc_url(pkc_url('student/')); ?>">Dashboard</a>
      <a href="<?php echo esc_url(pkc_url('student/courses/')); ?>">My courses</a>
      <a href="<?php echo esc_url(pkc_url('student/profile/')); ?>">Profile<?php if ((int) $acc->must_change_password) echo ' •'; ?></a>
    <?php else : ?>
      <a href="<?php echo esc_url(pkc_url('teacher/')); ?>">Dashboard</a>
      <a href="<?php echo esc_url(pkc_url('teacher/profile/')); ?>">Profile</a>
    <?php endif; ?>
    <a href="<?php echo esc_url(pkc_url('logout/')); ?>">Logout</a>
  </nav>
  <p class="pkc-side__note"><?php echo (int) $unread; ?> unread notifications</p>
</aside>
