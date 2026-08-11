<?php
/**
 * Course card.
 *
 * @package GigaClassMarket
 */

$course_id = get_the_ID();
$duration  = gcm_course_meta( $course_id, 'duration', __( 'Self-paced', 'giga-class-market' ) );
$price     = gcm_course_meta( $course_id, 'price', '' );
$rating    = gcm_course_meta( $course_id, 'rating', '4.9' );

$cta_label = __( 'Buy Course', 'giga-class-market' );
$cta_url   = gcm_course_purchase_url( $course_id );
$cta_note  = '';

if ( is_user_logged_in() && class_exists( 'GCM_Payment_Service' ) ) {
	$state = GCM_Payment_Service::get_course_access_state( get_current_user_id(), $course_id );
	$cta_label = $state['label'];
	$cta_url   = $state['url'];
	if ( 'enrolled' === $state['state'] ) {
		$cta_note = sprintf( __( '%d%% complete', 'giga-class-market' ), (int) $state['progress'] );
		$cta_label = (int) $state['progress'] > 0 ? __( 'Continue Learning', 'giga-class-market' ) : __( 'Start Learning', 'giga-class-market' );
	} elseif ( 'under_review' === $state['state'] ) {
		$cta_note = __( 'Waiting for admin approval', 'giga-class-market' );
	}
}
?>
<article class="gcm-course-card gcm-animate">
	<a class="gcm-course-card__media" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr( get_the_title() ); ?>">
		<?php if ( has_post_thumbnail() ) : ?>
			<?php the_post_thumbnail( 'large', array( 'alt' => esc_attr( get_the_title() ) ) ); ?>
		<?php else : ?>
			<div class="gcm-course-card__placeholder" aria-hidden="true">
				<span><?php esc_html_e( 'GCM', 'giga-class-market' ); ?></span>
			</div>
		<?php endif; ?>
	</a>
	<div class="gcm-course-card__body">
		<div class="gcm-course-card__meta">
			<span><?php echo esc_html( gcm_course_category_label( $course_id ) ); ?></span>
			<span><?php echo esc_html( $duration ); ?></span>
		</div>
		<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
		<p><?php echo esc_html( has_excerpt() ? get_the_excerpt() : wp_trim_words( wp_strip_all_tags( get_the_content() ), 18 ) ); ?></p>
		<div class="gcm-course-card__footer">
			<strong><?php echo esc_html( gcm_format_price( $price ) ); ?></strong>
			<span class="gcm-rating" aria-label="<?php echo esc_attr( sprintf( __( '%s out of 5 rating', 'giga-class-market' ), $rating ) ); ?>">★ <?php echo esc_html( $rating ); ?></span>
		</div>
		<?php if ( $cta_note ) : ?>
			<p class="gcm-course-card__status"><?php echo esc_html( $cta_note ); ?></p>
		<?php endif; ?>
		<a class="gcm-button gcm-button--small" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $cta_label ); ?></a>
	</div>
</article>
