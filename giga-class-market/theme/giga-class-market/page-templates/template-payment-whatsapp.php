<?php
/**
 * Template Name: GCM Payment WhatsApp Gate
 *
 * Buy Course landing — get account details via WhatsApp, then proceed to verification.
 * Bank details page is kept in the theme but not shown in this flow.
 *
 * @package GigaClassMarket
 */

$course_id = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0;
$course    = ( $course_id && class_exists( 'GCM_Course_Service' ) ) ? GCM_Course_Service::get( $course_id ) : null;

$regular_price   = $course ? (float) $course['price'] : 0;
$discount_price  = $course ? (float) ( $course['discount_price'] ?? 0 ) : 0;
$sale_label      = $course ? (string) ( $course['sale_label'] ?? '' ) : '';
$effective_price = $course ? (float) ( $course['effective_price'] ?? $regular_price ) : 0;
$on_sale         = $discount_price > 0 && $discount_price < $regular_price;

$wa_display = '03165639987';
$wa_e164    = '923165639987';
$wa_text    = rawurlencode(
	$course
		? sprintf( 'Hi, I want account details to buy: %s', $course['title'] )
		: 'Hi, I want account details to buy a course.'
);
$wa_url = 'https://wa.me/' . $wa_e164 . '?text=' . $wa_text;

$verify_url = add_query_arg(
	array_filter(
		array(
			'course_id' => $course_id ? $course_id : null,
		)
	),
	home_url( '/payment-verification/' )
);

get_header();
?>
<section class="gcm-payment-page gcm-payment-whatsapp-gate">
	<div class="gcm-container">
		<div class="gcm-payment-gate-banner" role="status">
			<?php esc_html_e( 'After sending payment, open this page and save your transaction ID.', 'giga-class-market' ); ?>
		</div>

		<div class="gcm-payment-card gcm-animate">
			<p class="gcm-eyebrow"><?php esc_html_e( 'Purchase', 'giga-class-market' ); ?></p>
			<h1><?php esc_html_e( 'Get account details', 'giga-class-market' ); ?></h1>

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
				</div>
			<?php else : ?>
				<div class="gcm-empty-state">
					<p><?php esc_html_e( 'Message us on WhatsApp to get payment account details for your course.', 'giga-class-market' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="gcm-payment-gate-whatsapp">
				<a class="gcm-button gcm-button--gold gcm-payment-gate-whatsapp__btn" href="<?php echo esc_url( $wa_url ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'WhatsApp', 'giga-class-market' ); ?>
				</a>
				<p class="gcm-payment-gate-whatsapp__hint">
					<?php esc_html_e( 'Msg here to get account details', 'giga-class-market' ); ?>
					<br />
					<a href="<?php echo esc_url( $wa_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $wa_display ); ?></a>
				</p>
			</div>

			<div class="gcm-hero__actions gcm-payment-gate-actions">
				<a class="gcm-button gcm-button--gold" href="<?php echo esc_url( $verify_url ); ?>">
					<?php esc_html_e( 'I have paid my fees and I want to proceed to information addition', 'giga-class-market' ); ?>
				</a>
				<a class="gcm-button gcm-button--outline" href="<?php echo esc_url( $course_id ? get_permalink( $course_id ) : home_url( '/courses/' ) ); ?>">
					<?php esc_html_e( 'Not Now', 'giga-class-market' ); ?>
				</a>
			</div>
		</div>
	</div>
</section>
<?php
get_footer();
