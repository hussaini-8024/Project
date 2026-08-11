<?php
/**
 * Template Name: GCM Student Login
 *
 * Branded student login — authenticates on this page (does not send users to wp-login.php).
 *
 * @package GigaClassMarket
 */

if ( is_user_logged_in() ) {
	$user = wp_get_current_user();
	if ( user_can( $user, 'manage_options' ) ) {
		wp_safe_redirect( admin_url() );
	} else {
		wp_safe_redirect( home_url( '/student-dashboard/' ) );
	}
	exit;
}

$redirect_to = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : home_url( '/student-dashboard/' );
if ( ! $redirect_to || false !== strpos( $redirect_to, 'wp-login.php' ) ) {
	$redirect_to = home_url( '/student-dashboard/' );
}

$action      = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : 'login';
$error       = '';
$info        = '';
$login_value = '';

if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['gcm_login_nonce'] ) ) {
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gcm_login_nonce'] ) ), 'gcm_student_login' ) ) {
		$error = __( 'Security check failed. Please try again.', 'giga-class-market' );
	} elseif ( 'lostpassword' === $action ) {
		$user_login = isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) : '';
		if ( '' === $user_login ) {
			$error = __( 'Enter your email or username to reset your password.', 'giga-class-market' );
		} else {
			$result = retrieve_password( $user_login );
			if ( is_wp_error( $result ) ) {
				$error = $result->get_error_message();
			} else {
				$info = __( 'Check your email for the password reset link.', 'giga-class-market' );
			}
		}
	} else {
		$login_value = isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : '';
		$password    = isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : '';
		$remember    = ! empty( $_POST['rememberme'] );

		if ( '' === $login_value || '' === $password ) {
			$error = __( 'Please enter your email/username and password.', 'giga-class-market' );
		} else {
			$creds = array(
				'user_login'    => $login_value,
				'user_password' => $password,
				'remember'      => $remember,
			);

			// Allow email login.
			if ( is_email( $login_value ) ) {
				$user_by_email = get_user_by( 'email', $login_value );
				if ( $user_by_email ) {
					$creds['user_login'] = $user_by_email->user_login;
				}
			}

			$user = wp_signon( $creds, is_ssl() );
			if ( is_wp_error( $user ) ) {
				$error = __( 'Invalid login details. Please check your student credentials and try again.', 'giga-class-market' );
			} else {
				if ( user_can( $user, 'manage_options' ) ) {
					$target = admin_url();
				} elseif ( in_array( 'gcm_student', (array) $user->roles, true ) ) {
					$target = $redirect_to ? $redirect_to : home_url( '/student-dashboard/' );
				} else {
					$target = home_url( '/' );
				}
				wp_safe_redirect( $target );
				exit;
			}
		}
	}
}

get_header();
?>
<section class="gcm-auth-page">
	<div class="gcm-container gcm-auth-page__grid">
		<div class="gcm-auth-page__copy gcm-animate">
			<p class="gcm-eyebrow"><?php esc_html_e( 'Student access', 'giga-class-market' ); ?></p>
			<h1><?php esc_html_e( 'Continue your premium learning path', 'giga-class-market' ); ?></h1>
			<p><?php esc_html_e( 'Sign in with the student credentials sent to your email or WhatsApp. You will land on your student dashboard — not the WordPress admin.', 'giga-class-market' ); ?></p>
		</div>

		<?php if ( 'lostpassword' === $action ) : ?>
			<form class="gcm-auth-card gcm-animate" method="post" action="<?php echo esc_url( add_query_arg( 'action', 'lostpassword', home_url( '/login/' ) ) ); ?>">
				<?php wp_nonce_field( 'gcm_student_login', 'gcm_login_nonce' ); ?>
				<input type="hidden" name="action" value="lostpassword">
				<?php if ( $error ) : ?>
					<p class="gcm-form-message gcm-form-message--error" role="alert"><?php echo esc_html( $error ); ?></p>
				<?php endif; ?>
				<?php if ( $info ) : ?>
					<p class="gcm-form-message gcm-form-message--success" role="status"><?php echo esc_html( $info ); ?></p>
				<?php endif; ?>
				<label>
					<span><?php esc_html_e( 'Email or username', 'giga-class-market' ); ?></span>
					<input type="text" name="user_login" required autocomplete="username">
				</label>
				<button class="gcm-button gcm-button--gold gcm-button--full" type="submit"><?php esc_html_e( 'Email reset link', 'giga-class-market' ); ?></button>
				<a class="gcm-auth-card__link" href="<?php echo esc_url( home_url( '/login/' ) ); ?>"><?php esc_html_e( 'Back to login', 'giga-class-market' ); ?></a>
			</form>
		<?php else : ?>
			<form class="gcm-auth-card gcm-animate" method="post" action="<?php echo esc_url( home_url( '/login/' ) ); ?>">
				<?php wp_nonce_field( 'gcm_student_login', 'gcm_login_nonce' ); ?>
				<?php if ( $error ) : ?>
					<p class="gcm-form-message gcm-form-message--error" role="alert"><?php echo esc_html( $error ); ?></p>
				<?php endif; ?>
				<label>
					<span><?php esc_html_e( 'Email or username', 'giga-class-market' ); ?></span>
					<input type="text" name="log" required autocomplete="username" value="<?php echo esc_attr( $login_value ); ?>">
				</label>
				<label>
					<span><?php esc_html_e( 'Password', 'giga-class-market' ); ?></span>
					<input type="password" name="pwd" required autocomplete="current-password">
				</label>
				<label class="gcm-checkbox">
					<input type="checkbox" name="rememberme" value="forever">
					<span><?php esc_html_e( 'Remember me', 'giga-class-market' ); ?></span>
				</label>
				<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>">
				<button class="gcm-button gcm-button--gold gcm-button--full" type="submit"><?php esc_html_e( 'Login', 'giga-class-market' ); ?></button>
				<a class="gcm-auth-card__link" href="<?php echo esc_url( add_query_arg( 'action', 'lostpassword', home_url( '/login/' ) ) ); ?>"><?php esc_html_e( 'Forgot password?', 'giga-class-market' ); ?></a>
			</form>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
