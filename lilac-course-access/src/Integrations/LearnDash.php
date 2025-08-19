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
        add_filter('learndash_course_access_from', [$this, 'checkCourseAccess'], 10, 3);
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
        return $this->accessManager->hasCourseAccess($userId, $courseId);
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
