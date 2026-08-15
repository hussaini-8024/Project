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

$course_id  = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0;
$lesson_id  = isset( $_GET['lesson_id'] ) ? absint( $_GET['lesson_id'] ) : 0;
$user_id    = get_current_user_id();
$has_access = gcm_user_can_access_course( $course_id, $user_id );
$curriculum = array();
$lessons    = array();

if ( $course_id && class_exists( 'GCM_Curriculum_Service' ) ) {
	$curriculum = GCM_Curriculum_Service::get_course_curriculum( $course_id );
	foreach ( $curriculum as $module ) {
		if ( empty( $module['lessons'] ) ) {
			continue;
		}
		foreach ( $module['lessons'] as $lesson ) {
			$lessons[] = is_array( $lesson ) ? (object) $lesson : $lesson;
		}
	}
}

if ( empty( $lesson_id ) && ! empty( $lessons ) ) {
	$last = class_exists( 'GCM_Progress_Service' ) ? GCM_Progress_Service::get_last_lesson( $user_id, $course_id ) : null;
	$lesson_id = $last ? (int) $last->id : (int) $lessons[0]->id;
}

$current = null;
$current_index = 0;
foreach ( $lessons as $index => $lesson ) {
	if ( (int) $lesson->id === (int) $lesson_id ) {
		$current = $lesson;
		$current_index = $index;
		break;
	}
}
if ( ! $current && ! empty( $lessons ) ) {
	$current = $lessons[0];
	$lesson_id = (int) $current->id;
	$current_index = 0;
}

$progress = class_exists( 'GCM_Progress_Service' ) ? GCM_Progress_Service::get_percentage( $user_id, $course_id ) : 0;
$learn_base = home_url( '/course-learn/' );
$live_class = ( $course_id && $has_access && class_exists( 'GCM_Class_Service' ) ) ? GCM_Class_Service::get_live_for_course( $course_id ) : null;
$course_classes = ( $course_id && $has_access && class_exists( 'GCM_Class_Service' ) ) ? GCM_Class_Service::get_for_course( $course_id ) : array();
$course_notes = ( $course_id && $has_access && class_exists( 'GCM_Notes_Service' ) ) ? GCM_Notes_Service::get_for_course( $course_id ) : array();
$course_messages = ( $course_id && $has_access && class_exists( 'GCM_Message_Service' ) ) ? GCM_Message_Service::get_thread( $course_id, $user_id ) : array();

get_header();
?>
<section class="gcm-learn">
	<div class="gcm-learn__layout">
		<aside class="gcm-learn__sidebar" aria-label="<?php esc_attr_e( 'Course curriculum', 'giga-class-market' ); ?>">
			<a class="gcm-learn__back" href="<?php echo esc_url( home_url( '/student-dashboard/' ) ); ?>">&larr; <?php esc_html_e( 'Dashboard', 'giga-class-market' ); ?></a>
			<h1><?php echo esc_html( $course_id ? get_the_title( $course_id ) : __( 'Course Player', 'giga-class-market' ) ); ?></h1>
			<div class="gcm-progress gcm-progress--learn" aria-label="<?php echo esc_attr( sprintf( __( '%d percent complete', 'giga-class-market' ), $progress ) ); ?>">
				<span style="width: <?php echo esc_attr( min( 100, max( 0, $progress ) ) ); ?>%"></span>
			</div>
			<p><?php echo esc_html( sprintf( __( '%d%% complete', 'giga-class-market' ), $progress ) ); ?></p>

			<?php if ( $has_access && $live_class && ! empty( $live_class->zoom_join_url ) ) : ?>
				<div class="gcm-live-banner">
					<strong><?php esc_html_e( 'Live class now', 'giga-class-market' ); ?></strong>
					<p><?php echo esc_html( $live_class->title ); ?></p>
					<a class="gcm-button gcm-button--gold" href="<?php echo esc_url( $live_class->zoom_join_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Join live class', 'giga-class-market' ); ?></a>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $curriculum ) ) : ?>
				<?php foreach ( $curriculum as $module ) : ?>
					<div class="gcm-module-block">
						<h3><?php echo esc_html( $module['title'] ?? __( 'Module', 'giga-class-market' ) ); ?></h3>
						<ol class="gcm-curriculum-list">
							<?php foreach ( (array) ( $module['lessons'] ?? array() ) as $lesson_row ) : ?>
								<?php $lesson_obj = is_array( $lesson_row ) ? (object) $lesson_row : $lesson_row; ?>
								<li class="<?php echo ( (int) $lesson_obj->id === (int) $lesson_id ) ? 'is-active' : ''; ?>">
									<a href="<?php echo esc_url( add_query_arg( array( 'course_id' => $course_id, 'lesson_id' => (int) $lesson_obj->id ), $learn_base ) ); ?>">
										<?php echo esc_html( $lesson_obj->title ); ?>
									</a>
								</li>
							<?php endforeach; ?>
						</ol>
					</div>
				<?php endforeach; ?>
			<?php else : ?>
				<p><?php esc_html_e( 'Curriculum will appear once modules and lessons are added by an administrator.', 'giga-class-market' ); ?></p>
			<?php endif; ?>

			<?php if ( $has_access ) : ?>
				<div class="gcm-module-block">
					<h3><?php esc_html_e( 'Upcoming classes', 'giga-class-market' ); ?></h3>
					<?php if ( empty( $course_classes ) ) : ?>
						<p><?php esc_html_e( 'No scheduled classes yet.', 'giga-class-market' ); ?></p>
					<?php else : ?>
						<ul class="gcm-upcoming-classes">
							<?php foreach ( $course_classes as $class ) : ?>
								<li>
									<strong><?php echo esc_html( $class->title ); ?></strong>
									<span><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $class->scheduled_at ) ); ?></span>
									<span class="gcm-status gcm-status-<?php echo esc_attr( $class->status ); ?>"><?php echo esc_html( ucfirst( $class->status ) ); ?></span>
									<?php if ( 'live' === $class->status && ! empty( $class->zoom_join_url ) ) : ?>
										<a class="gcm-button gcm-button--small gcm-button--gold" href="<?php echo esc_url( $class->zoom_join_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Join', 'giga-class-market' ); ?></a>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>

				<div class="gcm-module-block">
					<h3><?php esc_html_e( 'Course notes', 'giga-class-market' ); ?></h3>
					<?php if ( empty( $course_notes ) ) : ?>
						<p><?php esc_html_e( 'No notes uploaded yet.', 'giga-class-market' ); ?></p>
					<?php else : ?>
						<ul class="gcm-notes-list">
							<?php foreach ( $course_notes as $note ) : ?>
								<li>
									<strong><?php echo esc_html( $note->title ); ?></strong>
									<?php if ( ! empty( $note->content ) ) : ?>
										<p><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $note->content ), 24 ) ); ?></p>
									<?php endif; ?>
									<?php if ( ! empty( $note->file_url ) ) : ?>
										<a href="<?php echo esc_url( $note->file_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Download', 'giga-class-market' ); ?></a>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</aside>

		<div class="gcm-learn__player">
			<?php if ( ! $course_id || ! $has_access ) : ?>
				<div class="gcm-empty-state">
					<h2><?php esc_html_e( 'You do not have access to this course.', 'giga-class-market' ); ?></h2>
					<p><?php esc_html_e( 'Purchase and wait for payment approval, or sign in with the enrolled student account.', 'giga-class-market' ); ?></p>
					<a class="gcm-button gcm-button--gold" href="<?php echo esc_url( $course_id ? gcm_course_purchase_url( $course_id ) : get_post_type_archive_link( 'gcm_course' ) ); ?>"><?php esc_html_e( 'Get Access', 'giga-class-market' ); ?></a>
				</div>
			<?php elseif ( ! $current ) : ?>
				<div class="gcm-empty-state">
					<h2><?php esc_html_e( 'No lessons available yet', 'giga-class-market' ); ?></h2>
					<p><?php esc_html_e( 'Your instructor has not published lessons for this course. You can still join live classes, download notes, and message your teacher when available.', 'giga-class-market' ); ?></p>
				</div>
			<?php else : ?>
				<div class="gcm-video-frame">
					<?php if ( ! empty( $current->video_url ) ) : ?>
						<?php
						$embed = wp_oembed_get( esc_url( $current->video_url ) );
						echo $embed ? $embed : '<video controls src="' . esc_url( $current->video_url ) . '"></video>';
						?>
					<?php elseif ( ! empty( $current->video_attachment_id ) ) : ?>
						<video controls src="<?php echo esc_url( wp_get_attachment_url( (int) $current->video_attachment_id ) ); ?>"></video>
					<?php else : ?>
						<div class="gcm-video-placeholder">
							<span><?php esc_html_e( 'Premium Lesson Video', 'giga-class-market' ); ?></span>
						</div>
					<?php endif; ?>
				</div>
				<div class="gcm-learn__content">
					<p class="gcm-eyebrow"><?php echo esc_html( sprintf( __( 'Lesson %d of %d', 'giga-class-market' ), $current_index + 1, max( 1, count( $lessons ) ) ) ); ?></p>
					<h2><?php echo esc_html( $current->title ); ?></h2>
					<div class="gcm-lesson-content"><?php echo wp_kses_post( wpautop( $current->content ?? '' ) ); ?></div>
					<form class="gcm-inline-form" data-gcm-progress-form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
						<input type="hidden" name="action" value="gcm_mark_lesson_complete">
						<input type="hidden" name="lesson_id" value="<?php echo esc_attr( $lesson_id ); ?>">
						<input type="hidden" name="completed" value="1">
						<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'gcm_ajax_nonce' ) ); ?>">
						<button class="gcm-button gcm-button--gold" type="submit"><?php esc_html_e( 'Mark Lesson Complete', 'giga-class-market' ); ?></button>
						<p class="gcm-form-status" role="status" aria-live="polite"></p>
					</form>
					<nav class="gcm-lesson-nav" aria-label="<?php esc_attr_e( 'Lesson navigation', 'giga-class-market' ); ?>">
						<?php if ( $current_index > 0 ) : ?>
							<a class="gcm-button gcm-button--outline" href="<?php echo esc_url( add_query_arg( array( 'course_id' => $course_id, 'lesson_id' => (int) $lessons[ $current_index - 1 ]->id ), $learn_base ) ); ?>"><?php esc_html_e( 'Previous', 'giga-class-market' ); ?></a>
						<?php endif; ?>
						<?php if ( $current_index < count( $lessons ) - 1 ) : ?>
							<a class="gcm-button" href="<?php echo esc_url( add_query_arg( array( 'course_id' => $course_id, 'lesson_id' => (int) $lessons[ $current_index + 1 ]->id ), $learn_base ) ); ?>"><?php esc_html_e( 'Next', 'giga-class-market' ); ?></a>
						<?php endif; ?>
					</nav>
				</div>
			<?php endif; ?>

			<?php if ( $course_id && $has_access ) : ?>
				<div class="gcm-learn__messages gcm-dashboard-card">
					<h2><?php esc_html_e( 'Message your teacher', 'giga-class-market' ); ?></h2>
					<div class="gcm-message-thread">
						<?php if ( empty( $course_messages ) ) : ?>
							<p><?php esc_html_e( 'No messages yet. Ask a question about this course.', 'giga-class-market' ); ?></p>
						<?php else : ?>
							<?php foreach ( $course_messages as $msg ) : ?>
								<div class="gcm-message <?php echo ! empty( $msg->is_mine ) ? 'is-mine' : ''; ?>">
									<strong><?php echo esc_html( $msg->sender_name ); ?></strong>
									<p><?php echo esc_html( $msg->message ); ?></p>
									<small><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $msg->created_at ) ); ?></small>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
					<form class="gcm-ajax-form" data-action="gcm_send_course_message">
						<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'gcm_ajax_nonce' ) ); ?>" />
						<input type="hidden" name="course_id" value="<?php echo esc_attr( $course_id ); ?>" />
						<label><?php esc_html_e( 'Your message', 'giga-class-market' ); ?><textarea name="message" rows="3" required></textarea></label>
						<button type="submit" class="gcm-button gcm-button--gold"><?php esc_html_e( 'Send', 'giga-class-market' ); ?></button>
						<div class="gcm-form-message" aria-live="polite"></div>
					</form>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
<?php
get_footer();
