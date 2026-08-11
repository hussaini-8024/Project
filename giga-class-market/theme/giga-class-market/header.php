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
		<a class="gcm-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php esc_attr_e( 'Giga Class Market home', 'giga-class-market' ); ?>">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<span class="gcm-brand__mark" aria-hidden="true">G</span>
				<span class="gcm-brand__text">Giga Class Market</span>
			<?php endif; ?>
		</a>

		<button class="gcm-nav-toggle" type="button" aria-controls="gcm-primary-nav" aria-expanded="false">
			<span class="screen-reader-text"><?php esc_html_e( 'Open menu', 'giga-class-market' ); ?></span>
			<span></span><span></span><span></span>
		</button>

		<nav id="gcm-primary-nav" class="gcm-primary-nav" aria-label="<?php esc_attr_e( 'Primary menu', 'giga-class-market' ); ?>">
			<?php
			if ( has_nav_menu( 'primary' ) ) {
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'container'      => false,
						'menu_class'     => 'gcm-menu',
						'fallback_cb'    => false,
					)
				);
			} else {
				?>
				<ul class="gcm-menu">
					<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'giga-class-market' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>"><?php esc_html_e( 'About', 'giga-class-market' ); ?></a></li>
					<li><a href="<?php echo esc_url( get_post_type_archive_link( 'gcm_course' ) ?: home_url( '/courses/' ) ); ?>"><?php esc_html_e( 'Courses', 'giga-class-market' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Contact', 'giga-class-market' ); ?></a></li>
				</ul>
				<?php
			}
			?>
		</nav>

		<div class="gcm-header__actions">
			<a class="gcm-login-link" href="<?php echo esc_url( gcm_student_login_url() ); ?>"><?php esc_html_e( 'Login', 'giga-class-market' ); ?></a>
			<button class="gcm-theme-toggle" type="button" data-gcm-theme-toggle aria-label="<?php esc_attr_e( 'Toggle dark mode', 'giga-class-market' ); ?>">
				<span class="gcm-theme-toggle__sun" aria-hidden="true"></span>
				<span class="gcm-theme-toggle__moon" aria-hidden="true"></span>
			</button>
		</div>
	</div>
</header>

<main id="primary" class="gcm-main">
