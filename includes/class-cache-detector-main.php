<?php
/**
 * Main Cache Detector Class
 *
 * @package CacheDetector
 */

namespace Jules\CacheDetector;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 */
final class Main {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public $version;

	/**
	 * The single instance of the class.
	 *
	 * @var Main|null
	 */
	private static $_instance = null;

	/**
	 * Admin handler instance.
	 *
	 * @var Admin|null
	 */
	public $admin = null;

	/**
	 * Public handler instance.
	 * This will store the instance of Public_Handler.
	 *
	 * @var Public_Handler|null
	 */
	public $public_handler = null;

    /**
     * Holds the cache status determined by Public_Handler for the current frontend request.
     * This allows the Admin class (specifically the admin bar) to access it.
     * @var string
     */
    public $public_request_cache_status;

    /**
     * Holds the headers collected by Public_Handler.
     * @var array
     */
    public $public_request_headers = array();


	/**
	 * Main Cache_Detector Instance.
	 *
	 * Ensures only one instance of Cache_Detector is loaded or can be loaded.
	 *
	 * @static
	 * @return Main - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->version = CACHE_DETECTOR_VERSION;
		$this->public_request_cache_status = __('UNKNOWN (Main Init)', 'cache-detector');
		$this->setup_constants();
		$this->includes();
		$this->init_hooks();

		$this->admin   = new Admin( $this ); // Pass main instance for communication
		$this->public_handler  = new Public_Handler( $this ); // Pass main instance
	}

	/**
	 * Setup plugin constants.
	 *
	 * @access private
	 */
	private function setup_constants() {
		// CACHE_DETECTOR_PLUGIN_FILE will be defined in the root plugin file (cache-detector.php)
		// before this class is instantiated. We'll add CACHE_DETECTOR_REGISTER_FILE there.
	}

	/**
	 * Include required core files used in admin and public.
	 */
	private function includes() {
		require_once CACHE_DETECTOR_PLUGIN_DIR . 'admin/class-cache-detector-admin.php';
		require_once CACHE_DETECTOR_PLUGIN_DIR . 'public/class-cache-detector-public.php';
	}

	/**
	 * Hook into actions and filters.
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
		// Activation/deactivation hooks will be registered in the main plugin file (cache-detector.php)
		// using CACHE_DETECTOR_REGISTER_FILE, and will call static methods of this class.
	}

	/**
	 * Actions to perform once all plugins are loaded.
	 */
	public function on_plugins_loaded() {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Cache Detector] Main class initialized via plugins_loaded.' );
		}
		load_plugin_textdomain( 'cache-detector', false, dirname( plugin_basename( CACHE_DETECTOR_PLUGIN_FILE ) ) . '/languages/' );
	}

	/**
	 * Plugin activation. Static method to be called by register_activation_hook.
	 */
	public static function activate( $network_wide ) {
		// $network_wide is true if network activating on a multisite install
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Cache Detector] Plugin activated (from Main class static method).' );
		}
		// Activation code here (e.g., set default options, flush rewrite rules if CPTs were added).
	}

	/**
	 * Plugin deactivation. Static method to be called by register_deactivation_hook.
	 */
	public static function deactivate() {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Cache Detector] Plugin deactivated (from Main class static method).' );
		}
		// Deactivation code here (e.g., remove cron jobs).
	}

	/**
	 * Get the plugin version.
	 *
	 * @return string
	 */
	public function get_version() {
		return $this->version;
	}
}
