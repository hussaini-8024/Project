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

$user      = wp_get_current_user();
$user_id   = (int) $user->ID;
$courses   = class_exists( 'GCM_Enrollment_Service' ) ? GCM_Enrollment_Service::get_student_courses( $user_id ) : array();
$whatsapp  = get_user_meta( $user_id, 'gcm_whatsapp', true );
$address   = get_user_meta( $user_id, 'gcm_address', true );

get_header();
?>
<section class="gcm-dashboard-hero">
	<div class="gcm-container">
		<p class="gcm-eyebrow"><?php esc_html_e( 'Student dashboard', 'giga-class-market' ); ?></p>
		<h1><?php echo esc_html( sprintf( __( 'Welcome back, %s', 'giga-class-market' ), $user->display_name ) ); ?></h1>
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
						<?php foreach ( $courses as $enrollment ) : ?>
							<?php
							$course_id = absint( $enrollment->course_id ?? 0 );
							if ( ! $course_id ) {
								continue;
							}
							$progress   = class_exists( 'GCM_Progress_Service' ) ? GCM_Progress_Service::get_percentage( $user_id, $course_id ) : 0;
							$last       = class_exists( 'GCM_Progress_Service' ) ? GCM_Progress_Service::get_last_lesson( $user_id, $course_id ) : null;
							$learn_url  = add_query_arg( 'course_id', $course_id, home_url( '/course-learn/' ) );
							$status     = sanitize_key( $enrollment->status ?? 'active' );
							?>
							<article class="gcm-dashboard-course">
								<div class="gcm-dashboard-course__media">
									<?php if ( has_post_thumbnail( $course_id ) ) : ?>
										<?php echo get_the_post_thumbnail( $course_id, 'medium' ); ?>
									<?php endif; ?>
								</div>
								<div>
									<h3><?php echo esc_html( get_the_title( $course_id ) ); ?></h3>
									<p><?php echo esc_html( sprintf( __( 'Status: %s', 'giga-class-market' ), ucfirst( $status ) ) ); ?></p>
									<?php if ( $last ) : ?>
										<p><?php echo esc_html( sprintf( __( 'Last watched: %s', 'giga-class-market' ), $last->title ) ); ?></p>
									<?php endif; ?>
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
						<h3><?php esc_html_e( "You haven't enrolled in any courses yet. Explore our courses to start learning.", 'giga-class-market' ); ?></h3>
						<a class="gcm-button gcm-button--gold" href="<?php echo esc_url( get_post_type_archive_link( 'gcm_course' ) ?: home_url( '/courses/' ) ); ?>"><?php esc_html_e( 'Explore Courses', 'giga-class-market' ); ?></a>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<aside class="gcm-dashboard__sidebar">
			<div class="gcm-dashboard-card">
				<h2><?php esc_html_e( 'My Profile', 'giga-class-market' ); ?></h2>
				<form class="gcm-contact-form" data-gcm-ajax-form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
					<input type="hidden" name="action" value="gcm_update_profile">
					<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'gcm_ajax_nonce' ) ); ?>">
					<label>
						<span><?php esc_html_e( 'Name', 'giga-class-market' ); ?></span>
						<input type="text" name="full_name" value="<?php echo esc_attr( $user->display_name ); ?>" required>
					</label>
					<label>
						<span><?php esc_html_e( 'Email', 'giga-class-market' ); ?></span>
						<input type="email" name="email" value="<?php echo esc_attr( $user->user_email ); ?>" required>
					</label>
					<label>
						<span><?php esc_html_e( 'WhatsApp', 'giga-class-market' ); ?></span>
						<input type="text" name="whatsapp" value="<?php echo esc_attr( $whatsapp ); ?>">
					</label>
					<label>
						<span><?php esc_html_e( 'Address', 'giga-class-market' ); ?></span>
						<textarea name="address" rows="3"><?php echo esc_textarea( $address ); ?></textarea>
					</label>
					<button class="gcm-button gcm-button--small" type="submit"><?php esc_html_e( 'Save Profile', 'giga-class-market' ); ?></button>
					<p class="gcm-form-status" role="status" aria-live="polite"></p>
				</form>
			</div>

			<div class="gcm-dashboard-card">
				<h2><?php esc_html_e( 'Change Password', 'giga-class-market' ); ?></h2>
				<form class="gcm-contact-form" data-gcm-ajax-form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
					<input type="hidden" name="action" value="gcm_change_password">
					<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'gcm_ajax_nonce' ) ); ?>">
					<label>
						<span><?php esc_html_e( 'Current password', 'giga-class-market' ); ?></span>
						<input type="password" name="current_password" required autocomplete="current-password">
					</label>
					<label>
						<span><?php esc_html_e( 'New password', 'giga-class-market' ); ?></span>
						<input type="password" name="new_password" required minlength="8" autocomplete="new-password">
					</label>
					<button class="gcm-button gcm-button--small" type="submit"><?php esc_html_e( 'Update Password', 'giga-class-market' ); ?></button>
					<p class="gcm-form-status" role="status" aria-live="polite"></p>
				</form>
			</div>

			<div class="gcm-dashboard-card gcm-dashboard-card--accent">
				<h2><?php esc_html_e( 'Browse more', 'giga-class-market' ); ?></h2>
				<p><?php esc_html_e( 'Purchase additional courses on the same account — no duplicate accounts needed.', 'giga-class-market' ); ?></p>
				<a class="gcm-button gcm-button--gold" href="<?php echo esc_url( get_post_type_archive_link( 'gcm_course' ) ?: home_url( '/courses/' ) ); ?>"><?php esc_html_e( 'All Courses', 'giga-class-market' ); ?></a>
			</div>
		</aside>
	</div>
</section>
<?php
get_footer();
