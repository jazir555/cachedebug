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

global $wpdb;

// Delete user-specific REST API call transients
// Transients are stored in the options table with names like:
// _transient_cd_rest_calls_{user_id}
// _transient_timeout_cd_rest_calls_{user_id}

// We can use a LIKE query to find all such option names.
$rest_transient_options = $wpdb->get_col(
    $wpdb->prepare(
        "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s OR option_name LIKE %s",
        '_transient_cd_rest_calls_%',
        '_transient_timeout_cd_rest_calls_%'
    )
);

$deleted_count = 0;
if ( ! empty( $rest_transient_options ) ) {
    foreach ( $rest_transient_options as $option_name ) {
        // To use delete_transient(), we need the base key (without _transient_ or _transient_timeout_)
        if ( strpos( $option_name, '_transient_timeout_' ) === 0 ) {
            $base_key = substr( $option_name, strlen( '_transient_timeout_' ) );
        } elseif ( strpos( $option_name, '_transient_' ) === 0 ) {
            $base_key = substr( $option_name, strlen( '_transient_' ) );
        } else {
            // Should not happen with the LIKE query, but as a safeguard
            $base_key = null;
        }

        if ( $base_key && strpos( $base_key, 'cd_rest_calls_' ) === 0 ) {
            delete_transient( $base_key ); // This will delete both the transient and its timeout.
            if ( strpos( $option_name, '_transient_timeout_' ) !== 0 ) { // Count actual transients, not timeouts separately for this log.
                 $deleted_count++;
            }
        }
    }
}

// Asset transients 'cd_assets_{md5(page_url_user_id)}' are harder to clean up without knowing all page URLs and user IDs.
// The md5 hash makes wildcard SQL deletion impractical for these specific keys without iterating all options.
// Relying on auto-expiration for these is a more practical approach to avoid performance issues on uninstall.

// No persistent options are currently stored by this plugin that need deletion.

if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log('[Cache Detector] Uninstall script run. Attempted to delete ' . $deleted_count . ' user-specific REST call transients (cd_rest_calls_*). Asset-related transients (cd_assets_*) will auto-expire.');
}
