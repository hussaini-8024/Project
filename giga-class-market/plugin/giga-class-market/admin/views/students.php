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
				<th><?php esc_html_e( 'Enrollments', 'giga-class-market' ); ?></th>
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
						<?php echo esc_html( number_format_i18n( (int) $student->enrollments ) ); ?>
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
