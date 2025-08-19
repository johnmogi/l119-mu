<?php
/**
 * Plugin Name: Ensure Open Courses Accessible
 * Description: Ensures that courses marked as 'Open' in LearnDash remain accessible regardless of other restrictions
 * Version: 1.0.0
 * Author: LILAC Development
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Ensure_Open_Courses_Accessible {
    
    public function __construct() {
        // Hook into LearnDash's course access check
        add_filter('learndash_can_enroll_course', [$this, 'allow_open_course_access'], 10, 3);
        add_filter('learndash_show_login_redirect', [$this, 'bypass_login_redirect_for_open_courses'], 10, 2);
        add_filter('learndash_lesson_video', [$this, 'allow_video_access_for_open_courses'], 10, 2);
    }
    
    /**
     * Allow access to open courses regardless of enrollment
     */
    public function allow_open_course_access($can_enroll, $course_id, $user_id) {
        if ($this->is_course_open($course_id)) {
            return true;
        }
        return $can_enroll;
    }
    
    /**
     * Bypass login redirect for open courses
     */
    public function bypass_login_redirect_for_open_courses($login_url, $redirect_url) {
        // Check if we're trying to access a course
        if (is_singular('sfwd-courses')) {
            global $post;
            if ($this->is_course_open($post->ID)) {
                return false; // Don't redirect to login for open courses
            }
        }
        return $login_url;
    }
    
    /**
     * Allow video access for open courses
     */
    public function allow_video_access_for_open_courses($video_data, $post) {
        if (empty($post) || !is_object($post)) {
            return $video_data;
        }
        
        // Check if this is a course or lesson in an open course
        $course_id = 0;
        if ($post->post_type === 'sfwd-courses') {
            $course_id = $post->ID;
        } elseif ($post->post_type === 'sfwd-lessons' || $post->post_type === 'sfwd-topic') {
            $course_id = learndash_get_course_id($post->ID);
        }
        
        if ($course_id && $this->is_course_open($course_id)) {
            // Ensure video is accessible by setting has_access to true
            if (is_array($video_data) && isset($video_data['videos_found_provider'])) {
                $video_data['has_access'] = true;
            }
        }
        
        return $video_data;
    }
    
    /**
     * Check if a course is set to 'Open' mode
     */
    private function is_course_open($course_id) {
        // Get the course access mode
        $course_access_mode = get_post_meta($course_id, '_ld_price_type', true);
        
        // If the course is set to 'open' (price type 'open' or 'free' in LearnDash)
        if (in_array($course_access_mode, ['open', 'free'])) {
            return true;
        }
        
        // Additional check for WooCommerce integration
        if (class_exists('WooCommerce')) {
            $product_id = get_post_meta($course_id, '_linked_products', true);
            if (empty($product_id)) {
                // If no product is linked, it might be an open course
                $course_price = get_post_meta($course_id, 'course_price', true);
                if (empty($course_price) || $course_price === '0' || $course_price === '') {
                    return true;
                }
            }
        }
        
        return false;
    }
}

// Initialize the plugin
new Ensure_Open_Courses_Accessible();
