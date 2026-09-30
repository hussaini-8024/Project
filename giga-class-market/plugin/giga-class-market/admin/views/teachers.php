<?php
/**
 * Teachers admin view.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$all_courses = class_exists( 'GCM_Course_Service' ) ? GCM_Course_Service::search( array( 'limit' => 200 ) ) : array();
?>
<div class="wrap gcm-admin-wrap">
	<h1><?php esc_html_e( 'Teachers', 'giga-class-market' ); ?></h1>
	<p><?php esc_html_e( 'Teachers are created only here. They use the same Login page as students (/login/), then land on the Teacher Dashboard. Each course can have only one teacher. Assign courses, set passwords, and manage accounts below.', 'giga-class-market' ); ?></p>

	<div class="gcm-admin-panel" style="margin-bottom:24px;">
		<h2><?php esc_html_e( 'Create teacher', 'giga-class-market' ); ?></h2>
		<form class="gcm-ajax-form gcm-create-teacher-form" enctype="multipart/form-data">
			<input type="hidden" name="action" value="gcm_create_teacher" />
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'gcm_ajax_nonce' ) ); ?>" />
			<label><?php esc_html_e( 'Full name', 'giga-class-market' ); ?><input type="text" name="full_name" required /></label>
			<label><?php esc_html_e( 'Email', 'giga-class-market' ); ?><input type="email" name="email" required /></label>
			<label><?php esc_html_e( 'Username (optional)', 'giga-class-market' ); ?><input type="text" name="username" /></label>
			<label><?php esc_html_e( 'Password (assigned by admin)', 'giga-class-market' ); ?><input type="text" name="password" required minlength="8" autocomplete="new-password" /></label>
			<label><?php esc_html_e( 'WhatsApp', 'giga-class-market' ); ?><input type="text" name="whatsapp" /></label>
			<label><?php esc_html_e( 'Assign courses', 'giga-class-market' ); ?>
				<select name="course_ids[]" multiple size="6" style="min-width:280px;height:auto;">
					<?php foreach ( $all_courses as $course ) : ?>
						<option value="<?php echo esc_attr( $course['id'] ); ?>"><?php echo esc_html( $course['title'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<p class="description"><?php esc_html_e( 'Hold Ctrl/Cmd to select multiple courses. Assigning a course moves it to this teacher (one teacher per course).', 'giga-class-market' ); ?></p>
			<p>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Create teacher', 'giga-class-market' ); ?></button>
				<span class="gcm-form-message"></span>
			</p>
		</form>
	</div>

	<form method="get" class="gcm-admin-search">
		<input type="hidden" name="page" value="gcm-teachers" />
		<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search teachers', 'giga-class-market' ); ?>" />
		<button class="button"><?php esc_html_e( 'Search', 'giga-class-market' ); ?></button>
	</form>

	<table class="widefat striped gcm-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Teacher', 'giga-class-market' ); ?></th>
				<th><?php esc_html_e( 'WhatsApp', 'giga-class-market' ); ?></th>
				<th><?php esc_html_e( 'Courses', 'giga-class-market' ); ?></th>
				<th><?php esc_html_e( 'Registered', 'giga-class-market' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'giga-class-market' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $teachers ) ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'No teachers yet. Create one above.', 'giga-class-market' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $teachers as $teacher ) : ?>
				<?php
				$assigned_ids = array();
				foreach ( (array) $teacher->courses as $c ) {
					$assigned_ids[] = (int) ( is_array( $c ) ? ( $c['id'] ?? 0 ) : 0 );
				}
				?>
				<tr>
					<td>
						<strong><?php echo esc_html( $teacher->display_name ); ?></strong><br />
						<code><?php echo esc_html( $teacher->user_login ); ?></code><br />
						<a href="mailto:<?php echo esc_attr( $teacher->user_email ); ?>"><?php echo esc_html( $teacher->user_email ); ?></a>
					</td>
					<td><?php echo esc_html( $teacher->whatsapp ); ?></td>
					<td>
						<?php if ( empty( $teacher->courses ) ) : ?>
							<em><?php esc_html_e( 'No courses assigned', 'giga-class-market' ); ?></em>
						<?php else : ?>
							<ul class="gcm-student-courses">
								<?php foreach ( $teacher->courses as $course ) : ?>
									<li><?php echo esc_html( is_array( $course ) ? ( $course['title'] ?? '' ) : '' ); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
						<details style="margin-top:8px;">
							<summary><?php esc_html_e( 'Edit course assignment', 'giga-class-market' ); ?></summary>
							<form class="gcm-ajax-form gcm-assign-teacher-courses" style="margin-top:8px;">
								<input type="hidden" name="action" value="gcm_assign_teacher_courses" />
								<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'gcm_ajax_nonce' ) ); ?>" />
								<input type="hidden" name="teacher_id" value="<?php echo esc_attr( $teacher->ID ); ?>" />
								<select name="course_ids[]" multiple size="5" style="min-width:220px;height:auto;">
									<?php foreach ( $all_courses as $course ) : ?>
										<option value="<?php echo esc_attr( $course['id'] ); ?>" <?php selected( in_array( (int) $course['id'], $assigned_ids, true ) ); ?>><?php echo esc_html( $course['title'] ); ?></option>
									<?php endforeach; ?>
								</select>
								<p>
									<button type="submit" class="button"><?php esc_html_e( 'Save courses', 'giga-class-market' ); ?></button>
									<span class="gcm-form-message"></span>
								</p>
							</form>
						</details>
					</td>
					<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $teacher->registered ) ); ?></td>
					<td>
						<form class="gcm-ajax-form gcm-set-teacher-password" style="margin-bottom:8px;">
							<input type="hidden" name="action" value="gcm_set_teacher_password" />
							<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'gcm_ajax_nonce' ) ); ?>" />
							<input type="hidden" name="teacher_id" value="<?php echo esc_attr( $teacher->ID ); ?>" />
							<input type="text" name="password" placeholder="<?php esc_attr_e( 'New password', 'giga-class-market' ); ?>" minlength="8" required autocomplete="new-password" />
							<button type="submit" class="button"><?php esc_html_e( 'Set password', 'giga-class-market' ); ?></button>
							<span class="gcm-form-message"></span>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
