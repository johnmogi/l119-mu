<?php
/**
 * Plugin Name: Elementor Transient Cleanup Manager
 * Description: Clean Elementor transients and cache with version control and security
 * Version: 1.0.0
 * Author: LILAC Development
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Elementor_Transient_Cleanup {
    
    private $version = '1.0.0';
    private $max_versions = 3;
    private $cleanup_log_key = 'elementor_cleanup_log';
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('wp_ajax_elementor_cleanup_transients', [$this, 'ajax_cleanup_transients']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // Auto-cleanup on Elementor updates
        add_action('upgrader_process_complete', [$this, 'auto_cleanup_on_update'], 10, 2);
    }
    
    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_submenu_page(
            'tools.php',
            'Elementor Cleanup',
            'Elementor Cleanup',
            'manage_options',
            'elementor-cleanup',
            [$this, 'admin_page']
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'tools_page_elementor-cleanup') {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_localize_script('jquery', 'elementor_cleanup_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('elementor_cleanup_nonce')
        ]);
    }
    
    /**
     * Admin page content
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>🧹 Elementor Transient Cleanup</h1>
            <p class="description">Clean Elementor transients, cache, and temporary data to resolve performance issues.</p>
            
            <?php $this->display_cleanup_stats(); ?>
            
            <div class="cleanup-actions" style="background: #fff; padding: 20px; border: 1px solid #c3c4c7; border-radius: 4px; margin: 20px 0;">
                <h2>Cleanup Actions</h2>
                
                <div class="cleanup-buttons" style="margin: 15px 0;">
                    <button type="button" id="cleanup-transients" class="button button-primary" style="margin-right: 10px;">
                        🗑️ Clean Transients
                    </button>
                    <button type="button" id="cleanup-elementor-cache" class="button" style="margin-right: 10px;">
                        🔄 Clear Elementor Cache
                    </button>
                    <button type="button" id="cleanup-all" class="button button-secondary">
                        🧹 Full Cleanup
                    </button>
                </div>
                
                <div id="cleanup-progress" style="display: none; background: #e7f3ff; border-left: 4px solid #2271b1; padding: 10px; margin: 10px 0;">
                    <p><strong>Cleanup in progress...</strong></p>
                    <div id="progress-details"></div>
                </div>
                
                <div id="cleanup-results" style="display: none; margin: 10px 0;"></div>
            </div>
            
            <?php $this->display_cleanup_history(); ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            function performCleanup(action) {
                $('#cleanup-progress').show();
                $('#cleanup-results').hide();
                $('#progress-details').html('Initializing cleanup...');
                
                $.ajax({
                    url: elementor_cleanup_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'elementor_cleanup_transients',
                        cleanup_action: action,
                        nonce: elementor_cleanup_ajax.nonce
                    },
                    success: function(response) {
                        $('#cleanup-progress').hide();
                        if (response.success) {
                            $('#cleanup-results').html(
                                '<div class="notice notice-success"><p><strong>✅ Cleanup completed successfully!</strong><br>' + 
                                response.data.message + '</p></div>'
                            ).show();
                            
                            // Refresh page after 3 seconds to show updated stats
                            setTimeout(function() {
                                location.reload();
                            }, 3000);
                        } else {
                            $('#cleanup-results').html(
                                '<div class="notice notice-error"><p><strong>❌ Cleanup failed:</strong> ' + 
                                response.data + '</p></div>'
                            ).show();
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#cleanup-progress').hide();
                        $('#cleanup-results').html(
                            '<div class="notice notice-error"><p><strong>❌ AJAX Error:</strong> ' + 
                            error + '</p></div>'
                        ).show();
                    }
                });
            }
            
            $('#cleanup-transients').click(function() {
                if (confirm('Are you sure you want to clean transients? This action cannot be undone.')) {
                    performCleanup('transients');
                }
            });
            
            $('#cleanup-elementor-cache').click(function() {
                if (confirm('Are you sure you want to clear Elementor cache?')) {
                    performCleanup('elementor_cache');
                }
            });
            
            $('#cleanup-all').click(function() {
                if (confirm('Are you sure you want to perform a full cleanup? This will clear all transients and cache.')) {
                    performCleanup('full');
                }
            });
        });
        </script>
        
        <style>
        .cleanup-stat {
            display: inline-block;
            background: #f0f6fc;
            border-left: 4px solid #2271b1;
            padding: 10px 15px;
            margin: 5px 10px 5px 0;
            border-radius: 4px;
        }
        .cleanup-stat strong {
            display: block;
            font-size: 18px;
            color: #2271b1;
        }
        .cleanup-history {
            max-height: 300px;
            overflow-y: auto;
            background: #f9f9f9;
            padding: 10px;
            border-radius: 4px;
        }
        </style>
        <?php
    }
    
    /**
     * Display cleanup statistics
     */
    private function display_cleanup_stats() {
        $transient_count = $this->count_elementor_transients();
        $cache_size = $this->get_elementor_cache_size();
        $last_cleanup = get_option('elementor_last_cleanup', 'Never');
        
        echo '<div class="cleanup-stats" style="margin: 20px 0;">';
        echo '<div class="cleanup-stat">';
        echo '<strong>' . $transient_count . '</strong>';
        echo '<span>Elementor Transients</span>';
        echo '</div>';
        
        echo '<div class="cleanup-stat">';
        echo '<strong>' . size_format($cache_size) . '</strong>';
        echo '<span>Cache Size</span>';
        echo '</div>';
        
        echo '<div class="cleanup-stat">';
        echo '<strong>' . ($last_cleanup !== 'Never' ? human_time_diff($last_cleanup) . ' ago' : 'Never') . '</strong>';
        echo '<span>Last Cleanup</span>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Display cleanup history
     */
    private function display_cleanup_history() {
        $cleanup_log = get_option($this->cleanup_log_key, []);
        
        if (empty($cleanup_log)) {
            return;
        }
        
        echo '<div class="cleanup-history-container" style="background: #fff; padding: 20px; border: 1px solid #c3c4c7; border-radius: 4px; margin: 20px 0;">';
        echo '<h3>Recent Cleanup History</h3>';
        echo '<div class="cleanup-history">';
        
        foreach (array_reverse(array_slice($cleanup_log, -10)) as $entry) {
            $time = date('Y-m-d H:i:s', $entry['timestamp']);
            $status = $entry['success'] ? '✅' : '❌';
            echo '<div style="margin: 5px 0; padding: 5px; border-bottom: 1px solid #eee;">';
            echo '<strong>' . $status . ' ' . $time . '</strong> - ' . esc_html($entry['action']) . '<br>';
            echo '<small>' . esc_html($entry['details']) . '</small>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * AJAX handler for cleanup operations
     */
    public function ajax_cleanup_transients() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'elementor_cleanup_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $cleanup_action = sanitize_text_field($_POST['cleanup_action'] ?? 'transients');
        
        try {
            $result = $this->perform_cleanup($cleanup_action);
            $this->log_cleanup($cleanup_action, true, $result['message']);
            wp_send_json_success($result);
        } catch (Exception $e) {
            $this->log_cleanup($cleanup_action, false, $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Perform the actual cleanup
     */
    private function perform_cleanup($action) {
        $results = [];
        
        switch ($action) {
            case 'transients':
                $results = $this->cleanup_transients();
                break;
            case 'elementor_cache':
                $results = $this->cleanup_elementor_cache();
                break;
            case 'full':
                $transient_results = $this->cleanup_transients();
                $cache_results = $this->cleanup_elementor_cache();
                $results = [
                    'transients_deleted' => $transient_results['transients_deleted'],
                    'cache_cleared' => $cache_results['cache_cleared'],
                    'message' => $transient_results['message'] . ' ' . $cache_results['message']
                ];
                break;
            default:
                throw new Exception('Invalid cleanup action');
        }
        
        // Update last cleanup time
        update_option('elementor_last_cleanup', current_time('timestamp'));
        
        return $results;
    }
    
    /**
     * Clean Elementor transients
     */
    private function cleanup_transients() {
        global $wpdb;
        
        $transient_patterns = [
            'elementor_%',
            '_transient_elementor_%',
            '_transient_timeout_elementor_%',
            'elementor_css_%',
            'elementor_global_%',
            '_elementor_css_%'
        ];
        
        $deleted_count = 0;
        
        foreach ($transient_patterns as $pattern) {
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $pattern
            ));
            $deleted_count += $deleted;
        }
        
        // Clean object cache if available
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        return [
            'transients_deleted' => $deleted_count,
            'message' => "Deleted {$deleted_count} Elementor transients."
        ];
    }
    
    /**
     * Clean Elementor cache
     */
    private function cleanup_elementor_cache() {
        $cache_cleared = false;
        $message = '';
        
        // Clear Elementor cache if plugin is active
        if (class_exists('\Elementor\Plugin')) {
            try {
                \Elementor\Plugin::$instance->files_manager->clear_cache();
                $cache_cleared = true;
                $message = 'Elementor cache cleared successfully.';
            } catch (Exception $e) {
                $message = 'Elementor cache clear failed: ' . $e->getMessage();
            }
        } else {
            // Manual cache directory cleanup
            $upload_dir = wp_upload_dir();
            $cache_dir = $upload_dir['basedir'] . '/elementor/css/';
            
            if (is_dir($cache_dir)) {
                $files = glob($cache_dir . '*');
                $deleted_files = 0;
                
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                        $deleted_files++;
                    }
                }
                
                $cache_cleared = $deleted_files > 0;
                $message = "Deleted {$deleted_files} cache files.";
            } else {
                $message = 'No Elementor cache directory found.';
            }
        }
        
        return [
            'cache_cleared' => $cache_cleared,
            'message' => $message
        ];
    }
    
    /**
     * Count Elementor transients
     */
    private function count_elementor_transients() {
        global $wpdb;
        
        $count = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->options} 
            WHERE option_name LIKE 'elementor_%' 
            OR option_name LIKE '_transient_elementor_%'
            OR option_name LIKE '_elementor_css_%'
        ");
        
        return intval($count);
    }
    
    /**
     * Get Elementor cache size
     */
    private function get_elementor_cache_size() {
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/elementor/css/';
        
        if (!is_dir($cache_dir)) {
            return 0;
        }
        
        $size = 0;
        $files = glob($cache_dir . '*');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $size += filesize($file);
            }
        }
        
        return $size;
    }
    
    /**
     * Log cleanup operations
     */
    private function log_cleanup($action, $success, $details) {
        $log = get_option($this->cleanup_log_key, []);
        
        $log[] = [
            'timestamp' => current_time('timestamp'),
            'action' => $action,
            'success' => $success,
            'details' => $details,
            'version' => $this->version
        ];
        
        // Keep only the last entries (version control)
        if (count($log) > $this->max_versions * 10) {
            $log = array_slice($log, -($this->max_versions * 10));
        }
        
        update_option($this->cleanup_log_key, $log);
    }
    
    /**
     * Auto cleanup on Elementor updates
     */
    public function auto_cleanup_on_update($upgrader_object, $options) {
        if (isset($options['plugins']) && is_array($options['plugins'])) {
            foreach ($options['plugins'] as $plugin) {
                if (strpos($plugin, 'elementor') !== false) {
                    // Auto cleanup after Elementor update
                    try {
                        $this->perform_cleanup('full');
                        $this->log_cleanup('auto_update', true, 'Auto cleanup after Elementor update');
                    } catch (Exception $e) {
                        $this->log_cleanup('auto_update', false, $e->getMessage());
                    }
                    break;
                }
            }
        }
    }
}

// Initialize the plugin
new Elementor_Transient_Cleanup();
