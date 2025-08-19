<?php
/**
 * Direct Single Category Test - Run this script directly
 * 
 * This script will:
 * 1. Take the first selected category from quiz 10592
 * 2. Find questions using multiple methods
 * 3. Attach 10-15 questions to the quiz
 * 4. Show debug output
 */

// WordPress bootstrap - try multiple paths
if (file_exists('../../../../wp-config.php')) {
    require_once('../../../../wp-config.php');
} elseif (file_exists('../../../../../wp-config.php')) {
    require_once('../../../../../wp-config.php');
} elseif (file_exists('../../../../../../wp-config.php')) {
    require_once('../../../../../../wp-config.php');
} else {
    // Try absolute path for Local by Flywheel
    $wp_config_path = dirname(__FILE__) . '/../../../../wp-config.php';
    if (file_exists($wp_config_path)) {
        require_once($wp_config_path);
    } else {
        die('WordPress config not found. Please run this script from the WordPress admin instead.');
    }
}

if (!defined('ABSPATH')) {
    die('WordPress not loaded properly');
}

echo "<h1>🔧 Direct Single Category Debug Test</h1>";
echo "<p>Testing quiz auto-population from one category...</p>";

// Target quiz from the browser page
$quiz_id = 10592;
echo "<h2>📋 Setup</h2>";
echo "<p><strong>Quiz ID:</strong> {$quiz_id}</p>";

// Step 1: Get selected categories
$selected_categories = get_post_meta($quiz_id, '_ld_quiz_question_categories', true);
if (empty($selected_categories)) {
    echo "<p style='color: red;'>❌ No categories selected for this quiz</p>";
    echo "<p>Please select a category in the quiz edit screen first.</p>";
    exit;
}

$test_category_id = $selected_categories[0];
$category = get_term($test_category_id, 'ld_quiz_category');

echo "<p><strong>Selected Categories:</strong> " . implode(', ', $selected_categories) . "</p>";
echo "<p><strong>Testing Category:</strong> {$category->name} (ID: {$test_category_id})</p>";
echo "<p><strong>Category Count:</strong> {$category->count}</p>";

echo "<h2>🔍 Method Testing</h2>";

// Method 1: Direct question query
echo "<h3>Method 1: Direct Question Query</h3>";
$questions_method1 = get_posts(array(
    'post_type' => 'sfwd-question',
    'post_status' => 'publish',
    'posts_per_page' => 20,
    'fields' => 'ids',
    'tax_query' => array(
        array(
            'taxonomy' => 'ld_quiz_category',
            'field' => 'term_id',
            'terms' => $test_category_id
        )
    )
));

echo "<p>Found <strong>" . count($questions_method1) . "</strong> questions directly in category</p>";
if (!empty($questions_method1)) {
    echo "<p>Sample IDs: " . implode(', ', array_slice($questions_method1, 0, 5)) . "</p>";
}

// Method 2: Questions from quizzes in category
echo "<h3>Method 2: Questions from Quizzes in Category</h3>";
$quizzes_in_category = get_posts(array(
    'post_type' => 'sfwd-quiz',
    'post_status' => 'publish',
    'posts_per_page' => 10,
    'fields' => 'ids',
    'tax_query' => array(
        array(
            'taxonomy' => 'ld_quiz_category',
            'field' => 'term_id',
            'terms' => $test_category_id
        )
    )
));

echo "<p>Found <strong>" . count($quizzes_in_category) . "</strong> quizzes in category</p>";

$questions_from_quizzes = array();
if (!empty($quizzes_in_category)) {
    foreach (array_slice($quizzes_in_category, 0, 5) as $source_quiz_id) {
        $quiz_title = get_the_title($source_quiz_id);
        echo "<p>📝 <strong>Quiz:</strong> {$quiz_title} (ID: {$source_quiz_id})</p>";
        
        $quiz_questions = get_post_meta($source_quiz_id, 'ld_quiz_questions', true);
        if (!empty($quiz_questions) && is_array($quiz_questions)) {
            $valid_questions = array();
            foreach (array_keys($quiz_questions) as $question_id) {
                if (get_post_type($question_id) === 'sfwd-question' && get_post_status($question_id) === 'publish') {
                    $valid_questions[] = $question_id;
                    $questions_from_quizzes[] = $question_id;
                }
            }
            echo "<p>  → Found <strong>" . count($valid_questions) . "</strong> valid questions</p>";
            if (!empty($valid_questions)) {
                echo "<p>  → Sample: " . implode(', ', array_slice($valid_questions, 0, 3)) . "</p>";
            }
        } else {
            echo "<p>  → No questions found in this quiz</p>";
        }
    }
}

$questions_from_quizzes = array_unique($questions_from_quizzes);
echo "<p><strong>Total unique questions from quizzes: " . count($questions_from_quizzes) . "</strong></p>";

// Step 3: Choose best method and attach questions
echo "<h2>🚀 Attaching Questions</h2>";

$questions_to_attach = array();
$method_used = '';

if (!empty($questions_method1)) {
    $questions_to_attach = array_slice($questions_method1, 0, 15);
    $method_used = 'Direct Category Query';
} elseif (!empty($questions_from_quizzes)) {
    $questions_to_attach = array_slice($questions_from_quizzes, 0, 15);
    $method_used = 'Questions from Quizzes in Category';
} else {
    echo "<p style='color: red;'>❌ <strong>FAILED:</strong> No questions found using any method!</p>";
    echo "<h3>🔍 Additional Debugging</h3>";
    
    // Check if any questions exist at all
    $all_questions = get_posts(array(
        'post_type' => 'sfwd-question',
        'post_status' => 'publish',
        'posts_per_page' => 10,
        'fields' => 'ids'
    ));
    echo "<p>Total questions in system: " . count($all_questions) . "</p>";
    
    // Check if category exists
    $cat_exists = term_exists($test_category_id, 'ld_quiz_category');
    echo "<p>Category exists: " . ($cat_exists ? 'Yes' : 'No') . "</p>";
    
    exit;
}

echo "<p><strong>Method Used:</strong> {$method_used}</p>";
echo "<p><strong>Questions to Attach:</strong> " . count($questions_to_attach) . "</p>";
echo "<p><strong>Question IDs:</strong> " . implode(', ', $questions_to_attach) . "</p>";

// Step 4: Attach questions to quiz
if (!empty($questions_to_attach)) {
    // Format questions for LearnDash
    $formatted_questions = array();
    foreach ($questions_to_attach as $index => $question_id) {
        $formatted_questions[$question_id] = $index + 1; // Sort order
    }
    
    echo "<h3>📝 Updating Quiz</h3>";
    echo "<p>Formatted questions array:</p>";
    echo "<pre>" . print_r($formatted_questions, true) . "</pre>";
    
    // Update quiz with questions
    $update_result = update_post_meta($quiz_id, 'ld_quiz_questions', $formatted_questions);
    update_post_meta($quiz_id, 'ld_quiz_questions_dirty', time());
    
    echo "<p>Update result: " . ($update_result ? 'Success' : 'Failed') . "</p>";
    
    // Clear caches
    wp_cache_delete($quiz_id, 'posts');
    if (function_exists('learndash_delete_quiz_cache')) {
        learndash_delete_quiz_cache($quiz_id);
        echo "<p>✅ LearnDash cache cleared</p>";
    }
    
    // Verify the update
    $saved_questions = get_post_meta($quiz_id, 'ld_quiz_questions', true);
    $saved_count = is_array($saved_questions) ? count($saved_questions) : 0;
    
    echo "<h3>✅ Results</h3>";
    echo "<p style='color: green; font-size: 16px; font-weight: bold;'>SUCCESS: Attached {$saved_count} questions to Quiz #{$quiz_id}</p>";
    echo "<p style='color: blue;'>🔄 <strong>Go refresh your quiz edit page to see the questions in the builder!</strong></p>";
    
    // Log to debug
    error_log("LD Quiz Categories: Found " . count($questions_to_attach) . " questions in Category {$category->name}. Attached to Quiz #{$quiz_id}.");
    
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>📊 Summary</h4>";
    echo "<ul>";
    echo "<li><strong>Category:</strong> {$category->name}</li>";
    echo "<li><strong>Method:</strong> {$method_used}</li>";
    echo "<li><strong>Questions Found:</strong> " . count($questions_to_attach) . "</li>";
    echo "<li><strong>Questions Attached:</strong> {$saved_count}</li>";
    echo "<li><strong>Quiz ID:</strong> {$quiz_id}</li>";
    echo "</ul>";
    echo "</div>";
    
} else {
    echo "<p style='color: red;'>❌ No questions to attach</p>";
}

echo "<hr>";
echo "<p><small>Debug completed at " . date('Y-m-d H:i:s') . "</small></p>";
?>
