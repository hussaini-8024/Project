<?php
/**
 * Theme header.
 *
 * @package GigaClassMarket
 */

?><!doctype html>
<html <?php language_attributes(); ?> data-theme="<?php echo esc_attr( gcm_get_initial_theme() ); ?>">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#primary"><?php esc_html_e( 'Skip to content', 'giga-class-market' ); ?></a>

<header class="gcm-site-header" data-gcm-header>
	<div class="gcm-container gcm-header__inner">
		<button class="gcm-nav-toggle" type="button" aria-controls="gcm-primary-nav" aria-expanded="false">
			<span class="screen-reader-text"><?php esc_html_e( 'Open menu', 'giga-class-market' ); ?></span>
			<span></span><span></span><span></span>
		</button>

		<a class="gcm-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php esc_attr_e( 'Giga Class Market home', 'giga-class-market' ); ?>">
			<span class="gcm-brand__logo" aria-hidden="true">
				<?php
				$custom_logo_id = (int) get_theme_mod( 'custom_logo' );
				$custom_logo    = $custom_logo_id ? wp_get_attachment_image_url( $custom_logo_id, 'full' ) : '';
				if ( $custom_logo ) :
					?>
					<img class="gcm-brand__logo-img" src="<?php echo esc_url( $custom_logo ); ?>" alt="<?php esc_attr_e( 'Giga Class Market', 'giga-class-market' ); ?>" width="42" height="42">
				<?php else : ?>
					<svg viewBox="0 0 48 48" width="42" height="42" fill="none" xmlns="http://www.w3.org/2000/svg">
						<defs>
							<linearGradient id="gcmLogoGrad" x1="6" y1="4" x2="42" y2="44" gradientUnits="userSpaceOnUse">
								<stop stop-color="#F0D08E"/>
								<stop offset="0.55" stop-color="#E0A045"/>
								<stop offset="1" stop-color="#A86B1E"/>
							</linearGradient>
						</defs>
						<rect x="2" y="2" width="44" height="44" rx="14" fill="#0D3B45"/>
						<path d="M14 30.5V17.5h9.2c3.7 0 6.1 2.1 6.1 5.2 0 3.2-2.4 5.3-6.1 5.3H19.2V30.5H14zm5.2-7.3h3.7c1.5 0 2.4-.8 2.4-2.1s-.9-2.1-2.4-2.1h-3.7v4.2z" fill="url(#gcmLogoGrad)"/>
						<path d="M33.8 17.5l-4.2 13h-3.9l4.2-13h3.9z" fill="url(#gcmLogoGrad)"/>
					</svg>
				<?php endif; ?>
			</span>
			<span class="gcm-brand__text">
				<span class="gcm-brand__name">Giga Class Market</span>
				<span class="gcm-brand__tag"><?php esc_html_e( 'Premium Learning', 'giga-class-market' ); ?></span>
			</span>
		</a>

		<nav id="gcm-primary-nav" class="gcm-primary-nav" aria-label="<?php esc_attr_e( 'Primary menu', 'giga-class-market' ); ?>">
			<ul class="gcm-menu">
				<li><a href="<?php echo esc_url( get_post_type_archive_link( 'gcm_course' ) ?: home_url( '/courses/' ) ); ?>"><?php esc_html_e( 'Courses', 'giga-class-market' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/portfolio/' ) ); ?>"><?php esc_html_e( 'Portfolio', 'giga-class-market' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Contact Us', 'giga-class-market' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>"><?php esc_html_e( 'About Us', 'giga-class-market' ); ?></a></li>
				<?php if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) : ?>
					<li class="gcm-menu__account"><a href="<?php echo esc_url( admin_url() ); ?>"><?php esc_html_e( 'Admin', 'giga-class-market' ); ?></a></li>
				<?php elseif ( is_user_logged_in() && current_user_can( 'gcm_teacher_dashboard' ) ) : ?>
					<li class="gcm-menu__account"><a href="<?php echo esc_url( home_url( '/teacher-dashboard/' ) ); ?>"><?php esc_html_e( 'Teacher Dashboard', 'giga-class-market' ); ?></a></li>
				<?php endif; ?>
			</ul>
		</nav>

		<div class="gcm-header__actions">
			<a class="gcm-header-link gcm-verify-link" href="<?php echo esc_url( home_url( '/verify-certificate/' ) ); ?>"><?php esc_html_e( 'Verify Certificate', 'giga-class-market' ); ?></a>
			<?php if ( is_user_logged_in() ) : ?>
				<a class="gcm-login-link gcm-logout-link" href="<?php echo esc_url( wp_logout_url( home_url( '/login/' ) ) ); ?>"><?php esc_html_e( 'Logout', 'giga-class-market' ); ?></a>
			<?php else : ?>
				<a class="gcm-login-link" href="<?php echo esc_url( gcm_student_login_url() ); ?>"><?php esc_html_e( 'Login', 'giga-class-market' ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</header>

<main id="primary" class="gcm-main">
