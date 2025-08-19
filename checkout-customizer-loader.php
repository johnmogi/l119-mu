<?php
/**
 * Plugin Name: Checkout Customizer (MU)
 * Description: Loads the Checkout Customizer plugin as a must-use plugin
 * Version: 1.0.0
 * Author: Lilac
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('CHECKOUT_CUSTOMIZER_PLUGIN_PATH', WPMU_PLUGIN_DIR . '/checkout-customizer/');

// Check if the main plugin file exists
$main_plugin_file = CHECKOUT_CUSTOMIZER_PLUGIN_PATH . 'checkout-customizer.php';
if (file_exists($main_plugin_file)) {
    require_once $main_plugin_file;
} else {
    // Log error if the plugin file is missing
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Checkout Customizer Error: Main plugin file not found at ' . $main_plugin_file);
    }
}
