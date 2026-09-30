<?php
/**
 * Coupons admin view.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$courses = class_exists( 'GCM_Course_Service' ) ? GCM_Course_Service::search( array( 'limit' => 200 ) ) : array();
$coupons = class_exists( 'GCM_Coupon_Service' ) ? GCM_Coupon_Service::get_all( 200 ) : array();
?>
<div class="wrap gcm-admin-wrap">
	<h1><?php esc_html_e( 'Coupon Codes', 'giga-class-market' ); ?></h1>
	<p><?php esc_html_e( 'Create discount codes students can apply at payment. You can also set a sale price on each course edit screen.', 'giga-class-market' ); ?></p>

	<div class="gcm-admin-panel" style="max-width:720px;margin-bottom:1.5rem;">
		<h2><?php esc_html_e( 'Create coupon', 'giga-class-market' ); ?></h2>
		<form class="gcm-ajax-form" data-action="gcm_create_coupon">
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'gcm_ajax_nonce' ) ); ?>" />
			<table class="form-table">
				<tr>
					<th><label for="gcm_coupon_code"><?php esc_html_e( 'Code', 'giga-class-market' ); ?></label></th>
					<td><input required class="regular-text" type="text" id="gcm_coupon_code" name="code" placeholder="SAVE20" /></td>
				</tr>
				<tr>
					<th><label for="gcm_coupon_desc"><?php esc_html_e( 'Description', 'giga-class-market' ); ?></label></th>
					<td><input class="regular-text" type="text" id="gcm_coupon_desc" name="description" /></td>
				</tr>
				<tr>
					<th><label for="gcm_coupon_type"><?php esc_html_e( 'Discount type', 'giga-class-market' ); ?></label></th>
					<td>
						<select id="gcm_coupon_type" name="discount_type">
							<option value="percent"><?php esc_html_e( 'Percent (%)', 'giga-class-market' ); ?></option>
							<option value="fixed"><?php esc_html_e( 'Fixed amount', 'giga-class-market' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="gcm_coupon_value"><?php esc_html_e( 'Discount value', 'giga-class-market' ); ?></label></th>
					<td><input required type="number" min="0" step="0.01" id="gcm_coupon_value" name="discount_value" value="10" /></td>
				</tr>
				<tr>
					<th><label for="gcm_coupon_course"><?php esc_html_e( 'Course limit', 'giga-class-market' ); ?></label></th>
					<td>
						<select id="gcm_coupon_course" name="course_id">
							<option value="0"><?php esc_html_e( 'All courses', 'giga-class-market' ); ?></option>
							<?php foreach ( $courses as $course ) : ?>
								<option value="<?php echo esc_attr( $course['id'] ); ?>"><?php echo esc_html( $course['title'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="gcm_coupon_max"><?php esc_html_e( 'Max uses (0 = unlimited)', 'giga-class-market' ); ?></label></th>
					<td><input type="number" min="0" id="gcm_coupon_max" name="max_uses" value="0" /></td>
				</tr>
				<tr>
					<th><label for="gcm_coupon_expires"><?php esc_html_e( 'Expires at', 'giga-class-market' ); ?></label></th>
					<td><input type="datetime-local" id="gcm_coupon_expires" name="expires_at" /></td>
				</tr>
			</table>
			<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Create coupon', 'giga-class-market' ); ?></button></p>
			<div class="gcm-form-message"></div>
		</form>
	</div>

	<table class="widefat striped gcm-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Code', 'giga-class-market' ); ?></th>
				<th><?php esc_html_e( 'Discount', 'giga-class-market' ); ?></th>
				<th><?php esc_html_e( 'Course', 'giga-class-market' ); ?></th>
				<th><?php esc_html_e( 'Uses', 'giga-class-market' ); ?></th>
				<th><?php esc_html_e( 'Status', 'giga-class-market' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'giga-class-market' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $coupons ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No coupons yet.', 'giga-class-market' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $coupons as $coupon ) : ?>
				<?php
				$course_title = ! empty( $coupon->course_id ) ? get_the_title( (int) $coupon->course_id ) : __( 'All courses', 'giga-class-market' );
				$discount     = 'percent' === $coupon->discount_type
					? rtrim( rtrim( number_format( (float) $coupon->discount_value, 2 ), '0' ), '.' ) . '%'
					: number_format_i18n( (float) $coupon->discount_value, 2 );
				$uses         = (int) $coupon->used_count . ( (int) $coupon->max_uses > 0 ? ' / ' . (int) $coupon->max_uses : '' );
				?>
				<tr>
					<td><code><?php echo esc_html( $coupon->code ); ?></code><br /><small><?php echo esc_html( $coupon->description ); ?></small></td>
					<td><?php echo esc_html( $discount ); ?></td>
					<td><?php echo esc_html( $course_title ? $course_title : __( 'Course removed', 'giga-class-market' ) ); ?></td>
					<td><?php echo esc_html( $uses ); ?></td>
					<td><?php echo esc_html( $coupon->is_active ? __( 'Active', 'giga-class-market' ) : __( 'Inactive', 'giga-class-market' ) ); ?></td>
					<td>
						<button type="button" class="button gcm-ajax-button" data-action="gcm_toggle_coupon" data-coupon-id="<?php echo esc_attr( $coupon->id ); ?>">
							<?php echo esc_html( $coupon->is_active ? __( 'Deactivate', 'giga-class-market' ) : __( 'Activate', 'giga-class-market' ) ); ?>
						</button>
						<button type="button" class="button gcm-ajax-button" data-action="gcm_delete_coupon" data-coupon-id="<?php echo esc_attr( $coupon->id ); ?>">
							<?php esc_html_e( 'Delete', 'giga-class-market' ); ?>
						</button>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
