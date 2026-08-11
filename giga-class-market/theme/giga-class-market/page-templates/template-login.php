<?php
/**
 * Template Name: GCM Student Login
 *
 * @package GigaClassMarket
 */

if ( is_user_logged_in() ) {
	wp_safe_redirect( gcm_setting( 'student_dashboard_url', home_url( '/student-dashboard/' ) ) );
	exit;
}

get_header();
?>
<section class="gcm-auth-page">
	<div class="gcm-container gcm-auth-page__grid">
		<div class="gcm-auth-page__copy gcm-animate">
			<p class="gcm-eyebrow"><?php esc_html_e( 'Student access', 'giga-class-market' ); ?></p>
			<h1><?php esc_html_e( 'Continue your premium learning path', 'giga-class-market' ); ?></h1>
			<p><?php esc_html_e( 'Sign in to view your courses, track progress, and keep learning where you left off.', 'giga-class-market' ); ?></p>
		</div>
		<form class="gcm-auth-card gcm-animate" method="post" action="<?php echo esc_url( wp_login_url( gcm_setting( 'student_dashboard_url', home_url( '/student-dashboard/' ) ) ) ); ?>">
			<?php wp_nonce_field( 'gcm_student_login', 'gcm_login_nonce' ); ?>
			<label>
				<span><?php esc_html_e( 'Email or username', 'giga-class-market' ); ?></span>
				<input type="text" name="log" required autocomplete="username">
			</label>
			<label>
				<span><?php esc_html_e( 'Password', 'giga-class-market' ); ?></span>
				<input type="password" name="pwd" required autocomplete="current-password">
			</label>
			<label class="gcm-checkbox">
				<input type="checkbox" name="rememberme" value="forever">
				<span><?php esc_html_e( 'Remember me', 'giga-class-market' ); ?></span>
			</label>
			<button class="gcm-button gcm-button--gold gcm-button--full" type="submit"><?php esc_html_e( 'Login', 'giga-class-market' ); ?></button>
			<a class="gcm-auth-card__link" href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Forgot password?', 'giga-class-market' ); ?></a>
		</form>
	</div>
</section>
<?php
get_footer();
