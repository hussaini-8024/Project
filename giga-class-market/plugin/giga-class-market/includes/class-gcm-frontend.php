<?php
/**
 * Frontend functionality.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend shortcodes, page guards, and SEO helpers.
 */
class GCM_Frontend {

	/**
	 * Register rewrites/query handlers.
	 *
	 * @return void
	 */
	public function register_rewrites() {
		add_rewrite_endpoint( 'gcm-payment-screenshot', EP_ROOT );
	}

	/**
	 * Uncached JSON endpoint for promo popup (front URL, not admin-ajax).
	 * Hosts that block /wp-admin/admin-ajax.php for guests can still serve this.
	 *
	 * @return void
	 */
	public function serve_promo_popup_json() {
		if ( empty( $_GET['gcm_promo_json'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		nocache_headers();
		if ( ! headers_sent() ) {
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );
			header( 'X-StackCache-Cacheable: no' );
		}

		$website  = class_exists( 'GCM_Settings_Service' ) ? GCM_Settings_Service::get_section( 'website' ) : array();
		$enabled  = ! empty( $website['popup_enabled'] );
		$image_id = absint( $website['popup_image_id'] ?? 0 );
		$image    = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '';
		$link     = esc_url_raw( $website['popup_link_url'] ?? '' );

		if ( ! $enabled || ! $image ) {
			wp_send_json_success( array( 'enabled' => false ) );
		}

		wp_send_json_success(
			array(
				'enabled' => true,
				'id'      => (string) $image_id,
				'image'   => $image,
				'link'    => $link,
				'alt'     => __( 'Promotional offer', 'giga-class-market' ),
			)
		);
	}

	/**
	 * Register shortcodes.
	 *
	 * @return void
	 */
	public function register_shortcodes() {
		add_shortcode( 'gcm_courses', array( $this, 'courses_shortcode' ) );
		add_shortcode( 'gcm_payment_form', array( $this, 'payment_form_shortcode' ) );
		add_shortcode( 'gcm_payment_verification', array( $this, 'payment_verification_shortcode' ) );
		add_shortcode( 'gcm_contact_form', array( $this, 'contact_form_shortcode' ) );
		add_shortcode( 'gcm_login_form', array( $this, 'login_form_shortcode' ) );
		add_shortcode( 'gcm_student_dashboard', array( $this, 'student_dashboard_shortcode' ) );
	}

	/**
	 * Enqueue public assets.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		wp_enqueue_style( 'gcm-public', GCM_PLUGIN_URL . 'public/css/gcm-public.css', array(), GCM_VERSION );
		wp_enqueue_script( 'gcm-public', GCM_PLUGIN_URL . 'public/js/gcm-public.js', array(), GCM_VERSION, true );
		wp_localize_script(
			'gcm-public',
			'gcmPublic',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'gcm_ajax_nonce' ),
				'paymentUrl' => home_url( '/payment/' ),
			)
		);
	}

	/**
	 * Course listing shortcode.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function courses_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'featured' => '',
				'limit'    => 12,
			),
			$atts,
			'gcm_courses'
		);

		$courses = '' !== $atts['featured'] ? GCM_Course_Service::get_featured( min( 3, absint( $atts['limit'] ) ) ) : GCM_Course_Service::search( array( 'limit' => absint( $atts['limit'] ) ) );
		$terms   = get_terms( array( 'taxonomy' => 'gcm_category', 'hide_empty' => false ) );

		ob_start();
		?>
		<div class="gcm-courses" data-nonce="<?php echo esc_attr( wp_create_nonce( 'gcm_ajax_nonce' ) ); ?>">
			<form class="gcm-course-search">
				<input type="search" name="search" placeholder="<?php esc_attr_e( 'Search courses', 'giga-class-market' ); ?>" />
				<select name="category">
					<option value=""><?php esc_html_e( 'All categories', 'giga-class-market' ); ?></option>
					<?php foreach ( $terms as $term ) : ?>
						<option value="<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit"><?php esc_html_e( 'Search', 'giga-class-market' ); ?></button>
			</form>
			<div class="gcm-course-grid">
				<?php echo wp_kses_post( $this->render_course_cards( $courses ) ); ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Payment form shortcode.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function payment_form_shortcode( $atts ) {
		$atts      = shortcode_atts( array( 'course_id' => 0 ), $atts, 'gcm_payment_form' );
		$course_id = absint( $atts['course_id'] );
		if ( ! $course_id && isset( $_GET['course_id'] ) ) {
			$course_id = absint( $_GET['course_id'] );
		}
		$course  = $course_id ? GCM_Course_Service::get( $course_id ) : null;
		$courses = GCM_Course_Service::search( array( 'limit' => 100 ) );
		$methods = GCM_Settings_Service::get_payment_methods();

		ob_start();
		?>
		<form class="gcm-ajax-form gcm-payment-form" enctype="multipart/form-data" data-action="gcm_payment_submit">
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'gcm_ajax_nonce' ) ); ?>" />
			<label>
				<?php esc_html_e( 'Course', 'giga-class-market' ); ?>
				<select name="course_id" required>
					<option value=""><?php esc_html_e( 'Select course', 'giga-class-market' ); ?></option>
					<?php foreach ( $courses as $item ) : ?>
						<option value="<?php echo esc_attr( $item['id'] ); ?>" <?php selected( $course_id, $item['id'] ); ?>>
							<?php echo esc_html( $item['title'] . ' - ' . number_format_i18n( $item['price'], 2 ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>
			<?php if ( $course ) : ?>
				<p class="gcm-payment-price"><?php echo esc_html( sprintf( __( 'Amount due: %s', 'giga-class-market' ), number_format_i18n( $course['price'], 2 ) ) ); ?></p>
			<?php endif; ?>
			<label><?php esc_html_e( 'Full name', 'giga-class-market' ); ?><input type="text" name="full_name" required /></label>
			<label><?php esc_html_e( 'Email', 'giga-class-market' ); ?><input type="email" name="email" required /></label>
			<label><?php esc_html_e( 'WhatsApp', 'giga-class-market' ); ?><input type="text" name="whatsapp" required /></label>
			<label><?php esc_html_e( 'Address', 'giga-class-market' ); ?><textarea name="address" rows="3"></textarea></label>
			<label>
				<?php esc_html_e( 'Payment method', 'giga-class-market' ); ?>
				<select name="payment_method" required>
					<?php foreach ( $methods as $name => $method ) : ?>
						<option value="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<div class="gcm-payment-methods">
				<?php foreach ( $methods as $name => $method ) : ?>
					<div class="gcm-payment-method">
						<strong><?php echo esc_html( $name ); ?></strong>
						<p><?php echo esc_html( $method['account_name'] ); ?> <?php echo esc_html( $method['account_no'] ); ?></p>
						<p><?php echo esc_html( $method['instructions'] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
			<label><?php esc_html_e( 'Transaction ID', 'giga-class-market' ); ?><input type="text" name="transaction_id" required /></label>
			<label><?php esc_html_e( 'Payment screenshot/receipt', 'giga-class-market' ); ?><input type="file" name="screenshot" accept=".jpg,.jpeg,.png,.pdf" required /></label>
			<button type="submit"><?php esc_html_e( 'Submit for verification', 'giga-class-market' ); ?></button>
			<div class="gcm-form-message" aria-live="polite"></div>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Payment verification shortcode.
	 *
	 * @return string
	 */
	public function payment_verification_shortcode() {
		return '<div class="gcm-card"><h2>' . esc_html__( 'Payment Verification', 'giga-class-market' ) . '</h2><p>' . esc_html__( 'After submitting payment, our team reviews it and sends login details after approval.', 'giga-class-market' ) . '</p></div>';
	}

	/**
	 * Contact form shortcode.
	 *
	 * @return string
	 */
	public function contact_form_shortcode() {
		ob_start();
		?>
		<form class="gcm-ajax-form gcm-contact-form" data-action="gcm_contact_submit">
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'gcm_ajax_nonce' ) ); ?>" />
			<label><?php esc_html_e( 'Full name', 'giga-class-market' ); ?><input type="text" name="full_name" required /></label>
			<label><?php esc_html_e( 'Email', 'giga-class-market' ); ?><input type="email" name="email" required /></label>
			<label><?php esc_html_e( 'WhatsApp', 'giga-class-market' ); ?><input type="text" name="whatsapp" /></label>
			<label><?php esc_html_e( 'Subject', 'giga-class-market' ); ?><input type="text" name="subject" required /></label>
			<label><?php esc_html_e( 'Message', 'giga-class-market' ); ?><textarea name="message" rows="5" required></textarea></label>
			<button type="submit"><?php esc_html_e( 'Send message', 'giga-class-market' ); ?></button>
			<div class="gcm-form-message" aria-live="polite"></div>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Login form shortcode — link to branded student login (never wp-login.php UI).
	 *
	 * @return string
	 */
	public function login_form_shortcode() {
		if ( is_user_logged_in() ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<p><a class="gcm-button" href="' . esc_url( admin_url() ) . '">' . esc_html__( 'Go to admin', 'giga-class-market' ) . '</a></p>';
			}
			if ( current_user_can( 'gcm_teacher_dashboard' ) ) {
				return '<p><a class="gcm-button" href="' . esc_url( home_url( '/teacher-dashboard/' ) ) . '">' . esc_html__( 'Go to teacher dashboard', 'giga-class-market' ) . '</a></p>';
			}
			if ( current_user_can( 'gcm_access_dashboard' ) ) {
				return '<p><a class="gcm-button" href="' . esc_url( home_url( '/student-dashboard/' ) ) . '">' . esc_html__( 'Go to dashboard', 'giga-class-market' ) . '</a></p>';
			}
			return '<p><a class="gcm-button" href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Go to home', 'giga-class-market' ) . '</a></p>';
		}

		$url = self::get_student_login_url( home_url( '/student-dashboard/' ) );
		return '<p><a class="gcm-button" href="' . esc_url( $url ) . '">' . esc_html__( 'Login', 'giga-class-market' ) . '</a></p>';
	}

	/**
	 * Student dashboard shortcode.
	 *
	 * @return string
	 */
	public function student_dashboard_shortcode() {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to access your dashboard.', 'giga-class-market' ) . '</p>' . $this->login_form_shortcode();
		}

		if ( ! current_user_can( 'gcm_access_dashboard' ) && ! current_user_can( 'manage_options' ) ) {
			return '<p>' . esc_html__( 'Your account does not have student dashboard access.', 'giga-class-market' ) . '</p>';
		}

		$user    = wp_get_current_user();
		$courses = GCM_Enrollment_Service::get_student_courses( $user->ID );

		ob_start();
		?>
		<div class="gcm-student-dashboard" data-nonce="<?php echo esc_attr( wp_create_nonce( 'gcm_ajax_nonce' ) ); ?>">
			<section class="gcm-card">
				<h2><?php echo esc_html( sprintf( __( 'Welcome, %s', 'giga-class-market' ), $user->display_name ) ); ?></h2>
				<form class="gcm-ajax-form gcm-profile-form" data-action="gcm_update_profile">
					<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'gcm_ajax_nonce' ) ); ?>" />
					<label><?php esc_html_e( 'Full name', 'giga-class-market' ); ?><input type="text" name="full_name" value="<?php echo esc_attr( $user->display_name ); ?>" /></label>
					<label><?php esc_html_e( 'Email', 'giga-class-market' ); ?><input type="email" name="email" value="<?php echo esc_attr( $user->user_email ); ?>" /></label>
					<label><?php esc_html_e( 'WhatsApp', 'giga-class-market' ); ?><input type="text" name="whatsapp" value="<?php echo esc_attr( get_user_meta( $user->ID, 'gcm_whatsapp', true ) ); ?>" /></label>
					<label><?php esc_html_e( 'Address', 'giga-class-market' ); ?><textarea name="address" rows="3"><?php echo esc_textarea( get_user_meta( $user->ID, 'gcm_address', true ) ); ?></textarea></label>
					<button type="submit"><?php esc_html_e( 'Update profile', 'giga-class-market' ); ?></button>
					<div class="gcm-form-message"></div>
				</form>
			</section>

			<section class="gcm-card">
				<h2><?php esc_html_e( 'My Courses', 'giga-class-market' ); ?></h2>
				<?php if ( empty( $courses ) ) : ?>
					<p><?php esc_html_e( 'No courses enrolled yet.', 'giga-class-market' ); ?></p>
				<?php endif; ?>
				<?php foreach ( $courses as $course ) : ?>
					<article class="gcm-dashboard-course">
						<h3><?php echo esc_html( $course['title'] ); ?></h3>
						<p><?php echo esc_html( sprintf( __( '%d%% complete', 'giga-class-market' ), $course['progress'] ) ); ?></p>
						<?php echo wp_kses_post( $this->render_curriculum_for_student( $user->ID, $course['id'] ) ); ?>
					</article>
				<?php endforeach; ?>
			</section>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Protect student dashboard pages.
	 *
	 * @return void
	 */
	public function protect_student_pages() {
		if ( $this->is_teacher_page() ) {
			if ( ! is_user_logged_in() ) {
				wp_safe_redirect( add_query_arg( 'redirect_to', rawurlencode( home_url( '/teacher-dashboard/' ) ), home_url( '/login/' ) ) );
				exit;
			}
			if ( ! current_user_can( 'gcm_teacher_dashboard' ) && ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have access to the teacher dashboard.', 'giga-class-market' ), esc_html__( 'Access denied', 'giga-class-market' ), array( 'response' => 403 ) );
			}
			return;
		}

		if ( $this->is_student_page() && ! is_user_logged_in() ) {
			$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/student-dashboard/';
			$target      = home_url( wp_parse_url( $request_uri, PHP_URL_PATH ) );
			$query       = wp_parse_url( $request_uri, PHP_URL_QUERY );
			if ( $query ) {
				parse_str( $query, $params );
				$target = add_query_arg( array_map( 'sanitize_text_field', $params ), $target );
			}
			wp_safe_redirect( add_query_arg( 'redirect_to', rawurlencode( $target ), home_url( '/login/' ) ) );
			exit;
		}

		if ( $this->is_student_page() && is_user_logged_in() && ! current_user_can( 'gcm_access_dashboard' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have access to this course.', 'giga-class-market' ), esc_html__( 'Access denied', 'giga-class-market' ), array( 'response' => 403 ) );
		}
	}

	/**
	 * Serve private screenshot by authenticated endpoint.
	 *
	 * @return void
	 */
	public function serve_private_screenshot() {
		if ( empty( $_GET['gcm_private_screenshot'] ) ) {
			return;
		}

		$payment_id = absint( $_GET['gcm_private_screenshot'] );
		$nonce      = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! is_user_logged_in() || ! wp_verify_nonce( $nonce, 'gcm_private_screenshot_' . $payment_id ) ) {
			status_header( 403 );
			exit;
		}

		$payment = GCM_Payment_Service::get( $payment_id );
		if ( ! $payment ) {
			status_header( 404 );
			exit;
		}

		$allowed = current_user_can( 'gcm_manage_payments' ) || ( (int) $payment->user_id === get_current_user_id() );
		if ( ! $allowed ) {
			status_header( 403 );
			exit;
		}

		$file = get_post_meta( (int) $payment->screenshot_id, '_gcm_private_file', true );
		if ( ! $file || ! file_exists( $file ) ) {
			status_header( 404 );
			exit;
		}

		$mime = get_post_mime_type( (int) $payment->screenshot_id );
		nocache_headers();
		header( 'Content-Type: ' . ( $mime ? $mime : 'application/octet-stream' ) );
		header( 'Content-Disposition: inline; filename="' . basename( $file ) . '"' );
		header( 'Content-Length: ' . filesize( $file ) );
		readfile( $file );
		exit;
	}

	/**
	 * Add noindex to student pages.
	 *
	 * @param array $robots Robots directives.
	 * @return array
	 */
	public function noindex_student_pages( $robots ) {
		if ( $this->is_student_page() || $this->is_teacher_page() ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}

		return $robots;
	}

	/**
	 * Print Course schema JSON-LD on single course pages.
	 *
	 * @return void
	 */
	public function print_course_schema() {
		if ( ! is_singular( 'gcm_course' ) ) {
			return;
		}

		$course = GCM_Course_Service::get( get_the_ID() );
		if ( ! $course ) {
			return;
		}

		$schema = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Course',
			'name'        => $course['title'],
			'description' => wp_strip_all_tags( $course['excerpt'] ? $course['excerpt'] : wp_trim_words( wp_strip_all_tags( $course['content'] ), 40 ) ),
			'provider'    => array(
				'@type' => 'Organization',
				'name'  => GCM_Settings_Service::get_settings()['company']['name'],
			),
			'offers'      => array(
				'@type'         => 'Offer',
				'price'         => (string) $course['price'],
				'priceCurrency' => 'PKR',
				'availability'  => 'https://schema.org/InStock',
				'url'           => $course['permalink'],
			),
		);

		echo '<script type="application/ld+json">' . wp_json_encode( $schema ) . '</script>' . "\n";
	}

	/**
	 * Redirect student logins to dashboard (admins to wp-admin).
	 *
	 * @param string           $redirect_to Redirect URL.
	 * @param string           $requested Requested URL.
	 * @param WP_User|WP_Error $user User.
	 * @return string
	 */
	public function login_redirect( $redirect_to, $requested, $user ) {
		if ( ! ( $user instanceof WP_User ) ) {
			return $redirect_to;
		}

		if ( user_can( $user, 'manage_options' ) ) {
			return admin_url();
		}

		if ( in_array( 'gcm_student', (array) $user->roles, true ) ) {
			if ( $requested && false === strpos( $requested, 'wp-admin' ) ) {
				return $requested;
			}
			return home_url( '/student-dashboard/' );
		}

		if ( in_array( 'gcm_teacher', (array) $user->roles, true ) ) {
			if ( $requested && false === strpos( $requested, 'wp-admin' ) && false !== strpos( $requested, 'teacher' ) ) {
				return $requested;
			}
			return home_url( '/teacher-dashboard/' );
		}

		return $redirect_to;
	}

	/**
	 * Force public login URLs to the branded /login/ page.
	 *
	 * @param string $login_url Login URL.
	 * @param string $redirect Redirect target.
	 * @param bool   $force_reauth Force reauth.
	 * @return string
	 */
	public function filter_login_url( $login_url, $redirect = '', $force_reauth = false ) {
		unset( $force_reauth );
		return self::get_student_login_url( $redirect );
	}

	/**
	 * Branded student login URL helper.
	 *
	 * @param string $redirect Optional redirect_to target.
	 * @return string
	 */
	public static function get_student_login_url( $redirect = '' ) {
		$url = home_url( '/login/' );
		if ( $redirect ) {
			$url = add_query_arg( 'redirect_to', $redirect, $url );
		}
		return $url;
	}

	/**
	 * Send visitors away from core wp-login.php UI to /login/.
	 * Keeps logout and admin postpass working on wp-login.php.
	 *
	 * @return void
	 */
	public function redirect_wp_login_to_branded() {
		if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) {
			return;
		}

		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : 'login';
		if ( in_array( $action, array( 'logout', 'postpass' ), true ) ) {
			return;
		}

		// Allow password-reset key links to continue on wp-login (email deep links).
		if ( in_array( $action, array( 'rp', 'resetpass' ), true ) && ! empty( $_GET['key'] ) ) {
			return;
		}

		$redirect = '';
		if ( ! empty( $_REQUEST['redirect_to'] ) ) {
			$redirect = esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) );
		}

		$target = self::get_student_login_url( $redirect );
		if ( in_array( $action, array( 'lostpassword', 'retrievepassword' ), true ) ) {
			$target = add_query_arg( 'action', 'lostpassword', $target );
		}

		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * Redirect students/teachers away from wp-admin.
	 *
	 * @return void
	 */
	public function redirect_students_from_admin() {
		if ( wp_doing_ajax() || current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( current_user_can( 'gcm_teacher_dashboard' ) ) {
			wp_safe_redirect( home_url( '/teacher-dashboard/' ) );
			exit;
		}

		if ( current_user_can( 'gcm_access_dashboard' ) ) {
			wp_safe_redirect( home_url( '/student-dashboard/' ) );
			exit;
		}
	}

	/**
	 * Force session-only auth cookies for students and teachers.
	 * Closing the browser ends the login; they must sign in again.
	 *
	 * Re-issues cookies with remember=false so the browser stores a session cookie
	 * (even if "Remember me" was checked elsewhere).
	 *
	 * @param string  $user_login Username.
	 * @param WP_User $user User object.
	 * @return void
	 */
	public function force_session_login_cookie( $user_login, $user ) {
		unset( $user_login );
		if ( ! ( $user instanceof WP_User ) ) {
			return;
		}

		$roles = (array) $user->roles;
		if ( ! in_array( 'gcm_student', $roles, true ) && ! in_array( 'gcm_teacher', $roles, true ) ) {
			return;
		}

		// Avoid recursion if wp_set_auth_cookie fires wp_login again (it does not).
		wp_clear_auth_cookie();
		wp_set_auth_cookie( (int) $user->ID, false, is_ssl() );
	}

	/**
	 * Hide the WordPress admin bar for students and teachers (keep it for admins).
	 *
	 * @param bool $show Whether to show the admin bar.
	 * @return bool
	 */
	public function maybe_hide_admin_bar( $show ) {
		if ( ! is_user_logged_in() ) {
			return $show;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return $show;
		}

		$user = wp_get_current_user();
		if ( $user && ( in_array( 'gcm_student', (array) $user->roles, true ) || in_array( 'gcm_teacher', (array) $user->roles, true ) ) ) {
			return false;
		}

		if ( ( current_user_can( 'gcm_access_dashboard' ) || current_user_can( 'gcm_teacher_dashboard' ) ) && ! current_user_can( 'edit_posts' ) ) {
			return false;
		}

		return $show;
	}

	/**
	 * Render course cards.
	 *
	 * @param array $courses Courses.
	 * @return string
	 */
	private function render_course_cards( $courses ) {
		if ( empty( $courses ) ) {
			return '<p>' . esc_html__( 'No courses found.', 'giga-class-market' ) . '</p>';
		}

		ob_start();
		foreach ( $courses as $course ) :
			$regular   = (float) ( $course['price'] ?? 0 );
			$sale      = (float) ( $course['discount_price'] ?? 0 );
			$on_sale   = $sale > 0 && $sale < $regular;
			$badge     = ! empty( $course['sale_label'] ) ? $course['sale_label'] : __( 'Sale', 'giga-class-market' );
			$show_price = $on_sale ? $sale : $regular;
			?>
			<article class="gcm-course-card<?php echo $on_sale ? ' gcm-course-card--sale' : ''; ?>">
				<div class="gcm-course-card__media">
					<?php if ( ! empty( $course['thumbnail'] ) ) : ?>
						<img src="<?php echo esc_url( $course['thumbnail'] ); ?>" alt="<?php echo esc_attr( $course['title'] ); ?>" />
					<?php endif; ?>
					<?php if ( $on_sale ) : ?>
						<span class="gcm-sale-badge"><?php echo esc_html( $badge ); ?></span>
					<?php endif; ?>
				</div>
				<h3><a href="<?php echo esc_url( $course['permalink'] ); ?>"><?php echo esc_html( $course['title'] ); ?></a></h3>
				<p><?php echo esc_html( $course['excerpt'] ); ?></p>
				<?php if ( $on_sale ) : ?>
					<p class="gcm-price gcm-price-block">
						<span class="gcm-price-block__sale"><?php echo esc_html( number_format_i18n( $show_price, 2 ) ); ?></span>
						<s class="gcm-price-block__original"><?php echo esc_html( number_format_i18n( $regular, 2 ) ); ?></s>
					</p>
				<?php else : ?>
					<p class="gcm-price"><?php echo esc_html( number_format_i18n( $show_price, 2 ) ); ?></p>
				<?php endif; ?>
				<a class="gcm-button" href="<?php echo esc_url( add_query_arg( 'course_id', $course['id'], home_url( '/payment/' ) ) ); ?>"><?php esc_html_e( 'Enroll now', 'giga-class-market' ); ?></a>
			</article>
			<?php
		endforeach;
		return ob_get_clean();
	}

	/**
	 * Render curriculum for a student with server-side access checks.
	 *
	 * @param int $user_id User ID.
	 * @param int $course_id Course ID.
	 * @return string
	 */
	private function render_curriculum_for_student( $user_id, $course_id ) {
		if ( ! GCM_Enrollment_Service::has_access( $user_id, $course_id ) ) {
			return '<p>' . esc_html__( 'This course is not currently active.', 'giga-class-market' ) . '</p>';
		}

		$modules = GCM_Curriculum_Service::get_course_curriculum( $course_id );
		ob_start();
		foreach ( $modules as $module ) :
			?>
			<div class="gcm-module">
				<h4><?php echo esc_html( $module['title'] ); ?></h4>
				<?php foreach ( $module['lessons'] as $lesson ) : ?>
					<div class="gcm-lesson">
						<strong><?php echo esc_html( $lesson['title'] ); ?></strong>
						<?php if ( ! empty( $lesson['video_url'] ) ) : ?>
							<p><a href="<?php echo esc_url( $lesson['video_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open lesson video', 'giga-class-market' ); ?></a></p>
						<?php endif; ?>
						<div><?php echo wp_kses_post( wpautop( $lesson['content'] ) ); ?></div>
						<button type="button" class="gcm-complete-lesson" data-lesson-id="<?php echo esc_attr( $lesson['id'] ); ?>"><?php esc_html_e( 'Mark complete', 'giga-class-market' ); ?></button>
					</div>
				<?php endforeach; ?>
			</div>
			<?php
		endforeach;
		return ob_get_clean();
	}

	/**
	 * Determine if current page is a student/dashboard private page.
	 *
	 * @return bool
	 */
	private function is_student_page() {
		return is_page( array( 'student-dashboard', 'course-learn' ) );
	}

	/**
	 * Teacher dashboard page.
	 *
	 * @return bool
	 */
	private function is_teacher_page() {
		return is_page( array( 'teacher-dashboard' ) );
	}
}
