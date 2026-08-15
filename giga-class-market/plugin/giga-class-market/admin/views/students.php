<?php
/**
 * Students view.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap gcm-admin-wrap">
	<h1><?php esc_html_e( 'Students', 'giga-class-market' ); ?></h1>
	<p><?php esc_html_e( 'Each student and the courses attached to their account (added automatically when a payment is approved).', 'giga-class-market' ); ?></p>
	<form method="get" class="gcm-admin-search">
		<input type="hidden" name="page" value="gcm-students" />
		<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search students', 'giga-class-market' ); ?>" />
		<button class="button"><?php esc_html_e( 'Search', 'giga-class-market' ); ?></button>
	</form>
	<table class="widefat striped gcm-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Student', 'giga-class-market' ); ?></th>
				<th><?php esc_html_e( 'WhatsApp', 'giga-class-market' ); ?></th>
				<th><?php esc_html_e( 'Courses', 'giga-class-market' ); ?></th>
				<th><?php esc_html_e( 'Registered', 'giga-class-market' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'giga-class-market' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $students ) ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'No students found.', 'giga-class-market' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $students as $student ) : ?>
				<tr>
					<td>
						<strong><?php echo esc_html( $student->display_name ); ?></strong><br />
						<a href="mailto:<?php echo esc_attr( $student->user_email ); ?>"><?php echo esc_html( $student->user_email ); ?></a>
					</td>
					<td><?php echo esc_html( $student->whatsapp ); ?></td>
					<td>
						<?php if ( empty( $student->courses ) ) : ?>
							<em><?php esc_html_e( 'No courses yet', 'giga-class-market' ); ?></em>
						<?php else : ?>
							<ul class="gcm-student-courses">
								<?php foreach ( $student->courses as $course ) : ?>
									<li>
										<strong><?php echo esc_html( $course['title'] ); ?></strong>
										<span class="gcm-status gcm-status-<?php echo esc_attr( $course['status'] ); ?>"><?php echo esc_html( ucfirst( $course['status'] ) ); ?></span>
										— <?php echo esc_html( sprintf( __( '%d%% complete', 'giga-class-market' ), (int) $course['progress'] ) ); ?>
									</li>
								<?php endforeach; ?>
							</ul>
							<p><small><?php echo esc_html( sprintf( _n( '%d course', '%d courses', count( $student->courses ), 'giga-class-market' ), count( $student->courses ) ) ); ?></small></p>
						<?php endif; ?>
						<?php if ( (int) $student->frozen_enrollments > 0 ) : ?>
							<span class="gcm-status gcm-status-frozen"><?php echo esc_html( sprintf( __( '%d frozen', 'giga-class-market' ), (int) $student->frozen_enrollments ) ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $student->user_registered ) ); ?></td>
					<td>
						<button class="button gcm-ajax-button" data-action="gcm_freeze_student" data-user-id="<?php echo esc_attr( $student->ID ); ?>"><?php esc_html_e( 'Freeze', 'giga-class-market' ); ?></button>
						<button class="button gcm-ajax-button" data-action="gcm_unfreeze_student" data-user-id="<?php echo esc_attr( $student->ID ); ?>"><?php esc_html_e( 'Unfreeze', 'giga-class-market' ); ?></button>
						<button class="button gcm-ajax-button" data-action="gcm_send_credentials" data-user-id="<?php echo esc_attr( $student->ID ); ?>"><?php esc_html_e( 'Send Credentials', 'giga-class-market' ); ?></button>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
