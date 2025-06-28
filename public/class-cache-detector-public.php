<?php
/**
 * Public-facing functionality for Cache Detector.
 *
 * @package CacheDetector
 */

namespace Jules\CacheDetector;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public_Handler class.
 */
class Public_Handler {

    /**
     * Reference to the main plugin instance.
     * @var Main
     */
    private $main_plugin;

	// These properties will store the status for the *current* public request.
	private $current_request_cache_status;
	private $current_request_headers = array();

	/**
	 * Constructor.
     * @param Main $main_plugin Reference to the main plugin instance.
	 */
	public function __construct( Main $main_plugin ) {
        $this->main_plugin = $main_plugin;
        $this->current_request_cache_status = __('UNKNOWN by Cache Detector (Public Init)', 'cache-detector');

		// These hooks should only run on the front-end.
		if ( is_admin() && ! wp_doing_ajax() ) { // Allow AJAX for admin bar updates from frontend.
			return;
		}

		add_action( 'send_headers', array( $this, 'inspect_main_request_headers_public' ), 9 );
		add_action( 'template_redirect', array( $this, 'start_html_inspection_buffer_public' ), 0 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets_public' ) );
	}

    /**
     * Updates the main plugin instance with the status from the current public request.
     * This allows the Admin class (for the admin bar) to access this information.
     */
    private function update_main_plugin_status() {
        $this->main_plugin->public_request_cache_status = $this->current_request_cache_status;
        $this->main_plugin->public_request_headers = $this->current_request_headers;
    }


	/**
	 * Enqueue frontend assets.
	 */
	public function enqueue_frontend_assets_public() {
        // Only enqueue if admin bar is showing and user can manage options.
		if ( is_admin_bar_showing() && current_user_can( 'manage_options' ) ) {
			wp_enqueue_style(
				'cache-detector-admin-bar',
				CACHE_DETECTOR_PLUGIN_URL . 'assets/cache-detector-admin-bar.css',
				array(),
				$this->main_plugin->get_version()
			);

			wp_enqueue_script(
				'cache-detector-assets',
				CACHE_DETECTOR_PLUGIN_URL . 'assets/cache-detector-assets.js',
				array('jquery'), // jquery is listed as a dependency for AJAX convenience, ensure it's needed by the JS.
				$this->main_plugin->get_version(),
				true
			);

            // Ensure REQUEST_URI is set, fallback if not
            $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
			wp_localize_script(
				'cache-detector-assets',
				'cache_detector_ajax',
				array(
					'ajax_url'         => admin_url( 'admin-ajax.php' ),
					'asset_nonce'      => wp_create_nonce( 'cache_detector_asset_nonce' ),
					'rest_api_nonce'   => wp_create_nonce( 'cache_detector_rest_api_nonce' ),
					'current_page_url' => ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? "https" : "http" ) . "://$_SERVER[HTTP_HOST]" . $request_uri,
				)
			);
		}
	}

	/**
	 * Inspect main request headers on the public side.
	 */
	public function inspect_main_request_headers_public() {
		if ( headers_sent() || is_admin() || wp_doing_ajax() ) { // Check is_admin() again in case of weird hook firing
			return;
		}
		$this->current_request_headers = headers_list();
		$this->current_request_cache_status = $this->analyze_headers( $this->current_request_headers );

        $this->update_main_plugin_status();

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Cache Detector] Public Headers Inspected. Status: ' . $this->current_request_cache_status );
		}
	}

	/**
	 * Start output buffering for HTML inspection on the public side.
	 */
	public function start_html_inspection_buffer_public() {
		if ( headers_sent() || is_admin() || wp_doing_ajax() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		ob_start( array( $this, 'inspect_html_footprints_public' ) );
	}

	/**
	 * Inspect HTML for cache footprints. Callback for ob_start.
	 *
	 * @param string $buffer The output buffer.
	 * @return string Modified buffer or original buffer.
	 */
	public function inspect_html_footprints_public( $buffer ) {
		if ( empty( $buffer ) || ! is_string( $buffer ) || strlen($buffer) < 50 ) { // Basic check for minimal content
			return $buffer;
		}

		$html_status_text = 'UNKNOWN_HTML'; // Default if no footprint found
		$html_detected_by = '';

		// WP Rocket
		if ( strpos( $buffer, 'Performance optimized by WP Rocket' ) !== false ) {
			$html_detected_by = 'WP Rocket HTML';
			// WP Rocket adds "<!-- This website is like a Rocket, isn't it? Performance optimized by WP Rocket." for cached pages.
			// And "<!-- This website is like a Rocket, isn't it? Performance optimized by WP Rocket. Cached @ <timestamp> -->"
			// For non-cached (e.g. user excluded, cart page), the "cached@" part is missing.
			$html_status_text = ( strpos( $buffer, 'cached@' ) !== false ) ? 'HIT' : 'MISS_OR_BYPASS_HTML';
		}
		// W3 Total Cache
		elseif ( preg_match( '/<!--\s*Performance optimized by W3 Total Cache.*?Page Caching(?:\s+using\s+.*?)?:\s*(enabled\s*\(.*?\)|disabled|not\s*applicable|hit|miss)(?:\s*for\s*query\s*".*?")?(\s*<|\s*Content Delivery Network|\s*Minify|\s*Database Caching|\s*Object Caching|$)/is', $buffer, $w3tc_matches ) ) {
			$w3tc_status_val_raw = trim( $w3tc_matches[1] );
            $w3tc_status_val = strtolower($w3tc_status_val_raw);
			$html_detected_by = 'W3TC HTML Debug';

			if ( strpos($w3tc_status_val, 'enabled') !== false && strpos($w3tc_status_val, 'hit') !== false ) { $html_status_text = 'HIT'; } // "enabled (disk enhanced) - Hit"
            elseif ( strpos($w3tc_status_val, 'hit') !== false ) { $html_status_text = 'HIT';} // Direct "hit"
			elseif ( strpos($w3tc_status_val, 'miss') !== false ) { $html_status_text = 'MISS'; } // "enabled (disk enhanced) - Miss" or just "Miss"
			elseif ( strpos($w3tc_status_val, 'not applicable') !== false || strpos($w3tc_status_val, 'disabled') !== false ) { $html_status_text = 'BYPASS'; }
			else { $html_status_text = 'INFO: ' . esc_html( ucfirst( $w3tc_status_val_raw ) ); }
		}
        // LiteSpeed Cache
        elseif ( strpos( $buffer, '<!-- Page generated by LiteSpeed Cache ' ) !== false ) {
            $html_detected_by = 'LiteSpeed HTML';
            // LiteSpeed comment typically means HIT. If it's a MISS, it might not add this specific comment,
            // or relies on headers. For HTML footprint, this comment usually implies a cached version.
            $html_status_text = 'HIT';
        }
        // SG Optimizer
        elseif ( strpos( $buffer, '<!-- SG Optimizer -->') !== false || strpos( $buffer, '<!-- Cached by SG Optimizer') !== false ) {
            $html_detected_by = 'SG Optimizer HTML';
            $html_status_text = 'HIT'; // Presence of comment implies it was processed by SGO, usually a HIT.
        }


		if ( $html_status_text !== 'UNKNOWN_HTML' ) {
            // Combine with header status
            $header_status_part = explode(' by ', $this->current_request_cache_status)[0];
            $header_by_part = isset(explode(' by ', $this->current_request_cache_status)[1]) ? explode(' by ', $this->current_request_cache_status)[1] : '';

            $is_header_definitive_hit = ($header_status_part === 'HIT' && !empty($header_by_part) && (
                stripos($header_by_part, 'Cloudflare') !== false ||
                stripos($header_by_part, 'LiteSpeed') !== false ||
                stripos($header_by_part, 'SG Optimizer') !== false ||
                stripos($header_by_part, 'Varnish') !== false ||
                stripos($header_by_part, 'Sucuri') !== false ||
                stripos($header_by_part, 'Fastly') !== false ||
                stripos($header_by_part, 'Akamai') !== false
            ));

            $original_status_for_log = $this->current_request_cache_status;
            $status_updated_by_html_logic = false;

            if ($html_status_text === 'HIT') {
                // If headers show a definitive HIT from a known CDN/server cache,
                // and HTML also indicates a HIT from a *different* page cache system, append.
                if ($is_header_definitive_hit && stripos($html_detected_by, $header_by_part) === false && stripos($header_by_part, $html_detected_by) === false) {
                    $this->current_request_cache_status .= '; ' . $html_status_text . ' (HTML: ' . $html_detected_by . ')';
                } else {
                    // Otherwise, HTML HIT can confirm or override less definitive header statuses.
                    $this->current_request_cache_status = $html_status_text . ' by ' . $html_detected_by;
                }
                $status_updated_by_html_logic = true;
            } elseif (in_array($html_status_text, ['MISS', 'MISS_OR_BYPASS_HTML', 'BYPASS'])) {
                // If headers are UNKNOWN, MISS, BROWSER CACHED, or INFO, HTML MISS/BYPASS from page cache is more specific.
                // Or if the header MISS/BYPASS is from the same system as HTML, HTML confirms it.
                if (in_array($header_status_part, ['UNKNOWN', 'MISS', 'POTENTIALLY BROWSER CACHED', 'INFO (Handled)']) ||
                    ($header_by_part && stripos($html_detected_by, $header_by_part) !== false) ) {
                    $this->current_request_cache_status = $html_status_text . ' by ' . $html_detected_by;
                    $status_updated_by_html_logic = true;
                }
            } elseif (strpos($html_status_text, 'INFO:') === 0 && $header_status_part === 'UNKNOWN') {
                // If header is UNKNOWN, HTML INFO can be primary.
                $this->current_request_cache_status = $html_status_text . ' by ' . $html_detected_by;
                $status_updated_by_html_logic = true;
            }


			if ( $status_updated_by_html_logic && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Cache Detector] HTML Footprint. Original header status: "' . $original_status_for_log . '". HTML found: "' . $html_status_text . ' by ' . $html_detected_by . '". Final combined status: "' . $this->current_request_cache_status . '"');
			}
            $this->update_main_plugin_status();
		}
		return $buffer;
	}

	/**
	 * Analyze response headers to determine cache status.
	 * Public to allow Admin class to use it for REST API call analysis.
	 *
	 * @param array $headers Array of header strings.
	 * @return string Cache status string.
	 */
	public function analyze_headers( $headers ) {
		$status_candidates = array();
		if ( is_array( $headers ) ) {
			foreach ( $headers as $header_line ) {
                if (!is_string($header_line) || strpos($header_line, ':') === false) continue;
				list($name_raw, $value_raw) = explode( ':', $header_line, 2 );
				$name = strtolower( trim( $name_raw ) );
				$value = trim( $value_raw );
				$value_lower = strtolower( $value );

				if ( $name === 'cf-cache-status' ) { $status_candidates[] = array( 'status' => strtoupper( $value_lower ), 'by' => 'Cloudflare', 'priority' => 1 ); }
				elseif ( $name === 'x-sucuri-cache' ) { $status_candidates[] = array( 'status' => strtoupper( $value_lower ), 'by' => 'Sucuri FW', 'priority' => 1 ); }
                elseif ( $name === 'x-fastly-status' || ($name === 'x-cache' && (strpos($value_lower, 'fastly') !== false)) ) { // Fastly specific x-cache or x-fastly-status
                    $f_status = 'UNKNOWN';
                    if (strpos($value_lower, 'hit') !== false) $f_status = 'HIT';
                    elseif (strpos($value_lower, 'miss') !== false) $f_status = 'MISS';
                    elseif (strpos($value_lower, 'pass') !== false) $f_status = 'BYPASS'; // Fastly uses PASS
                    else $f_status = strtoupper($value_lower);
                    $status_candidates[] = array( 'status' => $f_status, 'by' => 'Fastly', 'priority' => 1 );
                }
                elseif ( $name === 'x-akamai-cache-action' || $name === 'x-akamai-cache-status' || ($name === 'x-cache' && (strpos($value_lower, 'akamai') !== false)) ) {
                    $ak_status = 'UNKNOWN';
                    if (strpos($value_lower, 'hit') !== false) $ak_status = 'HIT';
                    elseif (strpos($value_lower, 'miss') !== false) $ak_status = 'MISS';
                    elseif (strpos($value_lower, 'no_cache') !== false || strpos($value_lower, 'bypass') !== false) $ak_status = 'BYPASS';
                    else $ak_status = strtoupper($value_lower);
                     $status_candidates[] = array('status' => $ak_status, 'by' => 'Akamai', 'priority' => 1);
                }
				elseif ( $name === 'x-litespeed-cache' ) {
                    $ls_status = 'UNKNOWN';
                    if (strpos($value_lower, 'hit') !== false) $ls_status = 'HIT';
                    elseif (strpos($value_lower, 'miss') !== false) $ls_status = 'MISS';
                    elseif (strpos($value_lower, 'no-cache') !== false || strpos($value_lower, 'pass') !== false) $ls_status = 'BYPASS';
                    else $ls_status = strtoupper($value_lower);
                    $status_candidates[] = array( 'status' => $ls_status, 'by' => 'LiteSpeed', 'priority' => 2 );
                }
				elseif ( $name === 'x-sg-cache' ) { $status_candidates[] = array( 'status' => ( ( $value_lower === 'hit' ) ? 'HIT' : strtoupper( $value_lower ) ), 'by' => 'SG Optimizer', 'priority' => 2 ); }
				elseif ( $name === 'x-varnish-cache' || $name === 'x-varnish' || ($name === 'x-cache' && strpos($value_lower, 'varnish') !== false)) {
                    $v_status = 'UNKNOWN';
                    if (strpos($value_lower, 'hit') !== false) $v_status = 'HIT';
                    elseif (strpos($value_lower, 'miss') !== false) $v_status = 'MISS';
                    elseif (strpos($value_lower, 'pass') !== false) $v_status = 'BYPASS';
                    elseif (preg_match('/\d+/', $value_lower) && $name === 'x-varnish') $v_status = 'INFO (Handled)';
                    else $v_status = strtoupper($value_lower);
                    $status_candidates[] = array( 'status' => $v_status, 'by' => 'Varnish', 'priority' => 2 );
                }
				elseif ( $name === 'x-wp-rocket-cache' ) { $status_candidates[] = array( 'status' => strtoupper( $value_lower ), 'by' => 'WP Rocket Header', 'priority' => 3 ); }
                elseif ( $name === 'x-cache-handler' && strpos($value_lower, 'w3tc') !== false) { // W3TC can add this
                     $status_candidates[] = array( 'status' => 'INFO (Handled)', 'by' => 'W3TC Header', 'priority' => 3);
                }
				elseif ( $name === 'x-cache' ) { // Generic X-Cache (if not matched by more specific above)
					$xc_status = 'UNKNOWN';
					if ( strpos( $value_lower, 'hit' ) !== false ) { $xc_status = 'HIT'; }
					elseif ( strpos( $value_lower, 'miss' ) !== false ) { $xc_status = 'MISS'; }
                    elseif ( strpos( $value_lower, 'bypass') !== false || strpos( $value_lower, 'pass') !== false ) { $xc_status = 'BYPASS'; }
                    else $xc_status = strtoupper($value_lower);
					$status_candidates[] = array( 'status' => $xc_status, 'by' => 'Proxy (X-Cache)', 'priority' => 3 );
				}
                elseif ( $name === 'x-cache-hits' && intval($value) > 0) { $status_candidates[] = array( 'status' => 'HIT', 'by' => 'Proxy (X-Cache-Hits)', 'priority' => 3); }
				elseif ( $name === 'age' && is_numeric( $value ) && intval( $value ) > 0 ) { $status_candidates[] = array( 'status' => 'HIT_AGE', 'by' => 'Proxy/CDN (Age Header)', 'priority' => 4 ); }
			}
		}

		$final_status = 'UNKNOWN'; $final_by = '';
		if ( ! empty( $status_candidates ) ) {
			usort( $status_candidates, function( $a, $b ) { return $a['priority'] - $b['priority']; } );

            $chosen_candidate = null;
            foreach ($status_candidates as $candidate) { // Prefer specific HITs/MISS/BYPASS first by priority
                if (in_array($candidate['status'], array('HIT', 'MISS', 'BYPASS', 'UNCACHED', 'NO-CACHE', 'HIT_AGE', 'PASS'))) { $chosen_candidate = $candidate; break; }
            }
            if (!$chosen_candidate) { // Fallback to INFO or other statuses
                foreach ($status_candidates as $candidate) {
                     if ($candidate['status'] !== 'UNKNOWN' && !empty($candidate['status'])) { $chosen_candidate = $candidate; break; }
                }
            }
            if (!$chosen_candidate && !empty($status_candidates)) { // Absolute fallback to first if all were UNKNOWN
                $chosen_candidate = $status_candidates[0];
            }


			if ( $chosen_candidate ) {
				$final_status = $chosen_candidate['status'];
				$final_by = $chosen_candidate['by'];

                // Normalize status values
				if ( $final_status === 'HIT_AGE' || strpos($final_status, 'HIT') !== false) { $final_status = 'HIT'; } // Covers TCP_HIT, MEM_HIT etc.
                elseif ( strpos($final_status, 'MISS') !== false) { $final_status = 'MISS';}
                elseif ( $final_status === 'PASS' || $final_status === 'NOCACHE' || $final_status === 'NO_CACHE' || strpos($final_status, 'BYPASS') !== false) {$final_status = 'BYPASS';}
                elseif ( $final_status === 'INFO (Handled)' ) { $final_status = 'INFO (Handled)'; }
                // If still unknown or some other specific term, keep it.
			}
		}

		if ( in_array( $final_status, array( 'UNKNOWN', 'MISS', 'INFO (Handled)' ) ) ) {
			$cache_control = ''; $expires = ''; $pragma = '';
			if ( is_array( $headers ) ) {
				foreach ( $headers as $header_line ) {
                    if (!is_string($header_line) || strpos($header_line, ':') === false) continue;
					list($name_raw, $value_raw) = explode( ':', $header_line, 2 );
					$name = strtolower( trim( $name_raw ) );
					$value_l = strtolower( trim( $value_raw ) );
					if ( $name === 'cache-control' ) { $cache_control = $value_l; }
					if ( $name === 'expires' && strtotime($value_l) > time() ) { $expires = $value_l; } // Only consider future expires
					if ( $name === 'pragma' ) { $pragma = $value_l; }
				}
			}

			if ( strpos( $cache_control, 'no-cache' ) !== false || strpos( $cache_control, 'no-store' ) !== false || strpos( $pragma, 'no-cache' ) !== false ) {
                $final_status = 'UNCACHED'; $final_by = ($final_by ? $final_by . ', ' : '') . 'Browser Directives';
			} elseif ( strpos( $cache_control, 'max-age=0' ) !== false && ($final_status === 'MISS' || $final_status === 'UNKNOWN')) {
                $final_status = 'UNCACHED'; $final_by = ($final_by ? $final_by . ', ' : '') . 'Browser Directives';
            } elseif ( $final_status === 'UNKNOWN' && (!empty($cache_control) || !empty($expires)) ) {
                if ( (strpos($cache_control, 'public') !== false || strpos($cache_control, 'private') !== false ) &&
                     (strpos($cache_control, 'max-age=0') === false && strpos($cache_control, 's-maxage=0') === false && strpos($cache_control, 'no-store') === false && strpos($cache_control, 'no-cache') === false) ||
                     !empty($expires)
                ) {
                     $final_status = 'POTENTIALLY BROWSER CACHED'; $final_by = 'Browser Headers';
                }
            }
		}
		return $final_status . ( $final_by ? " by " . $final_by : "" );
	}
}
