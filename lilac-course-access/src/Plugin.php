<?php

namespace Lilac\CourseAccess;

/**
 * Main Plugin Class
 */
class Plugin {
    
    private static $instance = null;
    
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
        $this->init();
    }
    
    /**
     * Initialize the plugin
     */
    private function init() {
        // Initialize core components
        Core\AccessManager::getInstance();
        
        // Initialize admin interface if in admin
        if (is_admin()) {
            Admin\AdminPage::getInstance();
        }
        
        // Initialize integrations
        if (class_exists('SFWD_LMS')) {
            Integrations\LearnDash::getInstance();
        }
        
        if (class_exists('WooCommerce')) {
            Integrations\WooCommerce::getInstance();
        }
        
        // Add activation/deactivation hooks
        add_action('init', [$this, 'onInit']);
    }
    
    /**
     * Plugin initialization
     */
    public function onInit() {
        // Plugin initialization code here
        do_action('lilac_course_access_init');
    }
    
    /**
     * Get plugin version
     */
    public function getVersion() {
        return LILAC_COURSE_ACCESS_VERSION;
    }
    
    /**
     * Get plugin path
     */
    public function getPath() {
        return LILAC_COURSE_ACCESS_PATH;
    }
    
    /**
     * Get plugin URL
     */
    public function getUrl() {
        return LILAC_COURSE_ACCESS_URL;
    }
}
