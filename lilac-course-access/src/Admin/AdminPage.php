<?php

namespace Lilac\CourseAccess\Admin;

use Lilac\CourseAccess\Core\AccessManager;

/**
 * Admin Page Handler
 */
class AdminPage {
    
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
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        
        // AJAX handlers
        add_action('wp_ajax_lilac_get_user_courses', [$this, 'ajaxGetUserCourses']);
        add_action('wp_ajax_lilac_set_course_expiration', [$this, 'ajaxSetCourseExpiration']);
    }
    
    /**
     * Add admin menu
     */
    public function addAdminMenu() {
        add_menu_page(
            __('Course Access Manager', 'lilac-course-access'),
            __('Course Access', 'lilac-course-access'),
            'manage_options',
            'lilac-course-access',
            [$this, 'renderAdminPage'],
            'dashicons-clock',
            30
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueueAdminAssets($hook) {
        if (strpos($hook, 'lilac-course-access') === false) {
            return;
        }
        
        // Enqueue jQuery UI for dialogs
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_style('wp-jquery-ui-dialog');
        
        // Enqueue admin CSS
        wp_enqueue_style(
            'lilac-course-access-admin',
            LILAC_COURSE_ACCESS_URL . 'assets/css/admin.css',
            [],
            LILAC_COURSE_ACCESS_VERSION
        );
        
        // Enqueue admin JS
        wp_enqueue_script(
            'lilac-course-access-admin',
            LILAC_COURSE_ACCESS_URL . 'assets/js/admin.js',
            ['jquery', 'jquery-ui-dialog'],
            LILAC_COURSE_ACCESS_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('lilac-course-access-admin', 'lilacCourseAccess', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lilac_course_access_nonce'),
            'i18n' => [
                'loading' => __('Loading...', 'lilac-course-access'),
                'error_loading' => __('Error loading data. Please try again.', 'lilac-course-access'),
                'error_saving' => __('Error saving data. Please try again.', 'lilac-course-access'),
                'no_results' => __('No users found matching your criteria.', 'lilac-course-access'),
                'no_courses' => __('No courses assigned.', 'lilac-course-access'),
                'active' => __('Active', 'lilac-course-access'),
                'expiring_soon' => __('Expiring Soon', 'lilac-course-access'),
                'expired' => __('Expired', 'lilac-course-access'),
                'permanent' => __('Permanent', 'lilac-course-access'),
                'never' => __('Never', 'lilac-course-access'),
                'set_expiration' => __('Set Expiration', 'lilac-course-access'),
                'manage_courses' => __('Manage Courses', 'lilac-course-access'),
                'expires' => __('Expires', 'lilac-course-access'),
                'status' => __('Status', 'lilac-course-access'),
                'actions' => __('Actions', 'lilac-course-access'),
                'course' => __('Course', 'lilac-course-access')
            ]
        ]);
    }
    
    /**
     * Render admin page
     */
    public function renderAdminPage() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'lilac-course-access'));
        }
        
        // Get users for filter dropdown
        $users = get_users([
            'orderby' => 'display_name',
            'order' => 'ASC',
            'number' => 1000,
            'fields' => ['ID', 'display_name', 'user_email']
        ]);
        
        // Get courses for filter dropdown
        $courses = get_posts([
            'post_type' => 'sfwd-courses',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
        
        include LILAC_COURSE_ACCESS_PATH . 'templates/admin-page.php';
    }
    
    /**
     * AJAX handler to get user courses
     */
    public function ajaxGetUserCourses() {
        if (!check_ajax_referer('lilac_course_access_nonce', 'nonce', false)) {
            wp_send_json_error(__('Invalid security token.', 'lilac-course-access'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'lilac-course-access'));
        }
        
        $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        $courseId = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
        
        // Get users based on filters
        $args = [
            'orderby' => 'display_name',
            'order' => 'ASC',
            'number' => 1000,
            'fields' => ['ID', 'display_name', 'user_email']
        ];
        
        if ($userId) {
            $args['include'] = [$userId];
        }
        
        $users = get_users($args);
        $result = [];
        
        foreach ($users as $user) {
            $userCourses = $this->accessManager->getUserCourses($user->ID, $courseId);
            
            if ($courseId && empty($userCourses)) {
                continue;
            }
            
            $result[] = [
                'ID' => $user->ID,
                'display_name' => $user->display_name,
                'user_email' => $user->user_email,
                'courses' => $userCourses
            ];
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX handler to set course expiration
     */
    public function ajaxSetCourseExpiration() {
        if (!check_ajax_referer('lilac_course_access_nonce', 'nonce', false)) {
            wp_send_json_error(__('Invalid security token.', 'lilac-course-access'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'lilac-course-access'));
        }
        
        $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $courseId = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
        $expires = isset($_POST['expires']) ? intval($_POST['expires']) : 0;
        
        if (!$userId || !$courseId) {
            wp_send_json_error(__('Invalid user or course ID.', 'lilac-course-access'));
        }
        
        // Special case: remove access
        if ($expires === -1) {
            $this->accessManager->removeCourseAccess($userId, $courseId);
            wp_send_json_success([
                'message' => __('Course access has been removed.', 'lilac-course-access')
            ]);
        }
        
        // Set course access
        $this->accessManager->setCourseAccess($userId, $courseId, $expires);
        
        $expiresFormatted = $expires ? date_i18n(get_option('date_format'), $expires) : __('Never', 'lilac-course-access');
        $status = $this->accessManager->getAccessStatus($userId, $courseId);
        
        wp_send_json_success([
            'message' => $expires > 0 
                ? sprintf(__('Course access updated to expire on %s.', 'lilac-course-access'), $expiresFormatted)
                : __('Permanent course access has been granted.', 'lilac-course-access'),
            'expires' => $expires,
            'expires_formatted' => $expiresFormatted,
            'status' => $status
        ]);
    }
}
