<?php
/**
 * Template Name: GCM Course Details (single uses this structure)
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
	$price      = gcm_course_meta( $course_id, 'price', '' );
	$rating     = gcm_course_meta( $course_id, 'rating', '5.0' );
	$instructor = gcm_course_meta( $course_id, 'instructor', __( 'Giga Class Market Faculty', 'giga-class-market' ) );
	$learn      = gcm_course_meta( $course_id, 'what_you_learn', '' );
	$require    = gcm_course_meta( $course_id, 'requirements', '' );
	$curriculum = class_exists( 'GCM_Curriculum_Service' ) ? GCM_Curriculum_Service::get_course_curriculum( $course_id ) : array();
	$learn_items = array_filter( array_map( 'trim', preg_split( "/\r\n|\n|\r/", (string) $learn ) ) );
	$req_items   = array_filter( array_map( 'trim', preg_split( "/\r\n|\n|\r/", (string) $require ) ) );
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
				<aside class="gcm-course-buy-card" aria-label="<?php esc_attr_e( 'Course enrollment', 'giga-class-market' ); ?>">
					<?php if ( has_post_thumbnail() ) : ?>
						<?php the_post_thumbnail( 'large', array( 'alt' => esc_attr( get_the_title() ) ) ); ?>
					<?php endif; ?>
					<div class="gcm-course-buy-card__body">
						<strong><?php echo esc_html( gcm_format_price( $price ) ); ?></strong>
						<a class="gcm-button gcm-button--gold gcm-button--full" href="<?php echo esc_url( gcm_course_purchase_url( $course_id ) ); ?>"><?php esc_html_e( 'Buy Now', 'giga-class-market' ); ?></a>
						<ul>
							<li><?php echo esc_html( sprintf( __( 'Instructor: %s', 'giga-class-market' ), $instructor ) ); ?></li>
							<li><?php echo esc_html( sprintf( __( 'Duration: %s', 'giga-class-market' ), $duration ) ); ?></li>
							<li><?php esc_html_e( 'Access after payment verification', 'giga-class-market' ); ?></li>
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
										<li><?php echo esc_html( $lesson['title'] ?? '' ); ?></li>
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
