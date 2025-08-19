<?php
/**
 * Plugin Name: Direct Quiz Populator
 * Description: Direct admin page for quiz population - no meta boxes, no AJAX
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class DirectQuizPopulator {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_page']);
        add_action('admin_init', [$this, 'handle_form_submission']);
    }
    
    public function add_admin_page() {
        add_submenu_page(
            'edit.php?post_type=sfwd-quiz',
            'Quiz Populator',
            'Quiz Populator',
            'edit_posts',
            'quiz-populator',
            [$this, 'render_admin_page']
        );
    }
    
    public function handle_form_submission() {
        if (!isset($_POST['dqp_action']) || !isset($_POST['dqp_nonce'])) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['dqp_nonce'], 'dqp_populate')) {
            wp_die('Security check failed');
        }
        
        if ($_POST['dqp_action'] === 'populate') {
            $this->populate_quiz();
        }
    }
    
    private function populate_quiz() {
        $quiz_id = intval($_POST['quiz_id']);
        $per_category = intval($_POST['per_category']) ?: 5;
        
        if (!$quiz_id) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>❌ Please select a valid quiz</p></div>';
            });
            return;
        }
        
        // Verify quiz exists
        $quiz = get_post($quiz_id);
        if (!$quiz || $quiz->post_type !== 'sfwd-quiz') {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>❌ Invalid quiz selected</p></div>';
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
                '✅ Successfully populated quiz "%s" with %d questions from %d categories: %s',
                $quiz->post_title,
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
    
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1>🚀 Direct Quiz Populator</h1>
            <p>This tool bypasses all AJAX and meta box complications for maximum reliability.</p>
            
            <?php
            // Show system status
            $total_questions = wp_count_posts('sfwd-question');
            $taxonomy_exists = taxonomy_exists('ld_quiz_category');
            $categories = get_terms(['taxonomy' => 'ld_quiz_category', 'hide_empty' => false]);
            ?>
            
            <div class="card" style="max-width: 800px;">
                <h2>📊 System Status</h2>
                <p><strong>Total Questions:</strong> <?php echo $total_questions->publish ?? 0; ?></p>
                <p><strong>Quiz Category Taxonomy:</strong> <?php echo $taxonomy_exists ? '✅ Exists' : '❌ Missing'; ?></p>
                <p><strong>Categories Found:</strong> <?php echo is_array($categories) ? count($categories) : 0; ?></p>
                
                <?php if (is_array($categories) && !empty($categories)): ?>
                    <h3>Available Categories:</h3>
                    <div style="max-height: 200px; overflow-y: auto; background: #f9f9f9; padding: 10px; border: 1px solid #ddd;">
                        <?php foreach ($categories as $cat): ?>
                            <div style="color: <?php echo $cat->count > 0 ? '#008000' : '#999'; ?>; margin: 2px 0;">
                                <?php echo esc_html($cat->name); ?> (<?php echo intval($cat->count); ?> questions)
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>🎯 Populate Quiz</h2>
                <form method="post">
                    <?php wp_nonce_field('dqp_populate', 'dqp_nonce'); ?>
                    <input type="hidden" name="dqp_action" value="populate">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="quiz_id">Select Quiz:</label>
                            </th>
                            <td>
                                <?php
                                $quizzes = get_posts([
                                    'post_type' => 'sfwd-quiz',
                                    'post_status' => 'any',
                                    'posts_per_page' => -1,
                                    'orderby' => 'title',
                                    'order' => 'ASC'
                                ]);
                                ?>
                                <select name="quiz_id" id="quiz_id" required style="min-width: 300px;">
                                    <option value="">-- Select a Quiz --</option>
                                    <?php foreach ($quizzes as $quiz): ?>
                                        <option value="<?php echo $quiz->ID; ?>">
                                            <?php echo esc_html($quiz->post_title); ?> (ID: <?php echo $quiz->ID; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="per_category">Questions per category:</label>
                            </th>
                            <td>
                                <input type="number" id="per_category" name="per_category" value="5" min="1" max="50" style="width: 100px;">
                                <p class="description">How many random questions to add from each category that has questions.</p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary button-large">🚀 Populate Quiz Now</button>
                    </p>
                    
                    <p style="color: #666;">
                        <strong>💡 How it works:</strong> This tool will add random questions from all categories that have questions. 
                        Existing questions in the quiz will be preserved (no duplicates will be created).
                    </p>
                </form>
            </div>
        </div>
        <?php
    }
}

new DirectQuizPopulator();
?>
