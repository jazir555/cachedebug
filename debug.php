<?php
/*
Plugin Name: Full Cache Debug Logger
Description: Logs every front-end URL with caching status across major WP cache plugins.
Version:     1.0
Author:      Your Name
*/

// Only front-end
add_action('template_redirect', function() {
    if ( is_admin() || wp_doing_ajax() ) {
        return;
    }

    $uri = ($_SERVER['REQUEST_URI'] ?? '') . ' [' . ($_SERVER['HTTP_USER_AGENT'] ?? '') . ']';
    $qs  = $_GET ? json_encode($_GET) : '';
    $hit = 'UNKNOWN';
    $plugin = 'none';

    // Detect known cache headers
    foreach ( headers_list() as $h ) {
        if ( stripos($h, 'x-flying-press-cache:') === 0 ) {
            $hit = stripos($h, 'hit') !== false ? 'HIT' : 'MISS';
            $plugin = 'FlyingPress';
            break;
        }
        if ( stripos($h, 'x-cache:') === 0 ) {
            $hit = stripos($h, 'hit') !== false ? 'HIT' : 'MISS';
            $plugin = 'Generic-Cache';
            break;
        }
        if ( stripos($h, 'x-litespeed-cache:') === 0 ) {
            $hit = stripos($h, 'hit') !== false ? 'HIT' : 'MISS';
            $plugin = 'LiteSpeed';
            break;
        }
    }

    // Fallback: WP Rocket output buffer marker
    if ($hit === 'UNKNOWN' && function_exists('rocket_clean_domain')) {
        ob_start();
        // Flush buffer (ensuring plugin appended)
        $buf = ob_get_flush();
        if (strpos($buf, 'Performance optimized by WP Rocket') !== false) {
            $hit = strpos($buf, 'cached@') !== false ? 'HIT' : 'MISS';
            $plugin = 'WP Rocket';
        }
    }

    $log = sprintf(
        "[%s][%s] URL=\"%s\" QS=%s",
        date('Y-m-d H:i:s'),
        "$plugin:$hit",
        $uri,
        $qs
    );

    $file = WP_CONTENT_DIR . '/cache-debug.log';
    file_put_contents($file, $log.PHP_EOL, FILE_APPEND | LOCK_EX);
}, PHP_INT_MAX);
