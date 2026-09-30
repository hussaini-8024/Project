<?php
/**
 * Template Name: GCM Contact
 *
 * Legacy URL. The website inquiry form now lives on the Services page.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_safe_redirect( gcm_services_url(), 301 );
exit;
