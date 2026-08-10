<?php
/**
 * Plugin Name: WP Integration Toolkit
 * Plugin URI:  https://github.com/DagemawiDeveloper/wordpress-plugin-development-demo
 * Description: A production-style reference plugin for secure inbound/outbound webhooks, REST endpoints, AJAX actions, logging, retries, and maintainable WordPress integrations.
 * Version:     1.0.0
 * Author:      Dagemawi Alemayehu
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-integration-toolkit
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WPITK_VERSION', '1.0.0' );
define( 'WPITK_FILE', __FILE__ );
define( 'WPITK_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPITK_URL', plugin_dir_url( __FILE__ ) );

require_once WPITK_PATH . 'includes/class-wpitk-activator.php';
require_once WPITK_PATH . 'includes/class-wpitk-crypto.php';
require_once WPITK_PATH . 'includes/class-wpitk-logger.php';
require_once WPITK_PATH . 'includes/class-wpitk-webhook-service.php';
require_once WPITK_PATH . 'includes/class-wpitk-rest-controller.php';
require_once WPITK_PATH . 'includes/class-wpitk-admin.php';
require_once WPITK_PATH . 'includes/class-wpitk-plugin.php';

register_activation_hook( __FILE__, array( 'WPITK_Activator', 'activate' ) );

function wpitk_bootstrap() {
    $plugin = new WPITK_Plugin();
    $plugin->run();
}
add_action( 'plugins_loaded', 'wpitk_bootstrap' );
