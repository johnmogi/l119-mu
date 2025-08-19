<?php

namespace Lilac\CourseAccess\Integrations;

use Lilac\CourseAccess\Core\AccessManager;

/**
 * WooCommerce Integration
 */
class WooCommerce {
    
    private static $instance = null;
    private $accessManager;
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->accessManager = AccessManager::getInstance();
        $this->initHooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function initHooks() {
        add_action('woocommerce_order_status_completed', [$this, 'handleOrderCompletion'], 10, 1);
        add_action('woocommerce_payment_complete', [$this, 'handlePaymentComplete'], 10, 1);
    }
    
    /**
     * Handle order completion
     */
    public function handleOrderCompletion($orderId) {
        $this->processOrderCourses($orderId, 'order_completed');
    }
    
    /**
     * Handle payment completion
     */
    public function handlePaymentComplete($orderId) {
        $this->processOrderCourses($orderId, 'payment_complete');
    }
    
    /**
     * Process courses for an order
     */
    private function processOrderCourses($orderId, $trigger) {
        $order = wc_get_order($orderId);
        
        if (!$order) {
            return;
        }
        
        $userId = $order->get_user_id();
        
        if (!$userId) {
            // Create user from order if needed
            $userId = $this->createUserFromOrder($order);
        }
        
        if (!$userId) {
            return;
        }
        
        // Set grace period for recent purchase
        update_user_meta($userId, 'lilac_recent_purchase_redirect', time());
        
        // Process each item in the order
        foreach ($order->get_items() as $item) {
            $productId = $item->get_product_id();
            $courses = $this->getCoursesForProduct($productId);
            
            foreach ($courses as $courseId) {
                $duration = $this->getCourseAccessDuration($courseId);
                $expires = $duration > 0 ? (time() + ($duration * DAY_IN_SECONDS)) : 0;
                
                $this->accessManager->setCourseAccess($userId, $courseId, $expires);
            }
        }
        
        $this->logDebug("Processed order {$orderId} for user {$userId} via {$trigger}");
    }
    
    /**
     * Create user from order
     */
    private function createUserFromOrder($order) {
        $email = $order->get_billing_email();
        $firstName = $order->get_billing_first_name();
        $lastName = $order->get_billing_last_name();
        
        if (!$email) {
            return false;
        }
        
        // Check if user already exists
        $existingUser = get_user_by('email', $email);
        if ($existingUser) {
            return $existingUser->ID;
        }
        
        // Create new user
        $username = sanitize_user($email);
        $password = wp_generate_password();
        
        $userId = wp_create_user($username, $password, $email);
        
        if (is_wp_error($userId)) {
            return false;
        }
        
        // Update user meta
        wp_update_user([
            'ID' => $userId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'display_name' => trim($firstName . ' ' . $lastName)
        ]);
        
        $this->logDebug("Created user {$userId} from order");
        
        return $userId;
    }
    
    /**
     * Get courses associated with a product
     */
    private function getCoursesForProduct($productId) {
        // Check multiple possible meta keys for course associations
        $courses = [];
        
        // Check LearnDash WooCommerce integration meta keys
        $meta_keys = [
            '_learndash_courses',     // Our custom meta
            '_related_course',        // LearnDash WooCommerce
            'lilac_courses',         // Legacy fallback
            '_course_id'             // Another possible key
        ];
        
        foreach ($meta_keys as $meta_key) {
            $course_data = get_post_meta($productId, $meta_key, false);
            if (!empty($course_data)) {
                if (is_array($course_data[0])) {
                    $courses = array_merge($courses, $course_data[0]);
                } else {
                    $courses = array_merge($courses, $course_data);
                }
                break; // Use first found meta key
            }
        }
        
        // Log for debugging
        $this->logDebug("Product {$productId} courses found: " . print_r($courses, true));
        
        return array_filter(array_map('intval', $courses));
    }
    
    /**
     * Get course access duration in days
     */
    private function getCourseAccessDuration($courseId) {
        // Check course-specific duration
        $duration = get_post_meta($courseId, 'lilac_access_duration', true);
        
        if (!$duration) {
            // Use default duration
            $duration = get_option('lilac_default_access_duration', 30);
        }
        
        return intval($duration);
    }
    
    /**
     * Debug logging
     */
    private function logDebug($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Lilac Course Access - WooCommerce] ' . $message);
        }
    }
}
