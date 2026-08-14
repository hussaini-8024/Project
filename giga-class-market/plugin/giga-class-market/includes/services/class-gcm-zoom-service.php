<?php
/**
 * Zoom Server-to-Server OAuth meeting creation.
 *
 * @package GigaClassMarket
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class GCM_Zoom_Service
 */
class GCM_Zoom_Service {

	/**
	 * Create a Zoom meeting. Falls back to placeholder join URL if Zoom is not configured.
	 *
	 * @param string $topic Topic.
	 * @param string $start_time MySQL datetime (site timezone).
	 * @param int    $duration_minutes Duration.
	 * @return array{join_url:string,start_url:string,meeting_id:string}|WP_Error
	 */
	public static function create_meeting( $topic, $start_time = '', $duration_minutes = 60 ) {
		$settings = gcm_get_setting( 'zoom', array() );
		$account_id    = isset( $settings['account_id'] ) ? trim( (string) $settings['account_id'] ) : '';
		$client_id     = isset( $settings['client_id'] ) ? trim( (string) $settings['client_id'] ) : '';
		$client_secret = isset( $settings['client_secret'] ) ? trim( (string) $settings['client_secret'] ) : '';

		if ( '' === $account_id || '' === $client_id || '' === $client_secret ) {
			$token = wp_generate_password( 12, false );
			$fallback = home_url( '/live-class/?token=' . rawurlencode( $token ) );
			return array(
				'join_url'   => $fallback,
				'start_url'  => $fallback,
				'meeting_id' => 'local-' . $token,
			);
		}

		$token = self::get_access_token( $account_id, $client_id, $client_secret );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$tz = wp_timezone();
		$start = $start_time ? $start_time : current_time( 'mysql' );
		try {
			$dt = new DateTime( $start, $tz );
		} catch ( Exception $e ) {
			$dt = new DateTime( 'now', $tz );
		}

		$body = array(
			'topic'      => $topic,
			'type'       => 2,
			'start_time' => $dt->format( 'Y-m-d\TH:i:s' ),
			'duration'   => max( 15, (int) $duration_minutes ),
			'timezone'   => wp_timezone_string(),
			'settings'   => array(
				'join_before_host'  => true,
				'waiting_room'      => false,
				'mute_upon_entry'   => true,
				'approval_type'     => 2,
			),
		);

		$response = wp_remote_post(
			'https://api.zoom.us/v2/users/me/meetings',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 || empty( $data['join_url'] ) ) {
			$message = isset( $data['message'] ) ? (string) $data['message'] : __( 'Could not create Zoom meeting.', 'giga-class-market' );
			return new WP_Error( 'gcm_zoom_create_failed', $message );
		}

		return array(
			'join_url'   => (string) $data['join_url'],
			'start_url'  => isset( $data['start_url'] ) ? (string) $data['start_url'] : (string) $data['join_url'],
			'meeting_id' => isset( $data['id'] ) ? (string) $data['id'] : '',
		);
	}

	/**
	 * Get Zoom access token (cached briefly).
	 *
	 * @param string $account_id Account ID.
	 * @param string $client_id Client ID.
	 * @param string $client_secret Client secret.
	 * @return string|WP_Error
	 */
	private static function get_access_token( $account_id, $client_id, $client_secret ) {
		$cache_key = 'gcm_zoom_token_' . md5( $account_id . $client_id );
		$cached    = get_transient( $cache_key );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$response = wp_remote_post(
			'https://zoom.us/oauth/token?grant_type=account_credentials&account_id=' . rawurlencode( $account_id ),
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 || empty( $data['access_token'] ) ) {
			$message = isset( $data['reason'] ) ? (string) $data['reason'] : __( 'Zoom authentication failed. Check Account ID, Client ID, and Client Secret in GCM Settings.', 'giga-class-market' );
			return new WP_Error( 'gcm_zoom_auth_failed', $message );
		}

		$token = (string) $data['access_token'];
		$ttl   = isset( $data['expires_in'] ) ? max( 60, (int) $data['expires_in'] - 60 ) : 3300;
		set_transient( $cache_key, $token, $ttl );

		return $token;
	}
}
