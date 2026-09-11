<?php
$course = pkc_course(get_query_var('pkc_slug'));
if (!$course) {
    status_header(404);
    get_template_part('404');
    return;
}
get_header();
$error = pkc_flash('pay_error');
$ok = pkc_flash('pay_ok') || !empty($_GET['submitted']);
$price = PKC_Coupons::course_price($course);
?>
<section class="pkc-page-hero">
  <div class="pkc-shell">
    <p class="pkc-kicker">Payment verification</p>
    <h1>Buy <?php echo esc_html($course->title); ?></h1>
    <p class="pkc-lede">The course is already selected. Complete the transfer using a PKCouncil payment method, then submit your receipt. Access begins after admin approval.</p>
  </div>
</section>
<div class="pkc-shell pkc-checkout">
  <?php if ($ok) : ?>
    <div class="pkc-panel pkc-alert-ok">Payment submitted. PKCouncil will verify it shortly. You will receive access after approval.</div>
  <?php endif; ?>
  <?php if ($error) : ?><div class="pkc-alert"><?php echo esc_html($error); ?></div><?php endif; ?>
  <form class="pkc-panel pkc-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
    <?php wp_nonce_field('pkc_payment'); ?>
    <input type="hidden" name="action" value="pkc_payment">
    <input type="hidden" name="course_id" value="<?php echo (int) $course->id; ?>">
    <input type="hidden" name="course_slug" value="<?php echo esc_attr($course->slug); ?>">
    <label>Full name<input name="full_name" required></label>
    <label>Email<input type="email" name="email" required></label>
    <label>WhatsApp number<input name="whatsapp" required></label>
    <label class="pkc-span">Address<textarea name="address" required></textarea></label>
    <label>Course<input value="<?php echo esc_attr($course->title); ?>" readonly></label>
    <label>Coupon code (optional)<input name="coupon_code" data-coupon data-course="<?php echo (int) $course->id; ?>"></label>
    <p class="pkc-span" data-coupon-out>Amount due: <strong><?php echo esc_html(pkc_format_price($price)); ?></strong></p>
    <label>Payment method
      <select name="payment_method_id" required data-method>
        <option value="">Select</option>
        <?php foreach (pkc_payment_methods() as $m) : ?>
          <option value="<?php echo (int) $m->id; ?>" data-ins="<?php echo esc_attr($m->instructions . "\n" . $m->account_details); ?>"><?php echo esc_html($m->name); ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <p class="pkc-span pkc-method-help" data-method-help></p>
    <label>Transaction ID<input name="transaction_id" required></label>
    <label>Payment receipt<input type="file" name="receipt" accept=".pdf,.jpg,.jpeg,.png,.webp" required></label>
    <button class="pkc-btn pkc-btn--gold pkc-span" type="submit">Submit for verification</button>
  </form>
</div>
<?php get_footer(); ?>
