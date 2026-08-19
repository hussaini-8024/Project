<?php
/**
 * Zoom meeting creation (Server-to-Server OAuth) with working Jitsi fallback.
 *
 * @package GigaClassMarket
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class GCM_Zoom_Service
 */
class GCM_Zoom_Service {

	/**
	 * Create a live meeting for a class.
	 * Uses Zoom when credentials are set; otherwise a real Jitsi room (never a broken site 404).
	 *
	 * @param string $topic Topic.
	 * @param string $start_time MySQL datetime (site timezone).
	 * @param int    $duration_minutes Duration.
	 * @param int    $class_id Optional class ID for unique room naming.
	 * @param string $host_email_override Prefer this Zoom licensed user (per-teacher). Falls back to Settings host email.
	 * @return array{join_url:string,start_url:string,meeting_id:string,provider:string}|WP_Error
	 */
	public static function create_meeting( $topic, $start_time = '', $duration_minutes = 60, $class_id = 0, $host_email_override = '' ) {
		$settings      = gcm_get_setting( 'zoom', array() );
		$account_id    = isset( $settings['account_id'] ) ? trim( (string) $settings['account_id'] ) : '';
		$client_id     = isset( $settings['client_id'] ) ? trim( (string) $settings['client_id'] ) : '';
		$client_secret = isset( $settings['client_secret'] ) ? trim( (string) $settings['client_secret'] ) : '';
		$host_email    = sanitize_email( (string) $host_email_override );
		if ( ! is_email( $host_email ) ) {
			$host_email = isset( $settings['host_email'] ) ? sanitize_email( (string) $settings['host_email'] ) : '';
		}

		if ( '' !== $account_id && '' !== $client_id && '' !== $client_secret ) {
			$zoom = self::create_zoom_meeting( $topic, $start_time, $duration_minutes, $account_id, $client_id, $client_secret, $host_email );
			if ( ! is_wp_error( $zoom ) ) {
				return $zoom;
			}
			// Fall through to Jitsi so Start Class never sends users to a 404.
		}

		return self::create_jitsi_meeting( $topic, $class_id );
	}

	/**
	 * Working video room when Zoom is not configured / fails.
	 *
	 * @param string $topic Topic.
	 * @param int    $class_id Class ID.
	 * @return array
	 */
	public static function create_jitsi_meeting( $topic, $class_id = 0 ) {
		$slug = 'GigaClassMarket';
		if ( $class_id ) {
			$slug .= '-' . absint( $class_id );
		} else {
			$slug .= '-' . wp_generate_password( 8, false, false );
		}
		$slug = preg_replace( '/[^A-Za-z0-9\-]/', '', $slug );
		$url  = 'https://meet.jit.si/' . $slug;

		return array(
			'join_url'   => $url,
			'start_url'  => $url,
			'meeting_id' => 'jitsi-' . $slug,
			'provider'   => 'jitsi',
		);
	}

	/**
	 * Whether a stored URL is a usable external meeting (Zoom/Jitsi), not a dead site path.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function is_usable_meeting_url( $url ) {
		$url = (string) $url;
		if ( '' === $url ) {
			return false;
		}
		if ( false !== strpos( $url, '/live-class' ) ) {
			return false;
		}
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $host ) {
			return false;
		}
		$host = strtolower( $host );
		return ( false !== strpos( $host, 'zoom.us' ) )
			|| ( false !== strpos( $host, 'zoom.com' ) )
			|| ( false !== strpos( $host, 'jit.si' ) )
			|| ( false !== strpos( $host, 'jitsi' ) );
	}

	/**
	 * Create Zoom meeting via Server-to-Server OAuth.
	 *
	 * @param string $topic Topic.
	 * @param string $start_time Start.
	 * @param int    $duration_minutes Duration.
	 * @param string $account_id Account ID.
	 * @param string $client_id Client ID.
	 * @param string $client_secret Client secret.
	 * @param string $host_email Optional host email.
	 * @return array|WP_Error
	 */
	private static function create_zoom_meeting( $topic, $start_time, $duration_minutes, $account_id, $client_id, $client_secret, $host_email = '' ) {
		$token = self::get_access_token( $account_id, $client_id, $client_secret );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$user_id = self::resolve_zoom_user_id( $token, $host_email );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$tz    = wp_timezone();
		$start = $start_time ? $start_time : current_time( 'mysql' );
		try {
			$dt = new DateTime( $start, $tz );
		} catch ( Exception $e ) {
			$dt = new DateTime( 'now', $tz );
		}

		$body = array(
			'topic'      => $topic ? $topic : 'Giga Class Market Live Class',
			'type'       => 2,
			'start_time' => $dt->format( 'Y-m-d\TH:i:s' ),
			'duration'   => max( 15, (int) $duration_minutes ),
			'timezone'   => wp_timezone_string(),
			'settings'   => array(
				'join_before_host' => true,
				'waiting_room'     => false,
				'mute_upon_entry'  => true,
				'approval_type'    => 2,
			),
		);

		$response = wp_remote_post(
			'https://api.zoom.us/v2/users/' . rawurlencode( $user_id ) . '/meetings',
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
			'provider'   => 'zoom',
		);
	}

	/**
	 * Resolve Zoom user id (email or first active user). Prefer host_email from settings.
	 *
	 * @param string $token Access token.
	 * @param string $host_email Host email.
	 * @return string|WP_Error
	 */
	private static function resolve_zoom_user_id( $token, $host_email = '' ) {
		if ( is_email( $host_email ) ) {
			return $host_email;
		}

		$response = wp_remote_get(
			'https://api.zoom.us/v2/users?status=active&page_size=1',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 200 && $code < 300 && ! empty( $data['users'][0]['id'] ) ) {
			return (string) $data['users'][0]['id'];
		}
		if ( $code >= 200 && $code < 300 && ! empty( $data['users'][0]['email'] ) ) {
			return (string) $data['users'][0]['email'];
		}

		return new WP_Error(
			'gcm_zoom_no_host',
			__( 'Zoom is connected but no host user was found. Add Host Email in GCM → Settings → Zoom.', 'giga-class-market' )
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
