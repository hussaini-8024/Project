<?php
/**
 * Template Name: GCM Payment Verify
 *
 * @package GigaClassMarket
 */

$course_id = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0;

get_header();
?>
<section class="gcm-payment-page">
	<div class="gcm-container gcm-contact-grid">
		<div class="gcm-payment-card gcm-animate">
			<p class="gcm-eyebrow"><?php esc_html_e( 'Verification', 'giga-class-market' ); ?></p>
			<h1><?php esc_html_e( 'Verify your payment', 'giga-class-market' ); ?></h1>
			<p><?php esc_html_e( 'Upload your payment confirmation so the Giga Class Market team can activate your course access.', 'giga-class-market' ); ?></p>
		</div>
		<form class="gcm-contact-form gcm-animate" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-gcm-ajax-form>
			<input type="hidden" name="action" value="gcm_payment_verify">
			<input type="hidden" name="course_id" value="<?php echo esc_attr( $course_id ); ?>">
			<?php wp_nonce_field( 'gcm_payment_verify_nonce', 'nonce' ); ?>
			<label>
				<span><?php esc_html_e( 'Full name', 'giga-class-market' ); ?></span>
				<input type="text" name="name" required autocomplete="name">
			</label>
			<label>
				<span><?php esc_html_e( 'Email address', 'giga-class-market' ); ?></span>
				<input type="email" name="email" required autocomplete="email">
			</label>
			<label>
				<span><?php esc_html_e( 'Transaction reference', 'giga-class-market' ); ?></span>
				<input type="text" name="transaction_reference" required>
			</label>
			<label>
				<span><?php esc_html_e( 'Upload proof', 'giga-class-market' ); ?></span>
				<input type="file" name="payment_proof" accept="image/*,.pdf" required>
			</label>
			<button class="gcm-button gcm-button--gold" type="submit"><?php esc_html_e( 'Submit Verification', 'giga-class-market' ); ?></button>
			<p class="gcm-form-status" role="status" aria-live="polite"></p>
		</form>
	</div>
</section>
<?php
get_footer();
