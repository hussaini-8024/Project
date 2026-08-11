<?php
/**
 * Template Name: GCM Payment Verify
 *
 * @package GigaClassMarket
 */

$course_id = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0;
$course    = ( $course_id && class_exists( 'GCM_Course_Service' ) ) ? GCM_Course_Service::get( $course_id ) : null;
$methods   = class_exists( 'GCM_Settings_Service' ) ? GCM_Settings_Service::get_payment_methods() : array();
$user      = wp_get_current_user();

get_header();
?>
<section class="gcm-payment-page">
	<div class="gcm-container gcm-contact-grid">
		<div class="gcm-payment-card gcm-animate">
			<p class="gcm-eyebrow"><?php esc_html_e( 'Verification', 'giga-class-market' ); ?></p>
			<h1><?php esc_html_e( 'Submit payment verification', 'giga-class-market' ); ?></h1>
			<?php if ( $course ) : ?>
				<p><?php echo esc_html( sprintf( __( 'Course: %1$s — Amount: %2$s', 'giga-class-market' ), $course['title'], gcm_format_price( $course['price'] ) ) ); ?></p>
			<?php endif; ?>
			<p><?php esc_html_e( 'After you submit, your request stays pending until an administrator verifies the payment. You will then receive account/course access details.', 'giga-class-market' ); ?></p>
			<?php if ( is_user_logged_in() ) : ?>
				<p class="gcm-notice"><?php esc_html_e( 'You are logged in. This purchase will be linked to your existing student account.', 'giga-class-market' ); ?></p>
			<?php endif; ?>
		</div>

		<form class="gcm-contact-form gcm-animate" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-gcm-ajax-form>
			<input type="hidden" name="action" value="gcm_payment_submit">
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'gcm_ajax_nonce' ) ); ?>">
			<input type="hidden" name="course_id" value="<?php echo esc_attr( $course_id ); ?>">

			<label>
				<span><?php esc_html_e( 'Full Name', 'giga-class-market' ); ?></span>
				<input type="text" name="full_name" required autocomplete="name" value="<?php echo esc_attr( $user->display_name ?? '' ); ?>">
			</label>
			<label>
				<span><?php esc_html_e( 'Email', 'giga-class-market' ); ?></span>
				<input type="email" name="email" required autocomplete="email" value="<?php echo esc_attr( $user->user_email ?? '' ); ?>">
			</label>
			<label>
				<span><?php esc_html_e( 'WhatsApp Number', 'giga-class-market' ); ?></span>
				<input type="text" name="whatsapp" required autocomplete="tel" value="<?php echo esc_attr( get_user_meta( get_current_user_id(), 'gcm_whatsapp', true ) ); ?>">
			</label>
			<label>
				<span><?php esc_html_e( 'Address', 'giga-class-market' ); ?></span>
				<textarea name="address" rows="3"><?php echo esc_textarea( get_user_meta( get_current_user_id(), 'gcm_address', true ) ); ?></textarea>
			</label>
			<label>
				<span><?php esc_html_e( 'Course', 'giga-class-market' ); ?></span>
				<input type="text" value="<?php echo esc_attr( $course ? $course['title'] : '' ); ?>" readonly>
			</label>
			<label>
				<span><?php esc_html_e( 'Payment method', 'giga-class-market' ); ?></span>
				<select name="payment_method" required>
					<option value=""><?php esc_html_e( 'Select method', 'giga-class-market' ); ?></option>
					<?php foreach ( $methods as $name => $method ) : ?>
						<option value="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<span><?php esc_html_e( 'Transaction ID', 'giga-class-market' ); ?></span>
				<input type="text" name="transaction_id" required>
			</label>
			<label>
				<span><?php esc_html_e( 'Payment screenshot / receipt (optional)', 'giga-class-market' ); ?></span>
				<input type="file" name="screenshot" accept="image/jpeg,image/png,application/pdf">
			</label>
			<button class="gcm-button gcm-button--gold" type="submit"><?php esc_html_e( 'I Have Paid / Submit for Verification', 'giga-class-market' ); ?></button>
			<p class="gcm-form-status" role="status" aria-live="polite"></p>
		</form>
	</div>
</section>
<?php
get_footer();
