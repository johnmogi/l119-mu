<?php
/**
 * Plugin Name: MU Upload Limits
 * Description: Enforce 200MB upload limit via MU plugin.
 * Author: Cascade
 */

// Apply runtime PHP limits early
add_action('plugins_loaded', function () {
    if (function_exists('ini_set')) {
        @ini_set('upload_max_filesize', '200M');
        @ini_set('post_max_size', '200M');
        // Keep generous execution/input times for large uploads
        @ini_set('max_execution_time', '1200');
        @ini_set('max_input_time', '600');
        // Memory limit likely already set in wp-config; keep as-is if higher
        // @ini_set('memory_limit', '512M'); // uncomment if needed
    }
}, 1);

// Enforce WordPress-level cap reported in Media UI and used by core
add_filter('upload_size_limit', function ($size) {
    $target = 200 * 1024 * 1024; // 200 MB in bytes
    // Return the minimum of PHP effective limits and our target to avoid lying to UI
    $php_upload_max = wp_convert_hr_to_bytes(ini_get('upload_max_filesize'));
    $php_post_max   = wp_convert_hr_to_bytes(ini_get('post_max_size'));
    $effective_php  = min($php_upload_max ?: $target, $php_post_max ?: $target);
    return min($target, $effective_php);
}, 20);
