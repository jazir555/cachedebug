<?php
/**
 * Admin-specific functionality for Cache Detector.
 *
 * @package CacheDetector
 */

namespace Jules\CacheDetector;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin class.
 */
class Admin {

    /**
     * Reference to the main plugin instance.
     * @var Main
     */
    private $main_plugin;

	/**
	 * Constructor.
     * @param Main $main_plugin Reference to the main plugin instance.
	 */
	public function __construct( Main $main_plugin ) {
        $this->main_plugin = $main_plugin;

		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_menu' ), 999 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_cache_detector_receive_assets', array( $this, 'handle_receive_assets_ajax' ) );
		add_action( 'wp_ajax_cache_detector_receive_rest_api_calls', array( $this, 'handle_receive_rest_api_calls_ajax' ) );
	}

	/**
	 * Enqueue admin assets.
	 */
	public function enqueue_admin_assets() {
		// This function is hooked to 'admin_enqueue_scripts', so it only runs on admin pages.
		if ( is_admin_bar_showing() && current_user_can( 'manage_options' ) ) {
			wp_enqueue_style(
				'cache-detector-admin-bar',
				CACHE_DETECTOR_PLUGIN_URL . 'assets/cache-detector-admin-bar.css',
				array(),
				$this->main_plugin->get_version()
			);
		}
	}

	/**
	 * Add admin bar menu.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar WP_Admin_Bar instance.
	 */
	public function add_admin_bar_menu( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

        // Get status from the main plugin class, which is updated by Public_Handler on frontend.
        $current_status = $this->main_plugin->public_request_cache_status;
        $current_headers = $this->main_plugin->public_request_headers;

        // If on an admin page and no frontend status has been captured yet, show a default admin status.
        if (is_admin() && $current_status === __('UNKNOWN (Main Init)', 'cache-detector')) {
            $current_status = __('Admin Area (No Frontend Page Analyzed Yet)', 'cache-detector');
        } elseif (empty($current_status)) {
            $current_status = __('UNKNOWN (Admin Bar)', 'cache-detector'); // Fallback
        }


		$status_class = 'cache-status-unknown';
		if ( strpos( $current_status, 'HIT' ) === 0 ) { $status_class = 'cache-status-hit'; }
		elseif ( strpos( $current_status, 'MISS' ) === 0 ) { $status_class = 'cache-status-miss'; }
		elseif ( strpos( $current_status, 'BYPASS' ) === 0 ) { $status_class = 'cache-status-bypass'; }
		elseif ( strpos( $current_status, 'DYNAMIC' ) === 0 ) { $status_class = 'cache-status-dynamic'; }
        elseif ( strpos( $current_status, 'UNCACHED' ) === 0 ) { $status_class = 'cache-status-uncached'; }
		elseif ( strpos( $current_status, 'INFO' ) === 0 ) { $status_class = 'cache-status-dynamic'; }
        elseif ( strpos( $current_status, __('Admin Area', 'cache-detector')) === 0) { $status_class = 'cache-status-admin';}


		$wp_admin_bar->add_node(
			array(
				'id'    => 'cache_detector_status',
				/* translators: %s: Cache status */
				'title' => '<span class="ab-icon dashicons-performance"></span><span class="cache-detector-status-text ' . esc_attr( $status_class ) . '">' . sprintf(esc_html__( 'Cache: %s', 'cache-detector' ), esc_html( $current_status )) . '</span>',
				'href'  => '#',
				'meta'  => array( 'class' => 'cache-detector-admin-bar-node', 'title' => esc_attr__( 'Cache Detector Status', 'cache-detector' ) ),
			)
		);

		$headers_string = '';
		if ( ! empty( $current_headers ) && is_array( $current_headers ) ) {
			foreach ( $current_headers as $header ) {
				$headers_string .= esc_html( $header ) . "\n";
			}
		} else {
			$headers_string = esc_html__( 'No headers captured for this view (or not on a frontend page).', 'cache-detector' );
		}

		$wp_admin_bar->add_node(
			array(
				'id'     => 'cache_detector_headers_raw',
				'parent' => 'cache_detector_status',
				'title'  => esc_html__( 'View Raw Page Headers', 'cache-detector' ),
				'href'   => '#',
				'meta'   => array( 'onclick' => 'alert("' . esc_js( __( 'Collected Headers For Main Page:', 'cache-detector' ) ) . '\n\n' . esc_js( $headers_string ) . '"); return false;', 'title' => esc_attr__( 'Click to view raw response headers for the main page.', 'cache-detector' ) ),
			)
		);

		// Asset and REST API display is only relevant when on a frontend page where data is collected.
		if ( is_admin() && ! wp_doing_ajax() ) {
            $wp_admin_bar->add_node( array(
                'id' => 'cache_detector_info_admin',
                'parent' => 'cache_detector_status',
                'title' => esc_html__( 'Asset/REST details available on frontend.', 'cache-detector' ),
                'href' => false,
                'meta' => array('class' => 'cache-detector-admin-bar-info')
            ));
			return;
        }

		// Display Assets (from transient, collected by frontend JS)
		$user_id = get_current_user_id();
        // Ensure REQUEST_URI is set, fallback if not (e.g. CLI context, though unlikely for admin bar)
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
		$current_page_url = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? "https" : "http" ) . "://$_SERVER[HTTP_HOST]" . $request_uri;

		$transient_key_assets = 'cd_assets_' . md5( $current_page_url . '_' . $user_id );
		$assets = get_transient( $transient_key_assets );

		if ( $assets && is_array( $assets ) && !empty($assets) ) {
            $asset_summary = array( 'HIT' => 0, 'MISS' => 0, 'BYPASS' => 0, 'UNCACHED' => 0, 'OTHER' => 0, 'TOTAL' => count($assets) );
            foreach($assets as $asset_item) {
                $s = strtoupper(explode(' ', $asset_item['status'])[0]);
                if (strpos($s, 'HIT') !== false) $asset_summary['HIT']++;
                elseif (strpos($s, 'MISS') !== false || strpos($s, 'DOWNLOADED') !== false || strpos($s, 'DYNAMIC') !== false) $asset_summary['MISS']++;
                elseif (strpos($s, 'BYPASS') !== false) $asset_summary['BYPASS']++;
                elseif (strpos($s, 'UNCACHED') !== false) $asset_summary['UNCACHED']++;
                else $asset_summary['OTHER']++;
            }
            $asset_summary_str = sprintf(esc_html__('Assets: %d (H:%d M:%d B:%d U:%d O:%d)', 'cache-detector'), $asset_summary['TOTAL'], $asset_summary['HIT'], $asset_summary['MISS'], $asset_summary['BYPASS'], $asset_summary['UNCACHED'], $asset_summary['OTHER']);

			$wp_admin_bar->add_node( array( 'id' => 'cache_detector_assets_title', 'parent' => 'cache_detector_status', 'title' => $asset_summary_str . ' ' . esc_html__( '(Max 20 shown)', 'cache-detector' ), 'href' => false, 'meta'  => array('class' => 'cache-detector-asset-title') ) );
			$count = 0;
			foreach ( $assets as $index => $asset ) {
                if ($count++ >= 20) {
                    $wp_admin_bar->add_node( array( 'id' => 'cache_detector_asset_limit', 'parent' => 'cache_detector_status', 'title' => esc_html__( '...more assets loaded (display limited to 20)', 'cache-detector' ), 'href' => '#') );
                    break;
                }
				$asset_status_class = 'cache-status-unknown';
                if (strpos($asset['status'], 'HIT') === 0) $asset_status_class = 'cache-status-hit';
                elseif (strpos($asset['status'], 'MISS') === 0 || strpos($asset['status'], 'DOWNLOADED') === 0 || strpos($asset['status'], 'DYNAMIC') === 0) $asset_status_class = 'cache-status-miss';
                elseif (strpos($asset['status'], 'BYPASS') === 0) $asset_status_class = 'cache-status-bypass';
                elseif (strpos($asset['status'], 'UNCACHED') === 0) $asset_status_class = 'cache-status-uncached';

				$asset_url_display = basename( $asset['url'] );
				if ( strlen( $asset_url_display ) > 45 ) { $asset_url_display = '...' . substr( $asset_url_display, -42 ); }
				$title = '<span class="' . esc_attr($asset_status_class) . '" style="display: inline-block; padding: 0px 2px; border-radius: 2px; margin-right: 3px; font-size:0.9em;">' . esc_html( strtoupper(explode(' ', $asset['status'])[0]) ) . '</span> ' . esc_html( $asset_url_display );

                $full_details = sprintf(esc_js(__( 'URL: %s', 'cache-detector' )), esc_js($asset['url'])) . "\n";
                $full_details .= sprintf(esc_js(__( 'Status: %s', 'cache-detector' )), esc_js($asset['status'])) . "\n";
                $full_details .= sprintf(esc_js(__( 'Detected By: %s', 'cache-detector' )), esc_js($asset['detectedBy'])) . "\n";
                $full_details .= sprintf(esc_js(__( 'Transfer Size: %s B', 'cache-detector' )), esc_js($asset['transferSize'])) . "\n";
                $full_details .= sprintf(esc_js(__( 'Decoded Size: %s B', 'cache-detector' )), esc_js($asset['decodedBodySize'])) . "\n";
                $full_details .= sprintf(esc_js(__( 'Initiator: %s', 'cache-detector' )), esc_js($asset['initiatorType']));
                if (!empty($asset['serverTiming']) && is_array($asset['serverTiming'])) {
                    $full_details .= "\n" . esc_js(__( 'Server Timing:', 'cache-detector' )) . " \n";
                    foreach($asset['serverTiming'] as $st) $full_details .= " - " . esc_js($st) . "\n";
                }
				$wp_admin_bar->add_node( array( 'id' => 'cache_detector_asset_' . $index, 'parent' => 'cache_detector_status', 'title' => $title, 'href' => '#', 'meta' => array( 'title' => esc_attr($asset['url']) . ' | ' . esc_attr__( 'Status:', 'cache-detector') . ' ' . esc_attr($asset['status']), 'onclick' => 'alert("' . esc_js(__( 'Asset Details:', 'cache-detector' )) . '\n\n' . $full_details . '"); return false;' ) ) );
			}
		} elseif (!is_admin()) { // Only show "no assets" if on frontend
            $wp_admin_bar->add_node( array( 'id' => 'cache_detector_no_assets', 'parent' => 'cache_detector_status', 'title' => esc_html__( '(No asset data for this page view)', 'cache-detector' ), 'href' => false, 'meta'  => array('class' => 'cache-detector-admin-bar-info') ) );
        }


		// Display REST API Calls
		$transient_key_rest = 'cd_rest_calls_' . $user_id;
		$rest_api_calls = get_transient( $transient_key_rest );

		if ( $rest_api_calls && is_array( $rest_api_calls ) && !empty($rest_api_calls) ) {
            $rest_summary = array( 'HIT' => 0, 'MISS' => 0, 'BYPASS' => 0, 'UNCACHED' => 0, 'OTHER' => 0, 'TOTAL' => count($rest_api_calls) );
            foreach($rest_api_calls as $rest_call) {
                $s = strtoupper(explode(' ', $rest_call['cache_status'])[0]);
                if (strpos($s, 'HIT') !== false) $rest_summary['HIT']++;
                elseif (strpos($s, 'MISS') !== false) $rest_summary['MISS']++;
                elseif (strpos($s, 'BYPASS') !== false) $rest_summary['BYPASS']++;
                elseif (strpos($s, 'UNCACHED') !== false) $rest_summary['UNCACHED']++;
                else $rest_summary['OTHER']++;
            }
            $rest_summary_str = sprintf(esc_html__('REST API: %d (H:%d M:%d B:%d U:%d O:%d)', 'cache-detector'), $rest_summary['TOTAL'], $rest_summary['HIT'], $rest_summary['MISS'], $rest_summary['BYPASS'], $rest_summary['UNCACHED'], $rest_summary['OTHER']);

            $wp_admin_bar->add_node( array( 'id' => 'cache_detector_rest_api_title', 'parent' => 'cache_detector_status', 'title' => $rest_summary_str . ' ' . esc_html__('(Max 15 shown)', 'cache-detector'), 'href' => false, 'meta'  => array('class' => 'cache-detector-asset-title') ) );
            $display_count = 0;

			foreach ( $rest_api_calls as $index => $call ) { // Iterate directly as it's newest first because they are prepended in AJAX handler
                if ($display_count++ >= 15) { // Limit display
                    $wp_admin_bar->add_node( array( 'id' => 'cache_detector_rest_api_limit', 'parent' => 'cache_detector_status', 'title' => esc_html__( '...more REST calls recorded (display limited to 15)', 'cache-detector' ), 'href' => '#') );
                   break;
               }
				$call_status_class = 'cache-status-unknown';
                if (strpos($call['cache_status'], 'HIT') === 0) $call_status_class = 'cache-status-hit';
                elseif (strpos($call['cache_status'], 'MISS') === 0) $call_status_class = 'cache-status-miss';
                elseif (strpos($call['cache_status'], 'BYPASS') === 0) $call_status_class = 'cache-status-bypass';
                elseif (strpos($call['cache_status'], 'DYNAMIC') === 0) $call_status_class = 'cache-status-dynamic';
                elseif (strpos($call['cache_status'], 'UNCACHED') === 0) $call_status_class = 'cache-status-uncached';

                $url_parts = parse_url($call['url']);
                $path_display = isset($url_parts['path']) ? basename($url_parts['path']) : esc_html__('invalid_url', 'cache-detector');
                if (isset($url_parts['query'])) $path_display .= '?' . $url_parts['query'];
                if (strlen($path_display) > 40) $path_display = '...' . substr($path_display, -37);

                $title = '<span class="' . esc_attr($call_status_class) . '" style="display: inline-block; padding: 0px 2px; border-radius: 2px; margin-right: 3px; font-size:0.9em;">' . esc_html( strtoupper(explode(' ', $call['cache_status'])[0]) ) . '</span> ';
                $title .= esc_html( $call['method'] . ' ' . $path_display );

                $raw_headers_string = '';
                if (!empty($call['raw_headers']) && is_array($call['raw_headers'])) {
                    foreach($call['raw_headers'] as $header) $raw_headers_string .= esc_js($header) . "\n";
                } else {
                    $raw_headers_string = esc_js(__( 'No headers captured.', 'cache-detector' ));
                }

                $full_details = sprintf(esc_js(__( 'Request URL: %s', 'cache-detector' )), esc_js($call['url'])) . "\n";
                $full_details .= sprintf(esc_js(__( 'Method: %s', 'cache-detector' )), esc_js($call['method'])) . "\n";
                $full_details .= sprintf(esc_js(__( 'HTTP Status: %s', 'cache-detector' )), esc_js($call['status'])) . "\n";
                $full_details .= sprintf(esc_js(__( 'Cache Status: %s', 'cache-detector' )), esc_js($call['cache_status'])) . "\n";
                $full_details .= sprintf(esc_js(__( 'Initiating Page: %s', 'cache-detector' )), esc_js($call['initiating_page_url'])) . "\n\n";
                $full_details .= esc_js(__( 'Raw Response Headers:', 'cache-detector' )) . "\n" . $raw_headers_string;

				$wp_admin_bar->add_node( array(
                    'id' => 'cache_detector_rest_api_' . md5($call['url'] . $index), // Ensure unique ID
                    'parent' => 'cache_detector_status',
                    'title' => $title,
                    'href' => '#',
                    'meta' => array(
                        'title' => esc_attr($call['url']) . ' | ' . esc_attr__( 'Status:', 'cache-detector') . ' ' . esc_attr($call['cache_status']),
                        'onclick' => 'alert("' . esc_js(__( 'REST API Call Details:', 'cache-detector' )) . '\n\n' . $full_details . '"); return false;'
                    )
                ) );
			}
		} elseif(!is_admin()) { // Only show "no REST calls" if on frontend
            $wp_admin_bar->add_node( array( 'id' => 'cache_detector_no_rest_calls', 'parent' => 'cache_detector_status', 'title' => esc_html__( '(No REST API calls recorded for this session)', 'cache-detector' ), 'href' => false, 'meta'  => array('class' => 'cache-detector-admin-bar-info') ) );
        }
	}


	/**
	 * Handle AJAX request for receiving asset data.
	 */
	public function handle_receive_assets_ajax() {
		check_ajax_referer( 'cache_detector_asset_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array('message' => esc_html__('Permission denied.', 'cache-detector')), 403 );
			return;
		}

		$asset_data_json = isset( $_POST['asset_data'] ) ? stripslashes( $_POST['asset_data'] ) : '';
		$page_url = isset( $_POST['page_url'] ) ? esc_url_raw( $_POST['page_url'] ) : '';

		if ( empty( $asset_data_json ) || empty( $page_url ) ) {
			wp_send_json_error( array('message' => esc_html__('Missing data.', 'cache-detector')), 400 );
			return;
		}

		$asset_data = json_decode( $asset_data_json, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			/* translators: %s: JSON error message */
			wp_send_json_error( array('message' => sprintf(esc_html__('Invalid JSON data: %s', 'cache-detector'), json_last_error_msg())), 400 );
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
                    'serverTiming'    => array(),
                );
                if (isset($item['serverTiming']) && is_array($item['serverTiming'])) {
                    foreach ($item['serverTiming'] as $st_entry) {
                        if (is_array($st_entry) && isset($st_entry['name'])) { // PerformanceServerTiming format
                             $sanitized_item['serverTiming'][] = sanitize_text_field($st_entry['name'] . ': ' . (isset($st_entry['description']) ? $st_entry['description'] : (isset($st_entry['duration']) ? round($st_entry['duration'], 2) . 'ms' : '')));
                        } elseif (is_string($st_entry)) { // Already stringified
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
            wp_send_json_error( array('message' => esc_html__('No valid asset data provided after sanitization.', 'cache-detector')), 400 );
            return;
        }

		$user_id = get_current_user_id();
		$transient_key = 'cd_assets_' . md5( $page_url . '_' . $user_id );
		set_transient( $transient_key, $sanitized_asset_data, 5 * MINUTE_IN_SECONDS );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Cache Detector] AJAX Handler (Admin Class): Received and stored asset data for URL: ' . $page_url . '. Count: ' . count($sanitized_asset_data) );
		}

		wp_send_json_success( array( 'message' => esc_html__('Asset data received.', 'cache-detector'), 'count' => count( $sanitized_asset_data ) ) );
	}

	/**
	 * Handle AJAX request for receiving REST API call data.
	 */
	public function handle_receive_rest_api_calls_ajax() {
		check_ajax_referer( 'cache_detector_rest_api_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array('message' => esc_html__('Permission denied.', 'cache-detector')), 403 );
			return;
		}

		$rest_api_calls_json = isset( $_POST['rest_api_calls'] ) ? stripslashes( $_POST['rest_api_calls'] ) : '';
        $page_url = isset( $_POST['page_url'] ) ? esc_url_raw( $_POST['page_url'] ) : '';


		if ( empty( $rest_api_calls_json ) || empty($page_url) ) {
			wp_send_json_error( array('message' => esc_html__('Missing REST API call data or page URL.', 'cache-detector')), 400 );
			return;
		}

		$rest_api_calls = json_decode( $rest_api_calls_json, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			/* translators: %s: JSON error message */
			wp_send_json_error( array('message' => sprintf(esc_html__('Invalid JSON data for REST API calls: %s', 'cache-detector'), json_last_error_msg())), 400 );
			return;
		}

		$sanitized_api_calls = array();
        // The analyze_headers method is in Public_Handler. We can get it via main_plugin instance.
        $public_handler = $this->main_plugin->public_handler;


        if (is_array($rest_api_calls) && $public_handler) {
            foreach ($rest_api_calls as $call) {
                $headers_array = array();
                if (isset($call['headers']) && is_array($call['headers'])) {
                    foreach ($call['headers'] as $header_line) {
                        if (is_string($header_line)) {
                            $headers_array[] = sanitize_text_field($header_line);
                        }
                    }
                }
                $sanitized_call = array(
                    'url'            => isset($call['url']) ? esc_url_raw($call['url']) : '',
                    'method'         => isset($call['method']) ? sanitize_text_field(strtoupper($call['method'])) : 'GET',
                    'status'         => isset($call['status']) ? absint($call['status']) : 0,
                    'raw_headers'    => $headers_array,
                    'cache_status'   => $public_handler->analyze_headers($headers_array),
                    'initiating_page_url' => $page_url,
                );
                if (!empty($sanitized_call['url']) && $sanitized_call['status'] > 0) {
                    $sanitized_api_calls[] = $sanitized_call;
                }
            }
        }

        if (empty($sanitized_api_calls)) {
            wp_send_json_error( array('message' => esc_html__('No valid REST API call data provided after sanitization.', 'cache-detector')), 400 );
            return;
        }

		$user_id = get_current_user_id();
		$transient_key = 'cd_rest_calls_' . $user_id; // Store all user's REST calls in one transient for the session
        $existing_calls = get_transient( $transient_key );
        if ( !is_array($existing_calls) ) {
            $existing_calls = array();
        }
        // Prepend new calls to show them at the top when displaying, then trim.
        $updated_calls = array_merge($sanitized_api_calls, $existing_calls);
        if (count($updated_calls) > 50) { // Limit stored calls
            $updated_calls = array_slice($updated_calls, 0, 50); // Keep the 50 newest (which are at the start)
        }
		set_transient( $transient_key, $updated_calls, 15 * MINUTE_IN_SECONDS );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Cache Detector] AJAX REST Handler (Admin Class): Received ' . count($sanitized_api_calls) . ' REST API calls. Total stored for user: ' . count($updated_calls) );
		}

		wp_send_json_success( array( 'message' => esc_html__('REST API call data received.', 'cache-detector'), 'count' => count( $sanitized_api_calls ) ) );
	}
}
