<?php
/**
 * Course completion certificates.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generate, email, and verify premium course certificates.
 */
class GCM_Certificate_Service {

	/**
	 * Get certificate for a student+course pair.
	 *
	 * @param int $user_id User ID.
	 * @param int $course_id Course ID.
	 * @return object|null
	 */
	public static function get_for_enrollment( $user_id, $course_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_certificates WHERE user_id = %d AND course_id = %d LIMIT 1",
				absint( $user_id ),
				absint( $course_id )
			)
		);
	}

	/**
	 * Lookup by public certificate code.
	 *
	 * @param string $code Certificate ID/code.
	 * @return object|null
	 */
	public static function get_by_code( $code ) {
		global $wpdb;

		$code = self::normalize_code( $code );
		if ( '' === $code ) {
			return null;
		}

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gcm_certificates WHERE certificate_code = %s LIMIT 1",
				$code
			)
		);
	}

	/**
	 * Normalize entered certificate codes.
	 *
	 * @param string $code Raw code.
	 * @return string
	 */
	public static function normalize_code( $code ) {
		$code = strtoupper( trim( (string) $code ) );
		$code = preg_replace( '/[^A-Z0-9\-]/', '', $code );
		return is_string( $code ) ? $code : '';
	}

	/**
	 * Create a unique public certificate ID.
	 *
	 * @return string
	 */
	public static function generate_code() {
		global $wpdb;

		do {
			$part_a = strtoupper( wp_generate_password( 4, false, false ) );
			$part_b = strtoupper( wp_generate_password( 4, false, false ) );
			$part_c = strtoupper( wp_generate_password( 4, false, false ) );
			$code   = sprintf( 'GCM-%s-%s-%s', $part_a, $part_b, $part_c );
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}gcm_certificates WHERE certificate_code = %s LIMIT 1",
					$code
				)
			);
		} while ( $exists );

		return $code;
	}

	/**
	 * Generate (or regenerate email for) a certificate and email the student.
	 *
	 * @param int $user_id Student user ID.
	 * @param int $course_id Course ID.
	 * @param int $issued_by Admin user ID.
	 * @return object|WP_Error
	 */
	public static function generate_and_send( $user_id, $course_id, $issued_by = 0 ) {
		global $wpdb;

		$user_id   = absint( $user_id );
		$course_id = absint( $course_id );
		$issued_by = $issued_by ? absint( $issued_by ) : get_current_user_id();

		$user = get_userdata( $user_id );
		if ( ! $user || ! is_email( $user->user_email ) ) {
			return new WP_Error( 'gcm_no_student', __( 'Student account or email is missing.', 'giga-class-market' ) );
		}

		$course = get_post( $course_id );
		if ( ! $course || 'gcm_course' !== $course->post_type ) {
			return new WP_Error( 'gcm_no_course', __( 'Course not found.', 'giga-class-market' ) );
		}

		if ( ! GCM_Enrollment_Service::has_access( $user_id, $course_id ) ) {
			return new WP_Error( 'gcm_not_enrolled', __( 'This student is not enrolled in that course.', 'giga-class-market' ) );
		}

		$existing = self::get_for_enrollment( $user_id, $course_id );
		if ( $existing ) {
			$cert = $existing;
		} else {
			$code = self::generate_code();
			$ok   = $wpdb->insert(
				$wpdb->prefix . 'gcm_certificates',
				array(
					'certificate_code' => $code,
					'user_id'          => $user_id,
					'course_id'        => $course_id,
					'student_name'     => $user->display_name,
					'course_title'     => get_the_title( $course ),
					'issued_at'        => current_time( 'mysql' ),
					'issued_by'        => $issued_by ? $issued_by : null,
					'email_status'     => 'pending',
					'meta'             => wp_json_encode(
						array(
							'student_email' => $user->user_email,
						)
					),
				),
				array( '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
			);

			if ( ! $ok ) {
				return new WP_Error( 'gcm_cert_save', __( 'Could not save the certificate.', 'giga-class-market' ) );
			}

			$cert = self::get_by_code( $code );
			if ( ! $cert ) {
				return new WP_Error( 'gcm_cert_save', __( 'Could not save the certificate.', 'giga-class-market' ) );
			}

			if ( class_exists( 'GCM_Audit_Service' ) ) {
				GCM_Audit_Service::log(
					'certificate_generated',
					'certificate',
					(int) $cert->id,
					array(
						'user_id'   => $user_id,
						'course_id' => $course_id,
						'code'      => $cert->certificate_code,
					)
				);
			}
		}

		$sent = self::email_certificate( $cert, $user );
		$wpdb->update(
			$wpdb->prefix . 'gcm_certificates',
			array( 'email_status' => $sent ? 'sent' : 'failed' ),
			array( 'id' => (int) $cert->id ),
			array( '%s' ),
			array( '%d' )
		);
		$cert->email_status = $sent ? 'sent' : 'failed';

		if ( ! $sent ) {
			return new WP_Error(
				'gcm_cert_email',
				sprintf(
					/* translators: %s: certificate code */
					__( 'Certificate %s was created, but the email could not be sent. Ask the student to verify with this ID.', 'giga-class-market' ),
					$cert->certificate_code
				)
			);
		}

		return $cert;
	}

	/**
	 * Email certificate details to the student.
	 *
	 * @param object  $cert Certificate row.
	 * @param WP_User $user Student.
	 * @return bool
	 */
	public static function email_certificate( $cert, $user ) {
		global $wpdb;

		$verify_url = self::verify_url( $cert->certificate_code );
		$view_html  = self::render_certificate_html( $cert, array( 'email' => true ) );

		$title = sprintf(
			/* translators: %s: course title */
			__( 'Your Giga Class Market certificate — %s', 'giga-class-market' ),
			$cert->course_title
		);

		$message  = '<div style="font-family:Outfit,Arial,sans-serif;color:#0D2A2E;line-height:1.6;font-weight:600;">';
		$message .= '<p><strong>' . esc_html__( 'Congratulations!', 'giga-class-market' ) . '</strong></p>';
		$message .= '<p>' . esc_html(
			sprintf(
				/* translators: 1: student name, 2: course title */
				__( 'Dear %1$s, your official certificate for “%2$s” is ready.', 'giga-class-market' ),
				$user->display_name,
				$cert->course_title
			)
		) . '</p>';
		$message .= '<p><strong>' . esc_html__( 'Certificate ID:', 'giga-class-market' ) . '</strong> ' . esc_html( $cert->certificate_code ) . '</p>';
		$message .= '<p><a href="' . esc_url( $verify_url ) . '" style="color:#0D3B45;font-weight:700;">' . esc_html__( 'View / verify your certificate', 'giga-class-market' ) . '</a></p>';
		$message .= '<hr style="border:none;border-top:1px solid #ddd;margin:24px 0;" />';
		$message .= $view_html;
		$message .= '</div>';

		$table = $wpdb->prefix . 'gcm_notifications';
		$wpdb->insert(
			$table,
			array(
				'user_id'    => (int) $user->ID,
				'type'       => 'certificate_issued',
				'title'      => sanitize_text_field( $title ),
				'message'    => wp_kses_post( $message ),
				'channel'    => 'email',
				'status'     => 'queued',
				'meta'       => wp_json_encode(
					array(
						'certificate_code' => $cert->certificate_code,
						'course_id'        => (int) $cert->course_id,
					)
				),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		$notification_id = (int) $wpdb->insert_id;

		$from_email = GCM_Settings_Service::get_from_email();
		$from_name  = GCM_Settings_Service::get_from_name();
		$headers    = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $from_name, $from_email ),
			sprintf( 'Reply-To: %s <%s>', $from_name, $from_email ),
		);

		$sent = wp_mail( $user->user_email, $title, $message, $headers );
		if ( $notification_id ) {
			$wpdb->update(
				$table,
				array( 'status' => $sent ? 'sent' : 'failed' ),
				array( 'id' => $notification_id ),
				array( '%s' ),
				array( '%d' )
			);
		}

		return (bool) $sent;
	}

	/**
	 * Public verify page URL.
	 *
	 * @param string $code Optional certificate code.
	 * @return string
	 */
	public static function verify_url( $code = '' ) {
		$url = home_url( '/verify-certificate/' );
		if ( $code ) {
			$url = add_query_arg( 'code', rawurlencode( self::normalize_code( $code ) ), $url );
		}
		return $url;
	}

	/**
	 * Absolute theme asset URL helper.
	 *
	 * @param string $relative Relative path under theme assets.
	 * @return string
	 */
	public static function asset_url( $relative ) {
		if ( defined( 'GCM_THEME_URI' ) ) {
			return trailingslashit( GCM_THEME_URI ) . ltrim( $relative, '/' );
		}
		return trailingslashit( get_template_directory_uri() ) . ltrim( $relative, '/' );
	}

	/**
	 * Resolve brand logo URL for certificates.
	 *
	 * @return string
	 */
	public static function logo_url() {
		$custom_logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( $custom_logo_id ) {
			$url = wp_get_attachment_image_url( $custom_logo_id, 'full' );
			if ( $url ) {
				return $url;
			}
		}
		return self::asset_url( 'assets/images/certificate/logo.svg' );
	}

	/**
	 * Premium HTML certificate markup.
	 *
	 * @param object $cert Certificate row.
	 * @param array  $args Render args.
	 * @return string
	 */
	public static function render_certificate_html( $cert, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'email' => false,
			)
		);

		$issued     = mysql2date( get_option( 'date_format' ), $cert->issued_at );
		$company    = class_exists( 'GCM_Settings_Service' ) ? GCM_Settings_Service::get_settings() : array();
		$brand      = ! empty( $company['company']['name'] ) ? $company['company']['name'] : 'Giga Class Market';
		$logo       = self::logo_url();
		$buildings  = self::asset_url( 'assets/images/certificate/buildings.jpg' );
		$watermark  = self::asset_url( 'assets/images/certificate/logo-watermark.svg' );
		$is_email   = ! empty( $args['email'] );
		$uid        = 'gcmc' . substr( md5( (string) $cert->certificate_code ), 0, 8 );

		ob_start();
		?>
		<div class="gcm-certificate<?php echo $is_email ? ' gcm-certificate--email' : ''; ?>" data-certificate-code="<?php echo esc_attr( $cert->certificate_code ); ?>">
			<div
				class="gcm-certificate__frame"
				style="--gcm-cert-buildings:url('<?php echo esc_url( $buildings ); ?>');--gcm-cert-watermark:url('<?php echo esc_url( $watermark ); ?>');"
			>
				<div class="gcm-certificate__skyline" aria-hidden="true"></div>
				<div class="gcm-certificate__watermark" aria-hidden="true"></div>
				<div class="gcm-certificate__ornament gcm-certificate__ornament--tl" aria-hidden="true"></div>
				<div class="gcm-certificate__ornament gcm-certificate__ornament--tr" aria-hidden="true"></div>
				<div class="gcm-certificate__ornament gcm-certificate__ornament--bl" aria-hidden="true"></div>
				<div class="gcm-certificate__ornament gcm-certificate__ornament--br" aria-hidden="true"></div>

				<div class="gcm-certificate__inner">
					<div class="gcm-certificate__header">
						<img
							class="gcm-certificate__logo"
							src="<?php echo esc_url( $logo ); ?>"
							alt="<?php echo esc_attr( $brand ); ?>"
							width="58"
							height="58"
						>
						<div class="gcm-certificate__brand-block">
							<p class="gcm-certificate__brand"><?php echo esc_html( $brand ); ?></p>
							<p class="gcm-certificate__tagline"><?php esc_html_e( 'Premium Learning', 'giga-class-market' ); ?></p>
						</div>
					</div>

					<p class="gcm-certificate__ribbon"><?php esc_html_e( 'Official Certificate', 'giga-class-market' ); ?></p>
					<p class="gcm-certificate__kicker"><?php esc_html_e( 'Certificate of Achievement', 'giga-class-market' ); ?></p>
					<p class="gcm-certificate__title"><?php esc_html_e( 'This is to certify that', 'giga-class-market' ); ?></p>
					<p class="gcm-certificate__name"><?php echo esc_html( $cert->student_name ); ?></p>
					<p class="gcm-certificate__body">
						<?php echo wp_kses( __( 'has successfully completed the <strong>professional learning program</strong>', 'giga-class-market' ), array( 'strong' => array() ) ); ?>
					</p>
					<p class="gcm-certificate__course"><?php echo esc_html( $cert->course_title ); ?></p>
					<p class="gcm-certificate__body gcm-certificate__body--muted">
						<?php echo wp_kses( __( 'in recognition of <strong>dedication</strong>, skill development, and successful participation with Giga Class Market.', 'giga-class-market' ), array( 'strong' => array() ) ); ?>
					</p>

					<div class="gcm-certificate__meta">
						<div>
							<span><?php esc_html_e( 'Issued on', 'giga-class-market' ); ?></span>
							<strong><?php echo esc_html( $issued ); ?></strong>
						</div>
						<div>
							<span><?php esc_html_e( 'Certificate ID', 'giga-class-market' ); ?></span>
							<strong class="gcm-certificate__code"><?php echo esc_html( $cert->certificate_code ); ?></strong>
						</div>
					</div>

					<div class="gcm-certificate__footer">
						<div class="gcm-certificate__sign">
							<span class="gcm-certificate__sign-line" aria-hidden="true"></span>
							<strong><?php esc_html_e( 'Giga Class Market', 'giga-class-market' ); ?></strong>
							<small><?php esc_html_e( 'Authorized Issuing Authority', 'giga-class-market' ); ?></small>
						</div>
						<div class="gcm-certificate__seal" aria-hidden="true">
							<span><?php esc_html_e( 'Verified', 'giga-class-market' ); ?></span>
						</div>
					</div>
				</div>
			</div>
			<?php if ( $is_email ) : ?>
				<style type="text/css">
					.gcm-certificate--email .gcm-certificate__frame {
						background-image: linear-gradient(180deg, rgba(255,253,248,0.94) 0%, rgba(255,255,255,0.88) 48%, rgba(244,250,249,0.92) 100%), url('<?php echo esc_url( $buildings ); ?>') !important;
						background-size: cover !important;
						background-position: center bottom !important;
						aspect-ratio: 1.414 / 1 !important;
						width: 100% !important;
						max-width: 1100px !important;
					}
					.gcm-certificate--email .gcm-certificate__skyline {
						background-image: url('<?php echo esc_url( $buildings ); ?>') !important;
						background-repeat: no-repeat !important;
						background-position: center bottom !important;
						background-size: 100% auto !important;
						opacity: 0.5 !important;
					}
					.gcm-certificate--email .gcm-certificate__watermark {
						background-image: url('<?php echo esc_url( $watermark ); ?>') !important;
						background-repeat: no-repeat !important;
						background-position: center 46% !important;
						background-size: 280px auto !important;
						opacity: 0.5 !important;
					}
					.gcm-certificate--email .gcm-certificate__logo {
						display: block !important;
						margin: 0 auto 0.35rem !important;
					}
					.gcm-certificate--email .gcm-certificate__ribbon {
						display: inline-block !important;
						padding: 0.4rem 1.2rem !important;
						background: #0d3b45 !important;
						color: #f7efd4 !important;
						border-radius: 999px !important;
						font-size: 11px !important;
						font-weight: 700 !important;
						letter-spacing: 0.14em !important;
						text-transform: uppercase !important;
					}
					.gcm-certificate--email .gcm-certificate__seal {
						background: #e0a045 !important;
						border: 2px solid #fffdf8 !important;
					}
				</style>
			<?php endif; ?>
		</div>
		<?php
		unset( $uid );
		return (string) ob_get_clean();
	}
}
