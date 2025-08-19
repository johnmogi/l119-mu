<?php
/**
 * Plugin Name: Simple Quiz Fixer
 * Description: No-AJAX, guaranteed working quiz population tool
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SimpleQuizFixer {
    
    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('init', [$this, 'handle_form_submission']);
    }
    
    public function add_meta_box() {
        global $post;
        if (!$post || $post->post_type !== 'sfwd-quiz') {
            return;
        }
        
        add_meta_box(
            'simple-quiz-fixer',
            '⚡ Simple Quiz Fixer (No AJAX)',
            [$this, 'render_meta_box'],
            'sfwd-quiz',
            'normal',
            'high'
        );
    }
    
    public function handle_form_submission() {
        if (!isset($_POST['sqf_action']) || !isset($_POST['sqf_nonce'])) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['sqf_nonce'], 'sqf_populate')) {
            return;
        }
        
        if ($_POST['sqf_action'] === 'populate') {
            $this->populate_quiz();
        }
    }
    
    private function populate_quiz() {
        $quiz_id = intval($_POST['quiz_id']);
        $per_category = intval($_POST['per_category']) ?: 5;
        
        if (!$quiz_id) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>❌ Invalid quiz ID</p></div>';
            });
            return;
        }
        
        // Get all categories
        $categories = get_terms([
            'taxonomy' => 'ld_quiz_category',
            'hide_empty' => false
        ]);
        
        if (is_wp_error($categories) || empty($categories)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>❌ No categories found</p></div>';
            });
            return;
        }
        
        $all_question_ids = [];
        $added_from_categories = [];
        
        foreach ($categories as $cat) {
            if ($cat->count <= 0) continue;
            
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
                    'terms' => $cat->term_id
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
                        'value' => $cat->term_id,
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
                echo '<div class="notice notice-error"><p>❌ No questions found in any category</p></div>';
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
        wp_nonce_field('sqf_populate', 'sqf_nonce');
        
        echo '<div style="background: #f0f8ff; padding: 15px; border: 1px solid #0073aa; margin-bottom: 20px;">';
        echo '<h3>⚡ Simple Quiz Fixer</h3>';
        echo '<p>This tool uses standard WordPress form submission (no AJAX) for maximum reliability.</p>';
        
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
            echo '<div style="max-height: 200px; overflow-y: auto; background: white; padding: 10px; border: 1px solid #ddd;">';
            foreach ($categories as $cat) {
                $color = $cat->count > 0 ? '#008000' : '#999';
                echo '<div style="color: ' . $color . '; margin: 2px 0;">';
                echo esc_html($cat->name) . ' (' . intval($cat->count) . ' questions)';
                echo '</div>';
            }
            echo '</div>';
        }
        
        echo '</div>';
        
        // Simple form
        echo '<form method="post" style="background: #fff; padding: 15px; border: 1px solid #ddd;">';
        echo '<input type="hidden" name="sqf_action" value="populate">';
        echo '<input type="hidden" name="quiz_id" value="' . esc_attr($post->ID) . '">';
        
        echo '<h4>🚀 Populate Quiz</h4>';
        echo '<p>';
        echo '<label for="per_category">Questions per category: </label>';
        echo '<input type="number" id="per_category" name="per_category" value="5" min="1" max="50" style="width: 80px;">';
        echo '</p>';
        
        echo '<p>';
        echo '<button type="submit" class="button button-primary button-large">🚀 Add Questions Now</button>';
        echo '</p>';
        
        echo '<p style="color: #666; font-size: 0.9em;">';
        echo '💡 This will add random questions from all categories that have questions. ';
        echo 'Existing questions will be preserved (no duplicates).';
        echo '</p>';
        
        echo '</form>';
    }
}

new SimpleQuizFixer();
?>
