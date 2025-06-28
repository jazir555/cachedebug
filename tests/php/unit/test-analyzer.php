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

    public function setUp(): void {
        parent::setUp();
        // Ensure Main instance is available as Public_Handler constructor expects it.
        // The autoloader should handle loading Main if not already loaded by WordPress test suite.
        $main_instance = Main::instance();
        $this->public_handler = new Public_Handler($main_instance);
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
}
