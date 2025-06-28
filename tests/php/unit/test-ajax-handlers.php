<?php
/**
 * Test case for Cache Detector AJAX Handlers.
 *
 * @package CacheDetector\Tests
 */

use Jules\CacheDetector\Admin;
use Jules\CacheDetector\Main;
use Jules\CacheDetector\Public_Handler;

/**
 * Class Test_Ajax_Handlers
 * Needs to extend WP_Ajax_UnitTestCase for testing AJAX handlers properly.
 */
class Test_Ajax_Handlers extends \WP_Ajax_UnitTestCase {

    private $admin_handler_instance; // Store the instance of Admin class
    private $main_plugin_mock;
    private $public_handler_mock;
    private static $user_id;

    public static function wpSetUpBeforeClass( $factory ) {
        self::$user_id = $factory->user->create( array( 'role' => 'administrator' ) );
    }

    public function setUp(): void {
        parent::setUp(); // This will set the current user if a test class property $user_id is defined.

        wp_set_current_user( self::$user_id );

        // Mock Main and Public_Handler as Admin class depends on them.
        $this->main_plugin_mock = $this->createMock(Main::class);
        $this->public_handler_mock = $this->createMock(Public_Handler::class);

        // Configure the main plugin mock to return the public handler mock
        $this->main_plugin_mock->method('get_version')->willReturn('0.2.0');
        $this->main_plugin_mock->public_handler = $this->public_handler_mock; // Make public_handler a public property or use a getter if it's private/protected

        $this->admin_handler_instance = new Admin($this->main_plugin_mock);

        // AJAX actions are hooked in Admin constructor.
        // For WP_Ajax_UnitTestCase, we usually call the target method directly after setting up $_POST, $_GET.
    }

    public function test_handle_receive_assets_ajax_success() {
        $asset_data_payload = json_encode([
            ['url' => 'http://example.com/style.css', 'status' => 'HIT', 'transferSize' => 100, 'decodedBodySize' => 200, 'initiatorType' => 'link', 'detectedBy' => 'Test', 'serverTiming' => ['cdn-cache; desc=HIT']]
        ]);
        $page_url = 'http://example.com/test-page';

        $_POST['action'] = 'cache_detector_receive_assets'; // Action name
        $_POST['asset_data'] = $asset_data_payload;
        $_POST['page_url'] = $page_url;
        $_POST['nonce'] = wp_create_nonce('cache_detector_asset_nonce');

        try {
            $this->_handleAjax('cache_detector_receive_assets'); // WP_Ajax_UnitTestCase internal method
        } catch (\WPAjaxDieContinueException $e) {
            // Expected behavior for successful wp_send_json_success
        }

        $response = json_decode($this->_last_response, true);
        $this->assertTrue($response['success']);
        $this->assertEquals(1, $response['data']['count']);

        $transient_key = 'cd_assets_' . md5( $page_url . '_' . self::$user_id );
        $stored_data = get_transient($transient_key);

        $this->assertNotFalse($stored_data, 'Transient should be set.');
        $this->assertIsArray($stored_data);
        $this->assertCount(1, $stored_data);
        $this->assertEquals('http://example.com/style.css', $stored_data[0]['url']);
        $this->assertEquals('Test', $stored_data[0]['detectedBy']);

        delete_transient($transient_key);
    }

    public function test_handle_receive_assets_ajax_permission_denied() {
        wp_set_current_user(0); // Logged out user

        $_POST['action'] = 'cache_detector_receive_assets';
        $_POST['asset_data'] = json_encode([]);
        $_POST['page_url'] = 'http://example.com';
        $_POST['nonce'] = wp_create_nonce('cache_detector_asset_nonce');

        try {
            $this->_handleAjax('cache_detector_receive_assets');
        } catch (\WPAjaxDieStopException $e) { // wp_send_json_error calls wp_die which is caught by this
            // Check the response
            $response = json_decode($this->_last_response, true);
            $this->assertFalse($response['success']);
            $this->assertEquals(esc_html__('Permission denied.', 'cache-detector'), $response['data']['message']);
        }
        // Reset user for other tests
        wp_set_current_user( self::$user_id );
    }


    public function test_handle_receive_rest_api_calls_ajax_success() {
        $rest_calls_payload = json_encode([
            ['url' => 'http://example.com/wp-json/wp/v2/posts', 'method' => 'GET', 'status' => 200, 'headers' => ['cf-cache-status: HIT']]
        ]);
        $page_url = 'http://example.com/initiating-page';

        $this->public_handler_mock->method('analyze_headers')
                                   ->willReturn('HIT by Cloudflare');

        $_POST['action'] = 'cache_detector_receive_rest_api_calls';
        $_POST['rest_api_calls'] = $rest_calls_payload;
        $_POST['page_url'] = $page_url;
        $_POST['nonce'] = wp_create_nonce('cache_detector_rest_api_nonce');


        try {
            // Directly call the method hooked to WP AJAX action for testing with WP_Ajax_UnitTestCase
            // The hook is 'wp_ajax_cache_detector_receive_rest_api_calls' which maps to
            // $this->admin_handler_instance->handle_receive_rest_api_calls_ajax()
            // So, we'd ideally call that method directly or use _handleAjax if the handler is correctly registered
            // For _handleAjax to work, the Admin class instance and its hooks need to be set up during WP's ajax_loaded action.
            // Let's ensure the instance used by the AJAX handler is our $this->admin_handler_instance
            // This is typically done by adding the action in setUp or a test method.
            // Since Admin constructor adds the hooks, and we instantiate Admin in setUp, it should be fine.

            $this->_handleAjax('cache_detector_receive_rest_api_calls');
        } catch (\WPAjaxDieContinueException $e) {
            // Expected for wp_send_json_success
        }

        $response = json_decode($this->_last_response, true);
        $this->assertTrue($response['success']);
        $this->assertEquals(1, $response['data']['count']);


        $transient_key = 'cd_rest_calls_' . self::$user_id;
        $stored_data = get_transient($transient_key);

        $this->assertNotFalse($stored_data);
        $this->assertIsArray($stored_data);
        $this->assertCount(1, $stored_data);
        $this->assertEquals('http://example.com/wp-json/wp/v2/posts', $stored_data[0]['url']);
        $this->assertEquals('HIT by Cloudflare', $stored_data[0]['cache_status']);

        delete_transient($transient_key);
    }

    public function tearDown(): void {
        parent::tearDown();
        // Clean up superglobals if modified, though WP_Ajax_UnitTestCase might handle some of this.
        $_POST = array();
        $_GET = array();
    }

    // WP_Ajax_UnitTestCase requires this method if you're testing actions hooked by a class instance.
    // It tells the test case which instance's methods are handling the AJAX actions.
    // However, our hooks are added in the constructor of Admin.
    // The test environment might need a way to get this instance.
    // An alternative is to make admin_handler_instance static or access it via a global if necessary,
    // or re-add the action in the test pointing to $this->admin_handler_instance.
    // For now, WP_Ajax_UnitTestCase should find the hook if it was added by the Admin constructor.
}
