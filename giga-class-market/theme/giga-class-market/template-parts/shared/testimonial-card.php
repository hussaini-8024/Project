<?php
/**
 * Testimonial card.
 *
 * @package GigaClassMarket
 */

$rating = get_post_meta( get_the_ID(), '_gcm_rating', true );
if ( '' === $rating ) {
	$rating = get_post_meta( get_the_ID(), 'gcm_rating', true );
}
$role = get_post_meta( get_the_ID(), '_gcm_role', true );
if ( '' === $role ) {
	$role = get_post_meta( get_the_ID(), 'gcm_student_role', true );
}
if ( '' === $role ) {
	$role = __( 'GCM Learner', 'giga-class-market' );
}
$initials = '';
foreach ( preg_split( '/\s+/', get_the_title() ) as $part ) {
	if ( $part ) {
		$initials .= mb_substr( $part, 0, 1 );
	}
}
?>
<article class="gcm-testimonial-card gcm-animate">
	<div class="gcm-testimonial-card__stars" aria-label="<?php echo esc_attr( sprintf( __( '%s star review', 'giga-class-market' ), $rating ? $rating : 5 ) ); ?>">
		<?php echo esc_html( str_repeat( '★', max( 1, min( 5, (int) ( $rating ? $rating : 5 ) ) ) ) ); ?>
	</div>
	<blockquote><?php echo esc_html( has_excerpt() ? get_the_excerpt() : wp_trim_words( wp_strip_all_tags( get_the_content() ), 32 ) ); ?></blockquote>
	<div class="gcm-testimonial-card__person">
		<?php if ( has_post_thumbnail() ) : ?>
			<?php the_post_thumbnail( 'thumbnail', array( 'alt' => esc_attr( get_the_title() ) ) ); ?>
		<?php else : ?>
			<span class="gcm-avatar-placeholder" aria-hidden="true"><?php echo esc_html( strtoupper( $initials ) ); ?></span>
		<?php endif; ?>
		<div>
			<strong><?php the_title(); ?></strong>
			<span><?php echo esc_html( $role ); ?></span>
		</div>
	</div>
</article>
