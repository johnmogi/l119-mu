<?php

namespace Lilac\CourseAccess\Integrations;

use Lilac\CourseAccess\Core\AccessManager;

/**
 * LearnDash Integration
 */
class LearnDash {
    
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
        // Course access control
        add_filter('learndash_course_access_from', [$this, 'checkCourseAccess'], 10, 3);
        add_filter('sfwd_lms_has_access', [$this, 'filterCourseAccess'], 10, 3);
        
        // Content filtering
        add_filter('learndash_content', [$this, 'filterCourseContent'], 10, 2);
        add_filter('the_content', [$this, 'filterLearnDashContent'], 999);
        
        // Course completion
        add_action('learndash_course_completed', [$this, 'handleCourseCompletion'], 10, 2);
    }
    
    /**
     * Check course access with expiration
     */
    public function checkCourseAccess($hasAccess, $courseId, $userId) {
        if (!$hasAccess) {
            return false;
        }
        
        // Use our access manager to check expiration
        $hasValidAccess = $this->accessManager->hasCourseAccess($userId, $courseId);
        
        if (!$hasValidAccess) {
            $this->logDebug("Access denied for user {$userId} to course {$courseId} - expired or no access");
        }
        
        return $hasValidAccess;
    }
    
    /**
     * Filter LearnDash course access
     */
    public function filterCourseAccess($hasAccess, $postId, $userId) {
        if (!$hasAccess || !$userId) {
            return $hasAccess;
        }
        
        // Check if this is a course
        if (get_post_type($postId) !== 'sfwd-courses') {
            return $hasAccess;
        }
        
        // Use our access manager to check expiration
        $validAccess = $this->accessManager->hasCourseAccess($userId, $postId);
        
        if (!$validAccess) {
            $this->logDebug("Access denied for user {$userId} to course {$postId} via sfwd_lms_has_access");
        }
        
        return $validAccess;
    }
    
    /**
     * Filter course content for expired access
     */
    public function filterCourseContent($content, $post) {
        if (!is_user_logged_in() || get_post_type($post) !== 'sfwd-courses') {
            return $content;
        }
        
        $userId = get_current_user_id();
        $courseId = $post->ID;
        
        if (!$this->accessManager->hasCourseAccess($userId, $courseId)) {
            $this->logDebug("Filtering content for expired user {$userId} on course {$courseId}");
            return $this->getExpiredAccessMessage($courseId);
        }
        
        return $content;
    }
    
    /**
     * Filter LearnDash content in the_content
     */
    public function filterLearnDashContent($content) {
        global $post;
        
        if (!$post || !is_user_logged_in()) {
            return $content;
        }
        
        // Check if this is a LearnDash course
        if (get_post_type($post) !== 'sfwd-courses') {
            return $content;
        }
        
        $userId = get_current_user_id();
        $courseId = $post->ID;
        
        if (!$this->accessManager->hasCourseAccess($userId, $courseId)) {
            $this->logDebug("Blocking content access for expired user {$userId} on course {$courseId}");
            return $this->getExpiredAccessMessage($courseId);
        }
        
        return $content;
    }
    
    /**
     * Get expired access message
     */
    private function getExpiredAccessMessage($courseId) {
        $courseTitle = get_the_title($courseId);
        
        return '<div class="lilac-expired-access-notice" style="background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; padding: 20px; margin: 20px 0; text-align: center;">
            <h2 style="color: #856404; margin-top: 0;">⏰ תוקף הגישה פג</h2>
            <p style="font-size: 16px; color: #856404;">תוקף הגישה לקורס <strong>' . esc_html($courseTitle) . '</strong> פג.</p>
            <p style="color: #856404;">לחידוש הגישה, אנא רכוש את הקורס מחדש:</p>
            <div style="margin: 15px 0;">
                <a href="' . home_url('/shop/') . '" class="button" style="background: #0073aa; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; margin: 5px; display: inline-block; font-weight: bold;">
                    🛒 רכישת קורס
                </a>
            </div>
            <p style="font-size: 14px; color: #6c757d; margin-bottom: 0;">
                צריך עזרה? <a href="' . home_url('/contact') . '">צור קשר עם התמיכה</a>
            </p>
        </div>';
    }
    
    /**
     * Handle course completion
     */
    public function handleCourseCompletion($data, $user) {
        $courseId = $data['course']->ID;
        $userId = $user->ID;
        
        $this->logDebug("Course {$courseId} completed by user {$userId}");
        
        // You can add custom logic here for course completion
        do_action('lilac_course_completed', $courseId, $userId);
    }
    
    /**
     * Debug logging
     */
    private function logDebug($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Lilac Course Access - LearnDash] ' . $message);
        }
    }
}
