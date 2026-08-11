<?php
/**
 * Template Name: GCM Course Learn
 *
 * @package GigaClassMarket
 */

if ( ! is_user_logged_in() ) {
	wp_safe_redirect( gcm_student_login_url() );
	exit;
}

$course_id = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0;
$lesson    = isset( $_GET['lesson'] ) ? max( 0, absint( $_GET['lesson'] ) ) : 0;
$has_access = gcm_user_can_access_course( $course_id );

$curriculum = $course_id ? gcm_course_meta( $course_id, 'curriculum', array() ) : array();
if ( is_string( $curriculum ) ) {
	$curriculum = array_values( array_filter( array_map( 'trim', explode( "\n", $curriculum ) ) ) );
}
if ( empty( $curriculum ) ) {
	$curriculum = array(
		__( 'Welcome and course orientation', 'giga-class-market' ),
		__( 'Core concepts and premium workflow', 'giga-class-market' ),
		__( 'Hands-on implementation project', 'giga-class-market' ),
		__( 'Final review and certification prep', 'giga-class-market' ),
	);
}
$lesson       = min( $lesson, max( 0, count( $curriculum ) - 1 ) );
$progress     = (int) gcm_service_call( 'GCM_Progress_Service', 'get_course_progress', array( get_current_user_id(), $course_id ), 0 );
$video_url    = $course_id ? gcm_course_meta( $course_id, 'video_url', '' ) : '';
$current_item = $curriculum[ $lesson ] ?? __( 'Lesson', 'giga-class-market' );
$current_title = is_array( $current_item ) ? ( $current_item['title'] ?? __( 'Lesson', 'giga-class-market' ) ) : $current_item;
$current_video = is_array( $current_item ) && ! empty( $current_item['video_url'] ) ? $current_item['video_url'] : $video_url;

get_header();
?>
<section class="gcm-learn">
	<div class="gcm-learn__layout">
		<aside class="gcm-learn__sidebar" aria-label="<?php esc_attr_e( 'Course curriculum', 'giga-class-market' ); ?>">
			<a class="gcm-learn__back" href="<?php echo esc_url( gcm_setting( 'student_dashboard_url', home_url( '/student-dashboard/' ) ) ); ?>">&larr; <?php esc_html_e( 'Dashboard', 'giga-class-market' ); ?></a>
			<h1><?php echo esc_html( $course_id ? get_the_title( $course_id ) : __( 'Course Player', 'giga-class-market' ) ); ?></h1>
			<div class="gcm-progress gcm-progress--learn" aria-label="<?php echo esc_attr( sprintf( __( '%d percent complete', 'giga-class-market' ), $progress ) ); ?>">
				<span style="width: <?php echo esc_attr( min( 100, max( 0, $progress ) ) ); ?>%"></span>
			</div>
			<ol class="gcm-curriculum-list">
				<?php foreach ( $curriculum as $index => $item ) : ?>
					<?php $title = is_array( $item ) ? ( $item['title'] ?? '' ) : $item; ?>
					<li class="<?php echo $index === $lesson ? 'is-active' : ''; ?>">
						<a href="<?php echo esc_url( add_query_arg( array( 'course_id' => $course_id, 'lesson' => $index ), gcm_setting( 'course_learn_url', home_url( '/course-learn/' ) ) ) ); ?>"><?php echo esc_html( $title ); ?></a>
					</li>
				<?php endforeach; ?>
			</ol>
		</aside>

		<div class="gcm-learn__player">
			<?php if ( ! $course_id || ! $has_access ) : ?>
				<div class="gcm-empty-state">
					<h2><?php esc_html_e( 'Course access required', 'giga-class-market' ); ?></h2>
					<p><?php esc_html_e( 'Enroll in this course or sign in with an enrolled account to continue learning.', 'giga-class-market' ); ?></p>
					<a class="gcm-button gcm-button--gold" href="<?php echo esc_url( $course_id ? gcm_course_purchase_url( $course_id ) : get_post_type_archive_link( 'gcm_course' ) ); ?>"><?php esc_html_e( 'Get Access', 'giga-class-market' ); ?></a>
				</div>
			<?php else : ?>
				<div class="gcm-video-frame">
					<?php if ( $current_video ) : ?>
						<?php echo wp_oembed_get( esc_url( $current_video ) ) ?: '<video controls src="' . esc_url( $current_video ) . '"></video>'; ?>
					<?php else : ?>
						<div class="gcm-video-placeholder">
							<span><?php esc_html_e( 'Premium Lesson Video', 'giga-class-market' ); ?></span>
						</div>
					<?php endif; ?>
				</div>
				<div class="gcm-learn__content">
					<p class="gcm-eyebrow"><?php echo esc_html( sprintf( __( 'Lesson %d of %d', 'giga-class-market' ), $lesson + 1, count( $curriculum ) ) ); ?></p>
					<h2><?php echo esc_html( $current_title ); ?></h2>
					<p><?php esc_html_e( 'Watch the lesson, apply the exercise, then mark it complete to update your progress.', 'giga-class-market' ); ?></p>
					<form class="gcm-inline-form" data-gcm-progress-form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
						<input type="hidden" name="action" value="gcm_mark_complete">
						<input type="hidden" name="course_id" value="<?php echo esc_attr( $course_id ); ?>">
						<input type="hidden" name="lesson" value="<?php echo esc_attr( $lesson ); ?>">
						<?php wp_nonce_field( 'gcm_progress_nonce', 'nonce' ); ?>
						<button class="gcm-button gcm-button--gold" type="submit"><?php esc_html_e( 'Mark Complete', 'giga-class-market' ); ?></button>
						<p class="gcm-form-status" role="status" aria-live="polite"></p>
					</form>
					<nav class="gcm-lesson-nav" aria-label="<?php esc_attr_e( 'Lesson navigation', 'giga-class-market' ); ?>">
						<?php if ( $lesson > 0 ) : ?>
							<a class="gcm-button gcm-button--outline" href="<?php echo esc_url( add_query_arg( array( 'course_id' => $course_id, 'lesson' => $lesson - 1 ), gcm_setting( 'course_learn_url', home_url( '/course-learn/' ) ) ) ); ?>"><?php esc_html_e( 'Previous', 'giga-class-market' ); ?></a>
						<?php endif; ?>
						<?php if ( $lesson < count( $curriculum ) - 1 ) : ?>
							<a class="gcm-button" href="<?php echo esc_url( add_query_arg( array( 'course_id' => $course_id, 'lesson' => $lesson + 1 ), gcm_setting( 'course_learn_url', home_url( '/course-learn/' ) ) ) ); ?>"><?php esc_html_e( 'Next', 'giga-class-market' ); ?></a>
						<?php endif; ?>
					</nav>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
<?php
get_footer();
