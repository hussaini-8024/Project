<?php
/**
 * Payments view.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$statuses = array( '', 'pending', 'under_review', 'approved', 'rejected' );
?>
<div class="wrap gcm-admin-wrap">
	<h1><?php esc_html_e( 'Payments', 'giga-class-market' ); ?></h1>
	<ul class="subsubsub">
		<?php foreach ( $statuses as $item ) : ?>
			<li>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'gcm-payments', 'status' => $item ), admin_url( 'admin.php' ) ) ); ?>" class="<?php echo esc_attr( $status === $item ? 'current' : '' ); ?>">
					<?php echo esc_html( $item ? ucwords( str_replace( '_', ' ', $item ) ) : __( 'All', 'giga-class-market' ) ); ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
	<table class="widefat striped gcm-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'ID', 'giga-class-market' ); ?></th>
				<th><?php esc_html_e( 'Student', 'giga-class-market' ); ?></th>
				<th><?php esc_html_e( 'Course', 'giga-class-market' ); ?></th>
				<th><?php esc_html_e( 'Method / Transaction', 'giga-class-market' ); ?></th>
				<th><?php esc_html_e( 'Amount', 'giga-class-market' ); ?></th>
				<th><?php esc_html_e( 'Status', 'giga-class-market' ); ?></th>
				<th><?php esc_html_e( 'Screenshot', 'giga-class-market' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'giga-class-market' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $payments ) ) : ?>
				<tr><td colspan="8"><?php esc_html_e( 'No payments found.', 'giga-class-market' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $payments as $payment ) : ?>
				<?php
				$course = GCM_Course_Service::get( $payment->course_id );
				$link   = $payment->screenshot_id ? wp_nonce_url( add_query_arg( 'gcm_private_screenshot', $payment->id, home_url( '/' ) ), 'gcm_private_screenshot_' . $payment->id ) : '';
				?>
				<tr>
					<td><?php echo esc_html( $payment->id ); ?></td>
					<td>
						<strong><?php echo esc_html( $payment->full_name ); ?></strong><br />
						<a href="mailto:<?php echo esc_attr( $payment->email ); ?>"><?php echo esc_html( $payment->email ); ?></a><br />
						<?php echo esc_html( $payment->whatsapp ); ?>
					</td>
					<td><?php echo esc_html( $course ? $course['title'] : __( 'Course removed', 'giga-class-market' ) ); ?></td>
					<td><?php echo esc_html( $payment->payment_method ); ?><br /><code><?php echo esc_html( $payment->transaction_id ); ?></code></td>
					<td><?php echo esc_html( number_format_i18n( (float) $payment->amount, 2 ) ); ?></td>
					<td><span class="gcm-status gcm-status-<?php echo esc_attr( $payment->status ); ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $payment->status ) ) ); ?></span></td>
					<td><?php echo $link ? '<a href="' . esc_url( $link ) . '" target="_blank" rel="noopener">' . esc_html__( 'View', 'giga-class-market' ) . '</a>' : esc_html__( 'None', 'giga-class-market' ); ?></td>
					<td>
						<?php if ( 'approved' !== $payment->status ) : ?>
							<button class="button button-primary gcm-ajax-button" data-action="gcm_approve_payment" data-payment-id="<?php echo esc_attr( $payment->id ); ?>"><?php esc_html_e( 'Approve', 'giga-class-market' ); ?></button>
						<?php endif; ?>
						<?php if ( 'rejected' !== $payment->status ) : ?>
							<button class="button gcm-ajax-button gcm-reject-payment" data-action="gcm_reject_payment" data-payment-id="<?php echo esc_attr( $payment->id ); ?>"><?php esc_html_e( 'Reject', 'giga-class-market' ); ?></button>
						<?php endif; ?>
						<?php if ( $payment->user_id ) : ?>
							<button class="button gcm-ajax-button" data-action="gcm_send_credentials" data-user-id="<?php echo esc_attr( $payment->user_id ); ?>" data-payment-id="<?php echo esc_attr( $payment->id ); ?>"><?php esc_html_e( 'Send Credentials', 'giga-class-market' ); ?></button>
						<?php else : ?>
							<button class="button gcm-ajax-button" data-action="gcm_create_student_account" data-payment-id="<?php echo esc_attr( $payment->id ); ?>"><?php esc_html_e( 'Create Account', 'giga-class-market' ); ?></button>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
