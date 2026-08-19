<?php
/**
 * Contacts view.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$statuses = array( '', 'new', 'in_progress', 'contacted', 'resolved' );
?>
<div class="wrap gcm-admin-wrap">
	<h1><?php esc_html_e( 'Contact Messages', 'giga-class-market' ); ?></h1>
	<ul class="subsubsub">
		<?php foreach ( $statuses as $item ) : ?>
			<li>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'gcm-contacts', 'status' => $item ), admin_url( 'admin.php' ) ) ); ?>" class="<?php echo esc_attr( $status === $item ? 'current' : '' ); ?>">
					<?php echo esc_html( $item ? ucwords( str_replace( '_', ' ', $item ) ) : __( 'All', 'giga-class-market' ) ); ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
	<table class="widefat striped gcm-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'From', 'giga-class-market' ); ?></th>
				<th><?php esc_html_e( 'Subject', 'giga-class-market' ); ?></th>
				<th><?php esc_html_e( 'Message', 'giga-class-market' ); ?></th>
				<th><?php esc_html_e( 'Status', 'giga-class-market' ); ?></th>
				<th><?php esc_html_e( 'Created', 'giga-class-market' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'giga-class-market' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $contacts ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No messages found.', 'giga-class-market' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $contacts as $contact ) : ?>
				<tr>
					<td>
						<strong><?php echo esc_html( $contact->full_name ); ?></strong><br />
						<a href="mailto:<?php echo esc_attr( $contact->email ); ?>"><?php echo esc_html( $contact->email ); ?></a><br />
						<?php echo esc_html( $contact->whatsapp ); ?>
					</td>
					<td><?php echo esc_html( $contact->subject ); ?></td>
					<td><?php echo esc_html( wp_trim_words( $contact->message, 24 ) ); ?></td>
					<td><span class="gcm-status gcm-status-<?php echo esc_attr( $contact->status ); ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $contact->status ) ) ); ?></span></td>
					<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $contact->created_at ) ); ?></td>
					<td>
						<select class="gcm-contact-status" data-contact-id="<?php echo esc_attr( $contact->id ); ?>">
							<?php foreach ( array( 'new', 'in_progress', 'contacted', 'resolved' ) as $contact_status ) : ?>
								<option value="<?php echo esc_attr( $contact_status ); ?>" <?php selected( $contact->status, $contact_status ); ?>><?php echo esc_html( ucwords( str_replace( '_', ' ', $contact_status ) ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
