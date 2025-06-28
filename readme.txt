=== Cache Detector ===
Contributors: Jules AI Assistant
Tags: cache, performance, debug, http headers, cache status
Requires at least: 5.0
Tested up to: 6.5
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Detects and displays cache status for loaded URLs in WordPress. Helps in debugging cache configurations.

== Description ==

Cache Detector is a WordPress plugin designed to help site administrators and developers understand how their website's pages and assets are being cached. It inspects HTTP headers and other indicators from various caching layers (server cache, CDN, plugin caches, browser cache) and displays the status for each loaded resource.

This tool aims to make it easier to diagnose cache HIT/MISS issues, understand cache behavior with query strings (like UTM parameters), and verify that caching configurations are working as expected across different caching mechanisms.

Current version focuses on detecting main page cache status and preparing for asset status detection.

== Installation ==

1. Upload the `cache-detector` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. (Future steps will include viewing cache status in the admin bar or a dedicated debug panel).

== Frequently Asked Questions ==

= How does it detect cache status? =

The plugin inspects HTTP response headers for the main page and uses the browser's Performance API to analyze loaded assets (CSS, JS, images).
It looks for specific headers from CDNs (like Cloudflare's `CF-Cache-Status`), server-level caches (e.g., LiteSpeed's `X-LiteSpeed-Cache`, SiteGround's `X-SG-Cache`, Varnish's `X-Cache`), and WordPress caching plugins (e.g., `X-WP-Rocket-Cache`).
It also checks for HTML comments left by plugins like WP Rocket or W3 Total Cache (in debug mode).
For assets, cache status is inferred from `transferSize`, `decodedBodySize`, and `serverTiming` entries in the Performance API.
For WordPress REST API calls (routes including `/wp-json/`) initiated from the frontend by an administrator's browser, the plugin intercepts these calls (Fetch API and XMLHttpRequest), captures their response headers, and analyzes them using the same logic as main page requests.

= How do I see the cache status? =

If you are a logged-in administrator, the plugin adds an item to the WordPress Admin Bar.
It will display the main page's cache status (e.g., "Cache: HIT by Cloudflare").
Hovering over this item will reveal a dropdown:
*   "View Raw Page Headers": Shows the HTTP response headers collected for the main page.
*   A list of loaded assets (CSS, JS, images) from the current page, each with its inferred cache status (e.g., HIT, MISS, DOWNLOADED). Clicking an asset shows more details.
    *Note*: Asset data is sent from your browser to the server via AJAX and stored temporarily. It might take one page refresh after the initial visit for the full asset list to populate in the admin bar for a specific page.
*   A list of recent WordPress REST API calls (e.g., `/wp-json/...`) made from the frontend. Each entry shows the HTTP method, endpoint path, and its analyzed cache status. Clicking an entry shows more details, including the full URL, HTTP status code, and raw response headers for that API call.
    *Note*: REST API call data is also sent via AJAX and stored temporarily. The list shows calls initiated by your browser on frontend pages.

= What do the statuses mean? =

*   **HIT**: The resource was successfully served from a cache (CDN, server, plugin, or browser).
*   **MISS**: The resource was not found in the checked cache(s) and was likely served directly from the origin server or a lower cache layer.
*   **BYPASS**: A cache was intentionally skipped for this resource.
*   **DYNAMIC**: Typically from CDNs like Cloudflare, meaning the resource is not eligible for caching by default (often HTML pages) and was served from the origin.
*   **UNCACHED**: The resource is explicitly configured not to be cached by browser directives.
*   **POTENTIALLY BROWSER CACHED**: Server/CDN cache was a MISS or UNKNOWN, but browser caching headers are present.
*   **INFO (Handled)**: A caching layer (like Varnish) processed the request, but a specific HIT/MISS status wasn't determined from its headers alone.
*   **UNKNOWN**: The plugin could not determine the cache status from available headers or footprints.
The "by [Source]" part indicates which system or header was primarily used for the determination (e.g., "by Cloudflare", "by LiteSpeed", "by WP Rocket HTML").

= Will this plugin slow down my site? =

The plugin is designed to be lightweight. For regular site visitors, it adds no frontend processing.
For logged-in administrators, it collects headers on the server-side and uses the browser's Performance API on the client-side. These operations are generally efficient. The display is limited to the admin bar.

== Screenshots ==

1. Cache Detector in Admin Bar - Main Page HIT
2. Cache Detector Admin Bar - Dropdown with Asset List
3. Cache Detector - Asset Detail Alert

(Screenshots will be added once the plugin is visually testable in a WordPress environment)

== Changelog ==

= 0.1.0 - 2024-07-12 =
* Initial public release.
* Detects main page cache status via HTTP headers and HTML comments.
* Detects asset cache status (CSS, JS, images) using Performance API (transferSize, serverTiming) and AJAX.
* Detects cache status of WordPress REST API calls (`/wp-json/`) initiated from the frontend, by intercepting Fetch/XHR and analyzing response headers.
* Displays main page, asset, and REST API call cache statuses in the WordPress Admin Bar for logged-in administrators.
* Color-coded statuses for quick visual identification.
* Handles known headers from Cloudflare, LiteSpeed, WP Rocket, W3 Total Cache (debug), SiteGround Optimizer, Varnish, and generic browser caching for all detection types.
* Prioritized logic for interpreting multiple caching layers.

== Upgrade Notice ==

= 0.1.0 =
Initial version. Please report any issues or suggestions.

== Development ==

This plugin aims to be a comprehensive tool for WordPress cache diagnostics. Contributions and feedback are welcome.
GitHub: (Link to be added if publicly hosted)

Future considerations:
* More refined UI/UX, potentially a dedicated panel instead of just admin bar popups.
* Deeper integration for specific plugin APIs if available and useful.
* Visual cues on the frontend itself (optional overlay for admins).
* History or logging of cache statuses for pages.
* More robust handling of Service Worker caches for assets.
* Option to clear collected REST API call history from admin bar.
