<?php
/**
 * Single course template.
 *
 * @package GigaClassMarket
 */

get_header();
?>
<?php while ( have_posts() ) : ?>
	<?php the_post(); ?>
	<?php
	$course_id  = get_the_ID();
	$duration   = gcm_course_meta( $course_id, 'duration', __( 'Self-paced', 'giga-class-market' ) );
	$price      = (float) gcm_course_meta( $course_id, 'price', 0 );
	$sale_price = (float) get_post_meta( $course_id, '_gcm_discount_price', true );
	$sale_label = (string) get_post_meta( $course_id, '_gcm_sale_label', true );
	$on_sale    = $sale_price > 0 && $sale_price < $price;
	$display_price = $on_sale ? $sale_price : $price;
	$rating     = gcm_course_meta( $course_id, 'rating', '5.0' );
	$instructor = gcm_course_meta( $course_id, 'instructor', __( 'Giga Class Market Faculty', 'giga-class-market' ) );
	$learn      = gcm_course_meta( $course_id, 'what_you_learn', '' );
	$require    = gcm_course_meta( $course_id, 'requirements', '' );
	$faq_raw    = (string) get_post_meta( $course_id, '_gcm_faq', true );
	$bundle_raw = (string) get_post_meta( $course_id, '_gcm_bundle_ids', true );
	$community  = (string) get_post_meta( $course_id, '_gcm_community_whatsapp', true );
	if ( ! $community && class_exists( 'GCM_Settings_Service' ) ) {
		$settings  = GCM_Settings_Service::get_settings();
		$community = (string) ( $settings['website']['community_whatsapp'] ?? '' );
	}
	$curriculum = class_exists( 'GCM_Curriculum_Service' ) ? GCM_Curriculum_Service::get_course_curriculum( $course_id ) : array();
	$learn_items = array_filter( array_map( 'trim', preg_split( "/\r\n|\n|\r/", (string) $learn ) ) );
	$req_items   = array_filter( array_map( 'trim', preg_split( "/\r\n|\n|\r/", (string) $require ) ) );

	$faq_items = array();
	foreach ( preg_split( "/\r\n|\n|\r/", $faq_raw ) as $line ) {
		$line = trim( $line );
		if ( '' === $line || false === strpos( $line, '|' ) ) {
			continue;
		}
		$parts = array_map( 'trim', explode( '|', $line, 2 ) );
		if ( count( $parts ) === 2 && '' !== $parts[0] && '' !== $parts[1] ) {
			$faq_items[] = array( 'q' => $parts[0], 'a' => $parts[1] );
		}
	}

	$bundle_ids = array_filter( array_map( 'absint', preg_split( '/[\s,]+/', $bundle_raw ) ) );
	$reviews    = class_exists( 'GCM_Review_Service' ) ? GCM_Review_Service::get_for_course( $course_id, 'approved' ) : array();
	$is_enrolled = is_user_logged_in() && class_exists( 'GCM_Enrollment_Service' ) && GCM_Enrollment_Service::has_access( get_current_user_id(), $course_id );

	$community_url = '';
	if ( $community ) {
		if ( 0 === strpos( $community, 'http' ) ) {
			$community_url = $community;
		} else {
			$community_url = 'https://wa.me/' . preg_replace( '/[^0-9]/', '', $community );
		}
	}

	$cta_label = __( 'Buy Now', 'giga-class-market' );
	$cta_url   = gcm_course_purchase_url( $course_id );
	$cta_note  = __( 'Access after payment verification', 'giga-class-market' );
	if ( is_user_logged_in() && class_exists( 'GCM_Payment_Service' ) ) {
		$state     = GCM_Payment_Service::get_course_access_state( get_current_user_id(), $course_id );
		$cta_url   = $state['url'];
		$cta_label = $state['label'];
		if ( 'enrolled' === $state['state'] ) {
			$cta_label = (int) $state['progress'] > 0 ? __( 'Continue Learning', 'giga-class-market' ) : __( 'Start Learning', 'giga-class-market' );
			$cta_note  = sprintf( __( 'You are enrolled · %d%% complete', 'giga-class-market' ), (int) $state['progress'] );
		} elseif ( 'under_review' === $state['state'] ) {
			$cta_note = __( 'Your payment for this course is under review.', 'giga-class-market' );
		} elseif ( 'frozen' === $state['state'] ) {
			$cta_note = __( 'Access is currently frozen. Contact support.', 'giga-class-market' );
		}
	}
	?>
	<article <?php post_class( 'gcm-course-single' ); ?>>
		<section class="gcm-course-hero">
			<div class="gcm-container gcm-course-hero__grid">
				<div class="gcm-course-hero__copy">
					<p class="gcm-eyebrow"><?php echo esc_html( gcm_course_category_label( $course_id ) ); ?></p>
					<h1><?php the_title(); ?></h1>
					<p><?php echo esc_html( has_excerpt() ? get_the_excerpt() : wp_trim_words( wp_strip_all_tags( get_the_content() ), 36 ) ); ?></p>
					<div class="gcm-course-hero__facts">
						<span><?php echo esc_html( $duration ); ?></span>
						<span><?php echo esc_html( sprintf( __( 'Instructor: %s', 'giga-class-market' ), $instructor ) ); ?></span>
						<span>★ <?php echo esc_html( $rating ); ?></span>
					</div>
				</div>
				<aside class="gcm-course-buy-card<?php echo $on_sale ? ' gcm-course-buy-card--sale' : ''; ?>" aria-label="<?php esc_attr_e( 'Course enrollment', 'giga-class-market' ); ?>">
					<div class="gcm-course-buy-card__media">
						<?php if ( has_post_thumbnail() ) : ?>
							<?php the_post_thumbnail( 'full', array( 'alt' => esc_attr( get_the_title() ), 'class' => 'gcm-course-buy-card__image' ) ); ?>
						<?php else : ?>
							<div class="gcm-course-card__placeholder" aria-hidden="true">
								<span><?php esc_html_e( 'GCM', 'giga-class-market' ); ?></span>
							</div>
						<?php endif; ?>
					</div>
					<div class="gcm-course-buy-card__body">
						<?php if ( $on_sale ) : ?>
							<span class="gcm-sale-label"><?php echo esc_html( $sale_label ? $sale_label : __( 'On sale', 'giga-class-market' ) ); ?></span>
							<div class="gcm-price-block gcm-price-block--buy">
								<span class="gcm-price-block__sale"><?php echo esc_html( gcm_format_price( $display_price ) ); ?></span>
								<s class="gcm-price-block__original"><?php echo esc_html( gcm_format_price( $price ) ); ?></s>
							</div>
						<?php else : ?>
							<strong><?php echo esc_html( gcm_format_price( $display_price ) ); ?></strong>
						<?php endif; ?>
						<a class="gcm-button gcm-button--gold gcm-button--full" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $cta_label ); ?></a>
						<?php if ( $community_url ) : ?>
							<a class="gcm-button gcm-button--outline gcm-button--full" href="<?php echo esc_url( $community_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Join community WhatsApp', 'giga-class-market' ); ?></a>
						<?php endif; ?>
						<ul>
							<li><?php echo esc_html( sprintf( __( 'Instructor: %s', 'giga-class-market' ), $instructor ) ); ?></li>
							<li><?php echo esc_html( sprintf( __( 'Duration: %s', 'giga-class-market' ), $duration ) ); ?></li>
							<li><?php echo esc_html( $cta_note ); ?></li>
						</ul>
					</div>
				</aside>
			</div>
		</section>

		<section class="gcm-section">
			<div class="gcm-container gcm-course-detail-grid">
				<div class="gcm-content">
					<h2><?php esc_html_e( 'Course description', 'giga-class-market' ); ?></h2>
					<?php the_content(); ?>

					<?php if ( $learn_items ) : ?>
						<h2><?php esc_html_e( "What you'll learn", 'giga-class-market' ); ?></h2>
						<ul class="gcm-check-list">
							<?php foreach ( $learn_items as $item ) : ?>
								<li><?php echo esc_html( $item ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<?php if ( $req_items ) : ?>
						<h2><?php esc_html_e( 'Requirements', 'giga-class-market' ); ?></h2>
						<ul>
							<?php foreach ( $req_items as $item ) : ?>
								<li><?php echo esc_html( $item ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<?php if ( $faq_items ) : ?>
						<h2><?php esc_html_e( 'FAQ', 'giga-class-market' ); ?></h2>
						<div class="gcm-faq-list">
							<?php foreach ( $faq_items as $faq ) : ?>
								<details class="gcm-faq-item">
									<summary><?php echo esc_html( $faq['q'] ); ?></summary>
									<p><?php echo esc_html( $faq['a'] ); ?></p>
								</details>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<?php if ( $bundle_ids ) : ?>
						<h2><?php esc_html_e( 'Included / bundle courses', 'giga-class-market' ); ?></h2>
						<ul>
							<?php foreach ( $bundle_ids as $bundle_id ) : ?>
								<?php if ( get_post( $bundle_id ) ) : ?>
									<li><a href="<?php echo esc_url( get_permalink( $bundle_id ) ); ?>"><?php echo esc_html( get_the_title( $bundle_id ) ); ?></a></li>
								<?php endif; ?>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<h2><?php esc_html_e( 'Student reviews', 'giga-class-market' ); ?></h2>
					<?php if ( empty( $reviews ) ) : ?>
						<p><?php esc_html_e( 'No approved reviews yet.', 'giga-class-market' ); ?></p>
					<?php else : ?>
						<ul class="gcm-review-list">
							<?php foreach ( $reviews as $review ) : ?>
								<li>
									<strong><?php echo esc_html( $review->author_name ); ?></strong>
									<span>★ <?php echo esc_html( (int) $review->rating ); ?></span>
									<?php if ( ! empty( $review->review_title ) ) : ?>
										<em><?php echo esc_html( $review->review_title ); ?></em>
									<?php endif; ?>
									<p><?php echo esc_html( $review->review_body ); ?></p>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<?php if ( $is_enrolled ) : ?>
						<form class="gcm-contact-form" data-gcm-ajax-form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
							<input type="hidden" name="action" value="gcm_submit_review">
							<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'gcm_ajax_nonce' ) ); ?>">
							<input type="hidden" name="course_id" value="<?php echo esc_attr( $course_id ); ?>">
							<label>
								<span><?php esc_html_e( 'Your rating', 'giga-class-market' ); ?></span>
								<select name="rating" required>
									<?php for ( $i = 5; $i >= 1; $i-- ) : ?>
										<option value="<?php echo esc_attr( $i ); ?>"><?php echo esc_html( $i ); ?></option>
									<?php endfor; ?>
								</select>
							</label>
							<label>
								<span><?php esc_html_e( 'Title', 'giga-class-market' ); ?></span>
								<input type="text" name="review_title">
							</label>
							<label>
								<span><?php esc_html_e( 'Review', 'giga-class-market' ); ?></span>
								<textarea name="review_body" rows="3" required></textarea>
							</label>
							<button class="gcm-button gcm-button--gold" type="submit"><?php esc_html_e( 'Submit review', 'giga-class-market' ); ?></button>
							<p class="gcm-form-status" role="status" aria-live="polite"></p>
						</form>
					<?php endif; ?>
				</div>
				<aside class="gcm-curriculum-card">
					<h2><?php esc_html_e( 'Course curriculum', 'giga-class-market' ); ?></h2>
					<?php if ( ! empty( $curriculum ) ) : ?>
						<?php foreach ( $curriculum as $module ) : ?>
							<div class="gcm-module-block">
								<strong><?php echo esc_html( $module['title'] ?? '' ); ?></strong>
								<ol>
									<?php foreach ( (array) ( $module['lessons'] ?? array() ) as $lesson ) : ?>
										<?php $lesson = is_array( $lesson ) ? $lesson : (array) $lesson; ?>
										<li>
											<?php echo esc_html( $lesson['title'] ?? '' ); ?>
											<?php if ( ! empty( $lesson['is_preview'] ) ) : ?>
												<span class="gcm-preview-badge"><?php esc_html_e( 'Preview', 'giga-class-market' ); ?></span>
											<?php endif; ?>
										</li>
									<?php endforeach; ?>
								</ol>
							</div>
						<?php endforeach; ?>
					<?php else : ?>
						<p><?php esc_html_e( 'Curriculum details will be published soon.', 'giga-class-market' ); ?></p>
					<?php endif; ?>
					<a class="gcm-button gcm-button--gold" href="<?php echo esc_url( gcm_course_purchase_url( $course_id ) ); ?>"><?php esc_html_e( 'Buy Now', 'giga-class-market' ); ?></a>
				</aside>
			</div>
		</section>
	</article>
<?php endwhile; ?>
<?php
get_footer();
