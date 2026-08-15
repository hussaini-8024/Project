<?php
/**
 * Analytics admin view.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cards = array(
	'enrollments'      => __( 'Enrollments', 'giga-class-market' ),
	'completions'      => __( 'Completions', 'giga-class-market' ),
	'revenue'          => __( 'Approved revenue', 'giga-class-market' ),
	'certificates'     => __( 'Certificates issued', 'giga-class-market' ),
	'upcoming_classes' => __( 'Upcoming / live classes', 'giga-class-market' ),
	'pending'          => __( 'Pending payments', 'giga-class-market' ),
	'live_classes'     => __( 'Classes live now', 'giga-class-market' ),
	'active_students'  => __( 'Active students', 'giga-class-market' ),
);
?>
<div class="wrap gcm-admin-wrap">
	<h1><?php esc_html_e( 'Analytics', 'giga-class-market' ); ?></h1>
	<p><?php esc_html_e( 'Enrollment, revenue, completions, certificates, and live-class overview.', 'giga-class-market' ); ?></p>
	<div class="gcm-stat-grid">
		<?php foreach ( $cards as $key => $label ) : ?>
			<?php
			$raw   = isset( $stats[ $key ] ) ? $stats[ $key ] : 0;
			$value = 'revenue' === $key ? number_format_i18n( (float) $raw, 2 ) : number_format_i18n( (int) $raw );
			?>
			<div class="gcm-stat-card">
				<span><?php echo esc_html( $label ); ?></span>
				<strong><?php echo esc_html( $value ); ?></strong>
			</div>
		<?php endforeach; ?>
	</div>
	<div class="gcm-admin-panel">
		<h2><?php esc_html_e( 'Quick links', 'giga-class-market' ); ?></h2>
		<p>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=gcm-payments&status=under_review' ) ); ?>"><?php esc_html_e( 'Review pending payments', 'giga-class-market' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=gcm-classes' ) ); ?>"><?php esc_html_e( 'Manage live classes', 'giga-class-market' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=gcm-students' ) ); ?>"><?php esc_html_e( 'Students & certificates', 'giga-class-market' ); ?></a>
		</p>
	</div>
</div>
