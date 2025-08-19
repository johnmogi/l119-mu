<?php
/**
 * Plugin Name: Simple Form Quiz Populator
 * Description: Form-based quiz population directly in quiz edit interface
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SimpleFormQuizPopulator {
    
    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('save_post', [$this, 'handle_form_submission']);
    }
    
    public function add_meta_box() {
        global $post;
        if (!$post || $post->post_type !== 'sfwd-quiz') {
            return;
        }
        
        add_meta_box(
            'simple-form-quiz-populator',
            '⚡ Simple Quiz Populator (Form-Based)',
            [$this, 'render_meta_box'],
            'sfwd-quiz',
            'normal',
            'high'
        );
    }
    
    public function handle_form_submission($post_id) {
        // Check if this is our form submission
        if (!isset($_POST['sfqp_action']) || $_POST['sfqp_action'] !== 'populate') {
            return;
        }
        
        // Security check
        if (!wp_verify_nonce($_POST['sfqp_nonce'], 'sfqp_populate_' . $post_id)) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Check if this is the right post type
        if (get_post_type($post_id) !== 'sfwd-quiz') {
            return;
        }
        
        $this->populate_quiz($post_id);
    }
    
    private function populate_quiz($quiz_id) {
        $per_category = intval($_POST['per_category'] ?? 5);
        $selected_categories = isset($_POST['selected_categories']) ? array_map('intval', $_POST['selected_categories']) : [];
        
        // Get all categories if none selected
        if (empty($selected_categories)) {
            $categories = get_terms([
                'taxonomy' => 'ld_quiz_category',
                'hide_empty' => false
            ]);
            
            if (!is_wp_error($categories)) {
                foreach ($categories as $cat) {
                    if ($cat->count > 0) {
                        $selected_categories[] = $cat->term_id;
                    }
                }
            }
        }
        
        if (empty($selected_categories)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>❌ No categories with questions found</p></div>';
            });
            return;
        }
        
        $all_question_ids = [];
        $added_from_categories = [];
        
        foreach ($selected_categories as $cat_id) {
            $cat = get_term($cat_id, 'ld_quiz_category');
            if (!$cat || is_wp_error($cat) || $cat->count <= 0) {
                continue;
            }
            
            // Try taxonomy query first
            $questions = get_posts([
                'post_type' => 'sfwd-question',
                'post_status' => 'publish',
                'posts_per_page' => $per_category,
                'fields' => 'ids',
                'orderby' => 'rand',
                'tax_query' => [[
                    'taxonomy' => 'ld_quiz_category',
                    'field' => 'term_id',
                    'terms' => $cat_id
                ]]
            ]);
            
            // If no results from taxonomy, try meta
            if (empty($questions)) {
                $questions = get_posts([
                    'post_type' => 'sfwd-question',
                    'post_status' => 'publish',
                    'posts_per_page' => $per_category,
                    'fields' => 'ids',
                    'orderby' => 'rand',
                    'meta_query' => [[
                        'key' => 'question_pro_category',
                        'value' => $cat_id,
                        'compare' => '='
                    ]]
                ]);
            }
            
            if (!empty($questions)) {
                $all_question_ids = array_merge($all_question_ids, $questions);
                $added_from_categories[] = $cat->name . ' (' . count($questions) . ')';
            }
        }
        
        if (empty($all_question_ids)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>❌ No questions found in selected categories</p></div>';
            });
            return;
        }
        
        // Get existing questions
        $existing = get_post_meta($quiz_id, 'ld_quiz_questions', true);
        if (!is_array($existing)) {
            $existing = [];
        }
        
        // Merge and remove duplicates
        $final_questions = array_unique(array_merge($existing, $all_question_ids));
        
        // Update quiz
        $success = update_post_meta($quiz_id, 'ld_quiz_questions', $final_questions);
        
        if ($success) {
            $message = sprintf(
                '✅ Added %d questions from %d categories: %s',
                count($all_question_ids),
                count($added_from_categories),
                implode(', ', $added_from_categories)
            );
            add_action('admin_notices', function() use ($message) {
                echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>❌ Failed to update quiz</p></div>';
            });
        }
    }
    
    public function render_meta_box($post) {
        // Security nonce
        wp_nonce_field('sfqp_populate_' . $post->ID, 'sfqp_nonce');
        
        echo '<div style="background: #f0f8ff; padding: 15px; border: 1px solid #0073aa; margin-bottom: 20px;">';
        echo '<h3>⚡ Simple Form-Based Quiz Populator</h3>';
        echo '<p>This tool uses standard WordPress form submission for maximum reliability.</p>';
        
        // Show current quiz status
        $current_questions = get_post_meta($post->ID, 'ld_quiz_questions', true);
        $current_count = is_array($current_questions) ? count($current_questions) : 0;
        echo '<p><strong>Current Questions in Quiz:</strong> ' . $current_count . '</p>';
        
        // Show categories with counts
        $categories = get_terms([
            'taxonomy' => 'ld_quiz_category',
            'hide_empty' => false
        ]);
        
        if (!is_wp_error($categories) && !empty($categories)) {
            echo '<h4>📊 Available Categories:</h4>';
            echo '<div style="max-height: 200px; overflow-y: auto; background: white; padding: 10px; border: 1px solid #ddd; margin-bottom: 15px;">';
            
            $categories_with_questions = 0;
            foreach ($categories as $cat) {
                $color = $cat->count > 0 ? '#008000' : '#999';
                if ($cat->count > 0) $categories_with_questions++;
                
                echo '<label style="display: block; color: ' . $color . '; margin: 5px 0; cursor: pointer;">';
                echo '<input type="checkbox" name="selected_categories[]" value="' . esc_attr($cat->term_id) . '" style="margin-right: 8px;">';
                echo esc_html($cat->name) . ' (' . intval($cat->count) . ' questions)';
                echo '</label>';
            }
            echo '</div>';
            
            echo '<p style="color: #666; font-size: 0.9em;">';
            echo '💡 Select specific categories or leave all unchecked to use all ' . $categories_with_questions . ' categories with questions.';
            echo '</p>';
        }
        
        echo '</div>';
        
        // Simple form controls
        echo '<div style="background: #fff; padding: 15px; border: 1px solid #ddd;">';
        echo '<h4>🚀 Population Settings</h4>';
        
        echo '<p>';
        echo '<label for="per_category">Questions per category: </label>';
        echo '<input type="number" id="per_category" name="per_category" value="5" min="1" max="50" style="width: 80px;">';
        echo '</p>';
        
        echo '<input type="hidden" name="sfqp_action" value="populate">';
        
        echo '<p>';
        echo '<button type="submit" class="button button-primary button-large">🚀 Populate Quiz Now</button>';
        echo '</p>';
        
        echo '<p style="color: #666; font-size: 0.9em;">';
        echo '💡 Click "Update" button at the top of the page after clicking "Populate Quiz Now" to save the changes.';
        echo '</p>';
        
        echo '</div>';
    }
}

new SimpleFormQuizPopulator();
?>
