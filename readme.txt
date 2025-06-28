=== Cache Detector ===
Contributors: julesai
Tags: cache, debug, performance, admin, development
Requires at least: 5.2
Tested up to: 6.4
Stable tag: 0.2.0
Requires PHP: 7.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: cache-detector
Domain Path: /languages

Detects and displays cache status for loaded URLs in WordPress, helping developers and site admins understand how their site's caching is behaving.

== Description ==

Cache Detector is a utility plugin for WordPress developers and administrators. It aims to provide clear insights into the caching mechanisms at play on your website.

It works by:
* Inspecting HTTP response headers for known caching signatures (e.g., Cloudflare, LiteSpeed, WP Rocket, Varnish, Sucuri, Fastly, Akamai, SG Optimizer).
* Looking for HTML footprints left by common WordPress caching plugins.
* Displaying the detected cache status for the main page request directly in the WordPress admin bar.
* Collecting information about loaded assets (CSS, JS, images) and REST API calls on the frontend, showing their cache status in the admin bar dropdown for quick analysis.

This tool helps you to:
* Verify if your page caching is working as expected (HIT, MISS, BYPASS).
* Identify which caching layer is serving your content.
* Quickly see cache details for assets and API calls without manually checking browser developer tools for every resource.

Currently, the plugin is focused on detection and display. Future enhancements may include more detailed reporting and configuration options.

== Installation ==

1. Upload the `cache-detector` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to your website's frontend. The cache status will appear in the admin bar if you are logged in and have 'manage_options' capability.

== Frequently Asked Questions ==

= Who is this plugin for? =

Developers, site administrators, or anyone who needs to understand and verify the caching behavior of their WordPress site.

= How does it detect cache status? =

It checks for specific HTTP headers set by various caching systems and also looks for HTML comments or footprints that caching plugins often leave in the page source.

= What caching systems can it detect? =

It can detect a range of systems including Cloudflare, LiteSpeed, WP Rocket (header and HTML), W3 Total Cache (HTML), Varnish, SG Optimizer, Sucuri, Fastly, Akamai, and general proxy/browser caching indicators.

= Where is the information displayed? =

In the WordPress admin bar. The main page status is shown directly, and details about assets and REST API calls are in a dropdown.

== Screenshots ==

1. Cache Detector in the Admin Bar - HIT Status
2. Cache Detector in the Admin Bar - MISS Status with Asset Details

(Note: Screenshots would be added to the plugin assets folder on WordPress.org)

== Changelog ==

= 0.2.0 =
* Refactored plugin into a class-based structure with namespaces.
* Implemented PSR-4 autoloading.
* Enhanced cache detection logic for headers and HTML footprints, including Fastly and Akamai.
* Improved admin bar display for assets and REST API calls.
* Added basic Internationalization (I18n) support.
* Created uninstall.php for basic cleanup.

= 0.1.0 =
* Initial release. Basic header detection and admin bar display. Asset and REST API call collection.

== Upgrade Notice ==

= 0.2.0 =
This version includes significant refactoring and improved detection. Please report any issues.
