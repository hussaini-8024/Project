<?php
/**
 * Settings service.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages plugin settings.
 */
class GCM_Settings_Service {

	/**
	 * Get default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'company'  => array(
				'name'      => 'Giga Class Market',
				'email'     => 'Official@gigaclassmarket.com',
				'phone'     => '03288966951',
				'whatsapp'  => '+966509136037',
				'address'   => '',
				'hours'     => 'Mon–Sat, 9:00 AM – 6:00 PM',
				'facebook'  => '',
				'instagram' => '',
				'linkedin'  => '',
				'youtube'   => '',
			),
			'payment'  => array(
				'methods' => array(
					'Bank'     => array(
						'enabled'      => 1,
						'account_name' => 'Giga Class Market',
						'account_no'   => '',
						'instructions' => 'Transfer the exact course fee and keep your transaction ID.',
					),
					'JazzCash' => array(
						'enabled'      => 1,
						'account_name' => 'Giga Class Market',
						'account_no'   => '',
						'instructions' => 'Send payment via JazzCash and note the transaction ID.',
					),
					'Easypaisa' => array(
						'enabled'      => 1,
						'account_name' => 'Giga Class Market',
						'account_no'   => '',
						'instructions' => 'Send payment via Easypaisa and note the transaction ID.',
					),
				),
			),
			'website'  => array(
				'theme_color'         => '#0d3b45',
				'accent_color'        => '#e0a045',
				'student_page_slug'   => 'student-dashboard',
				'currency_symbol'     => 'PKR ',
				'community_whatsapp'  => '',
				'popup_enabled'       => 0,
				'popup_image_id'      => 0,
				'popup_link_url'      => '',
			),
			'course'   => array(
				'featured_count'   => 3,
				'default_duration' => '',
				'default_rating'   => '5.0',
				'reminder_hours'   => 2,
			),
			'security' => array(
				'default_password' => 'Student@giga',
				'max_upload_mb'    => 5,
			),
			'zoom'     => array(
				'account_id'    => '',
				'client_id'     => '',
				'client_secret' => '',
				'host_email'    => '',
			),
		);
	}

	/**
	 * Get merged settings.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$settings = get_option( 'gcm_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return self::recursive_parse_args( $settings, self::defaults() );
	}

	/**
	 * Get a single settings section.
	 *
	 * @param string $section Section key.
	 * @return array
	 */
	public static function get_section( $section ) {
		$settings = self::get_settings();
		return isset( $settings[ $section ] ) && is_array( $settings[ $section ] ) ? $settings[ $section ] : array();
	}

	/**
	 * Update all settings.
	 *
	 * @param array $settings Settings.
	 * @return bool
	 */
	public static function update_settings( $settings ) {
		$settings = self::sanitize_settings( $settings );
		$settings = self::recursive_parse_args( $settings, self::defaults() );
		update_option( 'gcm_settings', $settings, false );
		return true;
	}

	/**
	 * Update a single section.
	 *
	 * @param string $section Section.
	 * @param array  $values Values.
	 * @return bool
	 */
	public static function update_section( $section, $values ) {
		$settings             = self::get_settings();
		$settings[ $section ] = $values;
		return self::update_settings( $settings );
	}

	/**
	 * Get default student password.
	 *
	 * @return string
	 */
	public static function get_default_password() {
		$settings = self::get_settings();
		$password = isset( $settings['security']['default_password'] ) ? (string) $settings['security']['default_password'] : '';
		return $password ? $password : 'Student@giga';
	}

	/**
	 * Get enabled payment methods.
	 *
	 * @return array
	 */
	public static function get_payment_methods() {
		$settings = self::get_settings();
		$methods  = isset( $settings['payment']['methods'] ) && is_array( $settings['payment']['methods'] ) ? $settings['payment']['methods'] : array();

		return array_filter(
			$methods,
			function( $method ) {
				return ! empty( $method['enabled'] );
			}
		);
	}

	/**
	 * Get company WhatsApp number.
	 *
	 * @return string
	 */
	public static function get_company_whatsapp() {
		$settings = self::get_settings();
		return isset( $settings['company']['whatsapp'] ) ? (string) $settings['company']['whatsapp'] : '';
	}

	/**
	 * Outgoing From email address for site mail.
	 *
	 * @return string
	 */
	public static function get_from_email() {
		$settings = self::get_settings();
		$email    = isset( $settings['company']['email'] ) ? sanitize_email( $settings['company']['email'] ) : '';
		if ( ! is_email( $email ) ) {
			$email = 'Official@gigaclassmarket.com';
		}
		return $email;
	}

	/**
	 * Outgoing From display name for site mail.
	 *
	 * @return string
	 */
	public static function get_from_name() {
		$settings = self::get_settings();
		$name     = isset( $settings['company']['name'] ) ? sanitize_text_field( $settings['company']['name'] ) : '';
		return $name ? $name : 'Giga Class Market';
	}

	/**
	 * Sanitize settings recursively with known business fields.
	 *
	 * @param array $settings Raw settings.
	 * @return array
	 */
	private static function sanitize_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();
		$clean    = self::defaults();

		if ( isset( $settings['company'] ) && is_array( $settings['company'] ) ) {
			$clean['company']['name']      = sanitize_text_field( $settings['company']['name'] ?? $clean['company']['name'] );
			$clean['company']['email']     = sanitize_email( $settings['company']['email'] ?? $clean['company']['email'] );
			$clean['company']['phone']     = sanitize_text_field( $settings['company']['phone'] ?? '' );
			$clean['company']['whatsapp']  = sanitize_text_field( $settings['company']['whatsapp'] ?? '' );
			$clean['company']['address']   = sanitize_textarea_field( $settings['company']['address'] ?? '' );
			$clean['company']['hours']     = sanitize_text_field( $settings['company']['hours'] ?? ( $clean['company']['hours'] ?? '' ) );
			$clean['company']['facebook']  = esc_url_raw( $settings['company']['facebook'] ?? '' );
			$clean['company']['instagram'] = esc_url_raw( $settings['company']['instagram'] ?? '' );
			$clean['company']['linkedin']  = esc_url_raw( $settings['company']['linkedin'] ?? '' );
			$clean['company']['youtube']   = esc_url_raw( $settings['company']['youtube'] ?? '' );
		}

		if ( isset( $settings['payment']['methods'] ) && is_array( $settings['payment']['methods'] ) ) {
			$clean['payment']['methods'] = array();
			foreach ( $settings['payment']['methods'] as $name => $method ) {
				if ( ! is_array( $method ) ) {
					continue;
				}
				$method_name = sanitize_text_field( $name );
				if ( '' === $method_name ) {
					continue;
				}
				$clean['payment']['methods'][ $method_name ] = array(
					'enabled'      => ! empty( $method['enabled'] ) ? 1 : 0,
					'account_name' => sanitize_text_field( $method['account_name'] ?? '' ),
					'account_no'   => sanitize_text_field( $method['account_no'] ?? '' ),
					'instructions' => sanitize_textarea_field( $method['instructions'] ?? '' ),
				);
			}
		}

		if ( isset( $settings['website'] ) && is_array( $settings['website'] ) ) {
			$clean['website']['theme_color']         = sanitize_hex_color( $settings['website']['theme_color'] ?? '' ) ?: '#0d3b45';
			$clean['website']['accent_color']        = sanitize_hex_color( $settings['website']['accent_color'] ?? '' ) ?: '#e0a045';
			$clean['website']['student_page_slug']   = sanitize_title( $settings['website']['student_page_slug'] ?? 'student-dashboard' );
			$clean['website']['currency_symbol']     = sanitize_text_field( $settings['website']['currency_symbol'] ?? ( $clean['website']['currency_symbol'] ?? 'PKR ' ) );
			$clean['website']['community_whatsapp']  = sanitize_text_field( $settings['website']['community_whatsapp'] ?? '' );
			$clean['website']['popup_enabled']       = ! empty( $settings['website']['popup_enabled'] ) ? 1 : 0;
			$clean['website']['popup_image_id']      = absint( $settings['website']['popup_image_id'] ?? 0 );
			$clean['website']['popup_link_url']      = esc_url_raw( $settings['website']['popup_link_url'] ?? '' );
		}

		if ( isset( $settings['course'] ) && is_array( $settings['course'] ) ) {
			$clean['course']['featured_count']   = min( 3, max( 1, absint( $settings['course']['featured_count'] ?? 3 ) ) );
			$clean['course']['default_duration'] = sanitize_text_field( $settings['course']['default_duration'] ?? '' );
			$clean['course']['default_rating']   = (string) min( 5, max( 0, (float) ( $settings['course']['default_rating'] ?? 5 ) ) );
			$clean['course']['reminder_hours']   = min( 72, max( 1, absint( $settings['course']['reminder_hours'] ?? 2 ) ) );
		}

		if ( isset( $settings['security'] ) && is_array( $settings['security'] ) ) {
			$clean['security']['default_password'] = sanitize_text_field( $settings['security']['default_password'] ?? 'Student@giga' );
			$clean['security']['max_upload_mb']    = max( 1, absint( $settings['security']['max_upload_mb'] ?? 5 ) );
		}

		if ( isset( $settings['zoom'] ) && is_array( $settings['zoom'] ) ) {
			$clean['zoom']['account_id']    = sanitize_text_field( $settings['zoom']['account_id'] ?? '' );
			$clean['zoom']['client_id']     = sanitize_text_field( $settings['zoom']['client_id'] ?? '' );
			$clean['zoom']['client_secret'] = sanitize_text_field( $settings['zoom']['client_secret'] ?? '' );
			$clean['zoom']['host_email']    = sanitize_email( $settings['zoom']['host_email'] ?? '' );
		}

		return $clean;
	}

	/**
	 * Recursive wp_parse_args.
	 *
	 * @param array $args Args.
	 * @param array $defaults Defaults.
	 * @return array
	 */
	private static function recursive_parse_args( $args, $defaults ) {
		$args = (array) $args;
		foreach ( $defaults as $key => $default ) {
			if ( is_array( $default ) ) {
				$args[ $key ] = self::recursive_parse_args( $args[ $key ] ?? array(), $default );
			} elseif ( ! array_key_exists( $key, $args ) ) {
				$args[ $key ] = $default;
			}
		}

		return $args;
	}
}
