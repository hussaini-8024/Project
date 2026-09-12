<?php
/**
 * Plugin Name: PKCouncil Core
 * Plugin URI: https://pkcouncil.org
 * Description: Custom learning platform for PKCouncil — courses, payments, student/teacher portals, quizzes, chat, and administration. Business logic lives here, not in the theme.
 * Version: 1.0.2
 * Author: PKCouncil
 * Author URI: https://pkcouncil.org
 * License: GPL-2.0-or-later
 * Text Domain: pkcouncil
 * Requires at least: 6.4
 * Requires PHP: 8.1
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PKC_VERSION', '1.0.2');
define('PKC_FILE', __FILE__);
define('PKC_DIR', plugin_dir_path(__FILE__));
define('PKC_URL', plugin_dir_url(__FILE__));
define('PKC_BRAND', 'PKCouncil');

require_once PKC_DIR . 'includes/class-db.php';
require_once PKC_DIR . 'includes/helpers.php';
require_once PKC_DIR . 'includes/class-security.php';
require_once PKC_DIR . 'includes/class-audit.php';
require_once PKC_DIR . 'includes/class-mailer.php';
require_once PKC_DIR . 'includes/class-settings.php';
require_once PKC_DIR . 'includes/class-auth.php';
require_once PKC_DIR . 'includes/class-access.php';
require_once PKC_DIR . 'includes/class-coupons.php';
require_once PKC_DIR . 'includes/class-payments.php';
require_once PKC_DIR . 'includes/class-progress.php';
require_once PKC_DIR . 'includes/class-materials.php';
require_once PKC_DIR . 'includes/class-quiz.php';
require_once PKC_DIR . 'includes/class-chat.php';
require_once PKC_DIR . 'includes/class-notifications.php';
require_once PKC_DIR . 'includes/class-activator.php';
require_once PKC_DIR . 'includes/class-seed.php';
require_once PKC_DIR . 'includes/class-rewrites.php';
require_once PKC_DIR . 'includes/class-rest.php';
require_once PKC_DIR . 'includes/class-login-lock.php';
require_once PKC_DIR . 'admin/class-admin.php';
require_once PKC_DIR . 'includes/class-plugin.php';

register_activation_hook(__FILE__, array('PKC_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('PKC_Activator', 'deactivate'));

add_action('plugins_loaded', array('PKC_Plugin', 'instance'));
