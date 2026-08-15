<?php
/**
 * Dashboard view.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap gcm-admin-wrap">
	<h1><?php esc_html_e( 'Giga Class Market Dashboard', 'giga-class-market' ); ?></h1>
	<div class="gcm-stat-grid">
		<?php
		$cards = array(
			'courses'         => __( 'Total Courses', 'giga-class-market' ),
			'students'        => __( 'Students', 'giga-class-market' ),
			'active_students' => __( 'Active Students', 'giga-class-market' ),
			'pending'         => __( 'Pending Verification', 'giga-class-market' ),
			'approved'        => __( 'Approved Payments', 'giga-class-market' ),
			'rejected'        => __( 'Rejected Payments', 'giga-class-market' ),
			'contacts'        => __( 'Contact Messages', 'giga-class-market' ),
			'enrollments'     => __( 'Enrollments', 'giga-class-market' ),
			'completions'     => __( 'Completions', 'giga-class-market' ),
			'certificates'    => __( 'Certificates', 'giga-class-market' ),
			'live_classes'    => __( 'Live Classes', 'giga-class-market' ),
			'revenue'         => __( 'Approved Revenue', 'giga-class-market' ),
		);
		foreach ( $cards as $key => $label ) :
			$value = 'revenue' === $key ? number_format_i18n( (float) $stats[ $key ], 2 ) : number_format_i18n( (int) $stats[ $key ] );
			?>
			<div class="gcm-stat-card">
				<span><?php echo esc_html( $label ); ?></span>
				<strong><?php echo esc_html( $value ); ?></strong>
			</div>
		<?php endforeach; ?>
	</div>
	<div class="gcm-admin-panel">
		<h2><?php esc_html_e( 'Quick Actions', 'giga-class-market' ); ?></h2>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=gcm_course' ) ); ?>"><?php esc_html_e( 'Add Course', 'giga-class-market' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=gcm-payments&status=under_review' ) ); ?>"><?php esc_html_e( 'Review Payments', 'giga-class-market' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=gcm-settings' ) ); ?>"><?php esc_html_e( 'Configure Settings', 'giga-class-market' ); ?></a>
		</p>
	</div>
</div>
