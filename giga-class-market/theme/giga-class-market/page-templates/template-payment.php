<?php
/**
 * Template Name: GCM Payment
 *
 * @package GigaClassMarket
 */

$course_id = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0;
$course    = ( $course_id && class_exists( 'GCM_Course_Service' ) ) ? GCM_Course_Service::get( $course_id ) : null;
$methods   = class_exists( 'GCM_Settings_Service' ) ? GCM_Settings_Service::get_payment_methods() : array();
$verify_url = add_query_arg( 'course_id', $course_id, home_url( '/payment-verification/' ) );

$regular_price   = $course ? (float) $course['price'] : 0;
$discount_price  = $course ? (float) ( $course['discount_price'] ?? 0 ) : 0;
$sale_label      = $course ? (string) ( $course['sale_label'] ?? '' ) : '';
$effective_price = $course ? (float) ( $course['effective_price'] ?? $regular_price ) : 0;
$on_sale         = $discount_price > 0 && $discount_price < $regular_price;

get_header();
?>
<section class="gcm-payment-page">
	<div class="gcm-container gcm-payment-card gcm-animate">
		<p class="gcm-eyebrow"><?php esc_html_e( 'Purchase', 'giga-class-market' ); ?></p>
		<h1><?php esc_html_e( 'Payment information', 'giga-class-market' ); ?></h1>

		<?php if ( $course ) : ?>
			<div class="gcm-payment-summary">
				<h2><?php echo esc_html( $course['title'] ); ?></h2>
				<p class="gcm-payment-price">
					<?php if ( $on_sale ) : ?>
						<?php if ( $sale_label ) : ?>
							<span class="gcm-sale-label"><?php echo esc_html( $sale_label ); ?></span>
						<?php endif; ?>
						<s><?php echo esc_html( gcm_format_price( $regular_price ) ); ?></s>
						<strong><?php echo esc_html( gcm_format_price( $effective_price ) ); ?></strong>
					<?php else : ?>
						<?php echo esc_html( gcm_format_price( $effective_price ) ); ?>
					<?php endif; ?>
				</p>
				<p><?php esc_html_e( 'Transfer the exact course amount using one of the company payment accounts below, then proceed to submit your transaction ID for verification. Coupons can be applied on the next step.', 'giga-class-market' ); ?></p>
			</div>
		<?php else : ?>
			<div class="gcm-empty-state">
				<h2><?php esc_html_e( 'Course not found', 'giga-class-market' ); ?></h2>
				<p><?php esc_html_e( 'Please return to the courses page and choose a valid course.', 'giga-class-market' ); ?></p>
			</div>
		<?php endif; ?>

		<div class="gcm-payment-card__info">
			<h2><?php esc_html_e( 'Company payment accounts', 'giga-class-market' ); ?></h2>
			<?php if ( ! empty( $methods ) ) : ?>
				<div class="gcm-payment-methods">
					<?php foreach ( $methods as $name => $method ) : ?>
						<article class="gcm-payment-method">
							<strong><?php echo esc_html( $name ); ?></strong>
							<?php if ( ! empty( $method['account_name'] ) ) : ?>
								<p><?php echo esc_html( sprintf( __( 'Account title: %s', 'giga-class-market' ), $method['account_name'] ) ); ?></p>
							<?php endif; ?>
							<?php if ( ! empty( $method['account_no'] ) ) : ?>
								<p><?php echo esc_html( sprintf( __( 'Account number: %s', 'giga-class-market' ), $method['account_no'] ) ); ?></p>
							<?php endif; ?>
							<?php if ( ! empty( $method['instructions'] ) ) : ?>
								<p><?php echo esc_html( $method['instructions'] ); ?></p>
							<?php endif; ?>
						</article>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p><?php esc_html_e( 'Payment accounts will appear here once configured by the administrator in Giga Class Market → Settings.', 'giga-class-market' ); ?></p>
			<?php endif; ?>
			<p><?php esc_html_e( 'After payment, keep your Transaction ID ready. You will need it on the next step.', 'giga-class-market' ); ?></p>
		</div>

		<div class="gcm-hero__actions">
			<?php if ( $course ) : ?>
				<a class="gcm-button gcm-button--gold" href="<?php echo esc_url( $verify_url ); ?>"><?php esc_html_e( 'Proceed', 'giga-class-market' ); ?></a>
			<?php endif; ?>
			<a class="gcm-button gcm-button--outline" href="<?php echo esc_url( $course_id ? get_permalink( $course_id ) : home_url( '/courses/' ) ); ?>"><?php esc_html_e( 'Not Now', 'giga-class-market' ); ?></a>
		</div>
	</div>
</section>
<?php
get_footer();
