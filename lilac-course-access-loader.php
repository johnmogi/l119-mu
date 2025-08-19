<?php
/**
 * Plugin Name: Lilac Course Access Manager
 * Description: Enhanced Course Access Manager for LearnDash and WooCommerce integration
 * Version: 1.0.0
 * Author: Lilac Learning
 * License: GPL-2.0-or-later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('LILAC_COURSE_ACCESS_VERSION', '1.0.0');
define('LILAC_COURSE_ACCESS_PATH', __DIR__ . '/lilac-course-access/');
define('LILAC_COURSE_ACCESS_URL', plugin_dir_url(__FILE__) . 'lilac-course-access/');

// Load Composer autoloader
$autoloader = LILAC_COURSE_ACCESS_PATH . 'vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
}

// Initialize the plugin
add_action('plugins_loaded', function() {
    if (class_exists('Lilac\CourseAccess\Plugin')) {
        Lilac\CourseAccess\Plugin::getInstance();
    }
}, 10);
