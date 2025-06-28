<?php
/**
 * Plugin Name:       Cache Detector
 * Plugin URI:        https://example.com/plugins/cache-detector/
 * Description:       Detects and displays cache status for loaded URLs in WordPress.
 * Version:           0.1.0
 * Author:            Jules AI Assistant
 * Author URI:        https://example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cache-detector
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'CACHE_DETECTOR_VERSION', '0.1.0' );
define( 'CACHE_DETECTOR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CACHE_DETECTOR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Main plugin class
class Cache_Detector {

    private static $instance;
    private $main_request_cache_status = 'UNKNOWN by Cache Detector (Initializing)';
    private $main_request_headers = array();

    public static function get_instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'plugins_loaded', array( $this, 'init' ) );
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
    }

    public function init() {
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log('[Cache Detector] Plugin initialized.');
        }

        if( !is_admin() ) {
            add_action( 'send_headers', array( $this, 'inspect_main_request_headers' ), 9 );
            add_action( 'template_redirect', array( $this, 'start_html_inspection_buffer' ), 0 );
        }

        add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_menu' ), 999 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        add_action( 'wp_ajax_cache_detector_receive_assets', array( $this, 'handle_receive_assets_ajax' ) );
    }

    public function handle_receive_assets_ajax() {
        check_ajax_referer( 'cache_detector_asset_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.', 403 );
            return;
        }

        $asset_data_json = isset( $_POST['asset_data'] ) ? stripslashes( $_POST['asset_data'] ) : '';
        $page_url = isset( $_POST['page_url'] ) ? esc_url_raw( $_POST['page_url'] ) : '';

        if ( empty( $asset_data_json ) || empty( $page_url ) ) {
            wp_send_json_error( 'Missing data.', 400 );
            return;
        }

        $asset_data = json_decode( $asset_data_json, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            wp_send_json_error( 'Invalid JSON data: ' . json_last_error_msg(), 400 );
            return;
        }

        $sanitized_asset_data = array();
        if (is_array($asset_data)) {
            foreach ($asset_data as $item) {
                $sanitized_item = array(
                    'url'             => isset($item['url']) ? esc_url_raw($item['url']) : '',
                    'status'          => isset($item['status']) ? sanitize_text_field($item['status']) : 'UNKNOWN',
                    'transferSize'    => isset($item['transferSize']) ? intval($item['transferSize']) : 0,
                    'decodedBodySize' => isset($item['decodedBodySize']) ? intval($item['decodedBodySize']) : 0,
                    'initiatorType'   => isset($item['initiatorType']) ? sanitize_text_field($item['initiatorType']) : '',
                    'detectedBy'      => isset($item['detectedBy']) ? sanitize_text_field($item['detectedBy']) : '',
                    'serverTiming'    => array(), // Initialize
                );
                // Sanitize serverTiming if it exists and is an array
                if (isset($item['serverTiming']) && is_array($item['serverTiming'])) {
                    foreach ($item['serverTiming'] as $st_entry) {
                        if (is_array($st_entry) && isset($st_entry['name'])) { // Assuming it's an array of objects
                             $sanitized_item['serverTiming'][] = sanitize_text_field($st_entry['name'] . ': ' . (isset($st_entry['description']) ? $st_entry['description'] : (isset($st_entry['duration']) ? $st_entry['duration'] : '')));
                        } elseif (is_string($st_entry)) { // Fallback if it's already a string
                            $sanitized_item['serverTiming'][] = sanitize_text_field($st_entry);
                        }
                    }
                }

                if (!empty($sanitized_item['url'])) {
                    $sanitized_asset_data[] = $sanitized_item;
                }
            }
        }

        if (empty($sanitized_asset_data)) {
            wp_send_json_error( 'No valid asset data provided after sanitization.', 400 );
            return;
        }

        $user_id = get_current_user_id();
        $transient_key = 'cd_assets_' . md5( $page_url . '_' . $user_id );
        set_transient( $transient_key, $sanitized_asset_data, 5 * MINUTE_IN_SECONDS );

        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log('[Cache Detector] AJAX Handler: Received and stored asset data for URL: ' . $page_url . '. Transient key: ' . $transient_key . '. Count: ' . count($sanitized_asset_data));
        }

        wp_send_json_success( array( 'message' => 'Asset data received.', 'count' => count( $sanitized_asset_data ) ) );
    }

    public function enqueue_frontend_assets() {
        if ( is_admin_bar_showing() && current_user_can('manage_options') && !is_admin() ) {
            wp_enqueue_style(
                'cache-detector-admin-bar',
                CACHE_DETECTOR_PLUGIN_URL . 'assets/cache-detector-admin-bar.css',
                array(),
                CACHE_DETECTOR_VERSION
            );

            wp_enqueue_script(
                'cache-detector-assets',
                CACHE_DETECTOR_PLUGIN_URL . 'assets/cache-detector-assets.js',
                array(),
                CACHE_DETECTOR_VERSION,
                true
            );

            wp_localize_script(
                'cache-detector-assets',
                'cache_detector_ajax',
                array(
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'nonce'    => wp_create_nonce( 'cache_detector_asset_nonce' ),
                )
            );
        }
    }

    public function enqueue_admin_assets() {
        if ( is_admin_bar_showing() && current_user_can('manage_options') ) {
             wp_enqueue_style(
                'cache-detector-admin-bar',
                CACHE_DETECTOR_PLUGIN_URL . 'assets/cache-detector-admin-bar.css',
                array(),
                CACHE_DETECTOR_VERSION
            );
        }
    }

    public function add_admin_bar_menu( $wp_admin_bar ) {
        if ( ! current_user_can('manage_options') ) return;

        // On admin pages (except AJAX), header/HTML inspection doesn't run for main content,
        // so status would be the initial value or from a previous frontend load if we stored it more globally.
        // For now, it shows the current state of $this->main_request_cache_status.
        $status_output = $this->main_request_cache_status;

        $status_class = 'cache-status-unknown';
        if (strpos($status_output, 'HIT') === 0) $status_class = 'cache-status-hit';
        elseif (strpos($status_output, 'MISS') === 0) $status_class = 'cache-status-miss';
        elseif (strpos($status_output, 'BYPASS') === 0) $status_class = 'cache-status-bypass';
        elseif (strpos($status_output, 'DYNAMIC') === 0) $status_class = 'cache-status-dynamic';
        elseif (strpos($status_output, 'UNCACHED') === 0) $status_class = 'cache-status-uncached';
        elseif (strpos($status_output, 'INFO') === 0) $status_class = 'cache-status-dynamic'; // Treat INFO as dynamic for color

        $wp_admin_bar->add_node( array(
            'id'    => 'cache_detector_status',
            'title' => '<span class="ab-icon dashicons-performance"></span><span class="cache-detector-status-text ' . esc_attr($status_class) . '">Cache: ' . esc_html( $status_output ) . '</span>',
            'href'  => '#',
            'meta'  => array( 'class' => 'cache-detector-admin-bar-node', 'title' => 'Main Page Cache Status by Cache Detector' ),
        ) );

        $headers_string = '';
        if (!empty($this->main_request_headers) && is_array($this->main_request_headers)) {
            foreach($this->main_request_headers as $header) $headers_string .= esc_html($header) . "\n";
        } else {
            $headers_string = 'No headers captured for this view (or not on frontend).';
        }

        $wp_admin_bar->add_node( array(
            'id'     => 'cache_detector_headers_raw',
            'parent' => 'cache_detector_status',
            'title'  => 'View Raw Page Headers',
            'href'   => '#',
            'meta'   => array( 'onclick' => 'alert("Collected Headers For Main Page:\n\n' . esc_js($headers_string) . '"); return false;', 'title' => 'Click to view raw response headers for the main page.' )
        ));

        if (is_admin()) return; // Asset display is only for frontend.

        $user_id = get_current_user_id();
        $current_page_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $transient_key = 'cd_assets_' . md5( $current_page_url . '_' . $user_id );
        $assets = get_transient( $transient_key );

        if ( $assets && is_array( $assets ) ) {
            $wp_admin_bar->add_node( array( 'id' => 'cache_detector_assets_title', 'parent' => 'cache_detector_status', 'title' => '--- Loaded Assets (Max 20) ---', 'href' => false, 'meta'  => array('class' => 'cache-detector-asset-title') ) );
            $count = 0;
            foreach ( $assets as $index => $asset ) {
                if ($count++ >= 20) {
                    $wp_admin_bar->add_node( array( 'id' => 'cache_detector_asset_limit', 'parent' => 'cache_detector_status', 'title' => '...more assets loaded (display limited)', 'href' => '#') );
                    break;
                }
                $asset_status_class = 'cache-status-unknown';
                if (strpos($asset['status'], 'HIT') === 0) $asset_status_class = 'cache-status-hit';
                elseif (strpos($asset['status'], 'MISS') === 0 || strpos($asset['status'], 'DOWNLOADED') === 0 || strpos($asset['status'], 'DYNAMIC') === 0) $asset_status_class = 'cache-status-miss'; // Treat downloaded/dynamic as MISS for assets
                elseif (strpos($asset['status'], 'BYPASS') === 0) $asset_status_class = 'cache-status-bypass';
                elseif (strpos($asset['status'], 'UNCACHED') === 0) $asset_status_class = 'cache-status-uncached';

                $asset_url_display = basename( $asset['url'] );
                if (strlen($asset_url_display) > 45) $asset_url_display = '...' . substr($asset_url_display, -42);
                $title = '<span class="' . esc_attr($asset_status_class) . '" style="display: inline-block; padding: 0px 2px; border-radius: 2px; margin-right: 3px; font-size:0.9em;">' . esc_html( strtoupper(explode(' ', $asset['status'])[0]) ) . '</span> ' . esc_html( $asset_url_display );

                $full_details = "URL: " . esc_js($asset['url']) . "\n";
                $full_details .= "Status: " . esc_js($asset['status']) . "\n";
                $full_details .= "Detected By: " . esc_js($asset['detectedBy']) . "\n";
                $full_details .= "Transfer Size: " . esc_js($asset['transferSize']) . " B\n";
                $full_details .= "Decoded Size: " . esc_js($asset['decodedBodySize']) . " B\n";
                $full_details .= "Initiator: " . esc_js($asset['initiatorType']);
                if (!empty($asset['serverTiming']) && is_array($asset['serverTiming'])) {
                    $full_details .= "\nServer Timing: \n";
                    foreach($asset['serverTiming'] as $st) $full_details .= " - " . esc_js($st) . "\n";
                }
                $wp_admin_bar->add_node( array( 'id' => 'cache_detector_asset_' . $index, 'parent' => 'cache_detector_status', 'title' => $title, 'href' => '#', 'meta' => array( 'title' => esc_attr($asset['url']) . ' | Status: ' . esc_attr($asset['status']), 'onclick' => 'alert("Asset Details:\n\n' . $full_details . '"); return false;' ) ) );
            }
        } else {
            if ( defined('WP_DEBUG') && WP_DEBUG && !is_admin() ) error_log('[Cache Detector] Admin Bar: No asset data in transient ' . $transient_key);
        }
    }

    public function start_html_inspection_buffer() {
        if ( is_admin() || wp_doing_ajax() || !current_user_can('manage_options') || headers_sent() ) {
            return;
        }
        ob_start(array($this, 'inspect_html_footprints'));
    }

    public function inspect_html_footprints( $buffer ) {
        if ( empty($buffer) || !is_string($buffer) ) return $buffer;

        $html_status_text = 'UNKNOWN';
        $html_detected_by = '';

        if ( strpos( $buffer, 'Performance optimized by WP Rocket' ) !== false ) {
            $html_detected_by = 'WP Rocket HTML';
            $html_status_text = (strpos( $buffer, 'cached@' ) !== false) ? 'HIT' : 'MISS_OR_BYPASS_HTML';
        } elseif ( preg_match( '/<!--\s*Performance optimized by W3 Total Cache.*?Page Caching(?:\s+using\s+.*?)?:\s*(.*?)(\s*<|\s*Content Delivery Network|\s*Minify|\s*Database Caching|\s*Object Caching|$)/is', $buffer, $w3tc_matches ) ) {
            $w3tc_status_val = strtolower(trim($w3tc_matches[1]));
            $html_detected_by = 'W3TC HTML Debug';
            if ( $w3tc_status_val === 'enhanced' || $w3tc_status_val === 'basic' || strpos($w3tc_status_val, 'hit') !== false ) $html_status_text = 'HIT';
            elseif ( strpos($w3tc_status_val, 'miss') !== false ) $html_status_text = 'MISS';
            elseif ( strpos($w3tc_status_val, 'not appicable') !== false || strpos($w3tc_status_val, 'disabled') !== false ) $html_status_text = 'BYPASS';
            else $html_status_text = 'INFO: ' . esc_html(ucfirst($w3tc_status_val));
        }

        if ($html_status_text !== 'UNKNOWN') {
            $header_status_part = explode(' by ', $this->main_request_cache_status)[0];
            $header_by_part = isset(explode(' by ', $this->main_request_cache_status)[1]) ? explode(' by ', $this->main_request_cache_status)[1] : '';
            $is_header_definitive_cdn_or_server_hit = ($header_status_part === 'HIT' && (stripos($header_by_part, 'Cloudflare') !== false || stripos($header_by_part, 'LiteSpeed') !== false || stripos($header_by_part, 'SG Optimizer') !== false || stripos($header_by_part, 'Varnish') !== false));

            $updated_status = false;
            if ($html_status_text === 'HIT') {
                if ($is_header_definitive_cdn_or_server_hit && stripos($header_by_part, $html_detected_by) === false) {
                    $this->main_request_cache_status .= '; ' . $html_status_text . ' (HTML: ' . $html_detected_by . ')';
                } else {
                    $this->main_request_cache_status = $html_status_text . ' by ' . $html_detected_by;
                }
                $updated_status = true;
            } elseif (in_array($html_status_text, ['MISS', 'MISS_OR_BYPASS_HTML', 'BYPASS'])) {
                if (in_array($header_status_part, ['UNKNOWN', 'MISS', 'POTENTIALLY BROWSER CACHED', 'INFO (Handled)']) || stripos($header_by_part, $html_detected_by) !== false) {
                    $this->main_request_cache_status = $html_status_text . ' by ' . $html_detected_by;
                    $updated_status = true;
                }
            } elseif (strpos($html_status_text, 'INFO:') === 0 && $header_status_part === 'UNKNOWN') {
                $this->main_request_cache_status = $html_status_text . ' by ' . $html_detected_by;
                $updated_status = true;
            }
            if ($updated_status && defined('WP_DEBUG') && WP_DEBUG) {
                 error_log('[Cache Detector] HTML Footprint. Original header status: ' . $header_status_part . ' by ' . $header_by_part . '. HTML found: ' . $html_status_text . ' by ' . $html_detected_by . '. Final combined status: ' . $this->main_request_cache_status);
            }
        }
        return $buffer;
    }

    public function inspect_main_request_headers() {
        if ( is_admin() || wp_doing_ajax() || headers_sent() ) return;
        $this->main_request_headers = headers_list();
        $this->main_request_cache_status = $this->analyze_headers( $this->main_request_headers );
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log('[Cache Detector] Headers Inspected. Status: ' . $this->main_request_cache_status . '. Headers: ' . print_r($this->main_request_headers, true));
        }
    }

    private function analyze_headers( $headers ) {
        $status_candidates = [];
        if (is_array($headers)) {
            foreach ( $headers as $header_line ) {
                $header_parts = explode(':', $header_line, 2);
                if (count($header_parts) < 2) continue;
                $name = strtolower(trim($header_parts[0]));
                $value = trim($header_parts[1]);
                $value_lower = strtolower($value);

                if ( $name === 'cf-cache-status' ) $status_candidates[] = ['status' => strtoupper($value_lower), 'by' => 'Cloudflare', 'priority' => 1];
                elseif ( $name === 'x-litespeed-cache' ) {
                    $ls_status = 'UNKNOWN';
                    if (strpos($value_lower, 'hit') !== false) $ls_status = 'HIT';
                    elseif (strpos($value_lower, 'miss') !== false) $ls_status = 'MISS';
                    else $ls_status = strtoupper($value_lower);
                    $status_candidates[] = ['status' => $ls_status, 'by' => 'LiteSpeed', 'priority' => 2];
                }
                elseif ( $name === 'x-sg-cache' ) $status_candidates[] = ['status' => (($value_lower === 'hit') ? 'HIT' : strtoupper($value_lower)), 'by' => 'SG Optimizer', 'priority' => 2];
                elseif ( $name === 'x-varnish-cache' ) $status_candidates[] = ['status' => (strpos($value_lower, 'hit') !== false ? 'HIT' : (strpos($value_lower, 'miss') !== false ? 'MISS' : strtoupper($value_lower))), 'by' => 'Varnish (X-Varnish-Cache)', 'priority' => 2];
                elseif ( $name === 'x-wp-rocket-cache' ) $status_candidates[] = ['status' => strtoupper($value_lower), 'by' => 'WP Rocket Header', 'priority' => 3];
                elseif ( $name === 'x-cache' ) { // Generic X-Cache, could be Varnish or other
                    $xc_status = 'UNKNOWN';
                    if (strpos($value_lower, 'hit') !== false) $xc_status = 'HIT';
                    elseif (strpos($value_lower, 'miss') !== false) $xc_status = 'MISS';
                    else $xc_status = strtoupper($value_lower);
                    $status_candidates[] = ['status' => $xc_status, 'by' => 'Proxy (X-Cache)', 'priority' => 3];
                }
                elseif ( $name === 'x-varnish' ) $status_candidates[] = ['status' => 'INFO_HANDLED', 'by' => 'Varnish ID', 'priority' => 4];
                elseif ( $name === 'age' && is_numeric($value) && intval($value) > 0 ) $status_candidates[] = ['status' => 'HIT_AGE', 'by' => 'Proxy/CDN (Age Header)', 'priority' => 4];
                elseif ( $name === 'x-sucuri-cache' ) $status_candidates[] = ['status' => strtoupper($value_lower), 'by' => 'Sucuri FW', 'priority' => 1];
                 // Add more known headers here, e.g. Fastly, Akamai etc.
                 // Fastly: x-served-by, x-cache (HIT, MISS), x-cache-hits
                 // Akamai: x-check-cacheable, x-cache (TCP_HIT, TCP_MISS, etc)
            }
        }

        $final_status = 'UNKNOWN'; $final_by = '';
        if (!empty($status_candidates)) {
            usort($status_candidates, function($a, $b) { return $a['priority'] - $b['priority']; });
            $chosen_candidate = null;
            foreach ($status_candidates as $candidate) { // Prefer HITs first
                if ($candidate['status'] === 'HIT' || $candidate['status'] === 'HIT_AGE') { $chosen_candidate = $candidate; break; }
            }
            if (!$chosen_candidate) { // If no HIT, take the highest priority non-UNKNOWN, non-INFO
                foreach ($status_candidates as $candidate) {
                    if ($candidate['status'] !== 'INFO_HANDLED' && $candidate['status'] !== 'UNKNOWN') { $chosen_candidate = $candidate; break; }
                }
            }
            if (!$chosen_candidate && !empty($status_candidates) && $status_candidates[0]['status'] === 'INFO_HANDLED') { // Fallback to INFO
                $chosen_candidate = $status_candidates[0];
            }
             if (!$chosen_candidate && !empty($status_candidates)) { // Fallback to first if all else fails
                $chosen_candidate = $status_candidates[0];
            }

            if ($chosen_candidate) {
                $final_status = $chosen_candidate['status'];
                $final_by = $chosen_candidate['by'];
                if ($final_status === 'HIT_AGE') $final_status = 'HIT';
                if ($final_status === 'INFO_HANDLED') $final_status = 'INFO (Handled)';
            }
        }

        if ( in_array($final_status, ['UNKNOWN', 'MISS', 'INFO (Handled)']) ) {
            $cache_control = ''; $expires = ''; $pragma = '';
            if(is_array($headers)) {
                foreach($headers as $header_line) {
                    $header_parts = explode(':', $header_line, 2);
                    if (count($header_parts) < 2) continue;
                    $name = strtolower(trim($header_parts[0]));
                    $value_l = strtolower(trim($header_parts[1]));
                    if ($name === 'cache-control') $cache_control = $value_l;
                    if ($name === 'expires') $expires = $value_l;
                    if ($name === 'pragma') $pragma = $value_l;
                }
            }
            if (strpos($cache_control, 'no-cache') !== false || strpos($cache_control, 'no-store') !== false || strpos($pragma, 'no-cache') !== false) {
                $final_status = 'UNCACHED'; $final_by = ($final_by ? $final_by . ', ' : '') . 'Browser Directives';
            } elseif (strpos($cache_control, 'max-age=0') !== false && $final_status === 'MISS') {
                $final_status = 'UNCACHED'; $final_by = ($final_by ? $final_by . ', ' : '') . 'Browser Directives';
            } elseif ($final_status === 'UNKNOWN' && (!empty($cache_control) || !empty($expires))) {
                if (strpos($cache_control, 'public') !== false || (strpos($cache_control, 'private') !== false && strpos($cache_control, 'max-age=0') === false ) ) {
                     $final_status = 'POTENTIALLY BROWSER CACHED'; $final_by = 'Browser Headers';
                }
            }
        }
        return $final_status . ($final_by ? " by " . $final_by : "");
    }

    public function get_main_request_cache_status() {
        return $this->main_request_cache_status;
    }

    public function get_main_request_headers() {
        return $this->main_request_headers;
    }

    public function activate() {
        if ( defined('WP_DEBUG') && WP_DEBUG ) error_log('[Cache Detector] Plugin activated.');
    }

    public function deactivate() {
        if ( defined('WP_DEBUG') && WP_DEBUG ) error_log('[Cache Detector] Plugin deactivated.');
    }
}

Cache_Detector::get_instance();
