<?php
/**
 * Testimonial card.
 *
 * @package GigaClassMarket
 */

$rating = get_post_meta( get_the_ID(), 'gcm_rating', true );
$role   = get_post_meta( get_the_ID(), 'gcm_student_role', true );
?>
<article class="gcm-testimonial-card gcm-animate">
	<div class="gcm-testimonial-card__stars" aria-label="<?php echo esc_attr( sprintf( __( '%s star review', 'giga-class-market' ), $rating ? $rating : 5 ) ); ?>">
		<?php echo esc_html( str_repeat( '★', (int) ( $rating ? $rating : 5 ) ) ); ?>
	</div>
	<blockquote><?php echo esc_html( has_excerpt() ? get_the_excerpt() : wp_trim_words( wp_strip_all_tags( get_the_content() ), 32 ) ); ?></blockquote>
	<div class="gcm-testimonial-card__person">
		<?php if ( has_post_thumbnail() ) : ?>
			<?php the_post_thumbnail( 'thumbnail', array( 'alt' => esc_attr( get_the_title() ) ) ); ?>
		<?php endif; ?>
		<div>
			<strong><?php the_title(); ?></strong>
			<?php if ( $role ) : ?>
				<span><?php echo esc_html( $role ); ?></span>
			<?php endif; ?>
		</div>
	</div>
</article>
