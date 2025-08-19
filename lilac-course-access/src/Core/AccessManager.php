<?php

namespace Lilac\CourseAccess\Core;

/**
 * Core Access Manager
 * Handles course access logic and expiration management
 */
class AccessManager {
    
    private static $instance = null;
    
    // Expiration meta key format
    const EXPIRATION_META_KEY = 'course_%d_access_expires';
    
    // Grace period in seconds (10 minutes)
    const GRACE_PERIOD = 600;
    
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
        $this->initHooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function initHooks() {
        // LearnDash hooks
        add_action('learndash_update_course_access', [$this, 'updateCourseAccessMeta'], 10, 4);
        
        // Cron for checking expired access
        add_action('wp', [$this, 'scheduleExpirationCheck']);
        add_action('lilac_check_course_expiration', [$this, 'checkExpiredAccess']);
    }
    
    /**
     * Set course access with expiration
     */
    public function setCourseAccess($userId, $courseId, $expires = 0) {
        // Grant LearnDash access
        ld_update_course_access($userId, $courseId, $remove = false);
        
        // Set expiration meta
        if ($expires > 0) {
            update_user_meta($userId, sprintf(self::EXPIRATION_META_KEY, $courseId), $expires);
        } else {
            // Permanent access - remove expiration meta
            delete_user_meta($userId, sprintf(self::EXPIRATION_META_KEY, $courseId));
        }
        
        $this->logDebug("Set course access for user {$userId}, course {$courseId}, expires: " . ($expires ? date('Y-m-d H:i:s', $expires) : 'never'));
        
        return true;
    }
    
    /**
     * Check if user has course access
     */
    public function hasCourseAccess($userId, $courseId) {
        // Check if user has LearnDash access
        if (!sfwd_lms_has_access($courseId, $userId)) {
            return false;
        }
        
        // Check expiration
        $expires = $this->getCourseExpiration($userId, $courseId);
        
        if ($expires === 0) {
            // Permanent access
            return true;
        }
        
        if ($expires > 0 && $expires > time()) {
            // Access not yet expired
            return true;
        }
        
        // Check grace period for recent purchases
        if ($this->isRecentPurchase($userId)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get course expiration timestamp
     */
    public function getCourseExpiration($userId, $courseId) {
        $expires = get_user_meta($userId, sprintf(self::EXPIRATION_META_KEY, $courseId), true);
        return $expires ? intval($expires) : 0;
    }
    
    /**
     * Remove course access
     */
    public function removeCourseAccess($userId, $courseId) {
        // Remove LearnDash access
        ld_update_course_access($userId, $courseId, $remove = true);
        
        // Remove expiration meta
        delete_user_meta($userId, sprintf(self::EXPIRATION_META_KEY, $courseId));
        
        $this->logDebug("Removed course access for user {$userId}, course {$courseId}");
        
        return true;
    }
    
    /**
     * Get access status for a course
     */
    public function getAccessStatus($userId, $courseId) {
        if (!$this->hasCourseAccess($userId, $courseId)) {
            return 'expired';
        }
        
        $expires = $this->getCourseExpiration($userId, $courseId);
        
        if ($expires === 0) {
            return 'permanent';
        }
        
        $currentTime = time();
        $daysUntilExpiry = ($expires - $currentTime) / DAY_IN_SECONDS;
        
        if ($daysUntilExpiry <= 0) {
            return 'expired';
        } elseif ($daysUntilExpiry <= 7) {
            return 'expiring';
        } else {
            return 'active';
        }
    }
    
    /**
     * Get all courses a user has access to
     */
    public function getUserCourses($userId, $filterCourseId = 0) {
        global $wpdb;
        
        $courses = [];
        
        // Get all courses with expiration meta
        $courseResults = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_key, meta_value 
             FROM {$wpdb->usermeta} 
             WHERE user_id = %d 
             AND meta_key LIKE 'course_%_access_expires'",
            $userId
        ));
        
        foreach ($courseResults as $courseResult) {
            preg_match('/course_(\d+)_access_expires/', $courseResult->meta_key, $matches);
            
            if (empty($matches[1])) {
                continue;
            }
            
            $courseId = intval($matches[1]);
            $expires = intval($courseResult->meta_value);
            
            // Skip courses that don't match the filter
            if ($filterCourseId && $courseId !== $filterCourseId) {
                continue;
            }
            
            $courses[] = [
                'ID' => $courseId,
                'title' => get_the_title($courseId) ?: "Course #{$courseId}",
                'expires' => $expires,
                'expires_formatted' => $expires ? date_i18n(get_option('date_format'), $expires) : __('Never', 'lilac-course-access'),
                'status' => $this->getAccessStatus($userId, $courseId)
            ];
        }
        
        return $courses;
    }
    
    /**
     * Check for recent purchase (grace period)
     */
    private function isRecentPurchase($userId) {
        $recentPurchase = get_user_meta($userId, 'lilac_recent_purchase_redirect', true);
        
        if ($recentPurchase && (time() - intval($recentPurchase)) < self::GRACE_PERIOD) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Update course access meta when LearnDash access changes
     */
    public function updateCourseAccessMeta($userId, $courseId, $access, $remove) {
        if ($remove) {
            // Access removed - clean up our meta
            delete_user_meta($userId, sprintf(self::EXPIRATION_META_KEY, $courseId));
        }
        
        $this->logDebug("LearnDash access updated for user {$userId}, course {$courseId}, access: " . ($access ? 'granted' : 'removed'));
    }
    
    /**
     * Schedule expiration check cron job
     */
    public function scheduleExpirationCheck() {
        if (!wp_next_scheduled('lilac_check_course_expiration')) {
            wp_schedule_event(time(), 'hourly', 'lilac_check_course_expiration');
        }
    }
    
    /**
     * Check and remove expired course access
     */
    public function checkExpiredAccess() {
        global $wpdb;
        
        $currentTime = time();
        
        // Find all expired course access
        $expiredAccess = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, meta_key, meta_value 
             FROM {$wpdb->usermeta} 
             WHERE meta_key LIKE 'course_%_access_expires' 
             AND meta_value > 0 
             AND meta_value < %d",
            $currentTime
        ));
        
        foreach ($expiredAccess as $access) {
            preg_match('/course_(\d+)_access_expires/', $access->meta_key, $matches);
            
            if (!empty($matches[1])) {
                $courseId = intval($matches[1]);
                $userId = intval($access->user_id);
                
                // Remove expired access
                $this->removeCourseAccess($userId, $courseId);
                
                $this->logDebug("Removed expired access for user {$userId}, course {$courseId}");
            }
        }
    }
    
    /**
     * Debug logging
     */
    private function logDebug($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Lilac Course Access] ' . $message);
        }
    }
}
