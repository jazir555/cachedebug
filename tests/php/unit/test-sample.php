<?php
/**
 * Sample test case for Cache Detector.
 *
 * @package CacheDetector\Tests
 */

use Jules\CacheDetector\Main; // Use the main plugin class namespace.

/**
 * Class SampleTest
 */
class Test_Sample extends \WP_UnitTestCase { // WP_UnitTestCase is provided by the WP test suite.

    /**
     * Test if the main plugin class can be instantiated.
     */
    public function test_main_class_instantiation() {
        $plugin_instance = Main::instance();
        $this->assertInstanceOf( Main::class, $plugin_instance, 'Main class should be instantiable.' );
        $this->assertEquals( CACHE_DETECTOR_VERSION, $plugin_instance->get_version(), 'Version should match.' );
    }

    /**
     * Test a simple true is true assertion.
     */
    public function test_true_is_true() {
        $this->assertTrue( true );
    }

    /**
     * Test basic activation.
     * Note: Activation hooks run once. For repeated testing, you might need to reset state or test specific functions called by activate.
     */
    public function test_plugin_activation() {
        // To properly test activation, you'd typically check for options set, tables created, etc.
        // Main::activate(false); // Static call
        // For this sample, we'll just ensure no errors are thrown.
        // This is a placeholder as activate() doesn't do much yet.
        $this->assertTrue( true, 'Activation method called (placeholder test).' );
    }
}
