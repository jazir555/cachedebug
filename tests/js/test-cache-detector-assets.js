// tests/js/test-cache-detector-assets.js

QUnit.module('Cache Detector Assets JS', function(hooks) {
    hooks.beforeEach(function() {
        window.cache_detector_ajax = {
            ajax_url: '/fake-admin-ajax.php',
            asset_nonce: 'testassetnonce',
            rest_api_nonce: 'testrestnonce',
            current_page_url: 'http://example.com/current-page'
        };

        this.performanceEntries = [];
        // Ensure window.performance exists for the script
        if (!window.performance) {
            window.performance = {};
        }
        this.originalGetEntriesByType = window.performance.getEntriesByType;
        window.performance.getEntriesByType = sinon.stub().returns(this.performanceEntries);

        this.originalFetch = window.fetch;
        this.fetchStub = sinon.stub();
        window.fetch = this.fetchStub;

        this.xhr = sinon.useFakeXMLHttpRequest();
        this.requests = []; // To store captured XHR requests
        this.xhr.onCreate = (req) => {
            this.requests.push(req);
        };

        this.clock = sinon.useFakeTimers();

        // The main script cache-detector-assets.js is an IIFE.
        // It's assumed to be loaded in the test HTML (qunit-tests.html) AFTER sinon.js
        // and BEFORE this test script file.
        // Its event listeners and wrappers for fetch/XHR would have been set up when it loaded.
    });

    hooks.afterEach(function() {
        window.performance.getEntriesByType = this.originalGetEntriesByType;
        window.fetch = this.originalFetch;
        this.xhr.restore();
        this.clock.restore();
        delete window.cache_detector_ajax;
        this.requests = [];
    });

    QUnit.test('Asset data collection on window.load', function(assert) {
        const done = assert.async();

        const mockEntries = [
            { name: 'http://example.com/style.css', initiatorType: 'link', entryType: 'resource', transferSize: 100, decodedBodySize: 200, serverTiming: [], encodedBodySize: 200 },
            { name: 'http://example.com/script.js', initiatorType: 'script', entryType: 'resource', transferSize: 0, decodedBodySize: 300, serverTiming: [{name: 'cf-cache-status', description:'HIT', duration: 0}], encodedBodySize: 300 }
        ];
        window.performance.getEntriesByType.withArgs('resource').returns(mockEntries);

        // Dispatch the load event - this should trigger collectAndSendAssetData in the IIFE
        const loadEvent = new Event('load');
        window.dispatchEvent(loadEvent);

        // The script uses setTimeout(..., 1200) before sending asset data
        this.clock.tick(1200);

        assert.equal(this.requests.length, 1, "One AJAX request should have been made for assets.");
        if (this.requests.length > 0) {
            const request = this.requests[0];
            assert.ok(request.url.includes(window.cache_detector_ajax.ajax_url), "Request URL should be the AJAX URL.");
            assert.equal(request.method, "POST", "Request method should be POST.");

            const params = new URLSearchParams(request.requestBody);
            assert.equal(params.get('action'), 'cache_detector_receive_assets', 'Action should be cache_detector_receive_assets.');
            assert.equal(params.get('nonce'), 'testassetnonce', 'Nonce should match.');
            assert.ok(params.has('asset_data'), 'Request should contain asset_data.');

            const sentAssetData = JSON.parse(params.get('asset_data'));
            assert.equal(sentAssetData.length, 2, 'Two assets should have been sent.');
            assert.equal(sentAssetData[0].url, 'http://example.com/style.css');
            assert.equal(sentAssetData[0].status, 'DOWNLOADED/MISS', 'Style.css status based on size analysis');
            assert.equal(sentAssetData[1].status, 'HIT (CF)', 'Second asset status should be HIT from Cloudflare ServerTiming.');
        }
        done();
    });

    QUnit.test('REST API call (fetch) interception and data sending', function(assert) {
        const done = assert.async();
        const fakeRestUrl = 'http://example.com/wp-json/custom/v1/data';

        this.fetchStub.withArgs(fakeRestUrl)
            .resolves(Promise.resolve(new Response(JSON.stringify({ data: 'test' }), {
                status: 200,
                headers: new Headers({ 'Content-Type': 'application/json', 'X-Test-Header': 'RestValue' }),
                url: fakeRestUrl
            })));

        window.fetch(fakeRestUrl, { method: 'GET' })
            .then(() => {
                this.clock.tick(1800); // Advance timer for sendRestApiData

                assert.ok(this.requests.length >= 1, "At least one AJAX request should have been made for sending REST data.");
                const ajaxCall = this.requests.find(req => req.requestBody && new URLSearchParams(req.requestBody).get('action') === 'cache_detector_receive_rest_api_calls');

                assert.ok(ajaxCall, 'AJAX call to send REST API data should have been made.');
                if (ajaxCall) {
                    const params = new URLSearchParams(ajaxCall.requestBody);
                    assert.equal(params.get('nonce'), 'testrestnonce', 'REST API nonce should match.');
                    const sentRestData = JSON.parse(params.get('rest_api_calls'));
                    assert.equal(sentRestData.length, 1, 'One REST call should have been captured.');
                    assert.equal(sentRestData[0].url, fakeRestUrl);
                    assert.ok(sentRestData[0].headers.some(h => h.toLowerCase() === 'x-test-header: RestValue'.toLowerCase()));
                }
                done();
            })
            .catch(err => {
                assert.ok(false, "Fetch call failed: " + err);
                done();
            });
    });

    QUnit.test('REST API call (XHR) interception and data sending', function(assert) {
        const done = assert.async();
        const fakeRestUrl = 'http://example.com/wp-json/xhr/v1/data';

        const client = new XMLHttpRequest();
        client.open("GET", fakeRestUrl);
        client.setRequestHeader('X-Custom-XHR', 'XHRValue');

        client.onload = () => { // This is the onload for the XHR *itself*
            // The script's wrapper should have processed it by now (on loadend).
            this.clock.tick(1800); // Advance timer for sendRestApiData

            assert.ok(this.requests.length >= 1, "AJAX request for sending REST data should have been made.");
            const ajaxSendDataCall = this.requests.find(req => req.requestBody && new URLSearchParams(req.requestBody).get('action') === 'cache_detector_receive_rest_api_calls');

            assert.ok(ajaxSendDataCall, 'AJAX call to send REST API data should have been made.');
            if (ajaxSendDataCall) {
                const params = new URLSearchParams(ajaxSendDataCall.requestBody);
                assert.equal(params.get('nonce'), 'testrestnonce');
                const sentRestData = JSON.parse(params.get('rest_api_calls'));
                assert.equal(sentRestData.length, 1, 'One REST call should have been captured.');
                assert.equal(sentRestData[0].url, fakeRestUrl);
                // Check if response headers from the original XHR are captured.
                // getAllResponseHeaders() in the script gets "X-Response-Test: XHRTest\r\nContent-Type: application/json"
                assert.ok(sentRestData[0].headers.some(h => h.toLowerCase() === 'x-response-test: XHRTest'.toLowerCase()), 'Response header from original XHR should be captured');
            }
            done();
        };

        client.send();

        // Find the XHR request that the client just made to fakeRestUrl and respond to it
        const xhrRequestToServer = this.requests.find(req => req.url === fakeRestUrl && req.method === "GET");
        if (xhrRequestToServer) {
             xhrRequestToServer.respond(200, { "Content-Type": "application/json", "X-Response-Test": "XHRTest" }, JSON.stringify({data: "ok"}));
        } else {
            assert.ok(false, "XHR to fakeRestUrl was not captured by sinon.useFakeXMLHttpRequest as expected.");
            done();
        }
    });

    QUnit.test('isPotentiallyCachedByBrowser logic', function(assert) {
        // This tests the internal helper directly if it were exposed.
        // Since it's not, we test its effect through the main asset collection.
        // Test case 1: transferSize 0, decodedBodySize > 0  => HIT (Browser Cache - Memory/Disk)
        let mockEntry1 = { name: 'cached.js', transferSize: 0, decodedBodySize: 1000, encodedBodySize: 1000, serverTiming: [] };
        // Test case 2: encodedBodySize 0, transferSize > 0, decodedBodySize > 0 => HIT (Browser Cache - 304 Not Modified)
        let mockEntry2 = { name: '304.css', transferSize: 300, decodedBodySize: 2000, encodedBodySize: 0, serverTiming: [] };
        // Test case 3: Not matching browser cache conditions
        let mockEntry3 = { name: 'download.png', transferSize: 1000, decodedBodySize: 1000, encodedBodySize: 1000, serverTiming: [] };

        window.performance.getEntriesByType.withArgs('resource').returns([mockEntry1, mockEntry2, mockEntry3]);

        const loadEvent = new Event('load');
        window.dispatchEvent(loadEvent);
        this.clock.tick(1200);

        assert.equal(this.requests.length, 1, "Asset AJAX request made");
        if (this.requests.length === 1) {
            const params = new URLSearchParams(this.requests[0].requestBody);
            const sentAssetData = JSON.parse(params.get('asset_data'));
            assert.equal(sentAssetData.length, 3);
            assert.equal(sentAssetData[0].status, "HIT (Browser Cache - Memory/Disk)", "Test 1 Correct");
            assert.equal(sentAssetData[0].detectedBy, "Browser Cache Heuristics", "Test 1 DetectedBy Correct");
            assert.equal(sentAssetData[1].status, "HIT (Browser Cache - 304 Not Modified)", "Test 2 Correct");
            assert.equal(sentAssetData[1].detectedBy, "Browser Cache Heuristics", "Test 2 DetectedBy Correct");
            assert.equal(sentAssetData[2].status, "DOWNLOADED/MISS", "Test 3 Correct - Fell through to size analysis");
        }
    });
});
