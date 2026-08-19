<?php
/**
 * Template Name: GCM Live Class Redirect
 *
 * Safety page: if someone hits /live-class/?class_id=N, repair and redirect to the meeting.
 *
 * @package GigaClassMarket
 */

$class_id = isset( $_GET['class_id'] ) ? absint( $_GET['class_id'] ) : 0;

if ( ! is_user_logged_in() ) {
	wp_safe_redirect( add_query_arg( 'redirect_to', rawurlencode( home_url( '/live-class/?class_id=' . $class_id ) ), gcm_student_login_url() ) );
	exit;
}

if ( ! $class_id || ! class_exists( 'GCM_Class_Service' ) ) {
	wp_safe_redirect( home_url( '/' ) );
	exit;
}

$class = GCM_Class_Service::ensure_meeting_links( $class_id );
if ( is_wp_error( $class ) || empty( $class->zoom_join_url ) ) {
	get_header();
	echo '<section class="gcm-error-page"><div class="gcm-container"><h1>' . esc_html__( 'Live class unavailable', 'giga-class-market' ) . '</h1>';
	echo '<p>' . esc_html__( 'Ask your teacher to start the class again.', 'giga-class-market' ) . '</p></div></section>';
	get_footer();
	exit;
}

if ( class_exists( 'GCM_Attendance_Service' ) && 'live' === $class->status ) {
	GCM_Attendance_Service::record_join( $class_id, get_current_user_id() );
}

$target = $class->zoom_join_url;
if ( current_user_can( 'gcm_teacher_dashboard' ) || current_user_can( 'manage_options' ) ) {
	$target = ! empty( $class->zoom_start_url ) ? $class->zoom_start_url : $class->zoom_join_url;
}

wp_redirect( esc_url_raw( $target ), 302 );
exit;
