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
	 * @param string $passcode Optional custom meeting passcode.
	 * @return array{join_url:string,start_url:string,meeting_id:string,passcode:string,provider:string}|WP_Error
	 */
	public static function create_meeting( $topic, $start_time = '', $duration_minutes = 60, $class_id = 0, $passcode = '' ) {
		$settings      = gcm_get_setting( 'zoom', array() );
		$account_id    = isset( $settings['account_id'] ) ? trim( (string) $settings['account_id'] ) : '';
		$client_id     = isset( $settings['client_id'] ) ? trim( (string) $settings['client_id'] ) : '';
		$client_secret = isset( $settings['client_secret'] ) ? trim( (string) $settings['client_secret'] ) : '';
		$host_email    = isset( $settings['host_email'] ) ? sanitize_email( (string) $settings['host_email'] ) : '';
		$passcode      = self::resolve_passcode( $passcode );

		if ( '' !== $account_id && '' !== $client_id && '' !== $client_secret ) {
			$zoom = self::create_zoom_meeting( $topic, $start_time, $duration_minutes, $account_id, $client_id, $client_secret, $host_email, $passcode );
			if ( ! is_wp_error( $zoom ) ) {
				return $zoom;
			}
			// Fall through to Jitsi so Start Class never sends users to a 404.
		}

		return self::create_jitsi_meeting( $topic, $class_id, $passcode );
	}

	/**
	 * Working video room when Zoom is not configured / fails.
	 *
	 * @param string $topic Topic.
	 * @param int    $class_id Class ID.
	 * @param string $passcode Optional passcode (shown on invitations; meet.jit.si cannot lock it).
	 * @return array
	 */
	public static function create_jitsi_meeting( $topic, $class_id = 0, $passcode = '' ) {
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
			'passcode'   => self::resolve_passcode( $passcode ),
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
	 * Whether a stored meeting id is a Zoom numeric id (not Jitsi).
	 *
	 * @param string $meeting_id Meeting id.
	 * @return bool
	 */
	public static function is_zoom_meeting_id( $meeting_id ) {
		$id = preg_replace( '/\s+/', '', (string) $meeting_id );
		return (bool) preg_match( '/^\d{9,15}$/', $id );
	}

	/**
	 * Sanitize a Zoom-compatible passcode (4–10 of letters, numbers, @ - _ *).
	 *
	 * @param string $passcode Raw passcode.
	 * @return string
	 */
	public static function sanitize_passcode( $passcode ) {
		$passcode = preg_replace( '/[^A-Za-z0-9@\-\_\*]/', '', (string) $passcode );
		return substr( (string) $passcode, 0, 10 );
	}

	/**
	 * Generate a 6-character meeting passcode.
	 *
	 * @return string
	 */
	public static function generate_passcode() {
		$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		$out      = '';
		$max      = strlen( $alphabet ) - 1;
		for ( $i = 0; $i < 6; $i++ ) {
			$out .= $alphabet[ wp_rand( 0, $max ) ];
		}
		return $out;
	}

	/**
	 * Use a custom passcode when valid, otherwise generate one.
	 *
	 * @param string $passcode Requested passcode.
	 * @return string
	 */
	public static function resolve_passcode( $passcode ) {
		$clean = self::sanitize_passcode( $passcode );
		if ( strlen( $clean ) >= 4 ) {
			return $clean;
		}
		return self::generate_passcode();
	}

	/**
	 * Display-friendly meeting ID (Zoom grouped digits, Jitsi room name).
	 *
	 * @param string $meeting_id Stored id.
	 * @return string
	 */
	public static function format_meeting_id( $meeting_id ) {
		$id = trim( (string) $meeting_id );
		if ( 0 === strpos( $id, 'jitsi-' ) ) {
			return substr( $id, 6 );
		}
		$digits = preg_replace( '/\D+/', '', $id );
		if ( strlen( $digits ) >= 9 ) {
			return trim( chunk_split( $digits, 3, ' ' ) );
		}
		return $id;
	}

	/**
	 * Update the passcode on an existing Zoom meeting.
	 *
	 * @param string $meeting_id Zoom meeting id.
	 * @param string $passcode New passcode.
	 * @return array{join_url?:string,start_url?:string,passcode:string}|WP_Error
	 */
	public static function update_meeting_passcode( $meeting_id, $passcode ) {
		$passcode   = self::resolve_passcode( $passcode );
		$meeting_id = preg_replace( '/\D+/', '', (string) $meeting_id );
		if ( '' === $meeting_id ) {
			return new WP_Error( 'gcm_invalid_meeting', __( 'This meeting cannot update a Zoom passcode.', 'giga-class-market' ) );
		}

		$settings      = gcm_get_setting( 'zoom', array() );
		$account_id    = isset( $settings['account_id'] ) ? trim( (string) $settings['account_id'] ) : '';
		$client_id     = isset( $settings['client_id'] ) ? trim( (string) $settings['client_id'] ) : '';
		$client_secret = isset( $settings['client_secret'] ) ? trim( (string) $settings['client_secret'] ) : '';

		if ( '' === $account_id || '' === $client_id || '' === $client_secret ) {
			return array( 'passcode' => $passcode );
		}

		$token = self::get_access_token( $account_id, $client_id, $client_secret );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$response = wp_remote_request(
			'https://api.zoom.us/v2/meetings/' . rawurlencode( $meeting_id ),
			array(
				'method'  => 'PATCH',
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'password' => $passcode,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$data    = json_decode( wp_remote_retrieve_body( $response ), true );
			$message = isset( $data['message'] ) ? (string) $data['message'] : __( 'Could not update the Zoom passcode.', 'giga-class-market' );
			return new WP_Error( 'gcm_zoom_update_failed', $message );
		}

		$join_url = '';
		$get      = wp_remote_get(
			'https://api.zoom.us/v2/meetings/' . rawurlencode( $meeting_id ),
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
				),
			)
		);
		if ( ! is_wp_error( $get ) ) {
			$payload = json_decode( wp_remote_retrieve_body( $get ), true );
			if ( is_array( $payload ) && ! empty( $payload['join_url'] ) ) {
				$join_url = (string) $payload['join_url'];
			}
			if ( is_array( $payload ) && ! empty( $payload['password'] ) ) {
				$passcode = (string) $payload['password'];
			}
		}

		$out = array( 'passcode' => $passcode );
		if ( $join_url ) {
			$out['join_url'] = $join_url;
		}
		return $out;
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
	 * @param string $passcode Meeting passcode.
	 * @return array|WP_Error
	 */
	private static function create_zoom_meeting( $topic, $start_time, $duration_minutes, $account_id, $client_id, $client_secret, $host_email = '', $passcode = '' ) {
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

		$passcode = self::resolve_passcode( $passcode );

		$body = array(
			'topic'      => $topic ? $topic : 'Giga Class Market Live Class',
			'type'       => 2,
			'start_time' => $dt->format( 'Y-m-d\TH:i:s' ),
			'duration'   => max( 15, (int) $duration_minutes ),
			'timezone'   => wp_timezone_string(),
			'password'   => $passcode,
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

		$actual_passcode = ! empty( $data['password'] ) ? (string) $data['password'] : $passcode;

		return array(
			'join_url'   => (string) $data['join_url'],
			'start_url'  => isset( $data['start_url'] ) ? (string) $data['start_url'] : (string) $data['join_url'],
			'meeting_id' => isset( $data['id'] ) ? (string) $data['id'] : '',
			'passcode'   => $actual_passcode,
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
