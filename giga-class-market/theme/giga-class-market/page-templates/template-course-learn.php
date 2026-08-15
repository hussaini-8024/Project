<?php
/**
 * Template Name: GCM Course Learn
 *
 * Live class course room: join class, notes, shared chat (no progress bar).
 *
 * @package GigaClassMarket
 */

if ( ! is_user_logged_in() ) {
	wp_safe_redirect( gcm_student_login_url() );
	exit;
}

$course_id  = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0;
$user_id    = get_current_user_id();
$has_access = gcm_user_can_access_course( $course_id, $user_id );

$live_class     = ( $course_id && $has_access && class_exists( 'GCM_Class_Service' ) ) ? GCM_Class_Service::get_live_for_course( $course_id ) : null;
if ( $live_class && class_exists( 'GCM_Class_Service' ) ) {
	$fixed_live = GCM_Class_Service::ensure_meeting_links( (int) $live_class->id );
	if ( ! is_wp_error( $fixed_live ) ) {
		$live_class = $fixed_live;
	}
}
$course_classes = ( $course_id && $has_access && class_exists( 'GCM_Class_Service' ) ) ? GCM_Class_Service::get_for_course( $course_id ) : array();
$course_notes   = ( $course_id && $has_access && class_exists( 'GCM_Notes_Service' ) ) ? GCM_Notes_Service::get_for_course( $course_id ) : array();
$course_messages = ( $course_id && $has_access && class_exists( 'GCM_Message_Service' ) ) ? GCM_Message_Service::get_thread( $course_id, $user_id ) : array();
$teacher        = ( $course_id && class_exists( 'GCM_Teacher_Service' ) ) ? GCM_Teacher_Service::get_teacher_for_course( $course_id ) : null;
$my_joined_at   = ( $live_class && class_exists( 'GCM_Attendance_Service' ) )
	? GCM_Attendance_Service::get_joined_at( (int) $live_class->id, $user_id )
	: null;

get_header();
?>
<section class="gcm-learn gcm-learn--live">
	<div class="gcm-container">
		<p class="gcm-learn__back-wrap">
			<a class="gcm-learn__back" href="<?php echo esc_url( home_url( '/student-dashboard/' ) ); ?>">&larr; <?php esc_html_e( 'Dashboard', 'giga-class-market' ); ?></a>
		</p>
		<header class="gcm-learn__header">
			<p class="gcm-eyebrow"><?php esc_html_e( 'Live course room', 'giga-class-market' ); ?></p>
			<h1><?php echo esc_html( $course_id ? get_the_title( $course_id ) : __( 'Course', 'giga-class-market' ) ); ?></h1>
			<?php if ( $teacher ) : ?>
				<p><?php echo esc_html( sprintf( __( 'Teacher: %s', 'giga-class-market' ), $teacher->display_name ) ); ?></p>
			<?php endif; ?>
		</header>

		<?php if ( ! $course_id || ! $has_access ) : ?>
			<div class="gcm-empty-state">
				<h2><?php esc_html_e( 'You do not have access to this course.', 'giga-class-market' ); ?></h2>
				<p><?php esc_html_e( 'Purchase and wait for payment approval, or sign in with the enrolled student account.', 'giga-class-market' ); ?></p>
				<a class="gcm-button gcm-button--gold" href="<?php echo esc_url( $course_id ? gcm_course_purchase_url( $course_id ) : get_post_type_archive_link( 'gcm_course' ) ); ?>"><?php esc_html_e( 'Get Access', 'giga-class-market' ); ?></a>
			</div>
		<?php else : ?>

			<?php if ( $live_class && ! empty( $live_class->zoom_join_url ) ) : ?>
				<section class="gcm-live-banner gcm-live-banner--hero">
					<strong><?php esc_html_e( 'Class is live now', 'giga-class-market' ); ?></strong>
					<p><?php echo esc_html( $live_class->title ); ?></p>
					<?php if ( ! empty( $live_class->scheduled_at ) ) : ?>
						<p class="gcm-live-banner__time">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: exact class start datetime */
									__( 'Scheduled start: %s', 'giga-class-market' ),
									function_exists( 'gcm_format_exact_datetime' ) ? gcm_format_exact_datetime( $live_class->scheduled_at ) : $live_class->scheduled_at
								)
							);
							?>
						</p>
					<?php endif; ?>
					<?php if ( $my_joined_at ) : ?>
						<p class="gcm-live-banner__joined">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: exact join datetime */
									__( 'You joined at %s', 'giga-class-market' ),
									function_exists( 'gcm_format_exact_datetime' ) ? gcm_format_exact_datetime( $my_joined_at ) : $my_joined_at
								)
							);
							?>
						</p>
					<?php endif; ?>
					<button
						type="button"
						class="gcm-button gcm-button--gold gcm-join-live"
						data-class-id="<?php echo esc_attr( $live_class->id ); ?>"
					><?php echo esc_html( $my_joined_at ? __( 'Rejoin live class', 'giga-class-market' ) : __( 'Join live class', 'giga-class-market' ) ); ?></button>
				</section>
			<?php endif; ?>

			<div class="gcm-live-grid">
				<section class="gcm-dashboard-card">
					<h2><?php esc_html_e( 'Upcoming classes', 'giga-class-market' ); ?></h2>
					<?php if ( empty( $course_classes ) ) : ?>
						<p><?php esc_html_e( 'No scheduled classes yet. Check back soon.', 'giga-class-market' ); ?></p>
					<?php else : ?>
						<ul class="gcm-upcoming-classes">
							<?php foreach ( $course_classes as $class ) : ?>
								<li>
									<div>
										<strong><?php echo esc_html( $class->title ); ?></strong>
										<span>
											<?php
											echo esc_html(
												function_exists( 'gcm_format_exact_datetime' )
													? gcm_format_exact_datetime( $class->scheduled_at )
													: mysql2date( get_option( 'date_format' ) . ' H:i:s', $class->scheduled_at )
											);
											?>
											<?php if ( ! empty( $class->scheduled_end ) ) : ?>
												–
												<?php
												echo esc_html(
													function_exists( 'gcm_format_exact_datetime' )
														? gcm_format_exact_datetime( $class->scheduled_end )
														: mysql2date( get_option( 'date_format' ) . ' H:i:s', $class->scheduled_end )
												);
												?>
											<?php endif; ?>
										</span>
										<?php
										$class_joined = class_exists( 'GCM_Attendance_Service' )
											? GCM_Attendance_Service::get_joined_at( (int) $class->id, $user_id )
											: null;
										?>
										<?php if ( $class_joined ) : ?>
											<span class="gcm-joined-at">
												<?php
												echo esc_html(
													sprintf(
														/* translators: %s: exact join datetime */
														__( 'Joined at %s', 'giga-class-market' ),
														function_exists( 'gcm_format_exact_datetime' ) ? gcm_format_exact_datetime( $class_joined ) : $class_joined
													)
												);
												?>
											</span>
										<?php endif; ?>
										<span class="gcm-status gcm-status-<?php echo esc_attr( $class->status ); ?>"><?php echo esc_html( ucfirst( $class->status ) ); ?></span>
									</div>
									<?php if ( 'live' === $class->status && ! empty( $class->zoom_join_url ) ) : ?>
										<button type="button" class="gcm-button gcm-button--small gcm-button--gold gcm-join-live" data-class-id="<?php echo esc_attr( $class->id ); ?>"><?php echo esc_html( $class_joined ? __( 'Rejoin', 'giga-class-market' ) : __( 'Join', 'giga-class-market' ) ); ?></button>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</section>

				<section class="gcm-dashboard-card">
					<h2><?php esc_html_e( 'Study material', 'giga-class-market' ); ?></h2>
					<?php if ( empty( $course_notes ) ) : ?>
						<p><?php esc_html_e( 'No notes uploaded yet.', 'giga-class-market' ); ?></p>
					<?php else : ?>
						<ul class="gcm-notes-list">
							<?php foreach ( $course_notes as $note ) : ?>
								<li>
									<strong><?php echo esc_html( $note->title ); ?></strong>
									<?php if ( ! empty( $note->content ) ) : ?>
										<p><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $note->content ), 30 ) ); ?></p>
									<?php endif; ?>
									<?php if ( ! empty( $note->file_url ) ) : ?>
										<a class="gcm-button gcm-button--small gcm-button--outline" href="<?php echo esc_url( $note->file_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Download', 'giga-class-market' ); ?></a>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</section>
			</div>

			<section class="gcm-dashboard-card gcm-learn__messages">
				<h2><?php esc_html_e( 'Course chat room', 'giga-class-market' ); ?></h2>
				<p><?php esc_html_e( 'Ask questions here — everyone in the course can see them, and your teacher can answer.', 'giga-class-market' ); ?></p>
				<div class="gcm-message-thread">
					<?php if ( empty( $course_messages ) ) : ?>
						<p><?php esc_html_e( 'No messages yet. Be the first to ask a question.', 'giga-class-market' ); ?></p>
					<?php else : ?>
						<?php foreach ( $course_messages as $msg ) : ?>
							<div class="gcm-message <?php echo ! empty( $msg->is_mine ) ? 'is-mine' : ''; ?> gcm-message--<?php echo esc_attr( $msg->sender_role ?? 'student' ); ?>">
								<strong><?php echo esc_html( $msg->sender_name ); ?></strong>
								<?php if ( ! empty( $msg->sender_role ) && 'teacher' === $msg->sender_role ) : ?>
									<span class="gcm-chat-badge"><?php esc_html_e( 'Teacher', 'giga-class-market' ); ?></span>
								<?php endif; ?>
								<p><?php echo esc_html( $msg->message ); ?></p>
								<small><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $msg->created_at ) ); ?></small>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
				<form class="gcm-ajax-form" data-action="gcm_send_course_message">
					<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'gcm_ajax_nonce' ) ); ?>" />
					<input type="hidden" name="course_id" value="<?php echo esc_attr( $course_id ); ?>" />
					<label><?php esc_html_e( 'Your question or message', 'giga-class-market' ); ?><textarea name="message" rows="3" required></textarea></label>
					<button type="submit" class="gcm-button gcm-button--gold"><?php esc_html_e( 'Post to chat', 'giga-class-market' ); ?></button>
					<div class="gcm-form-message" aria-live="polite"></div>
				</form>
			</section>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
