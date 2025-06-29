<?php
/**
 * Test case for Cache Detector Analyzer functionality.
 *
 * @package CacheDetector\Tests
 */

use Jules\CacheDetector\Public_Handler;
use Jules\CacheDetector\Main;

/**
 * Class Test_Analyzer
 */
class Test_Analyzer extends \WP_UnitTestCase {

    private $public_handler;
    private $main_instance;

    public function setUp(): void {
        parent::setUp();
        // Ensure Main instance is available as Public_Handler constructor expects it.
        // The autoloader should handle loading Main if not already loaded by WordPress test suite.
        $this->main_instance = Main::instance();
        // Reset properties that might be modified by tests if Main is a true singleton across test runs
        $this->main_instance->public_request_cache_status = null;
        $this->main_instance->public_request_headers = array();

        $this->public_handler = new Public_Handler($this->main_instance);
    }

    /**
     * Data provider for header analysis.
     * format: [ expected_status_string, headers_array ]
     */
    public static function header_data_provider() {
        return [
            // Cloudflare
            'Cloudflare HIT' => ['HIT by Cloudflare', ['cf-cache-status: HIT']],
            'Cloudflare MISS' => ['MISS by Cloudflare', ['cf-cache-status: MISS']],
            'Cloudflare DYNAMIC' => ['DYNAMIC by Cloudflare', ['cf-cache-status: DYNAMIC']],
            'Cloudflare BYPASS' => ['BYPASS by Cloudflare', ['cf-cache-status: BYPASS']],
            'Cloudflare EXPIRED' => ['EXPIRED by Cloudflare', ['cf-cache-status: EXPIRED']],


            // LiteSpeed
            'LiteSpeed HIT' => ['HIT by LiteSpeed', ['x-litespeed-cache: hit']],
            'LiteSpeed MISS' => ['MISS by LiteSpeed', ['x-litespeed-cache: miss']],
            'LiteSpeed NO-CACHE (Bypass)' => ['BYPASS by LiteSpeed', ['x-litespeed-cache: no-cache']],
            'LiteSpeed PASS (Bypass)' => ['BYPASS by LiteSpeed', ['x-litespeed-cache: pass']],


            // SG Optimizer
            'SG Optimizer HIT' => ['HIT by SG Optimizer', ['x-sg-cache: HIT']],
            'SG Optimizer MISS (assumed)' => ['MISS by SG Optimizer', ['x-sg-cache: MISS']],
            'SG Optimizer BYPASS (assumed)' => ['BYPASS by SG Optimizer', ['x-sg-cache: BYPASS']],

            // WP Rocket (Header)
            'WP Rocket HIT Header' => ['HIT by WP Rocket Header', ['x-wp-rocket-cache: HIT']],
            'WP Rocket MISS Header' => ['MISS by WP Rocket Header', ['x-wp-rocket-cache: MISS']],

            // Varnish
            'Varnish HIT (x-varnish-cache)' => ['HIT by Varnish', ['x-varnish-cache: HIT']],
            'Varnish MISS (x-varnish-cache)' => ['MISS by Varnish', ['x-varnish-cache: MISS']],
            'Varnish HIT (x-cache generic)' => ['HIT by Varnish', ['x-cache: HIT from cache.example.com (Varnish)']],
            'Varnish HIT (Age header)' => ['HIT by Proxy/CDN (Age Header)', ['Age: 100']],
            'Varnish Multiple Headers (Age + x-cache miss should be HIT by Age)' => ['HIT by Proxy/CDN (Age Header)', ['Age: 100', 'x-cache: MISS from cache.example.com (Varnish)']],
            'Varnish Info (x-varnish IDs)' => ['INFO (Handled) by Varnish', ['x-varnish: 12345 67890']],
            'Varnish PASS (x-cache)' => ['BYPASS by Varnish', ['x-cache: PASS (Varnish)']],


            // Fastly
            'Fastly HIT (x-cache)' => ['HIT by Fastly', ['x-cache: HIT, HIT', 'x-served-by: cache-fastly-server']],
            'Fastly MISS (x-cache)' => ['MISS by Fastly', ['x-cache: MISS', 'x-served-by: cache-fastly-server']],
            'Fastly PASS (Bypass)' => ['BYPASS by Fastly', ['x-cache: PASS', 'x-served-by: cache-fastly-server']],
            'Fastly HIT (x-fastly-status HIT)' => ['HIT by Fastly', ['x-fastly-status: HIT']],


            // Akamai
            'Akamai HIT (x-cache TCP_HIT)' => ['HIT by Akamai', ['x-cache: TCP_HIT from AkamaiGHost (AkamaiIO)', 'x-check-cacheable: YES']],
            'Akamai MISS (x-cache TCP_MISS)' => ['MISS by Akamai', ['x-cache: TCP_MISS from AkamaiGHost (AkamaiIO)']],
            'Akamai BYPASS (x-akamai-cache-action no_cache)' => ['BYPASS by Akamai', ['x-akamai-cache-action: no_cache']],
            'Akamai HIT (x-akamai-cache-status Hit)' => ['HIT by Akamai', ['x-akamai-cache-status: Hit']],


            // Browser Caching Directives
            'Browser UNCACHED (no-store)' => ['UNCACHED by Browser Directives', ['Cache-Control: no-store, no-cache, must-revalidate, max-age=0']],
            'Browser UNCACHED (pragma no-cache)' => ['UNCACHED by Browser Directives', ['Pragma: no-cache', 'Expires: 0']],
            'Browser POTENTIALLY CACHED (public)' => ['POTENTIALLY BROWSER CACHED by Browser Headers', ['Cache-Control: public, max-age=3600']],
            'Browser POTENTIALLY CACHED (private)' => ['POTENTIALLY BROWSER CACHED by Browser Headers', ['Cache-Control: private, max-age=3600']],
            'Browser UNCACHED (max-age=0 with MISS from proxy)' => ['UNCACHED by Proxy (X-Cache), Browser Directives', ['x-cache: MISS', 'Cache-Control: max-age=0']],


            // Combinations and Priorities
            'Cloudflare HIT over LiteSpeed MISS' => ['HIT by Cloudflare', ['cf-cache-status: HIT', 'x-litespeed-cache: miss']],
            'LiteSpeed HIT over generic X-Cache MISS' => ['HIT by LiteSpeed', ['x-litespeed-cache: hit', 'x-cache: MISS']],
            'X-Cache HIT over Age when X-Cache is specific' => ['HIT by Fastly', ['x-cache: HIT, HIT', 'x-served-by: cache-fastly-server', 'Age: 50']], // Fastly (prio 1) vs Age (prio 4)

            // Unknown
            'Unknown no specific headers' => ['UNKNOWN', []],
            'Unknown with unrelated headers' => ['UNKNOWN', ['X-My-Custom-Header: value']],
        ];
    }

    /**
     * @dataProvider header_data_provider
     */
    public function test_analyze_headers( $expected_status, $headers ) {
        $this->assertEquals( $expected_status, $this->public_handler->analyze_headers( $headers ) );
    }

    /**
     * Data provider for HTML footprint analysis.
     * format: [ initial_header_status, html_buffer, expected_final_status ]
     */
    public static function html_footprint_data_provider() {
        return [
            // WP Rocket
            'WP Rocket HTML HIT (cached@)' => [
                'UNKNOWN', // Initial header status from Main instance
                '<!-- This website is like a Rocket, isn\'t it? Performance optimized by WP Rocket. Cached @ 12345 -->', // HTML buffer
                'HIT by WP Rocket HTML' // Expected final status on Main instance
            ],
            'WP Rocket HTML MISS (no cached@)' => [
                'UNKNOWN',
                '<!-- This website is like a Rocket, isn\'t it? Performance optimized by WP Rocket. -->',
                'MISS_OR_BYPASS_HTML by WP Rocket HTML'
            ],
            'WP Rocket HTML HIT overrides UNKNOWN header' => [
                'UNKNOWN by SomeProxy',
                '<!-- Performance optimized by WP Rocket. Cached @ 123 -->',
                'HIT by WP Rocket HTML'
            ],
            'Cloudflare HIT and WP Rocket HTML HIT (different systems)' => [
                'HIT by Cloudflare',
                '<!-- Performance optimized by WP Rocket. Cached @ 123 -->',
                'HIT by Cloudflare; HIT (HTML: WP Rocket HTML)'
            ],
             'WP Rocket HTML HIT with WP Rocket Header MISS (HTML more specific)' => [
                'MISS by WP Rocket Header',
                '<!-- Performance optimized by WP Rocket. Cached @ 123 -->',
                'HIT by WP Rocket HTML'
            ],

            // W3 Total Cache
            'W3TC HTML HIT (enabled disk enhanced hit)' => [
                'UNKNOWN',
                '<!-- Performance optimized by W3 Total Cache. Page Caching using disk: enhanced (Page cache debug: hit). Served in 0.123 seconds. -->',
                'HIT by W3TC HTML Debug'
            ],
            'W3TC HTML MISS (enabled disk enhanced miss)' => [
                'UNKNOWN',
                '<!-- Performance optimized by W3 Total Cache. Page Caching using disk: enhanced (Page cache debug: miss). Served in 0.123 seconds. -->',
                'MISS by W3TC HTML Debug'
            ],
            'W3TC HTML HIT (direct hit)' => [
                'UNKNOWN',
                '<!-- Performance optimized by W3 Total Cache. Page Caching: hit -->',
                'HIT by W3TC HTML Debug'
            ],
            'W3TC HTML BYPASS (not applicable)' => [
                'UNKNOWN',
                '<!-- Performance optimized by W3 Total Cache. Page Caching: not applicable -->',
                'BYPASS by W3TC HTML Debug'
            ],
            'W3TC HTML INFO (weird status)' => [
                'UNKNOWN',
                '<!-- Performance optimized by W3 Total Cache. Page Caching: weird status here -->',
                'INFO: Weird status here by W3TC HTML Debug'
            ],

            // LiteSpeed Cache
            'LiteSpeed HTML HIT' => [
                'UNKNOWN',
                '<!-- Page generated by LiteSpeed Cache 1.2.3 on 2023-10-26 10:00:00 -->',
                'HIT by LiteSpeed HTML'
            ],
            'LiteSpeed Header HIT and LiteSpeed HTML HIT (same system, HTML takes precedence if HIT)' => [
                'HIT by LiteSpeed', // Initial header status
                '<!-- Page generated by LiteSpeed Cache 1.2.3 -->', // HTML
                'HIT by LiteSpeed HTML' // HTML HIT overrides if from same system or if header is not definitive
            ],
            'LiteSpeed Header MISS and LiteSpeed HTML HIT' => [
                'MISS by LiteSpeed',
                '<!-- Page generated by LiteSpeed Cache 1.2.3 -->',
                'HIT by LiteSpeed HTML'
            ],


            // SG Optimizer
            'SG Optimizer HTML HIT (comment)' => [
                'UNKNOWN',
                '<!-- SG Optimizer -->',
                'HIT by SG Optimizer HTML'
            ],
            'SG Optimizer HTML HIT (cached by comment)' => [
                'UNKNOWN',
                '<!-- Cached by SG Optimizer on 2023-10-26 10:00:00 -->',
                'HIT by SG Optimizer HTML'
            ],

            // No Footprint
            'No footprint found, initial status unchanged' => [
                'HIT by Cloudflare',
                '<html><body>Regular content</body></html>',
                'HIT by Cloudflare'
            ],
            'No footprint found, UNKNOWN remains UNKNOWN' => [
                'UNKNOWN',
                '<html><body>Regular content</body></html>',
                'UNKNOWN'
            ],
            'Empty buffer, initial status unchanged' => [
                'MISS by Proxy',
                '',
                'MISS by Proxy'
            ],
            'Header MISS and HTML MISS (W3TC)' => [
                'MISS by SomeProxy',
                '<!-- Performance optimized by W3 Total Cache. Page Caching using disk: enhanced (Page cache debug: miss). -->',
                'MISS by W3TC HTML Debug' // HTML is more specific or confirms
            ],
            // Test case where header is a definitive HIT from CDN and HTML is also HIT from page cache
            'Akamai HIT Header and WP Rocket HTML HIT' => [
                'HIT by Akamai',
                '<!-- Performance optimized by WP Rocket. Cached @ 12345 -->',
                'HIT by Akamai; HIT (HTML: WP Rocket HTML)'
            ],
            // Test case: Header is UNKNOWN, HTML provides info
            'Header UNKNOWN, HTML provides W3TC MISS' => [
                'UNKNOWN',
                '<!-- Performance optimized by W3 Total Cache. Page Caching: miss -->',
                'MISS by W3TC HTML Debug'
            ]
        ];
    }

    /**
     * @dataProvider html_footprint_data_provider
     */
    public function test_inspect_html_footprints( $initial_header_status, $html_buffer, $expected_final_status ) {
        // Set the initial header status on the main plugin instance.
        // This simulates what would have been set by header analysis.
        $this->main_instance->public_request_cache_status = $initial_header_status;

        // The Public_Handler's internal current_request_cache_status is what inspect_html_footprints_public uses.
        // This is initialized from main_instance->public_request_cache_status when Public_Handler is constructed,
        // or when inspect_main_request_headers_public runs.
        // For this test, we need to ensure the Public_Handler's internal state reflects initial_header_status.
        // Re-initialize public_handler or set its internal state if it had a public setter or we used reflection.
        // Simplest for now: public_handler is fresh per test method due to setUp(), but main_instance is shared.
        // Let's ensure public_handler's internal state is also primed.
        // The constructor of Public_Handler sets its internal current_request_cache_status from $this->main_plugin->public_request_cache_status if it's not the init default.
        // However, the constructor sets it to "UNKNOWN by Cache Detector (Public Init)" initially.
        // Then inspect_main_request_headers_public updates it AND main_plugin's copy.
        // inspect_html_footprints_public directly uses $this->current_request_cache_status.
        // So, we need to make sure $this->public_handler->current_request_cache_status is the $initial_header_status.
        // The easiest way for this test is to directly set it if it were public, or simulate the prior call.

        // Simulate that inspect_main_request_headers_public has run and set the status
        // This is a bit of a workaround because current_request_cache_status is private.
        // We are setting it on main_instance, which is then read by public_handler's methods.
        // The Public_Handler's constructor copies the status from main_instance if it's not the default "UNKNOWN (Main Init)".
        // Let's refine this by directly setting the internal property via reflection for a cleaner unit test,
        // or by ensuring the public_handler's internal state is correctly primed.

        // For this test, Public_Handler's inspect_html_footprints_public method internally uses:
        // $this->current_request_cache_status which should be reflecting the initial_header_status.
        // The `update_main_plugin_status()` call inside `inspect_main_request_headers_public` sets both.
        // Let's assume `inspect_main_request_headers_public` would have set `public_handler->current_request_cache_status`
        // and `main_instance->public_request_cache_status` to the same $initial_header_status.
        // So, setting main_instance->public_request_cache_status is the key.
        // And then, inspect_html_footprints_public uses its *own* $this->current_request_cache_status
        // which it should have received from main_instance or through prior header scan.

        // To make the test more robust and less reliant on constructor sequence for this specific property:
        $reflector = new \ReflectionProperty( Public_Handler::class, 'current_request_cache_status' );
        $reflector->setAccessible( true );
        $reflector->setValue( $this->public_handler, $initial_header_status );


        // Call inspect_html_footprints_public - it returns the buffer,
        // and internally calls update_main_plugin_status() which updates main_instance->public_request_cache_status
        $this->public_handler->inspect_html_footprints_public( $html_buffer );

        $this->assertEquals( $expected_final_status, $this->main_instance->public_request_cache_status );
    }
}
