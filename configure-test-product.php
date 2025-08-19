<?php
/**
 * Configure Test Product with Course Assignment
 * Run this once to set up a test product properly
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add admin action to configure test product
add_action('admin_init', function() {
    if (isset($_GET['configure_test_product']) && current_user_can('manage_options')) {
        configure_test_product_with_course();
        wp_die('✅ Test product configured! Check the product edit page to see the LearnDash settings.');
    }
});

function configure_test_product_with_course() {
    // Find a course to assign (get the first available course)
    $courses = get_posts([
        'post_type' => 'sfwd-courses',
        'post_status' => 'publish',
        'numberposts' => 1
    ]);
    
    if (empty($courses)) {
        wp_die('❌ No courses found. Please create a LearnDash course first.');
    }
    
    $course_id = $courses[0]->ID;
    $course_title = $courses[0]->post_title;
    
    // Find a product to configure (get the first available product)
    $products = get_posts([
        'post_type' => 'product',
        'post_status' => 'publish',
        'numberposts' => 1
    ]);
    
    if (empty($products)) {
        wp_die('❌ No products found. Please create a WooCommerce product first.');
    }
    
    $product_id = $products[0]->ID;
    $product_title = $products[0]->post_title;
    
    // Configure the product with LearnDash settings
    update_post_meta($product_id, '_learndash_courses', [$course_id]);
    update_post_meta($product_id, '_learndash_access_duration', 'access_1month');
    update_post_meta($product_id, '_learndash_custom_end_date', '');
    
    error_log("✅ Configured product '{$product_title}' (ID: {$product_id}) with course '{$course_title}' (ID: {$course_id})");
    error_log("✅ Access duration set to: 1 month");
    
    // Also update the product title to be more descriptive
    wp_update_post([
        'ID' => $product_id,
        'post_title' => 'קורס ' . $course_title . ' - גישה לחודש'
    ]);
    
    return [
        'product_id' => $product_id,
        'product_title' => $product_title,
        'course_id' => $course_id,
        'course_title' => $course_title
    ];
}

// Add admin notice with instructions
add_action('admin_notices', function() {
    if (current_user_can('manage_options')) {
        echo '<div class="notice notice-info">';
        echo '<p><strong>🔧 Test Product Configuration</strong></p>';
        echo '<p>To configure a test product with course assignment, click: ';
        echo '<a href="' . admin_url('admin.php?configure_test_product=1') . '" class="button button-primary">Configure Test Product</a>';
        echo '</p>';
        echo '</div>';
    }
});
