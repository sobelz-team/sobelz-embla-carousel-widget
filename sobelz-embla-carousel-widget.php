<?php
/**
 * Plugin Name: sobelz-embla-carousel-widget
 * Description: A responsive Elementor carousel with locally bundled Embla scripts and on-demand plugins.
 * Version: 1.1.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Requires Plugins: elementor
 * Author: Sobelz
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sobelz-embla-carousel-widget
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EEW_VERSION', '1.1.0' );
define( 'EEW_FILE', __FILE__ );
define( 'EEW_PATH', plugin_dir_path( __FILE__ ) );
define( 'EEW_URL', plugin_dir_url( __FILE__ ) );

require_once EEW_PATH . 'includes/class-plugin.php';
add_action( 'plugins_loaded', [ '\EEW\Plugin', 'boot' ], 20 );
