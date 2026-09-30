<?php
/**
 * Project-owned live meeting helper.
 *
 * Zoom is used when it works. If Zoom (or WordPress helpers) fail, this class
 * always returns a Giga Class Market meeting so hosting never errors.
 *
 * @package GigaClassMarket
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class GCM_Meeting_Service
 */
class GCM_Meeting_Service {

	/**
	 * Create meeting credentials for a class. Never throws, never returns WP_Error.
	 *
	 * @param string $topic Topic.
	 * @param string $start_time Start datetime.
	 * @param int    $duration_minutes Duration.
	 * @param int    $class_id Class ID.
	 * @param string $passcode Optional passcode.
	 * @return array{join_url:string,start_url:string,meeting_id:string,passcode:string,provider:string}
	 */
	public static function create( $topic, $start_time = '', $duration_minutes = 60, $class_id = 0, $passcode = '' ) {
		$passcode = self::resolve_passcode( $passcode );

		if ( class_exists( 'GCM_Zoom_Service' ) ) {
			try {
				$meeting = GCM_Zoom_Service::create_meeting( $topic, $start_time, $duration_minutes, $class_id, $passcode );
				if ( ! is_wp_error( $meeting ) && self::is_usable( isset( $meeting['join_url'] ) ? $meeting['join_url'] : '' ) ) {
					return self::normalize( $meeting, $class_id, $passcode );
				}
			} catch ( Exception $e ) {
				unset( $e );
			} catch ( Throwable $e ) {
				unset( $e );
			}
		}

		return self::create_local( $topic, $class_id, $passcode );
	}

	/**
	 * In-project meeting room (Jitsi URL built here; never a site /live-class 404).
	 *
	 * @param string $topic Topic.
	 * @param int    $class_id Class ID.
	 * @param string $passcode Passcode.
	 * @return array
	 */
	public static function create_local( $topic, $class_id = 0, $passcode = '' ) {
		$passcode = self::resolve_passcode( $passcode );

		if ( class_exists( 'GCM_Zoom_Service' ) ) {
			try {
				$jitsi = GCM_Zoom_Service::create_jitsi_meeting( $topic, $class_id, $passcode );
				if ( self::is_usable( isset( $jitsi['join_url'] ) ? $jitsi['join_url'] : '' ) ) {
					return self::normalize( $jitsi, $class_id, $passcode );
				}
			} catch ( Exception $e ) {
				unset( $e );
			} catch ( Throwable $e ) {
				unset( $e );
			}
		}

		return self::create_jitsi( $topic, $class_id, $passcode );
	}

	/**
	 * Project-owned Jitsi room builder (does not depend on Zoom).
	 *
	 * @param string $topic Topic.
	 * @param int    $class_id Class ID.
	 * @param string $passcode Passcode.
	 * @return array
	 */
	public static function create_jitsi( $topic, $class_id = 0, $passcode = '' ) {
		unset( $topic );
		$passcode = self::resolve_passcode( $passcode );
		$slug     = 'GigaClassMarket';
		if ( $class_id ) {
			$slug .= '-' . absint( $class_id );
		} else {
			$rand = function_exists( 'wp_generate_password' ) ? wp_generate_password( 8, false, false ) : (string) wp_rand( 100000, 999999 );
			$slug .= '-' . $rand;
		}
		$slug = preg_replace( '/[^A-Za-z0-9\-]/', '', (string) $slug );
		if ( '' === $slug ) {
			$slug = 'GigaClassMarket-' . wp_rand( 1000, 9999 );
		}
		$url = 'https://meet.jit.si/' . $slug;

		return array(
			'join_url'   => $url,
			'start_url'  => $url,
			'meeting_id' => 'jitsi-' . $slug,
			'passcode'   => $passcode,
			'provider'   => 'jitsi',
		);
	}

	/**
	 * Whether a URL is the site live-class page (not a video room).
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function is_site_live_class_url( $url ) {
		$url = strtolower( (string) $url );
		return ( '' !== $url && false !== strpos( $url, '/live-class' ) );
	}

	/**
	 * Whether a stored URL can open a real meeting (Zoom/Jitsi), not a dead site path.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function is_usable( $url ) {
		$url = (string) $url;
		if ( '' === $url || self::is_site_live_class_url( $url ) ) {
			return false;
		}
		if ( class_exists( 'GCM_Zoom_Service' ) && method_exists( 'GCM_Zoom_Service', 'is_usable_meeting_url' ) ) {
			try {
				return (bool) GCM_Zoom_Service::is_usable_meeting_url( $url );
			} catch ( Exception $e ) {
				unset( $e );
			} catch ( Throwable $e ) {
				unset( $e );
			}
		}

		$host = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url, PHP_URL_HOST ) : '';
		$host = strtolower( (string) $host );
		if ( '' === $host ) {
			return false;
		}
		return ( false !== strpos( $host, 'zoom.us' ) )
			|| ( false !== strpos( $host, 'zoom.com' ) )
			|| ( false !== strpos( $host, 'jit.si' ) )
			|| ( false !== strpos( $host, 'jitsi' ) );
	}

	/**
	 * Normalize a meeting array with required keys.
	 *
	 * @param array  $meeting Raw meeting.
	 * @param int    $class_id Class ID.
	 * @param string $passcode Fallback passcode.
	 * @return array
	 */
	public static function normalize( $meeting, $class_id = 0, $passcode = '' ) {
		$meeting  = is_array( $meeting ) ? $meeting : array();
		$passcode = self::resolve_passcode( isset( $meeting['passcode'] ) ? $meeting['passcode'] : $passcode );
		$join     = isset( $meeting['join_url'] ) ? (string) $meeting['join_url'] : '';
		$start    = isset( $meeting['start_url'] ) ? (string) $meeting['start_url'] : $join;
		$id       = isset( $meeting['meeting_id'] ) ? (string) $meeting['meeting_id'] : '';
		$provider = isset( $meeting['provider'] ) ? (string) $meeting['provider'] : '';

		if ( '' === $id ) {
			$id = $class_id ? 'GCM' . absint( $class_id ) : 'GCM' . wp_rand( 1000, 9999 );
		}

		if ( ! self::is_usable( $join ) ) {
			$local    = self::create_jitsi( '', $class_id, $passcode );
			$join     = $local['join_url'];
			$start    = $local['start_url'];
			$id       = $local['meeting_id'];
			$provider = $local['provider'];
		}

		if ( '' === $provider ) {
			$provider = ( false !== strpos( strtolower( $join ), 'zoom' ) ) ? 'zoom' : 'jitsi';
		}

		return array(
			'join_url'   => $join,
			'start_url'  => $start ? $start : $join,
			'meeting_id' => $id,
			'passcode'   => $passcode,
			'provider'   => $provider,
		);
	}

	/**
	 * Passcode helper (uses Zoom rules when that class exists).
	 *
	 * @param string $passcode Raw passcode.
	 * @return string
	 */
	public static function resolve_passcode( $passcode ) {
		if ( class_exists( 'GCM_Zoom_Service' ) && method_exists( 'GCM_Zoom_Service', 'resolve_passcode' ) ) {
			try {
				$resolved = GCM_Zoom_Service::resolve_passcode( $passcode );
				if ( is_string( $resolved ) && strlen( $resolved ) >= 4 ) {
					return $resolved;
				}
			} catch ( Exception $e ) {
				unset( $e );
			} catch ( Throwable $e ) {
				unset( $e );
			}
		}

		$clean = preg_replace( '/[^A-Za-z0-9@\-\_\*]/', '', (string) $passcode );
		$clean = substr( (string) $clean, 0, 10 );
		if ( strlen( $clean ) >= 4 ) {
			return $clean;
		}

		$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		$out      = '';
		$max      = strlen( $alphabet ) - 1;
		for ( $i = 0; $i < 6; $i++ ) {
			$out .= $alphabet[ wp_rand( 0, $max ) ];
		}
		return $out;
	}
}
