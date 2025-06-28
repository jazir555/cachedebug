// This test file will use QUnit and Sinon.JS for mocking.
// Ensure Sinon.JS is included in the HTML test runner before this script.

QUnit.module('Cache Detector Assets JS', function(hooks) {
    hooks.beforeEach(function() {
        // Mock the global object provided by wp_localize_script
        window.cache_detector_ajax = {
            ajax_url: '/fake-admin-ajax.php',
            asset_nonce: 'testassetnonce',
            rest_api_nonce: 'testrestnonce',
            current_page_url: 'http://example.com/current-page'
        };

        // Mock performance.getEntriesByType
        this.originalGetEntriesByType = window.performance.getEntriesByType;
        this.performanceEntries = []; // Default to empty
        window.performance.getEntriesByType = sinon.stub().returns(this.performanceEntries);

        // Stub window.fetch
        this.originalFetch = window.fetch;
        window.fetch = sinon.stub();

        // Store the original XMLHttpRequest
        this.originalXMLHttpRequest = window.XMLHttpRequest;
        // We will use sinon.useFakeXMLHttpRequest to capture XHR calls if the script uses it for REST API.
    });

    hooks.afterEach(function() {
        // Restore original functions and objects
        window.performance.getEntriesByType = this.originalGetEntriesByType;
        window.fetch = this.originalFetch;
        window.XMLHttpRequest = this.originalXMLHttpRequest;

        delete window.cache_detector_ajax;
        // sinon.restore(); // This would restore all sinon stubs/spies if using a global sinon instance.
                           // Here, we are restoring them manually.
    });

    QUnit.test('Initialization - CacheDetectorAssets object and init method', function(assert) {
        assert.ok(window.CacheDetectorAssets, 'CacheDetectorAssets global object should exist.');
        assert.strictEqual(typeof window.CacheDetectorAssets.init, 'function', 'CacheDetectorAssets.init should be a function.');
        // Call init to ensure it doesn't throw errors with mocks in place
        assert.doesNotThrow(function() {
            window.CacheDetectorAssets.init();
        }, 'CacheDetectorAssets.init() should run without errors.');
    });

    QUnit.test('collectAndSendAssetData - sends correct data via fetch', function(assert) {
        const done = assert.async();

        // Prepare mock performance entries
        const mockEntries = [
            { name: 'http://example.com/style.css', initiatorType: 'link', entryType: 'resource', transferSize: 100, decodedBodySize: 200, serverTiming: [] },
            { name: 'http://example.com/script.js', initiatorType: 'script', entryType: 'resource', transferSize: 150, decodedBodySize: 300, serverTiming: [{name: 'cdn-cache', description:'HIT', duration: 0}] }
        ];
        window.performance.getEntriesByType.withArgs('resource').returns(mockEntries);

        // Configure fetch stub for asset data
        window.fetch.withArgs(window.cache_detector_ajax.ajax_url, sinon.match.has('body', sinon.match(formData => formData.get('action') === 'cache_detector_receive_assets')))
            .resolves(Promise.resolve(new Response(JSON.stringify({ success: true, data: { count: 2 } }), { status: 200, headers: { 'Content-Type': 'application/json' } })));

        // Call the method that triggers data collection and sending.
        // This assumes CacheDetectorAssets.init() sets up listeners or calls collectAndSendAssetData.
        // For direct testing, if collectAndSendAssetData is public, call it.
        // Let's assume it's part of the init or a load event.
        // We might need to simulate window.onload if it's tied to that.

        // Manually trigger the part of init that would call collectAndSendAssetData, or call it directly if public.
        // For this example, let's assume it's callable for testing or init triggers it.
        // If CacheDetectorAssets.collectAndSendAssetData is the actual function:
        if (typeof CacheDetectorAssets.collectAndSendAssetData === 'function') {
             CacheDetectorAssets.collectAndSendAssetData();
        } else {
            // If it's triggered by an event like 'load', that event needs to be dispatched.
            // For simplicity, we assume the function can be called or init already called it.
            // This might require refactoring the source JS for better testability if logic is too coupled to load event.
            console.warn('Test assumes collectAndSendAssetData is callable or init triggers it.');
            // As a fallback, let's call init again if the function isn't directly exposed.
            CacheDetectorAssets.init();
        }


        // Wait for the fetch call to be made (it might be inside a setTimeout or after 'load')
        setTimeout(function() {
            assert.ok(window.fetch.calledOnce, 'fetch should have been called once for asset data.');

            const fetchCall = window.fetch.getCall(0); // Get the first call to fetch
            if (!fetchCall) {
                assert.ok(false, 'Fetch was not called as expected.');
                done();
                return;
            }

            const fetchOptions = fetchCall.args[1]; // Second argument to fetch is the options object
            const formData = fetchOptions.body; // Assuming body is FormData

            assert.ok(formData.has('action'), 'AJAX body should have action.');
            assert.equal(formData.get('action'), 'cache_detector_receive_assets', 'Action should be cache_detector_receive_assets.');
            assert.equal(formData.get('nonce'), 'testassetnonce', 'Nonce should match.');
            assert.ok(formData.get('asset_data'), 'AJAX body should have asset_data.');

            const sentAssetData = JSON.parse(formData.get('asset_data'));
            assert.equal(sentAssetData.length, 2, 'Two assets should have been sent.');
            assert.equal(sentAssetData[0].url, 'http://example.com/style.css', 'First asset URL should match.');
            assert.equal(sentAssetData[1].serverTiming[0].name, 'cdn-cache', 'Server timing should be present.');

            done();
        }, 200); // Increased timeout to ensure any internal delays in script are covered
    });

    QUnit.test('captureAndSendRestApiCalls - intercepts fetch and sends data', function(assert) {
        const done = assert.async();
        const fakeRestUrl = 'http://example.com/wp-json/custom/v1/data';

        // Configure the main fetch stub (for the actual REST call being intercepted)
        window.fetch.withArgs(fakeRestUrl)
            .resolves(Promise.resolve(new Response(JSON.stringify({ data: 'test' }), { status: 200, headers: { 'Content-Type': 'application/json', 'X-Test-Header': 'RestValue' } })));

        // Configure fetch stub for the AJAX call sending the REST data
        window.fetch.withArgs(window.cache_detector_ajax.ajax_url, sinon.match.has('body', sinon.match(formData => formData.get('action') === 'cache_detector_receive_rest_api_calls')))
            .resolves(Promise.resolve(new Response(JSON.stringify({ success: true, data: { count: 1 } }), { status: 200, headers: { 'Content-Type': 'application/json' } })));

        // Initialize the CacheDetectorAssets to wrap fetch/XHR
        CacheDetectorAssets.init();

        // Make a "REST API" call using the now-wrapped fetch
        window.fetch(fakeRestUrl, { method: 'GET' }).then(response => response.json()).then(() => {
            // This part of the test assumes that captureAndSendRestApiCalls is triggered correctly
            // by the wrapped fetch. The original script might batch these or send on page unload.
            // For simplicity, let's assume a send operation occurs or can be triggered.
            // If CacheDetectorAssets.sendCapturedRestData is the function:
            if (typeof CacheDetectorAssets.sendCapturedRestData === 'function') {
                 CacheDetectorAssets.sendCapturedRestData();
            } else {
                console.warn('Test assumes sendCapturedRestData is callable or automatically triggered for REST API data.');
                // It might be sent on an interval or unload, which is harder to test synchronously.
                // We'll check the call count for the specific AJAX action.
            }

            setTimeout(function() {
                const ajaxCall = window.fetch.getCalls().find(call => {
                    if (call.args[1] && call.args[1].body instanceof FormData) {
                        return call.args[1].body.get('action') === 'cache_detector_receive_rest_api_calls';
                    }
                    return false;
                });

                assert.ok(ajaxCall, 'AJAX call to send REST API data should have been made.');
                if (ajaxCall) {
                    const formData = ajaxCall.args[1].body;
                    assert.equal(formData.get('nonce'), 'testrestnonce', 'REST API nonce should match.');
                    const sentRestData = JSON.parse(formData.get('rest_api_calls'));
                    assert.equal(sentRestData.length, 1, 'One REST call should have been captured and sent.');
                    assert.equal(sentRestData[0].url, fakeRestUrl, 'Captured REST call URL should match.');
                    assert.ok(sentRestData[0].raw_headers.includes('x-test-header: RestValue'), 'Captured REST call headers should be present.');
                }
                done();
            }, 200); // Timeout for async operations
        });
    });

    // TODO: Add tests for XMLHttpRequest interception if the plugin supports it.
    // TODO: Test batching of REST API calls if implemented.
    // TODO: Test 'beforeunload' sending logic if that's how REST/asset data is finally pushed.
});
