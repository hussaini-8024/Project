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

try {
	$class = GCM_Class_Service::ensure_meeting_links( $class_id );
} catch ( Exception $e ) {
	unset( $e );
	$class = GCM_Class_Service::get( $class_id );
} catch ( Throwable $e ) {
	unset( $e );
	$class = GCM_Class_Service::get( $class_id );
}

if ( is_wp_error( $class ) || ! $class ) {
	$class = GCM_Class_Service::get( $class_id );
}

$target = ( $class && ! is_wp_error( $class ) && ! empty( $class->zoom_join_url ) ) ? (string) $class->zoom_join_url : '';
if ( $class && ! is_wp_error( $class ) && ( current_user_can( 'gcm_teacher_dashboard' ) || current_user_can( 'manage_options' ) ) ) {
	$target = ! empty( $class->zoom_start_url ) ? (string) $class->zoom_start_url : $target;
}

$needs_room = ! $class
	|| empty( $target )
	|| ( class_exists( 'GCM_Meeting_Service' ) && GCM_Meeting_Service::is_site_live_class_url( $target ) )
	|| ( class_exists( 'GCM_Class_Service' ) && ! GCM_Class_Service::meeting_url_is_usable( $target ) );

if ( $class && ! is_wp_error( $class ) && $needs_room && class_exists( 'GCM_Meeting_Service' ) ) {
	$meeting = GCM_Meeting_Service::create_local( $class->title, (int) $class->id, isset( $class->zoom_passcode ) ? (string) $class->zoom_passcode : '' );
	GCM_Class_Service::save_meeting_fields(
		(int) $class->id,
		array(
			'zoom_meeting_id' => $meeting['meeting_id'],
			'zoom_join_url'   => $meeting['join_url'],
			'zoom_start_url'  => $meeting['start_url'],
			'zoom_passcode'   => $meeting['passcode'],
		)
	);
	$target = $meeting['join_url'];
}

if ( $class && ! is_wp_error( $class ) && class_exists( 'GCM_Attendance_Service' ) && 'live' === $class->status ) {
	try {
		GCM_Attendance_Service::record_join( $class_id, get_current_user_id() );
	} catch ( Exception $e ) {
		unset( $e );
	} catch ( Throwable $e ) {
		unset( $e );
	}
}

if ( $target && ( ! class_exists( 'GCM_Meeting_Service' ) || ! GCM_Meeting_Service::is_site_live_class_url( $target ) ) ) {
	wp_redirect( esc_url_raw( $target ), 302 );
	exit;
}

get_header();
echo '<section class="gcm-error-page"><div class="gcm-container"><h1>' . esc_html__( 'Live class unavailable', 'giga-class-market' ) . '</h1>';
echo '<p>' . esc_html__( 'Ask your teacher to start the class again.', 'giga-class-market' ) . '</p></div></section>';
get_footer();
exit;
