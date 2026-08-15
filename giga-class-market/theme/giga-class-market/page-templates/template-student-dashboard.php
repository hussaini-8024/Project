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
$payments  = class_exists( 'GCM_Payment_Service' ) ? GCM_Payment_Service::get_for_user( $user_id, $user->user_email ) : array();
$pending   = array_values(
	array_filter(
		$payments,
		static function ( $payment ) {
			return isset( $payment->status ) && 'under_review' === $payment->status;
		}
	)
);
$whatsapp = get_user_meta( $user_id, 'gcm_whatsapp', true );
$address  = get_user_meta( $user_id, 'gcm_address', true );

$announcements = array();
$recordings    = array();
$assignments   = array();
$quizzes       = array();
$live_now      = array();

foreach ( $courses as $course ) {
	$cid = absint( $course['id'] ?? 0 );
	if ( ! $cid ) {
		continue;
	}
	$live = class_exists( 'GCM_Class_Service' ) ? GCM_Class_Service::get_live_for_course( $cid ) : null;
	if ( $live ) {
		$live_now[] = array(
			'course_id' => $cid,
			'title'     => $course['title'] ?? get_the_title( $cid ),
			'class'     => $live,
		);
	}
	if ( class_exists( 'GCM_Announcement_Service' ) ) {
		foreach ( GCM_Announcement_Service::get_for_course( $cid ) as $row ) {
			$row->course_title = $course['title'] ?? get_the_title( $cid );
			$announcements[]   = $row;
		}
	}
	if ( class_exists( 'GCM_Recording_Service' ) ) {
		foreach ( GCM_Recording_Service::get_for_course( $cid ) as $row ) {
			$row->course_title = $course['title'] ?? get_the_title( $cid );
			$recordings[]      = $row;
		}
	}
	if ( class_exists( 'GCM_Assignment_Service' ) ) {
		foreach ( GCM_Assignment_Service::get_for_course( $cid ) as $row ) {
			$row->course_title = $course['title'] ?? get_the_title( $cid );
			$assignments[]     = $row;
		}
	}
	if ( class_exists( 'GCM_Quiz_Service' ) ) {
		foreach ( GCM_Quiz_Service::get_for_course( $cid ) as $row ) {
			$row->course_title = $course['title'] ?? get_the_title( $cid );
			$quizzes[]         = $row;
		}
	}
}

$announcements = array_slice( $announcements, 0, 8 );
$recordings    = array_slice( $recordings, 0, 8 );
$assignments   = array_slice( $assignments, 0, 8 );
$quizzes       = array_slice( $quizzes, 0, 8 );

get_header();
?>
<section class="gcm-dashboard-hero">
	<div class="gcm-container">
		<p class="gcm-eyebrow"><?php esc_html_e( 'Student dashboard', 'giga-class-market' ); ?></p>
		<h1><?php echo esc_html( sprintf( __( 'Welcome back, %s', 'giga-class-market' ), $user->display_name ) ); ?></h1>
		<p><?php esc_html_e( 'Join live classes, open study materials, and use your course chat room.', 'giga-class-market' ); ?></p>
	</div>
</section>

<section class="gcm-dashboard">
	<div class="gcm-container gcm-dashboard__grid">
		<div class="gcm-dashboard__main">
			<?php if ( ! empty( $live_now ) ) : ?>
				<div class="gcm-dashboard-card gcm-dashboard-card--accent">
					<h2><?php esc_html_e( 'Live class now', 'giga-class-market' ); ?></h2>
					<?php foreach ( $live_now as $item ) : ?>
						<p>
							<strong><?php echo esc_html( $item['title'] ); ?></strong>
							— <?php echo esc_html( $item['class']->title ); ?>
						</p>
						<button type="button" class="gcm-button gcm-button--gold gcm-join-live" data-class-id="<?php echo esc_attr( $item['class']->id ); ?>">
							<?php esc_html_e( 'Join Live Class', 'giga-class-market' ); ?>
						</button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="gcm-dashboard-card">
				<div class="gcm-dashboard-card__heading">
					<h2><?php esc_html_e( 'My Courses', 'giga-class-market' ); ?></h2>
					<a class="gcm-button gcm-button--small" href="<?php echo esc_url( get_post_type_archive_link( 'gcm_course' ) ?: home_url( '/courses/' ) ); ?>"><?php esc_html_e( 'All Courses', 'giga-class-market' ); ?></a>
				</div>
				<?php if ( ! empty( $courses ) ) : ?>
					<div class="gcm-dashboard-course-list">
						<?php foreach ( $courses as $course ) : ?>
							<?php
							$course_id = absint( $course['id'] ?? 0 );
							if ( ! $course_id ) {
								continue;
							}
							$enrollment = isset( $course['enrollment'] ) ? $course['enrollment'] : null;
							$learn_url  = add_query_arg( 'course_id', $course_id, home_url( '/course-learn/' ) );
							$status     = $enrollment ? sanitize_key( $enrollment->status ) : 'active';
							$title      = ! empty( $course['title'] ) ? $course['title'] : get_the_title( $course_id );
							$thumb      = ! empty( $course['thumbnail'] ) ? $course['thumbnail'] : get_the_post_thumbnail_url( $course_id, 'medium' );
							$status_label = 'completed' === $status
								? __( 'Completed', 'giga-class-market' )
								: ( 'frozen' === $status ? __( 'Frozen', 'giga-class-market' ) : __( 'Active', 'giga-class-market' ) );
							$live = class_exists( 'GCM_Class_Service' ) ? GCM_Class_Service::get_live_for_course( $course_id ) : null;
							$progress = class_exists( 'GCM_Progress_Service' ) ? (int) GCM_Progress_Service::get_percentage( $user_id, $course_id ) : 0;
							?>
							<article class="gcm-dashboard-course">
								<div class="gcm-dashboard-course__media">
									<?php if ( $thumb ) : ?>
										<img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $title ); ?>">
									<?php elseif ( has_post_thumbnail( $course_id ) ) : ?>
										<?php echo get_the_post_thumbnail( $course_id, 'medium' ); ?>
									<?php endif; ?>
								</div>
								<div>
									<h3><?php echo esc_html( $title ); ?></h3>
									<p>
										<span class="gcm-status gcm-status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $status_label ); ?></span>
										<?php if ( $live ) : ?>
											<span class="gcm-status gcm-status-live"><?php esc_html_e( 'Live now', 'giga-class-market' ); ?></span>
										<?php endif; ?>
									</p>
									<p><strong><?php echo esc_html( sprintf( __( '%d%% complete', 'giga-class-market' ), $progress ) ); ?></strong></p>
									<div class="gcm-progress-bar" aria-hidden="true" style="height:6px;background:#e5e7eb;border-radius:999px;overflow:hidden;max-width:220px;">
										<span style="display:block;height:100%;width:<?php echo esc_attr( min( 100, $progress ) ); ?>%;background:var(--gcm-accent,#e0a045);"></span>
									</div>
								</div>
								<?php if ( 'frozen' === $status ) : ?>
									<span class="gcm-button gcm-button--outline" aria-disabled="true"><?php esc_html_e( 'Access frozen', 'giga-class-market' ); ?></span>
								<?php elseif ( $live ) : ?>
									<button type="button" class="gcm-button gcm-button--gold gcm-join-live" data-class-id="<?php echo esc_attr( $live->id ); ?>">
										<?php esc_html_e( 'Join Live Class', 'giga-class-market' ); ?>
									</button>
								<?php else : ?>
									<a class="gcm-button gcm-button--outline" href="<?php echo esc_url( $learn_url ); ?>">
										<?php esc_html_e( 'Open course', 'giga-class-market' ); ?>
									</a>
								<?php endif; ?>
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

			<?php if ( ! empty( $announcements ) ) : ?>
				<div class="gcm-dashboard-card">
					<h2><?php esc_html_e( 'Announcements', 'giga-class-market' ); ?></h2>
					<ul class="gcm-dashboard-pending-list">
						<?php foreach ( $announcements as $row ) : ?>
							<li>
								<strong><?php echo esc_html( $row->title ); ?></strong>
								<small><?php echo esc_html( $row->course_title ); ?></small>
								<span><?php echo wp_kses_post( wp_trim_words( wp_strip_all_tags( $row->body ), 18 ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $recordings ) ) : ?>
				<div class="gcm-dashboard-card">
					<h2><?php esc_html_e( 'Recordings', 'giga-class-market' ); ?></h2>
					<ul class="gcm-dashboard-pending-list">
						<?php foreach ( $recordings as $row ) : ?>
							<li>
								<strong><?php echo esc_html( $row->title ); ?></strong>
								<small><?php echo esc_html( $row->course_title ); ?></small>
								<a href="<?php echo esc_url( $row->video_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Watch', 'giga-class-market' ); ?></a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $assignments ) ) : ?>
				<div class="gcm-dashboard-card">
					<h2><?php esc_html_e( 'Assignments', 'giga-class-market' ); ?></h2>
					<ul class="gcm-dashboard-pending-list">
						<?php foreach ( $assignments as $row ) : ?>
							<li>
								<strong><?php echo esc_html( $row->title ); ?></strong>
								<small><?php echo esc_html( $row->course_title ); ?></small>
								<?php if ( ! empty( $row->due_at ) ) : ?>
									<span><?php echo esc_html( sprintf( __( 'Due %s', 'giga-class-market' ), mysql2date( get_option( 'date_format' ), $row->due_at ) ) ); ?></span>
								<?php endif; ?>
								<form class="gcm-ajax-form" data-action="gcm_submit_assignment" enctype="multipart/form-data" style="margin-top:.5rem;">
									<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'gcm_ajax_nonce' ) ); ?>" />
									<input type="hidden" name="assignment_id" value="<?php echo esc_attr( $row->id ); ?>" />
									<label><?php esc_html_e( 'Notes', 'giga-class-market' ); ?><textarea name="notes" rows="2"></textarea></label>
									<label><?php esc_html_e( 'File', 'giga-class-market' ); ?><input type="file" name="assignment_file" /></label>
									<button type="submit" class="gcm-button gcm-button--small"><?php esc_html_e( 'Submit', 'giga-class-market' ); ?></button>
									<div class="gcm-form-message" aria-live="polite"></div>
								</form>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $quizzes ) ) : ?>
				<div class="gcm-dashboard-card">
					<h2><?php esc_html_e( 'Quizzes', 'giga-class-market' ); ?></h2>
					<ul class="gcm-dashboard-pending-list">
						<?php foreach ( $quizzes as $quiz ) : ?>
							<?php
							$best = class_exists( 'GCM_Quiz_Service' ) ? GCM_Quiz_Service::get_best_attempt( (int) $quiz->id, $user_id ) : null;
							?>
							<li>
								<strong><?php echo esc_html( $quiz->title ); ?></strong>
								<small><?php echo esc_html( $quiz->course_title ); ?></small>
								<?php if ( $best ) : ?>
									<span><?php echo esc_html( sprintf( __( 'Best score: %d%%', 'giga-class-market' ), (int) $best->score ) ); ?></span>
								<?php endif; ?>
								<?php if ( ! empty( $quiz->questions ) ) : ?>
									<form class="gcm-ajax-form" data-action="gcm_submit_quiz" style="margin-top:.5rem;">
										<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'gcm_ajax_nonce' ) ); ?>" />
										<input type="hidden" name="quiz_id" value="<?php echo esc_attr( $quiz->id ); ?>" />
										<?php foreach ( $quiz->questions as $q ) : ?>
											<fieldset style="margin-bottom:.75rem;">
												<legend><?php echo esc_html( wp_strip_all_tags( $q->question ) ); ?></legend>
												<?php foreach ( (array) $q->options as $idx => $opt ) : ?>
													<label style="display:block;">
														<input type="radio" name="answers[<?php echo esc_attr( $q->id ); ?>]" value="<?php echo esc_attr( $idx ); ?>" required />
														<?php echo esc_html( $opt ); ?>
													</label>
												<?php endforeach; ?>
											</fieldset>
										<?php endforeach; ?>
										<button type="submit" class="gcm-button gcm-button--small"><?php esc_html_e( 'Submit quiz', 'giga-class-market' ); ?></button>
										<div class="gcm-form-message" aria-live="polite"></div>
									</form>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $pending ) ) : ?>
				<div class="gcm-dashboard-card">
					<h2><?php esc_html_e( 'Payments under review', 'giga-class-market' ); ?></h2>
					<ul class="gcm-dashboard-pending-list">
						<?php foreach ( $pending as $payment ) : ?>
							<?php
							$course_title = get_the_title( absint( $payment->course_id ) );
							?>
							<li>
								<strong><?php echo esc_html( $course_title ? $course_title : __( 'Course', 'giga-class-market' ) ); ?></strong>
								<span class="gcm-status gcm-status-under_review"><?php esc_html_e( 'Under review', 'giga-class-market' ); ?></span>
								<span><?php echo esc_html( sprintf( __( 'Submitted %s', 'giga-class-market' ), mysql2date( get_option( 'date_format' ), $payment->submitted_at ) ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
					<p><?php esc_html_e( 'Once an admin approves a payment, that course is added to My Courses automatically.', 'giga-class-market' ); ?></p>
				</div>
			<?php endif; ?>
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
				<h2><?php esc_html_e( 'Buy another course', 'giga-class-market' ); ?></h2>
				<p><?php esc_html_e( 'Purchase additional courses on the same account. After approval they appear here with progress tracking.', 'giga-class-market' ); ?></p>
				<a class="gcm-button gcm-button--gold" href="<?php echo esc_url( get_post_type_archive_link( 'gcm_course' ) ?: home_url( '/courses/' ) ); ?>"><?php esc_html_e( 'All Courses', 'giga-class-market' ); ?></a>
			</div>
		</aside>
	</div>
</section>
<?php
get_footer();
