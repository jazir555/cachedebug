<?php
/**
 * Plugin Name:       Cache Detector
 * Plugin URI:        https://example.com/plugins/cache-detector/
 * Description:       Detects and displays cache status for loaded URLs in WordPress.
 * Version:           0.2.0
 * Author:            Jules AI Assistant
 * Author URI:        https://example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cache-detector
 * Domain Path:       /languages
 * Requires PHP:      7.0
 * Requires at least: 5.2
 * Namespace:         Jules\CacheDetector
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Define plugin constants.
define( 'CACHE_DETECTOR_VERSION', '0.2.0' );
define( 'CACHE_DETECTOR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CACHE_DETECTOR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CACHE_DETECTOR_PLUGIN_FILE', __FILE__ ); // Main plugin file path.
define( 'CACHE_DETECTOR_REGISTER_FILE', __FILE__ ); // File used for activation/deactivation hooks.

// PSR-4 Autoloader.
spl_autoload_register(
	function ( $class ) {
		// Project-specific namespace prefix.
		$prefix = 'Jules\\CacheDetector\\';

		// Base directory for the namespace prefix.
		$base_dir = CACHE_DETECTOR_PLUGIN_DIR . 'includes/';

		// Does the class use the namespace prefix?
		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			// No, move to the next registered autoloader.
			return;
		}

		// Get the relative class name.
		$relative_class = substr( $class, $len );

        // Replace the namespace prefix with the base directory, replace namespace
        // separators with directory separators in the relative class name, append
        // with .php.
        // Specific handling for Admin and Public classes in subdirectories.
        if ( $relative_class === 'Admin' ) {
            $file = CACHE_DETECTOR_PLUGIN_DIR . 'admin/class-cache-detector-admin.php';
        } elseif ( $relative_class === 'Public_Handler' ) {
            $file = CACHE_DETECTOR_PLUGIN_DIR . 'public/class-cache-detector-public.php';
        } else {
            // General includes path for other classes like Main.
            // e.g. Jules\CacheDetector\Main -> includes/class-cache-detector-main.php
            $file = $base_dir . 'class-cache-detector-' . str_replace( '_', '-', strtolower( $relative_class ) ) . '.php';
        }

		// If the file exists, require it.
		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

/**
 * The main function for running the plugin.
 *
 * @return \Jules\CacheDetector\Main
 */
function cache_detector_run() {
	return \Jules\CacheDetector\Main::instance();
}

// Register activation and deactivation hooks using static methods from the Main class.
register_activation_hook( CACHE_DETECTOR_REGISTER_FILE, array( 'Jules\\CacheDetector\\Main', 'activate' ) );
register_deactivation_hook( CACHE_DETECTOR_REGISTER_FILE, array( 'Jules\\CacheDetector\\Main', 'deactivate' ) );

// Let's get this party started.
cache_detector_run();

if ( defined('WP_DEBUG') && WP_DEBUG ) {
    error_log('[Cache Detector] Plugin main file loaded and run function called.');
}
