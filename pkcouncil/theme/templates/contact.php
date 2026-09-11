<?php
/**
 * Template Name: PKCouncil Contact
 */
get_header();
?>
<section class="pkc-page-hero">
  <div class="pkc-shell">
    <p class="pkc-kicker">Contact Us</p>
    <h1>Write to the council</h1>
    <p class="pkc-lede">Questions about a course, a payment, or how PKCouncil works — send a message or reach us on WhatsApp.</p>
  </div>
</section>
<div class="pkc-shell pkc-contact">
  <form class="pkc-panel pkc-form" data-contact-form>
    <label>Name<input name="name" required></label>
    <label>Email<input type="email" name="email" required></label>
    <label>WhatsApp / phone<input name="whatsapp"></label>
    <label>Subject<input name="subject" required></label>
    <label>Preferred contact
      <select name="preferred_contact">
        <option value="email">Email</option>
        <option value="whatsapp">WhatsApp</option>
      </select>
    </label>
    <label>Course of interest
      <select name="course_id">
        <option value="0">General enquiry</option>
        <?php foreach (pkc_courses(array('limit' => 50)) as $c) : ?>
          <option value="<?php echo (int) $c->id; ?>"><?php echo esc_html($c->title); ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="pkc-span">Message<textarea name="message" rows="6" required></textarea></label>
    <button class="pkc-btn pkc-btn--gold pkc-span" type="submit">Send message</button>
    <p class="pkc-form-msg pkc-span" data-form-msg hidden></p>
  </form>
  <aside class="pkc-panel">
    <h2>Direct lines</h2>
    <p><a class="pkc-btn pkc-btn--gold" href="<?php echo esc_url(pkc_whatsapp_url('Hello PKCouncil')); ?>" target="_blank" rel="noopener">WhatsApp</a></p>
    <p><a href="mailto:<?php echo esc_attr(pkc_settings('email')); ?>"><?php echo esc_html(pkc_settings('email')); ?></a></p>
    <p><?php echo esc_html(pkc_settings('phone')); ?></p>
    <p><?php echo esc_html(pkc_settings('address')); ?></p>
  </aside>
</div>
<?php get_footer(); ?>
