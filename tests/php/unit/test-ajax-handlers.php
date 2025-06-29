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
        parent::setUp();

        wp_set_current_user( self::$user_id );

        $this->main_plugin_mock = $this->createMock(Main::class);
        $this->public_handler_mock = $this->createMock(Public_Handler::class);

        $this->main_plugin_mock->method('get_version')->willReturn('0.2.0');
        $this->main_plugin_mock->public_handler = $this->public_handler_mock;

        $this->admin_handler_instance = new Admin($this->main_plugin_mock);
    }

    public function tearDown(): void {
        // Clean up any transients we might have set
        $transient_key_assets_prefix = 'cd_assets_'; // Asset transients are page+user specific
        $transient_key_rest_prefix = 'cd_rest_calls_';

        // For REST calls, key is based on user_id only.
        delete_transient($transient_key_rest_prefix . self::$user_id);

        parent::tearDown();
        $_POST = array();
        $_GET = array();
    }

    // --- Tests for handle_receive_assets_ajax ---

    public function test_handle_receive_assets_ajax_success() {
        $asset_data_payload = json_encode([
            ['url' => 'http://example.com/style.css', 'status' => 'HIT by CDN', 'transferSize' => 100, 'decodedBodySize' => 200, 'initiatorType' => 'link', 'detectedBy' => 'TestAnalyzer', 'serverTiming' => ['cdn-cache; desc=HIT', 'origin; dur=123', ['name' => 'db', 'duration' => 50, 'description' => 'database']]]
        ]);
        $page_url = 'http://example.com/test-page';
        $transient_key = 'cd_assets_' . md5( $page_url . '_' . self::$user_id );
        delete_transient($transient_key); // Ensure clean state

        $_POST['action'] = 'cache_detector_receive_assets';
        $_POST['asset_data'] = $asset_data_payload;
        $_POST['page_url'] = $page_url;
        $_POST['nonce'] = wp_create_nonce('cache_detector_asset_nonce');

        try {
            $this->_handleAjax('cache_detector_receive_assets');
        } catch (\WPAjaxDieContinueException $e) {
            // Expected
        }

        $response = json_decode($this->_last_response, true);
        $this->assertTrue($response['success']);
        $this->assertEquals(1, $response['data']['count']);

        $stored_data = get_transient($transient_key);
        $this->assertNotFalse($stored_data);
        $this->assertIsArray($stored_data);
        $this->assertCount(1, $stored_data);
        $this->assertEquals('http://example.com/style.css', $stored_data[0]['url']);
        $this->assertEquals('TestAnalyzer', $stored_data[0]['detectedBy']);
        $this->assertEquals('HIT by CDN', $stored_data[0]['status']);
        $this->assertContains('cdn-cache: HIT', $stored_data[0]['serverTiming']);
        $this->assertContains('origin: 123ms', $stored_data[0]['serverTiming']);
        $this->assertContains('db: 50ms', $stored_data[0]['serverTiming']);


        delete_transient($transient_key);
    }

    public function test_handle_receive_assets_ajax_permission_denied() {
        wp_set_current_user(0);

        $_POST['action'] = 'cache_detector_receive_assets';
        $_POST['asset_data'] = json_encode([]);
        $_POST['page_url'] = 'http://example.com';
        $_POST['nonce'] = wp_create_nonce('cache_detector_asset_nonce');

        try {
            $this->_handleAjax('cache_detector_receive_assets');
        } catch (\WPAjaxDieStopException $e) {
            $response = json_decode($this->_last_response, true);
            $this->assertFalse($response['success']);
            $this->assertEquals(esc_html__('Permission denied.', 'cache-detector'), $response['data']['message']);
        }
        wp_set_current_user( self::$user_id );
    }

    public function test_handle_receive_assets_ajax_missing_data_payload() {
        $_POST['action'] = 'cache_detector_receive_assets';
        // $_POST['asset_data'] is missing
        $_POST['page_url'] = 'http://example.com/test-page';
        $_POST['nonce'] = wp_create_nonce('cache_detector_asset_nonce');

        try {
            $this->_handleAjax('cache_detector_receive_assets');
        } catch (\WPAjaxDieStopException $e) {
            $response = json_decode($this->_last_response, true);
            $this->assertFalse($response['success']);
            $this->assertEquals(esc_html__('Missing data.', 'cache-detector'), $response['data']['message']);
        }
    }

    public function test_handle_receive_assets_ajax_missing_page_url() {
        $_POST['action'] = 'cache_detector_receive_assets';
        $_POST['asset_data'] = json_encode([['url' => 'http://example.com/style.css']]);
        // $_POST['page_url'] is missing
        $_POST['nonce'] = wp_create_nonce('cache_detector_asset_nonce');

        try {
            $this->_handleAjax('cache_detector_receive_assets');
        } catch (\WPAjaxDieStopException $e) {
            $response = json_decode($this->_last_response, true);
            $this->assertFalse($response['success']);
            $this->assertEquals(esc_html__('Missing data.', 'cache-detector'), $response['data']['message']);
        }
    }

    public function test_handle_receive_assets_ajax_invalid_json() {
        $_POST['action'] = 'cache_detector_receive_assets';
        $_POST['asset_data'] = 'this is not json';
        $_POST['page_url'] = 'http://example.com/test-page';
        $_POST['nonce'] = wp_create_nonce('cache_detector_asset_nonce');

        try {
            $this->_handleAjax('cache_detector_receive_assets');
        } catch (\WPAjaxDieStopException $e) {
            $response = json_decode($this->_last_response, true);
            $this->assertFalse($response['success']);
            $this->assertStringContainsString(esc_html__('Invalid JSON data:', 'cache-detector'), $response['data']['message']);
        }
    }

    public function test_handle_receive_assets_ajax_sanitization_of_asset_data() {
        $asset_data_payload = json_encode([
            ['url' => 'http://example.com/script.js<script>alert("xss")</script>', 'status' => 'MISS <script>alert("status_xss")</script>', 'transferSize' => "100xx", 'decodedBodySize' => "200yy", 'initiatorType' => 'script<alert>', 'detectedBy' => 'Evil<alert>', 'serverTiming' => ['<script>alert("st_xss")</script>', 'valid; desc=OK', ['name' => 'evil<name>', 'description' => 'evil<desc>', 'duration' => "bad"]]]
        ]);
        $page_url = 'http://example.com/sanitization-test';
        $transient_key = 'cd_assets_' . md5( $page_url . '_' . self::$user_id );
        delete_transient($transient_key);

        $_POST['action'] = 'cache_detector_receive_assets';
        $_POST['asset_data'] = $asset_data_payload;
        $_POST['page_url'] = $page_url;
        $_POST['nonce'] = wp_create_nonce('cache_detector_asset_nonce');

        try {
            $this->_handleAjax('cache_detector_receive_assets');
        } catch (\WPAjaxDieContinueException $e) {
            // Expected
        }

        $stored_data = get_transient($transient_key);
        $this->assertNotFalse($stored_data);
        $this->assertCount(1, $stored_data);
        $item = $stored_data[0];

        // esc_url_raw doesn't escape < > but removes dangerous chars.
        // For the test, let's assume the goal is to prevent XSS, so what esc_url_raw does is usually sufficient.
        // If specific HTML entity encoding is expected, the sanitization function or test needs adjustment.
        $this->assertEquals('http://example.com/script.jsscriptalert("xss")/script', $item['url']);
        $this->assertEquals('MISS &lt;script&gt;alert("status_xss")&lt;/script&gt;', $item['status']); // sanitize_text_field
        $this->assertEquals(100, $item['transferSize']); // intval
        $this->assertEquals(200, $item['decodedBodySize']); // intval
        $this->assertEquals('script&lt;alert&gt;', $item['initiatorType']); // sanitize_text_field
        $this->assertEquals('Evil&lt;alert&gt;', $item['detectedBy']);     // sanitize_text_field
        $this->assertContains('&lt;script&gt;alert("st_xss")&lt;/script&gt;', $item['serverTiming']);
        $this->assertContains('valid: OK', $item['serverTiming']);
        $this->assertContains('evil&lt;name&gt;: evil&lt;desc&gt;', $item['serverTiming']);


        delete_transient($transient_key);
    }

    public function test_handle_receive_assets_ajax_empty_sanitized_data() {
        // Payload with only an invalid URL, which should be removed after sanitization
        $asset_data_payload = json_encode([
            ['url' => '<completely invalid url>', 'status' => 'ANY']
        ]);
        $page_url = 'http://example.com/empty-test';
        $_POST['action'] = 'cache_detector_receive_assets';
        $_POST['asset_data'] = $asset_data_payload;
        $_POST['page_url'] = $page_url;
        $_POST['nonce'] = wp_create_nonce('cache_detector_asset_nonce');

        try {
            $this->_handleAjax('cache_detector_receive_assets');
        } catch (\WPAjaxDieStopException $e) {
            $response = json_decode($this->_last_response, true);
            $this->assertFalse($response['success']);
            $this->assertEquals(esc_html__('No valid asset data provided after sanitization.', 'cache-detector'), $response['data']['message']);
        }
    }


    // --- Tests for handle_receive_rest_api_calls_ajax ---

    public function test_handle_receive_rest_api_calls_ajax_success() {
        $rest_calls_payload = json_encode([
            ['url' => 'http://example.com/wp-json/wp/v2/posts', 'method' => 'GET', 'status' => 200, 'headers' => ['cf-cache-status: HIT', 'X-Custom: Value']]
        ]);
        $page_url = 'http://example.com/initiating-page';
        $transient_key = 'cd_rest_calls_' . self::$user_id;
        delete_transient($transient_key); // Clean state

        $this->public_handler_mock->method('analyze_headers')
                                   ->with($this->equalTo(['cf-cache-status: HIT', 'X-Custom: Value']))
                                   ->willReturn('HIT by Cloudflare');

        $_POST['action'] = 'cache_detector_receive_rest_api_calls';
        $_POST['rest_api_calls'] = $rest_calls_payload;
        $_POST['page_url'] = $page_url;
        $_POST['nonce'] = wp_create_nonce('cache_detector_rest_api_nonce');

        try {
            $this->_handleAjax('cache_detector_receive_rest_api_calls');
        } catch (\WPAjaxDieContinueException $e) {
            // Expected
        }

        $response = json_decode($this->_last_response, true);
        $this->assertTrue($response['success']);
        $this->assertEquals(1, $response['data']['count']);

        $stored_data = get_transient($transient_key);
        $this->assertNotFalse($stored_data);
        $this->assertIsArray($stored_data);
        $this->assertCount(1, $stored_data);
        $call = $stored_data[0];
        $this->assertEquals('http://example.com/wp-json/wp/v2/posts', $call['url']);
        $this->assertEquals('HIT by Cloudflare', $call['cache_status']);
        $this->assertEquals('GET', $call['method']);
        $this->assertEquals(200, $call['status']);
        $this->assertEquals($page_url, $call['initiating_page_url']);
        $this->assertContains('cf-cache-status: HIT', $call['raw_headers']);
        $this->assertContains('X-Custom: Value', $call['raw_headers']);

        delete_transient($transient_key);
    }

    public function test_handle_receive_rest_api_calls_ajax_multiple_calls_and_limit() {
        $transient_key = 'cd_rest_calls_' . self::$user_id;
        delete_transient($transient_key);

        $this->public_handler_mock->method('analyze_headers')->willReturn('MOCK STATUS');

        // First batch (2 calls)
        $rest_calls_payload1 = json_encode([
            ['url' => 'http://example.com/api/1', 'method' => 'GET', 'status' => 200, 'headers' => []],
            ['url' => 'http://example.com/api/2', 'method' => 'POST', 'status' => 201, 'headers' => []],
        ]);
        $_POST = [
            'action' => 'cache_detector_receive_rest_api_calls',
            'rest_api_calls' => $rest_calls_payload1,
            'page_url' => 'http://example.com/page1',
            'nonce' => wp_create_nonce('cache_detector_rest_api_nonce'),
        ];
        try { $this->_handleAjax('cache_detector_receive_rest_api_calls'); } catch (\WPAjaxDieContinueException $e) {}

        $stored_data1 = get_transient($transient_key);
        $this->assertCount(2, $stored_data1);
        $this->assertEquals('http://example.com/api/2', $stored_data1[0]['url']);

        // Second batch (3 calls) - total 5
        $rest_calls_payload2 = json_encode([
            ['url' => 'http://example.com/api/3', 'method' => 'GET', 'status' => 200, 'headers' => []],
            ['url' => 'http://example.com/api/4', 'method' => 'GET', 'status' => 200, 'headers' => []],
            ['url' => 'http://example.com/api/5', 'method' => 'GET', 'status' => 200, 'headers' => []],
        ]);
         $_POST['rest_api_calls'] = $rest_calls_payload2;
         $_POST['page_url'] = 'http://example.com/page2';
        try { $this->_handleAjax('cache_detector_receive_rest_api_calls'); } catch (\WPAjaxDieContinueException $e) {}

        $stored_data2 = get_transient($transient_key);
        $this->assertCount(5, $stored_data2);
        $this->assertEquals('http://example.com/api/5', $stored_data2[0]['url']);

        // Third batch (fills up to 50, then exceeds)
        $calls_to_add = [];
        for ($i = 6; $i <= 55; $i++) {
            $calls_to_add[] = ['url' => "http://example.com/api/$i", 'method' => 'GET', 'status' => 200, 'headers' => []];
        }
        $_POST['rest_api_calls'] = json_encode($calls_to_add);
        $_POST['page_url'] = 'http://example.com/page3';
        try { $this->_handleAjax('cache_detector_receive_rest_api_calls'); } catch (\WPAjaxDieContinueException $e) {}

        $stored_data_final = get_transient($transient_key);
        $this->assertCount(50, $stored_data_final);
        $this->assertEquals("http://example.com/api/55", $stored_data_final[0]['url']);
        $this->assertEquals("http://example.com/api/6", $stored_data_final[49]['url']);

        delete_transient($transient_key);
    }

    public function test_handle_receive_rest_api_calls_ajax_sanitization() {
        $rest_calls_payload = json_encode([
            ['url' => 'http://<script>alert(1)</script>.com/wp-json/wp/v2/posts', 'method' => 'GET<foo>', 'status' => "200xx", 'headers' => ['<script>alert(1)</script>: BAD', 'Good-Header: OK']]
        ]);
        $page_url = 'http://example.com/initiating-page-xss';
        $transient_key = 'cd_rest_calls_' . self::$user_id;
        delete_transient($transient_key);

        $this->public_handler_mock->method('analyze_headers')
                                   ->with($this->equalTo(['&lt;script&gt;alert(1)&lt;/script&gt;: BAD', 'Good-Header: OK']))
                                   ->willReturn('SANITIZED STATUS');

        $_POST['action'] = 'cache_detector_receive_rest_api_calls';
        $_POST['rest_api_calls'] = $rest_calls_payload;
        $_POST['page_url'] = $page_url;
        $_POST['nonce'] = wp_create_nonce('cache_detector_rest_api_nonce');

        try {
            $this->_handleAjax('cache_detector_receive_rest_api_calls');
        } catch (\WPAjaxDieContinueException $e) {
            // Expected
        }

        $stored_data = get_transient($transient_key);
        $this->assertNotFalse($stored_data);
        $this->assertCount(1, $stored_data);
        $call = $stored_data[0];

        $this->assertEquals('http://scriptalert(1)/script.com/wp-json/wp/v2/posts', $call['url']); // esc_url_raw removes < > and content
        $this->assertEquals('GET&lt;foo&gt;', $call['method']); // sanitize_text_field
        $this->assertEquals(200, $call['status']); // absint
        $this->assertEquals('SANITIZED STATUS', $call['cache_status']);
        $this->assertContains('&lt;script&gt;alert(1)&lt;/script&gt;: BAD', $call['raw_headers']);
        $this->assertContains('Good-Header: OK', $call['raw_headers']);

        delete_transient($transient_key);
    }

    public function test_handle_receive_rest_api_calls_ajax_empty_sanitized_data() {
        // Payload with only an invalid URL and status, which should be removed after sanitization
        $rest_calls_payload = json_encode([
            ['url' => '<completely invalid url>', 'status' => 0]
        ]);
        $page_url = 'http://example.com/empty-rest-test';
        $_POST['action'] = 'cache_detector_receive_rest_api_calls';
        $_POST['rest_api_calls'] = $rest_calls_payload;
        $_POST['page_url'] = $page_url;
        $_POST['nonce'] = wp_create_nonce('cache_detector_rest_api_nonce');

        try {
            $this->_handleAjax('cache_detector_receive_rest_api_calls');
        } catch (\WPAjaxDieStopException $e) {
            $response = json_decode($this->_last_response, true);
            $this->assertFalse($response['success']);
            $this->assertEquals(esc_html__('No valid REST API call data provided after sanitization.', 'cache-detector'), $response['data']['message']);
        }
    }

}
