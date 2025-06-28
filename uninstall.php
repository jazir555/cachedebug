<?php
/**
 * Uninstall Cache Detector
 *
 * Uninstalls the plugin and deletes options and transients.
 *
 * @package CacheDetector
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Note: Transients are generally auto-expired.
// Explicitly deleting them here is for thorough cleanup, especially if they store large data
// or if the plugin is not going to be used again.
// For user-specific transients like those used in this plugin (cd_assets_..., cd_rest_calls_...),
// they will eventually expire. A general cleanup of all plugin transients could be done
// if a very consistent prefix was used for ALL transients set by the plugin and there's a way
// to list/delete them, but this is often complex and potentially risky.

// Example: If the plugin stored options:
// delete_option( 'cache_detector_settings' );

// No options are currently stored by this plugin.
// Transients are user-specific and page-specific and will expire.
// A more aggressive cleanup of transients would require iterating through all options
// with `_transient_cd_%` which is not recommended for performance on uninstall.

if ( defined('WP_DEBUG') && WP_DEBUG ) {
    error_log('[Cache Detector] Uninstall script run. No specific options to delete. Transients will auto-expire.');
}
