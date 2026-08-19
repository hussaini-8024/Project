<?php
/**
 * Live classes admin view.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap gcm-admin-wrap">
	<h1><?php esc_html_e( 'Live Classes', 'giga-class-market' ); ?></h1>
	<p><?php esc_html_e( 'Admins can schedule, start, and end live classes for any course. Starting a class creates a Zoom meeting and shows Join to enrolled students.', 'giga-class-market' ); ?></p>

	<div class="gcm-admin-panel" style="margin-bottom:24px;">
		<h2><?php esc_html_e( 'Schedule a class', 'giga-class-market' ); ?></h2>
		<form class="gcm-ajax-form gcm-admin-schedule-class">
			<input type="hidden" name="action" value="gcm_schedule_class" />
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'gcm_ajax_nonce' ) ); ?>" />
			<label><?php esc_html_e( 'Course', 'giga-class-market' ); ?>
				<select name="course_id" required>
					<option value=""><?php esc_html_e( 'Select course', 'giga-class-market' ); ?></option>
					<?php foreach ( $courses as $course ) : ?>
						<?php
						$teacher = GCM_Teacher_Service::get_teacher_for_course( (int) $course['id'] );
						$label   = $course['title'];
						if ( $teacher ) {
							$label .= ' — ' . $teacher->display_name;
						}
						?>
						<option value="<?php echo esc_attr( $course['id'] ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label><?php esc_html_e( 'Title', 'giga-class-market' ); ?><input type="text" name="title" placeholder="<?php esc_attr_e( 'Optional', 'giga-class-market' ); ?>" /></label>
			<label><?php esc_html_e( 'Starts', 'giga-class-market' ); ?><input type="datetime-local" name="scheduled_at" required /></label>
			<label><?php esc_html_e( 'Ends', 'giga-class-market' ); ?><input type="datetime-local" name="scheduled_end" required /></label>
			<p>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Schedule class', 'giga-class-market' ); ?></button>
				<span class="gcm-form-message"></span>
			</p>
		</form>
	</div>

	<table class="widefat striped gcm-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Class', 'giga-class-market' ); ?></th>
				<th><?php esc_html_e( 'Course', 'giga-class-market' ); ?></th>
				<th><?php esc_html_e( 'Schedule', 'giga-class-market' ); ?></th>
				<th><?php esc_html_e( 'Status', 'giga-class-market' ); ?></th>
				<th><?php esc_html_e( 'Attendance', 'giga-class-market' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'giga-class-market' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $classes ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No classes scheduled yet.', 'giga-class-market' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $classes as $class ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $class->title ); ?></strong></td>
					<td><?php echo esc_html( get_the_title( (int) $class->course_id ) ); ?></td>
					<td>
						<?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $class->scheduled_at ) ); ?>
						<?php if ( ! empty( $class->scheduled_end ) ) : ?>
							– <?php echo esc_html( mysql2date( get_option( 'time_format' ), $class->scheduled_end ) ); ?>
						<?php endif; ?>
					</td>
					<td><span class="gcm-status gcm-status-<?php echo esc_attr( $class->status ); ?>"><?php echo esc_html( ucfirst( $class->status ) ); ?></span></td>
					<td><?php echo esc_html( (string) GCM_Attendance_Service::count_for_class( (int) $class->id ) ); ?></td>
					<td>
						<?php if ( 'scheduled' === $class->status ) : ?>
							<button type="button" class="button button-primary gcm-ajax-button" data-action="gcm_start_class" data-class-id="<?php echo esc_attr( $class->id ); ?>"><?php esc_html_e( 'Start class', 'giga-class-market' ); ?></button>
						<?php elseif ( 'live' === $class->status ) : ?>
							<?php if ( ! empty( $class->zoom_start_url ) ) : ?>
								<a class="button" href="<?php echo esc_url( $class->zoom_start_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open Zoom', 'giga-class-market' ); ?></a>
							<?php endif; ?>
							<button type="button" class="button gcm-ajax-button" data-action="gcm_end_class" data-class-id="<?php echo esc_attr( $class->id ); ?>"><?php esc_html_e( 'End class', 'giga-class-market' ); ?></button>
						<?php else : ?>
							—
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
