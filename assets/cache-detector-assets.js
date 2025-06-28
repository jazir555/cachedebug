// Cache Detector Assets JS
// Responsible for collecting asset performance data and REST API call data.

(function(global) {
    'use strict';

    if (typeof cache_detector_ajax === 'undefined' || !cache_detector_ajax.ajax_url) {
        console.warn('Cache Detector: AJAX config not available.');
        return;
    }
    // Check for specific nonces
    if (typeof cache_detector_ajax.asset_nonce === 'undefined' && typeof cache_detector_ajax.rest_api_nonce === 'undefined') {
        console.warn('Cache Detector: Nonce config not available for any actions.');
        return;
    }
    if (!cache_detector_ajax.current_page_url) {
        console.warn('Cache Detector: Current page URL not available.');
        return;
    }


    // --- Asset Collection Logic ---
    function isPotentiallyCachedByBrowser(entry) {
        if (entry.transferSize === 0 && entry.decodedBodySize > 0) { // Ensure it's not an empty file
            return "HIT (Browser Cache - Memory/Disk)";
        }
        // Heuristic for 304: encodedBodySize is 0 (no new body), but transferSize > 0 (headers)
        if (entry.encodedBodySize === 0 && entry.transferSize > 0 && entry.decodedBodySize > 0) {
             return "HIT (Browser Cache - 304 Not Modified)";
        }
        return null;
    }

    function collectAndSendAssetData() {
        if (typeof performance === 'undefined' || typeof performance.getEntriesByType === 'undefined') {
            // console.warn('[Cache Detector] Performance API not supported for asset collection.');
            return;
        }
        if (!cache_detector_ajax.asset_nonce) {
            // console.log('[Cache Detector] Asset nonce not available, skipping asset data collection.');
            return;
        }

        setTimeout(function() { // Ensure all resources are loaded
            const resources = performance.getEntriesByType('resource');
            const assetData = [];

            resources.forEach(function(entry) {
                let status = 'UNKNOWN';
                let detectedBy = 'PerformanceAPI';
                let serverTimingInfo = [];

                if (entry.serverTiming && entry.serverTiming.length > 0) {
                    entry.serverTiming.forEach(st => serverTimingInfo.push({ name: st.name, description: st.description, duration: st.duration }));
                    for (const st of entry.serverTiming) {
                        const st_name_lower = st.name.toLowerCase();
                        if (st_name_lower === 'cf-cache-status') { status = st.description.toUpperCase() + " (CF)"; detectedBy = 'Cloudflare (ServerTiming)'; break; }
                        else if (st_name_lower === 'x-litespeed-cache') { status = st.description.toUpperCase() + " (LS)"; detectedBy = 'LiteSpeed (ServerTiming)'; break; }
                        else if (st_name_lower === 'x-sg-cache') { status = st.description.toUpperCase() + " (SG)"; detectedBy = 'SiteGround (ServerTiming)'; break; }
                        else if ((st_name_lower.includes('cdn') || st_name_lower.includes('cache')) && st.description.toUpperCase() === 'HIT') {
                            status = 'HIT (CDN)'; detectedBy = 'ServerTiming (' + st_name_lower + ')'; break;
                        }
                    }
                }

                if (status === 'UNKNOWN') {
                    const browserCachedStatus = isPotentiallyCachedByBrowser(entry);
                    if (browserCachedStatus) {
                        status = browserCachedStatus;
                        detectedBy = 'Browser Cache Heuristics';
                    } else if (entry.transferSize > 0 && entry.decodedBodySize > 0 && entry.transferSize >= entry.decodedBodySize) {
                        status = 'DOWNLOADED/MISS'; detectedBy = 'PerformanceAPI (Size Analysis)';
                    } else if (entry.transferSize > 0 && entry.decodedBodySize > 0 && entry.transferSize < entry.decodedBodySize) {
                        status = 'COMPRESSED/MISS'; detectedBy = 'PerformanceAPI (Size Analysis)';
                    } else if (entry.decodedBodySize === 0 && entry.transferSize > 0) {
                        status = 'INFO (No Body/Redirect)'; detectedBy = 'PerformanceAPI';
                    } else {
                        status = 'UNKNOWN (Sizes: T' + entry.transferSize + '/D' + entry.decodedBodySize + '/E' + entry.encodedBodySize + ')';
                    }
                }

                assetData.push({
                    url: entry.name, status: status, transferSize: entry.transferSize,
                    decodedBodySize: entry.decodedBodySize, initiatorType: entry.initiatorType,
                    detectedBy: detectedBy, serverTiming: serverTimingInfo
                });
            });

            if (assetData.length > 0) {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', cache_detector_ajax.ajax_url, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                const params = 'action=cache_detector_receive_assets&nonce=' + cache_detector_ajax.asset_nonce + '&asset_data=' + encodeURIComponent(JSON.stringify(assetData)) + '&page_url=' + encodeURIComponent(cache_detector_ajax.current_page_url);
                xhr.send(params);
            }
        }, 1200); // Increased delay slightly
    }

    // --- REST API Call Collection Logic ---
    const collectedRestApiCalls = [];
    let restApiSendTimeout = null;

    function sendRestApiData() {
        if (collectedRestApiCalls.length === 0 || !cache_detector_ajax.rest_api_nonce) {
            if(collectedRestApiCalls.length > 0 && !cache_detector_ajax.rest_api_nonce) {
                // console.log('[Cache Detector] REST API nonce not available, cannot send REST data.');
                collectedRestApiCalls.length = 0; // Clear if cannot send
            }
            return;
        }

        const dataToSend = JSON.stringify(collectedRestApiCalls);
        collectedRestApiCalls.length = 0; // Clear after copying

        const xhr = new XMLHttpRequest();
        xhr.open('POST', cache_detector_ajax.ajax_url, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        const params = 'action=cache_detector_receive_rest_api_calls&nonce=' + cache_detector_ajax.rest_api_nonce + '&rest_api_calls=' + encodeURIComponent(dataToSend) + '&page_url=' + encodeURIComponent(cache_detector_ajax.current_page_url);
        xhr.send(params);
    }

    function scheduleRestApiSend() {
        clearTimeout(restApiSendTimeout);
        restApiSendTimeout = setTimeout(sendRestApiData, 1800); // Send data 1.8s after the last call
    }

    function processXhrResponse(xhrInstance, method, url) {
        if (!url || typeof url !== 'string' || !url.includes('/wp-json/')) {
            return;
        }
        const status = xhrInstance.status;
        const headersString = xhrInstance.getAllResponseHeaders();
        const headersArray = headersString ? headersString.trim().split(/[\r\n]+/).filter(h => h.length > 0) : [];

        collectedRestApiCalls.push({
            url: xhrInstance.responseURL || url,
            method: method ? method.toUpperCase() : 'GET',
            status: status,
            headers: headersArray
        });
        scheduleRestApiSend();
    }

    if (typeof XMLHttpRequest !== 'undefined') {
        const originalXhrOpen = XMLHttpRequest.prototype.open;
        const originalXhrSend = XMLHttpRequest.prototype.send;

        XMLHttpRequest.prototype.open = function(method, url) {
            this._cd_method = method;
            this._cd_url = url;
            originalXhrOpen.apply(this, arguments);
        };

        XMLHttpRequest.prototype.send = function() {
            if (this._cd_url && String(this._cd_url).includes('/wp-json/')) { // Only add listener for relevant URLs
                this.addEventListener('loadend', function() {
                    processXhrResponse(this, this._cd_method, this._cd_url);
                }.bind(this));
            }
            originalXhrSend.apply(this, arguments);
        };
    }

    if (global.fetch) {
        const originalFetch = global.fetch;
        global.fetch = function(input, init) {
            const method = (init && init.method) ? init.method.toUpperCase() : 'GET';
            let url = '';
            if (typeof input === 'string') {
                url = input;
            } else if (input instanceof Request) {
                url = input.url;
            } else if (input && input.url) { // Handle Request-like objects
                 url = input.url;
            }


            return originalFetch.apply(this, arguments).then(function(response) {
                if (response.url && response.url.includes('/wp-json/')) {
                    const headersArray = [];
                    response.headers.forEach((value, name) => {
                        headersArray.push(name + ": " + value);
                    });
                    collectedRestApiCalls.push({
                        url: response.url,
                        method: method,
                        status: response.status,
                        headers: headersArray
                    });
                    scheduleRestApiSend();
                }
                return response;
            }).catch(function(error) {
                // console.error('Cache Detector: Fetch error:', error, "URL:", url);
                throw error;
            });
        };
    }

    window.addEventListener('load', function() {
        collectAndSendAssetData(); // Collect asset data on load
    });

    window.addEventListener('beforeunload', function() {
        // Try to send any pending REST API data.
        // This is best-effort as browsers might terminate script execution.
        if (collectedRestApiCalls.length > 0) {
            sendRestApiData();
        }
    });

})(window);
