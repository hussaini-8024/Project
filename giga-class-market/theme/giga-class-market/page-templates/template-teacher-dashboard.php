<?php
/**
 * Template Name: GCM Teacher Dashboard
 *
 * Online class system — schedule, start Zoom, notes, chat, attendance.
 *
 * @package GigaClassMarket
 */

if ( ! is_user_logged_in() ) {
	wp_safe_redirect( add_query_arg( 'redirect_to', rawurlencode( home_url( '/teacher-dashboard/' ) ), gcm_student_login_url() ) );
	exit;
}

$user = wp_get_current_user();
if ( ! current_user_can( 'gcm_teacher_dashboard' ) && ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have access to the teacher dashboard.', 'giga-class-market' ), esc_html__( 'Access denied', 'giga-class-market' ), array( 'response' => 403 ) );
}

$teacher_id = (int) $user->ID;
$courses    = class_exists( 'GCM_Teacher_Service' ) ? GCM_Teacher_Service::get_teacher_courses( $teacher_id ) : array();
if ( current_user_can( 'manage_options' ) && empty( $courses ) && class_exists( 'GCM_Course_Service' ) ) {
	$courses = GCM_Course_Service::search( array( 'limit' => 100 ) );
}
$classes = class_exists( 'GCM_Class_Service' )
	? ( current_user_can( 'manage_options' ) ? GCM_Class_Service::get_all( 100 ) : GCM_Class_Service::get_for_teacher( $teacher_id ) )
	: array();

$active_course_id = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0;
if ( ! $active_course_id && ! empty( $courses ) ) {
	$active_course_id = (int) ( $courses[0]['id'] ?? 0 );
}

$students = ( $active_course_id && class_exists( 'GCM_Enrollment_Service' ) )
	? GCM_Enrollment_Service::get_course_students( $active_course_id )
	: array();
$notes = ( $active_course_id && class_exists( 'GCM_Notes_Service' ) )
	? GCM_Notes_Service::get_for_course( $active_course_id )
	: array();
$messages = ( $active_course_id && class_exists( 'GCM_Message_Service' ) )
	? GCM_Message_Service::get_thread( $active_course_id, $teacher_id )
	: array();

$upcoming = array_values(
	array_filter(
		(array) $classes,
		static function ( $class ) use ( $active_course_id ) {
			if ( ! isset( $class->status ) || ! in_array( $class->status, array( 'scheduled', 'live' ), true ) ) {
				return false;
			}
			if ( $active_course_id && (int) $class->course_id !== $active_course_id ) {
				return false;
			}
			return true;
		}
	)
);

get_header();
?>
<section class="gcm-dashboard-hero">
	<div class="gcm-container">
		<p class="gcm-eyebrow"><?php esc_html_e( 'Teacher dashboard', 'giga-class-market' ); ?></p>
		<h1><?php echo esc_html( sprintf( __( 'Welcome, %s', 'giga-class-market' ), $user->display_name ) ); ?></h1>
		<p><?php esc_html_e( 'Run live classes: set start and end times, start Zoom, share study materials, answer the course chat, and track attendance.', 'giga-class-market' ); ?></p>
	</div>
</section>

<section class="gcm-dashboard gcm-teacher-dashboard" data-gcm-teacher-dashboard>
	<div class="gcm-container gcm-dashboard__grid">
		<div class="gcm-dashboard__main">
			<div class="gcm-dashboard-card">
				<div class="gcm-dashboard-card__heading">
					<h2><?php esc_html_e( 'My courses', 'giga-class-market' ); ?></h2>
				</div>
				<?php if ( empty( $courses ) ) : ?>
					<p><?php esc_html_e( 'No courses assigned yet. Ask an administrator to assign a course (one teacher per course).', 'giga-class-market' ); ?></p>
				<?php else : ?>
					<div class="gcm-teacher-course-tabs">
						<?php foreach ( $courses as $course ) : ?>
							<?php $cid = (int) ( $course['id'] ?? 0 ); ?>
							<a class="gcm-button <?php echo $cid === $active_course_id ? 'gcm-button--gold' : 'gcm-button--outline'; ?>" href="<?php echo esc_url( add_query_arg( 'course_id', $cid, home_url( '/teacher-dashboard/' ) ) ); ?>">
								<?php echo esc_html( $course['title'] ?? get_the_title( $cid ) ); ?>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( $active_course_id ) : ?>
				<div class="gcm-dashboard-card">
					<div class="gcm-dashboard-card__heading">
						<h2><?php echo esc_html( sprintf( __( 'Classes — %s', 'giga-class-market' ), get_the_title( $active_course_id ) ) ); ?></h2>
					</div>

					<details class="gcm-teacher-panel" open>
						<summary><?php esc_html_e( 'Schedule class (start & end)', 'giga-class-market' ); ?></summary>
						<form class="gcm-ajax-form gcm-teacher-form" data-action="gcm_schedule_class">
							<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'gcm_ajax_nonce' ) ); ?>" />
							<input type="hidden" name="course_id" value="<?php echo esc_attr( $active_course_id ); ?>" />
							<label><?php esc_html_e( 'Class title', 'giga-class-market' ); ?><input type="text" name="title" placeholder="<?php esc_attr_e( 'Optional', 'giga-class-market' ); ?>" /></label>
							<label><?php esc_html_e( 'Starts', 'giga-class-market' ); ?><input type="datetime-local" name="scheduled_at" required /></label>
							<label><?php esc_html_e( 'Ends', 'giga-class-market' ); ?><input type="datetime-local" name="scheduled_end" required /></label>
							<button type="submit" class="gcm-button gcm-button--gold"><?php esc_html_e( 'Schedule', 'giga-class-market' ); ?></button>
							<div class="gcm-form-message" aria-live="polite"></div>
						</form>
					</details>

					<?php if ( empty( $upcoming ) ) : ?>
						<p><?php esc_html_e( 'No upcoming classes for this course.', 'giga-class-market' ); ?></p>
					<?php else : ?>
						<ul class="gcm-teacher-class-list">
							<?php foreach ( $upcoming as $class ) : ?>
								<?php
								if ( 'live' === $class->status && class_exists( 'GCM_Class_Service' ) ) {
									$fixed = GCM_Class_Service::ensure_meeting_links( (int) $class->id );
									if ( ! is_wp_error( $fixed ) ) {
										$class = $fixed;
									}
								}
								?>
								<li class="gcm-teacher-class gcm-teacher-class--<?php echo esc_attr( $class->status ); ?>">
									<div>
										<strong><?php echo esc_html( $class->title ); ?></strong>
										<p>
											<?php echo esc_html( gcm_format_exact_datetime( $class->scheduled_at ) ); ?>
											<?php if ( ! empty( $class->scheduled_end ) ) : ?>
												– <?php echo esc_html( gcm_format_exact_datetime( $class->scheduled_end ) ); ?>
											<?php endif; ?>
											· <span class="gcm-status gcm-status-<?php echo esc_attr( $class->status ); ?>"><?php echo esc_html( ucfirst( $class->status ) ); ?></span>
											<?php if ( 'live' === $class->status || 'ended' === $class->status ) : ?>
												· <?php echo esc_html( sprintf( __( '%d joined', 'giga-class-market' ), class_exists( 'GCM_Attendance_Service' ) ? GCM_Attendance_Service::count_for_class( (int) $class->id ) : 0 ) ); ?>
											<?php endif; ?>
										</p>
									</div>
									<div class="gcm-teacher-class__actions">
										<?php if ( 'scheduled' === $class->status ) : ?>
											<button type="button" class="gcm-button gcm-button--gold gcm-teacher-action" data-action="gcm_start_class" data-class-id="<?php echo esc_attr( $class->id ); ?>"><?php esc_html_e( 'Start class', 'giga-class-market' ); ?></button>
										<?php elseif ( 'live' === $class->status ) : ?>
											<?php if ( ! empty( $class->zoom_start_url ) && class_exists( 'GCM_Zoom_Service' ) && GCM_Zoom_Service::is_usable_meeting_url( $class->zoom_start_url ) ) : ?>
												<a class="gcm-button gcm-button--gold" href="<?php echo esc_url( $class->zoom_start_url ); ?>"><?php esc_html_e( 'Open live class', 'giga-class-market' ); ?></a>
											<?php else : ?>
												<button type="button" class="gcm-button gcm-button--gold gcm-teacher-action" data-action="gcm_start_class" data-class-id="<?php echo esc_attr( $class->id ); ?>"><?php esc_html_e( 'Open live class', 'giga-class-market' ); ?></button>
											<?php endif; ?>
											<button type="button" class="gcm-button gcm-button--outline gcm-teacher-action" data-action="gcm_end_class" data-class-id="<?php echo esc_attr( $class->id ); ?>"><?php esc_html_e( 'End class', 'giga-class-market' ); ?></button>
										<?php endif; ?>
									</div>
									<?php if ( in_array( $class->status, array( 'live', 'ended' ), true ) && class_exists( 'GCM_Attendance_Service' ) ) : ?>
										<?php $roster = GCM_Attendance_Service::get_for_class( (int) $class->id ); ?>
										<?php if ( ! empty( $roster ) ) : ?>
											<details class="gcm-attendance-details">
												<summary><?php esc_html_e( 'Attendance', 'giga-class-market' ); ?></summary>
												<ul class="gcm-attendance-list">
													<?php foreach ( $roster as $row ) : ?>
														<li>
															<?php echo esc_html( $row->display_name ); ?>
															<small><?php echo esc_html( function_exists( 'gcm_format_exact_datetime' ) ? gcm_format_exact_datetime( $row->joined_at ) : mysql2date( get_option( 'date_format' ) . ' H:i:s', $row->joined_at ) ); ?></small>
														</li>
													<?php endforeach; ?>
												</ul>
											</details>
										<?php endif; ?>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>

				<div class="gcm-dashboard-card">
					<details class="gcm-teacher-panel" open>
						<summary><?php esc_html_e( 'Upload study material', 'giga-class-market' ); ?></summary>
						<form class="gcm-ajax-form gcm-teacher-form" data-action="gcm_upload_note" enctype="multipart/form-data">
							<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'gcm_ajax_nonce' ) ); ?>" />
							<input type="hidden" name="course_id" value="<?php echo esc_attr( $active_course_id ); ?>" />
							<label><?php esc_html_e( 'Title', 'giga-class-market' ); ?><input type="text" name="title" required /></label>
							<label><?php esc_html_e( 'Notes / description', 'giga-class-market' ); ?><textarea name="content" rows="3"></textarea></label>
							<label><?php esc_html_e( 'File (PDF, DOC, PPT…)', 'giga-class-market' ); ?><input type="file" name="note_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.jpg,.jpeg,.png" /></label>
							<button type="submit" class="gcm-button gcm-button--gold"><?php esc_html_e( 'Upload material', 'giga-class-market' ); ?></button>
							<div class="gcm-form-message" aria-live="polite"></div>
						</form>
						<?php if ( ! empty( $notes ) ) : ?>
							<ul class="gcm-notes-list">
								<?php foreach ( $notes as $note ) : ?>
									<li>
										<strong><?php echo esc_html( $note->title ); ?></strong>
										<?php if ( ! empty( $note->file_url ) ) : ?>
											— <a href="<?php echo esc_url( $note->file_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Download', 'giga-class-market' ); ?></a>
										<?php endif; ?>
										<button type="button" class="gcm-link-button gcm-teacher-action" data-action="gcm_delete_note" data-note-id="<?php echo esc_attr( $note->id ); ?>"><?php esc_html_e( 'Delete', 'giga-class-market' ); ?></button>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</details>
				</div>

				<div class="gcm-dashboard-card">
					<div class="gcm-dashboard-card__heading">
						<h2><?php esc_html_e( 'Course chat room', 'giga-class-market' ); ?></h2>
					</div>
					<p><?php esc_html_e( 'Everyone in this course can see questions and answers here.', 'giga-class-market' ); ?></p>
					<div class="gcm-message-thread">
						<?php if ( empty( $messages ) ) : ?>
							<p class="gcm-message-empty"><?php esc_html_e( 'No messages yet.', 'giga-class-market' ); ?></p>
						<?php else : ?>
							<?php foreach ( $messages as $msg ) : ?>
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
					<form class="gcm-ajax-form gcm-teacher-form" data-action="gcm_send_teacher_message">
						<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'gcm_ajax_nonce' ) ); ?>" />
						<input type="hidden" name="course_id" value="<?php echo esc_attr( $active_course_id ); ?>" />
						<label><?php esc_html_e( 'Reply to the class', 'giga-class-market' ); ?><textarea name="message" rows="3" required></textarea></label>
						<button type="submit" class="gcm-button gcm-button--gold"><?php esc_html_e( 'Post answer', 'giga-class-market' ); ?></button>
						<div class="gcm-form-message" aria-live="polite"></div>
					</form>
				</div>

				<div class="gcm-dashboard-card">
					<div class="gcm-dashboard-card__heading">
						<h2><?php esc_html_e( 'Enrolled students', 'giga-class-market' ); ?></h2>
					</div>
					<?php if ( empty( $students ) ) : ?>
						<p><?php esc_html_e( 'No enrolled students yet.', 'giga-class-market' ); ?></p>
					<?php else : ?>
						<ul class="gcm-teacher-students">
							<?php foreach ( $students as $student ) : ?>
								<li>
									<strong><?php echo esc_html( $student->display_name ); ?></strong>
									<span><?php echo esc_html( $student->user_email ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<aside class="gcm-dashboard__side">
			<div class="gcm-dashboard-card">
				<h2><?php esc_html_e( 'How live class works', 'giga-class-market' ); ?></h2>
				<ol>
					<li><?php esc_html_e( 'Set start and end time for each class.', 'giga-class-market' ); ?></li>
					<li><?php esc_html_e( 'Click Start class to create Zoom — students see Join immediately.', 'giga-class-market' ); ?></li>
					<li><?php esc_html_e( 'Upload study material for this course.', 'giga-class-market' ); ?></li>
					<li><?php esc_html_e( 'Answer questions in the shared chat room.', 'giga-class-market' ); ?></li>
					<li><?php esc_html_e( 'Attendance is recorded when students click Join.', 'giga-class-market' ); ?></li>
				</ol>
				<p><a class="gcm-button gcm-button--outline" href="<?php echo esc_url( wp_logout_url( home_url( '/login/' ) ) ); ?>"><?php esc_html_e( 'Log out', 'giga-class-market' ); ?></a></p>
			</div>
		</aside>
	</div>
</section>
<?php
get_footer();
