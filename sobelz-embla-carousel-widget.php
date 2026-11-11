<?php
/**
 * Plugin Name: sobelz-embla-carousel-widget
 * Description: A responsive Elementor carousel with locally bundled Embla scripts and on-demand plugins.
 * Version: 1.3.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Requires Plugins: elementor
 * Author: Sobelz
 * Update URI: https://plugins.sobelz.ir/sobelz-embla-carousel-widget
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sobelz-embla-carousel-widget
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EEW_VERSION', '1.3.0' );
define( 'EEW_FILE', __FILE__ );
define( 'EEW_PATH', plugin_dir_path( __FILE__ ) );
define( 'EEW_URL', plugin_dir_url( __FILE__ ) );

require_once EEW_PATH . 'includes/updater/class-sobelz-plugin-updater.php';
\Sobelz\PluginUpdater\V1\Updater::register( [
	'plugin_file' => EEW_FILE,
	'slug'        => 'sobelz-embla-carousel-widget',
	'update_uri'  => 'https://plugins.sobelz.ir/sobelz-embla-carousel-widget',
] );

require_once EEW_PATH . 'includes/class-plugin.php';
add_action( 'plugins_loaded', [ '\EEW\Plugin', 'boot' ], 20 );
