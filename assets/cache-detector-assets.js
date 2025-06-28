// Cache Detector Assets - JavaScript
(function() {
    'use strict';

    window.addEventListener('load', function() {
        if (typeof performance === 'undefined' || typeof performance.getEntriesByType === 'undefined') {
            console.warn('[Cache Detector] Performance API not supported.');
            return;
        }

        // Ensure this runs only for admins who can see the admin bar (implicit capability check)
        // And only if our localized data is present
        if (typeof cache_detector_ajax === 'undefined' || !cache_detector_ajax.nonce) {
            // console.log('[Cache Detector] AJAX data not available for asset script.');
            return;
        }

        const resources = performance.getEntriesByType('resource');
        const assetData = [];

        resources.forEach(function(resource) {
            let status = 'UNKNOWN';
            let detectedBy = 'PerformanceAPI';
            let transferSize = resource.transferSize;
            let decodedBodySize = resource.decodedBodySize;
            let serverTiming = [];

            if (resource.serverTiming) {
                resource.serverTiming.forEach(function(timing) {
                    serverTiming.push({
                        name: timing.name,
                        duration: timing.duration,
                        description: timing.description
                    });
                });
            }

            // Check for ServiceWorker involvement first
            if (resource.deliveryType === 'servicebuffer') { // This is a custom property some SWs might set, not standard
                status = 'HIT (ServiceWorker)'; // Example, actual detection is more complex
                detectedBy = 'ServiceWorker Heuristic';
            } else if (transferSize === 0 && decodedBodySize > 0) {
                // If transferSize is 0 and it's not an empty file, it's likely from memory/disk cache (browser)
                status = 'HIT (Browser Cache - Memory/Disk)';
                detectedBy = 'PerformanceAPI (transferSize=0)';
            } else if (transferSize > 0 && transferSize < decodedBodySize) {
                // If transferSize is small but not 0, it could be a 304 Not Modified
                // This is a heuristic. A small file could also just be small.
                // True 304s often have transferSize representing only header size.
                // Let's assume for now if it's significantly smaller.
                // A more robust check would be to see if encodedBodySize is 0 and transferSize > 0
                 if (resource.encodedBodySize === 0 && transferSize > 0) {
                    status = 'HIT (Browser Cache - 304 Not Modified)';
                    detectedBy = 'PerformanceAPI (encodedBodySize=0, transferSize>0)';
                } else {
                    // Could be a small file, or partial content, or just compressed well
                    status = 'DOWNLOADED_OR_PARTIAL';
                }
            } else if (transferSize >= decodedBodySize && decodedBodySize > 0) {
                status = 'DOWNLOADED'; // Likely fetched from the network
                detectedBy = 'PerformanceAPI (transferSize >= decodedBodySize)';
            } else if (decodedBodySize === 0 && transferSize > 0){
                // Example: a redirect that isn't followed by the performance entry, or a HEAD response.
                status = 'INFO (No Body)';
            }


            // Attempt to interpret server-timing headers if available
            // This is highly dependent on server/CDN configuration
            if (serverTiming.length > 0) {
                let cdnHit = false;
                let cfCacheStatus = null;
                let otherServerTimingInfo = [];

                serverTiming.forEach(function(st) {
                    if (st.name === 'cdn-cache' && st.description === 'HIT') {
                        cdnHit = true;
                    }
                    if (st.name === 'cf-cache-status') { // Cloudflare specific via Server-Timing
                        cfCacheStatus = st.description;
                    }
                    // Akamai: Server-Timing: cdn-cache; desc=HIT
                    // Fastly: Server-Timing: fastly_cache; desc=HIT
                    // Generic: server-timing: cache;desc=hit
                    if ((st.name.includes('cdn') || st.name.includes('cache')) && st.description.toUpperCase() === 'HIT') {
                        cdnHit = true;
                    }
                    otherServerTimingInfo.push(st.name + ':' + st.description);
                });

                if (cfCacheStatus) {
                    status = cfCacheStatus.toUpperCase() + ' (Cloudflare)';
                    detectedBy = 'ServerTiming (cf-cache-status)';
                } else if (cdnHit) {
                    status = 'HIT (CDN)';
                    detectedBy = 'ServerTiming (cdn-cache)';
                } else if (otherServerTimingInfo.length > 0 && status === 'DOWNLOADED') {
                    // If downloaded, but server timing has info, it might be a MISS from CDN
                    status = 'MISS_OR_DYNAMIC (CDN)';
                    detectedBy = 'ServerTiming ('+ otherServerTimingInfo.join(', ') +')';
                }
            }


            assetData.push({
                url: resource.name,
                status: status,
                transferSize: transferSize,
                decodedBodySize: decodedBodySize,
                initiatorType: resource.initiatorType,
                detectedBy: detectedBy,
                serverTiming: serverTiming // Send for more detailed analysis later
            });
        });

        if (assetData.length > 0) {
            // Send data to backend via AJAX
            const xhr = new XMLHttpRequest();
            xhr.open('POST', cache_detector_ajax.ajax_url, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if (xhr.status >= 200 && xhr.status < 400) {
                    // console.log('[Cache Detector] Asset data sent:', xhr.responseText);
                    // Potentially trigger an event or update admin bar here if needed immediately
                } else {
                    console.error('[Cache Detector] Error sending asset data:', xhr.status, xhr.statusText);
                }
            };
            xhr.onerror = function() {
                console.error('[Cache Detector] Network error sending asset data.');
            };

            const params = new URLSearchParams();
            params.append('action', 'cache_detector_receive_assets');
            params.append('nonce', cache_detector_ajax.nonce);
            params.append('asset_data', JSON.stringify(assetData));
            params.append('page_url', window.location.href);


            xhr.send(params.toString());
        }
    });
})();
