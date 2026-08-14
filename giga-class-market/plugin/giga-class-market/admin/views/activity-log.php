<?php
/**
 * Activity log view.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap gcm-admin-wrap">
	<h1><?php esc_html_e( 'Activity Log', 'giga-class-market' ); ?></h1>
	<table class="widefat striped gcm-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Time', 'giga-class-market' ); ?></th>
				<th><?php esc_html_e( 'Admin', 'giga-class-market' ); ?></th>
				<th><?php esc_html_e( 'Action', 'giga-class-market' ); ?></th>
				<th><?php esc_html_e( 'Object', 'giga-class-market' ); ?></th>
				<th><?php esc_html_e( 'IP', 'giga-class-market' ); ?></th>
				<th><?php esc_html_e( 'Meta', 'giga-class-market' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $logs ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No activity yet.', 'giga-class-market' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $logs as $log ) : ?>
				<?php $admin = get_userdata( $log->admin_id ); ?>
				<tr>
					<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $log->created_at ) ); ?></td>
					<td><?php echo esc_html( $admin ? $admin->display_name : __( 'System/Public', 'giga-class-market' ) ); ?></td>
					<td><?php echo esc_html( $log->action ); ?></td>
					<td><?php echo esc_html( $log->object_type . ' #' . $log->object_id ); ?></td>
					<td><?php echo esc_html( $log->ip_address ); ?></td>
					<td><code><?php echo esc_html( $log->meta ); ?></code></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
