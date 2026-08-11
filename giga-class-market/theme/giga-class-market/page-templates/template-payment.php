<?php
/**
 * Template Name: GCM Payment
 *
 * @package GigaClassMarket
 */

$course_id = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0;

get_header();
?>
<section class="gcm-payment-page">
	<div class="gcm-container gcm-payment-card gcm-animate">
		<p class="gcm-eyebrow"><?php esc_html_e( 'Payment', 'giga-class-market' ); ?></p>
		<h1><?php esc_html_e( 'Complete your course enrollment', 'giga-class-market' ); ?></h1>
		<?php if ( $course_id ) : ?>
			<p><?php echo esc_html( sprintf( __( 'You are enrolling in %s.', 'giga-class-market' ), get_the_title( $course_id ) ) ); ?></p>
		<?php endif; ?>
		<div class="gcm-payment-card__info">
			<h2><?php esc_html_e( 'Payment instructions', 'giga-class-market' ); ?></h2>
			<p><?php echo esc_html( gcm_setting( 'payment_instructions', __( 'Review your enrollment details, proceed to payment, and submit verification if manual confirmation is required.', 'giga-class-market' ) ) ); ?></p>
		</div>
		<div class="gcm-hero__actions">
			<a class="gcm-button gcm-button--gold" href="<?php echo esc_url( add_query_arg( 'course_id', $course_id, gcm_setting( 'payment_verify_url', home_url( '/payment-verify/' ) ) ) ); ?>"><?php esc_html_e( 'Proceed', 'giga-class-market' ); ?></a>
			<a class="gcm-button gcm-button--outline" href="<?php echo esc_url( $course_id ? get_permalink( $course_id ) : home_url( '/' ) ); ?>"><?php esc_html_e( 'Not Now', 'giga-class-market' ); ?></a>
		</div>
	</div>
</section>
<?php
get_footer();
