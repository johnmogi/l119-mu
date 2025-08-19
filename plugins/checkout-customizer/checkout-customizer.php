<?php
/**
 * Plugin Name: Checkout Customizer
 * Description: Customizes the WooCommerce checkout page with Hebrew placeholders and removes unwanted fields
 * Version: 1.0.0
 * Author: Lilac
 */

defined('ABSPATH') || exit;

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

// Autoload classes
spl_autoload_register(function ($class) {
    $prefix = 'Lilac\\CheckoutCustomizer\\';
    $base_dir = __DIR__ . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Initialize the plugin
add_action('plugins_loaded', function() {
    new Lilac\CheckoutCustomizer\CheckoutCustomizer();
});
