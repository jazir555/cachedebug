<?php
/**
 * PHPUnit bootstrap file for Cache Detector plugin.
 */

// Determine the path to the WordPress tests directory.
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
    // Check if WP_DEVELOP_DIR is defined in phpunit.xml.dist
    // This constant is not directly available here, so we need to parse phpunit.xml or rely on environment.
    // For simplicity, we'll assume WP_DEVELOP_DIR is set if WP_TESTS_DIR isn't.
    // This usually means /tmp/wordpress-develop/ as defined in phpunit.xml.dist
    // This logic is a bit fragile and depends on the phpunit.xml config.
    // A better way is to define WP_TESTS_DIR directly as an env variable or in phpunit.xml <php> const.
    // We will assume installdependencies.sh sets up /tmp/wordpress-develop

    $_wp_develop_dir = getenv( 'WP_DEVELOP_DIR' );
    if ( ! $_wp_develop_dir && defined( 'WP_DEVELOP_DIR' ) ) { // Check if defined as a const by PHPUnit
        $_wp_develop_dir = WP_DEVELOP_DIR;
    } elseif ( ! $_wp_develop_dir ) {
         // Fallback if not defined by PHPUnit's XML constants nor env var.
        $_wp_develop_dir = '/tmp/wordpress-develop';
    }

    if ( file_exists( $_wp_develop_dir . '/tests/phpunit/includes/functions.php' ) ) {
        $_tests_dir = $_wp_develop_dir . '/tests/phpunit';
    } elseif ( file_exists( rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib/includes/functions.php' ) ) {
        // Standard WP test lib path if downloaded directly
        $_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
    } elseif ( file_exists( dirname( __DIR__, 2 ) . '/wordpress-develop/tests/phpunit/includes/functions.php' ) ) {
        // If wordpress-develop is cloned next to the plugin
         $_tests_dir = dirname( __DIR__, 2 ) . '/wordpress-develop/tests/phpunit';
    }
}


if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
    echo "Could not find WordPress PHPUnit test library at $_tests_dir/includes/functions.php" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo "Please run: bash installdependencies.sh" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
    // Load the main plugin file.
    // __DIR__ is tests/php, so need to go up two levels.
    require dirname( __DIR__, 2 ) . '/cache-detector.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';

// Ensure our namespace is available for tests
// Autoloader is in cache-detector.php, which is loaded by _manually_load_plugin

if ( did_action( 'init' ) ) {
    echo "Cache Detector PHPUnit Bootstrap completed. WP_VERSION: " . get_bloginfo( 'version' ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
} else {
    echo "Cache Detector PHPUnit Bootstrap: WordPress 'init' action did not run.\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
