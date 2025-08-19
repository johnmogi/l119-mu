<?php
/**
 * Meta box template for quiz categories
 *
 * @package LD_Instructor_Quiz_Categories
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get previously selected categories
$selected_categories = get_post_meta($post->ID, '_ld_quiz_question_categories', true);
if (!is_array($selected_categories)) {
    $selected_categories = array();
}
?>

<div class="ld-instructor-quiz-categories-wrapper">
    <?php wp_nonce_field('save_quiz_categories', 'ld_instructor_quiz_categories_nonce'); ?>
    
    <p><?php _e('Select question categories to include in this quiz:', 'ld-instructor-quiz-cats'); ?></p>
    
    <?php if (!empty($selected_categories)) : ?>
        <div class="ld-selected-categories-info">
            <small style="color: #0073aa; font-weight: 500;">
                <?php printf(__('Currently selected: %d categories', 'ld-instructor-quiz-cats'), count($selected_categories)); ?>
            </small>
        </div>
    <?php endif; ?>
    
    <?php foreach ($question_categories as $category) : 
        $is_selected = in_array($category->term_id, $selected_categories);
    ?>
        <label class="ld-quiz-category-item <?php echo $is_selected ? 'selected' : ''; ?>">
            <input 
                type="checkbox" 
                name="ld_instructor_quiz_categories[]" 
                value="<?php echo esc_attr($category->term_id); ?>"
                class="ld-quiz-category-checkbox"
                <?php checked($is_selected); ?>
            >
            <span class="ld-quiz-category-name"><?php echo esc_html($category->name); ?> 
                <small style="color: #666; font-size: 0.9em;">(<?php echo intval($category->count); ?>)</small>
            </span>
            <?php if (!empty($category->description)) : ?>
                <small class="ld-quiz-category-description">(<?php echo esc_html($category->description); ?>)</small>
            <?php endif; ?>
        </label>
    <?php endforeach; ?>
    
    <div class="ld-save-notice">
        <small style="color: #666; font-style: italic;">
            <?php _e('💡 Tip: Selected categories will be saved when you update the quiz. Questions from these categories can then be used to auto-populate the quiz.', 'ld-instructor-quiz-cats'); ?>
        </small>
    </div>
    
    <?php if (!empty($selected_categories)) : ?>
    <div class="ld-quiz-populator" style="margin-top: 15px; padding: 15px; background: #f0f8ff; border: 2px solid #0073aa; border-radius: 8px;">
        <h4 style="margin: 0 0 15px 0; color: #0073aa;">🎯 Quiz Population Tool</h4>
        
        <!-- Direct Link to Standalone Tool -->
        <div style="margin-bottom: 15px; padding: 12px; background: #e7f3ff; border: 1px solid #0073aa; border-radius: 6px;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <div style="flex: 1;">
                    <strong>🚀 Advanced Quiz Populator</strong><br>
                    <small>Full-featured tool with multi-category selection</small>
                </div>
                <a href="<?php echo home_url('/instructor-quiz-populator.php?quiz_id=' . $post->ID); ?>" 
                   target="_blank" 
                   class="button button-primary button-large"
                   style="text-decoration: none;">
                    Open Quiz Populator Tool
                </a>
            </div>
        </div>
        
        <!-- Settings Section -->
        <div class="ld-populator-settings" style="margin-bottom: 15px; padding: 12px; background: white; border-radius: 6px; border: 1px solid #ddd;">
            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 10px;">
                <label style="font-weight: 500;">Questions per category:</label>
                <input type="number" id="questions-per-category" value="5" min="1" max="50" style="width: 70px; padding: 4px 8px;">
                <label style="font-weight: 500;">
                    <input type="checkbox" id="clear-existing" style="margin-right: 5px;">
                    Clear existing questions first
                </label>
            </div>
            <div style="font-size: 12px; color: #666;">
                💡 This will populate the quiz with questions from all selected categories above
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="ld-quiz-actions" style="margin-bottom: 15px;">
            <button type="button" id="populate-quiz-multi" class="button button-primary button-large" style="margin-right: 10px;">
                🚀 Populate Quiz from Selected Categories
            </button>
            <button type="button" id="preview-population" class="button button-secondary">
                👁️ Preview Questions
            </button>
        </div>
        
        <!-- Advanced Actions -->
        <details style="margin-bottom: 15px;">
            <summary style="cursor: pointer; font-weight: 500; color: #0073aa;">🔧 Advanced Tools</summary>
            <div style="margin-top: 10px; padding: 10px; background: #f9f9f9; border-radius: 4px;">
                <div class="ld-advanced-actions">
                    <button type="button" id="single-category-debug" class="button button-secondary" style="margin-right: 8px;">
                        🔧 Debug Single Category
                    </button>
                    <button type="button" id="test-population" class="button button-secondary" style="margin-right: 8px;">
                        Test Population
                    </button>
                    <button type="button" id="bulk-categorize" class="button button-secondary" style="margin-right: 8px;">
                        Auto-Categorize Questions
                    </button>
                    <button type="button" id="fix-categories" class="button" style="background: #d63638; border-color: #d63638; color: white;">
                        🔧 Fix Question Categories
                    </button>
                </div>
                <div style="font-size: 11px; color: #666; margin-top: 8px;">
                    Debug tools for troubleshooting and maintenance
                </div>
            </div>
        </details>
        
        <div id="population-results" style="margin-top: 10px; display: none;"></div>
    </div>
    
    <script>
    (function() {
        function initQuizCategories($) {
        // Multi-category population handler
        $('#populate-quiz-multi').click(function() {
            var button = $(this);
            var results = $('#population-results');
            var perCategory = $('#questions-per-category').val();
            var clearExisting = $('#clear-existing').is(':checked');
            
            button.prop('disabled', true).text('🔄 Populating...');
            results.hide();
            
            var selectedCategories = [];
            $('input[name="ld_instructor_quiz_categories[]"]:checked').each(function() {
                selectedCategories.push($(this).val());
            });
            
            if (selectedCategories.length === 0) {
                results.html('<div style="padding: 8px; background: #f8d7da; border: 1px solid #f5c2c7; border-radius: 3px; color: #842029;">❌ Please select at least one category first</div>').show();
                button.prop('disabled', false).text('🚀 Populate Quiz from Selected Categories');
                return;
            }
            
            if (!confirm('This will populate the quiz with ' + perCategory + ' questions from each of the ' + selectedCategories.length + ' selected categories. ' + (clearExisting ? 'Existing questions will be cleared first. ' : '') + 'Continue?')) {
                button.prop('disabled', false).text('🚀 Populate Quiz from Selected Categories');
                return;
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'populate_quiz_multi_category',
                    quiz_id: <?php echo $post->ID; ?>,
                    categories: selectedCategories,
                    per_category: perCategory,
                    clear_existing: clearExisting ? 1 : 0,
                    nonce: '<?php echo wp_create_nonce('populate_multi_' . $post->ID); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        results.html('<div style="padding: 12px; background: #d1e7dd; border: 1px solid #badbcc; border-radius: 3px; color: #0f5132;"><strong>✅ Success!</strong><br>' + response.data.message + '</div>').show();
                        // Refresh the page after 3 seconds to show updated questions
                        setTimeout(function() {
                            location.reload();
                        }, 3000);
                    } else {
                        results.html('<div style="padding: 8px; background: #f8d7da; border: 1px solid #f5c2c7; border-radius: 3px; color: #842029;">❌ ' + response.data.message + '</div>').show();
                    }
                },
                error: function() {
                    results.html('<div style="padding: 8px; background: #f8d7da; border: 1px solid #f5c2c7; border-radius: 3px; color: #842029;">❌ AJAX Error</div>').show();
                },
                complete: function() {
                    button.prop('disabled', false).text('🚀 Populate Quiz from Selected Categories');
                }
            });
        });
        
        // Preview population handler
        $('#preview-population').click(function() {
            var button = $(this);
            var results = $('#population-results');
            var perCategory = $('#questions-per-category').val();
            
            button.prop('disabled', true).text('🔍 Loading Preview...');
            results.hide();
            
            var selectedCategories = [];
            $('input[name="ld_instructor_quiz_categories[]"]:checked').each(function() {
                selectedCategories.push($(this).val());
            });
            
            if (selectedCategories.length === 0) {
                results.html('<div style="padding: 8px; background: #f8d7da; border: 1px solid #f5c2c7; border-radius: 3px; color: #842029;">❌ Please select at least one category first</div>').show();
                button.prop('disabled', false).text('👁️ Preview Questions');
                return;
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'preview_quiz_population',
                    quiz_id: <?php echo $post->ID; ?>,
                    categories: selectedCategories,
                    per_category: perCategory,
                    nonce: '<?php echo wp_create_nonce('preview_population_' . $post->ID); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        results.html('<div style="padding: 12px; background: #e7f3ff; border: 1px solid #0073aa; border-radius: 3px; color: #0073aa;"><strong>👁️ Preview:</strong><br>' + response.data.message + '</div>').show();
                    } else {
                        results.html('<div style="padding: 8px; background: #f8d7da; border: 1px solid #f5c2c7; border-radius: 3px; color: #842029;">❌ ' + response.data.message + '</div>').show();
                    }
                },
                error: function() {
                    results.html('<div style="padding: 8px; background: #f8d7da; border: 1px solid #f5c2c7; border-radius: 3px; color: #842029;">❌ AJAX Error</div>').show();
                },
                complete: function() {
                    button.prop('disabled', false).text('👁️ Preview Questions');
                }
            });
        });
        
        $('#single-category-debug').click(function() {
            var button = $(this);
            var results = $('#population-results');
            
            button.prop('disabled', true).text('Debugging...');
            results.hide();
            
            var selectedCategories = [];
            $('input[name="ld_instructor_quiz_categories[]"]:checked').each(function() {
                selectedCategories.push($(this).val());
            });
            
            if (selectedCategories.length === 0) {
                results.html('<div style="padding: 8px; background: #f8d7da; border: 1px solid #f5c2c7; border-radius: 3px; color: #842029;">❌ Please select at least one category first</div>').show();
                button.prop('disabled', false).text('🔧 Debug Single Category');
                return;
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'single_category_debug',
                    quiz_id: <?php echo $post->ID; ?>,
                    categories: selectedCategories,
                    nonce: '<?php echo wp_create_nonce('test_population_' . $post->ID); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        results.html('<div style="padding: 8px; background: #d1e7dd; border: 1px solid #badbcc; border-radius: 3px; color: #0f5132;">✅ ' + response.data.message + '</div>').show();
                    } else {
                        results.html('<div style="padding: 8px; background: #f8d7da; border: 1px solid #f5c2c7; border-radius: 3px; color: #842029;">❌ ' + response.data.message + '</div>').show();
                    }
                },
                error: function() {
                    results.html('<div style="padding: 8px; background: #f8d7da; border: 1px solid #f5c2c7; border-radius: 3px; color: #842029;">❌ AJAX Error</div>').show();
                },
                complete: function() {
                    button.prop('disabled', false).text('🔧 Debug Single Category');
                }
            });
        });
        
        $('#test-population').click(function() {
            var button = $(this);
            var results = $('#population-results');
            
            button.prop('disabled', true).text('Testing...');
            results.hide();
            
            var selectedCategories = [];
            $('input[name="ld_instructor_quiz_categories[]"]:checked').each(function() {
                selectedCategories.push($(this).val());
            });
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'test_quiz_population',
                    quiz_id: <?php echo $post->ID; ?>,
                    categories: selectedCategories,
                    nonce: '<?php echo wp_create_nonce('test_population_' . $post->ID); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        results.html('<div style="padding: 8px; background: #d1e7dd; border: 1px solid #badbcc; border-radius: 3px; color: #0f5132;">✅ ' + response.data.message + '</div>').show();
                    } else {
                        results.html('<div style="padding: 8px; background: #f8d7da; border: 1px solid #f5c2c7; border-radius: 3px; color: #842029;">❌ ' + response.data.message + '</div>').show();
                    }
                },
                error: function() {
                    results.html('<div style="padding: 8px; background: #f8d7da; border: 1px solid #f5c2c7; border-radius: 3px; color: #842029;">❌ AJAX Error</div>').show();
                },
                complete: function() {
                    button.prop('disabled', false).text('Test Population Now');
                }
            });
        });
        
        $('#bulk-categorize').click(function() {
            console.log('Bulk categorize button clicked');
            var button = $(this);
            var results = $('#population-results');
            
            var selectedCategories = [];
            $('input[name="ld_instructor_quiz_categories[]"]:checked').each(function() {
                selectedCategories.push($(this).val());
            });
            
            console.log('Selected categories:', selectedCategories);
            
            if (selectedCategories.length === 0) {
                results.html('<div style="padding: 8px; background: #f8d7da; border: 1px solid #f5c2c7; border-radius: 3px; color: #842029;">❌ Please select at least one category first</div>').show();
                return;
            }
            
            if (!confirm('This will categorize uncategorized questions into the selected categories. Continue?')) {
                return;
            }
            
            button.prop('disabled', true).text('Categorizing...');
            results.hide();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'bulk_categorize_questions',
                    quiz_id: <?php echo $post->ID; ?>,
                    categories: selectedCategories,
                    nonce: '<?php echo wp_create_nonce('bulk_categorize_' . $post->ID); ?>'
                },
                success: function(response) {
                    console.log('AJAX response:', response);
                    if (response.success) {
                        results.html('<div style="padding: 8px; background: #d1e7dd; border: 1px solid #badbcc; border-radius: 3px; color: #0f5132;">✅ ' + response.data + '</div>').show();
                    } else {
                        results.html('<div style="padding: 8px; background: #f8d7da; border: 1px solid #f5c2c7; border-radius: 3px; color: #842029;">❌ ' + response.data + '</div>').show();
                    }
                },
                error: function(xhr, status, error) {
                    console.log('AJAX error:', xhr, status, error);
                    console.log('Response text:', xhr.responseText);
                    results.html('<div style="padding: 8px; background: #f8d7da; border: 1px solid #f5c2c7; border-radius: 3px; color: #842029;">❌ AJAX Error: ' + error + '</div>').show();
                },
                complete: function() {
                    button.prop('disabled', false).text('Auto-Categorize Questions');
                }
            });
        });
        
        $('#fix-categories').click(function() {
            var button = $(this);
            var results = $('#population-results');
            
            var selectedCategories = [];
            $('input[name="ld_instructor_quiz_categories[]"]:checked').each(function() {
                selectedCategories.push($(this).val());
            });
            
            if (selectedCategories.length === 0) {
                results.html('<div style="padding: 8px; background: #f8d7da; border: 1px solid #f5c2c7; border-radius: 3px; color: #842029;">❌ Please select at least one category first</div>').show();
                return;
            }
            
            if (!confirm('This will assign ALL uncategorized questions to the selected categories. This action cannot be undone. Continue?')) {
                return;
            }
            
            button.prop('disabled', true).text('Fixing Categories...');
            results.hide();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'fix_question_categories',
                    quiz_id: <?php echo $post->ID; ?>,
                    categories: selectedCategories,
                    nonce: '<?php echo wp_create_nonce('test_population_' . $post->ID); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        results.html('<div style="padding: 8px; background: #d1e7dd; border: 1px solid #badbcc; border-radius: 3px; color: #0f5132;">✅ ' + response.data + '</div>').show();
                        // Refresh the page after 2 seconds to show updated category counts
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        results.html('<div style="padding: 8px; background: #f8d7da; border: 1px solid #f5c2c7; border-radius: 3px; color: #842029;">❌ ' + response.data + '</div>').show();
                    }
                },
                error: function() {
                    results.html('<div style="padding: 8px; background: #f8d7da; border: 1px solid #f5c2c7; border-radius: 3px; color: #842029;">❌ AJAX Error</div>').show();
                },
                complete: function() {
                    button.prop('disabled', false).text('🔧 Fix Question Categories');
                }
            });
        });
        }
        
        // Try multiple ways to ensure jQuery is loaded
        if (typeof jQuery !== 'undefined') {
            jQuery(document).ready(function($) { initQuizCategories($); });
        } else if (typeof window.ldQuizCategoriesReady !== 'undefined') {
            window.ldQuizCategoriesReady(initQuizCategories);
        } else {
            // Fallback - wait for jQuery to load
            var checkJQuery = setInterval(function() {
                if (typeof jQuery !== 'undefined') {
                    clearInterval(checkJQuery);
                    jQuery(document).ready(function($) { initQuizCategories($); });
                }
            }, 100);
        }
    })();
    </script>
    <?php endif; ?>
</div>

<style>
.ld-instructor-quiz-categories-wrapper {
    padding: 10px 0;
}

.ld-selected-categories-info {
    margin-bottom: 15px;
    padding: 8px 12px;
    background: #e7f3ff;
    border-left: 3px solid #0073aa;
    border-radius: 3px;
}

.ld-quiz-category-item {
    display: block;
    margin-bottom: 8px;
    cursor: pointer;
    padding: 6px 8px;
    transition: all 0.2s ease;
    border-radius: 4px;
    border: 1px solid transparent;
}

.ld-quiz-category-item:hover {
    background-color: #f0f0f1;
    border-color: #ddd;
}

.ld-quiz-category-item.selected {
    background-color: #e7f3ff;
    border-color: #0073aa;
    box-shadow: 0 1px 3px rgba(0, 115, 170, 0.1);
}

.ld-quiz-category-item.selected:hover {
    background-color: #d0e7ff;
}

.ld-quiz-category-checkbox {
    margin-right: 8px;
}

.ld-quiz-category-name {
    font-weight: 500;
}

.ld-quiz-category-description {
    color: #666;
    font-style: italic;
    margin-left: 4px;
}

.ld-save-notice {
    margin-top: 15px;
    padding: 10px;
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 4px;
}
</style>
