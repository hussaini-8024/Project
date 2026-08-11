<?php
/**
 * Template Name: GCM Student Dashboard
 *
 * @package GigaClassMarket
 */

if ( ! is_user_logged_in() ) {
	wp_safe_redirect( gcm_student_login_url() );
	exit;
}

$user_id = get_current_user_id();
$courses = gcm_service_call( 'GCM_Enrollment_Service', 'get_student_courses', array( $user_id ), array() );

get_header();
?>
<section class="gcm-dashboard-hero">
	<div class="gcm-container">
		<p class="gcm-eyebrow"><?php esc_html_e( 'Student dashboard', 'giga-class-market' ); ?></p>
		<h1><?php echo esc_html( sprintf( __( 'Welcome back, %s', 'giga-class-market' ), wp_get_current_user()->display_name ) ); ?></h1>
		<p><?php esc_html_e( 'Continue learning, track your progress, and manage your Giga Class Market profile.', 'giga-class-market' ); ?></p>
	</div>
</section>

<section class="gcm-dashboard">
	<div class="gcm-container gcm-dashboard__grid">
		<div class="gcm-dashboard__main">
			<div class="gcm-dashboard-card">
				<div class="gcm-dashboard-card__heading">
					<h2><?php esc_html_e( 'My Courses', 'giga-class-market' ); ?></h2>
					<a class="gcm-button gcm-button--small" href="<?php echo esc_url( get_post_type_archive_link( 'gcm_course' ) ?: home_url( '/courses/' ) ); ?>"><?php esc_html_e( 'All Courses', 'giga-class-market' ); ?></a>
				</div>
				<?php if ( ! empty( $courses ) ) : ?>
					<div class="gcm-dashboard-course-list">
						<?php foreach ( $courses as $course ) : ?>
							<?php
							$course_id = is_object( $course ) ? absint( $course->ID ?? $course->course_id ?? 0 ) : absint( $course['ID'] ?? $course['course_id'] ?? 0 );
							if ( ! $course_id ) {
								continue;
							}
							$progress = (int) gcm_service_call( 'GCM_Progress_Service', 'get_course_progress', array( $user_id, $course_id ), gcm_course_meta( $course_id, 'progress', 0 ) );
							$learn_url = add_query_arg( 'course_id', $course_id, gcm_setting( 'course_learn_url', home_url( '/course-learn/' ) ) );
							?>
							<article class="gcm-dashboard-course">
								<div>
									<h3><?php echo esc_html( get_the_title( $course_id ) ); ?></h3>
									<div class="gcm-progress" aria-label="<?php echo esc_attr( sprintf( __( '%d percent complete', 'giga-class-market' ), $progress ) ); ?>">
										<span style="width: <?php echo esc_attr( min( 100, max( 0, $progress ) ) ); ?>%"></span>
									</div>
									<p><?php echo esc_html( sprintf( __( '%d%% complete', 'giga-class-market' ), $progress ) ); ?></p>
								</div>
								<a class="gcm-button gcm-button--outline" href="<?php echo esc_url( $learn_url ); ?>"><?php esc_html_e( 'Continue Learning', 'giga-class-market' ); ?></a>
							</article>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<div class="gcm-empty-state">
						<h3><?php esc_html_e( 'No enrolled courses yet', 'giga-class-market' ); ?></h3>
						<p><?php esc_html_e( 'Enroll in a premium course to begin tracking your progress here.', 'giga-class-market' ); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<aside class="gcm-dashboard__sidebar">
			<div class="gcm-dashboard-card">
				<h2><?php esc_html_e( 'My Profile', 'giga-class-market' ); ?></h2>
				<p><strong><?php echo esc_html( wp_get_current_user()->display_name ); ?></strong></p>
				<p><?php echo esc_html( wp_get_current_user()->user_email ); ?></p>
				<a class="gcm-button gcm-button--small" href="<?php echo esc_url( admin_url( 'profile.php' ) ); ?>"><?php esc_html_e( 'Edit Profile', 'giga-class-market' ); ?></a>
			</div>
			<div class="gcm-dashboard-card gcm-dashboard-card--accent">
				<h2><?php esc_html_e( 'Next opportunity', 'giga-class-market' ); ?></h2>
				<p><?php esc_html_e( 'Explore the marketplace for your next skill upgrade.', 'giga-class-market' ); ?></p>
				<a class="gcm-button gcm-button--gold" href="<?php echo esc_url( get_post_type_archive_link( 'gcm_course' ) ?: home_url( '/courses/' ) ); ?>"><?php esc_html_e( 'All Courses', 'giga-class-market' ); ?></a>
			</div>
		</aside>
	</div>
</section>
<?php
get_footer();
