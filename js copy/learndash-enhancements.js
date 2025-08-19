/**
 * LearnDash Course Enhancements
 * Handles navigation colors, Hebrew prefixes, and UI translations
 */
jQuery(document).ready(function($) {
    'use strict';
    
    console.log('LearnDash Course Enhancements: Script loaded');
    
    // Throttle function to prevent excessive calls
    function throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        }
    }
    
    // Function to adjust video height - DISABLED to prevent interference with video plugin
    function adjustVideoHeight() {
        console.log('Video height adjustment DISABLED - handled by video plugin');
        return; // Exit early, don't process videos
    }
    
    // Function to translate UI elements
    function translateUI() {
        console.log('Translating UI elements...');
        
        // Expand/Collapse All button
        $('.ld-expand-button .ld-text').each(function() {
            const $button = $(this);
            const currentText = $button.text().trim();
            
            if (currentText === 'Expand All') {
                console.log('Found Expand All button, updating text');
                $button.text('הרחב הכל');
                // Also update the data attribute for the toggle state
                $button.closest('.ld-expand-button')
                    .attr('data-ld-expand-text', 'הרחב הכל')
                    .attr('data-ld-collapse-text', 'סגור הכל');
            } else if (currentText === 'Collapse All') {
                console.log('Found Collapse All button, updating text');
                $button.text('סגור הכל');
            }
        });
        
        // Update the Lessons heading if it exists
        $('.ld-section-heading h2:contains("Lessons")').text('שיעורים');
    }
    
    // Function to fix navigation button colors
    function fixNavigationColors() {
        console.log('Fixing navigation button colors...');
        
        // Fix navigation buttons with blue-on-blue issue
        const navigationSelectors = [
            '.learndash-wrapper .ld-content-actions .ld-button',
            '.learndash-wrapper .ld-content-action .ld-button',
            '.learndash-wrapper .ld-primary-color',
            '.learndash-wrapper .ld-button.ld-button-transparent',
            '.learndash-wrapper .ld-button.ld-button-reverse'
        ];
        
        navigationSelectors.forEach(selector => {
            $(selector).each(function() {
                const $button = $(this);
                
                // Skip if already processed
                if ($button.hasClass('ld-color-fixed')) {
                    return;
                }
                
                // Apply white text on blue background
                $button.css({
                    'color': '#ffffff !important',
                    'background-color': '#2c3391 !important',
                    'padding': '8px 16px',
                    'border-radius': '4px',
                    'text-decoration': 'none',
                    'display': 'inline-block',
                    'transition': 'all 0.3s ease'
                });
                
                // Add hover effect
                $button.on('mouseenter', function() {
                    $(this).css({
                        'background-color': '#1e2570 !important',
                        'transform': 'translateY(-1px)'
                    });
                }).on('mouseleave', function() {
                    $(this).css({
                        'background-color': '#2c3391 !important',
                        'transform': 'translateY(0)'
                    });
                });
                
                $button.addClass('ld-color-fixed');
                console.log('Fixed navigation button:', $button);
            });
        });
        
        // Fix breadcrumb links
        $('.learndash-wrapper .ld-breadcrumbs a').each(function() {
            const $link = $(this);
            
            if ($link.hasClass('ld-breadcrumb-fixed')) {
                return;
            }
            
            $link.css({
                'color': '#2c3391 !important',
                'background-color': 'transparent',
                'padding': '4px 8px',
                'border-radius': '3px',
                'text-decoration': 'none'
            });
            
            $link.on('mouseenter', function() {
                $(this).css({
                    'background-color': '#2c3391 !important',
                    'color': '#ffffff !important'
                });
            }).on('mouseleave', function() {
                $(this).css({
                    'background-color': 'transparent',
                    'color': '#2c3391 !important'
                });
            });
            
            $link.addClass('ld-breadcrumb-fixed');
            console.log('Fixed breadcrumb link:', $link);
        });
    }

    // Function to add Hebrew text prefixes to lessons and tests
    function addHebrewPrefixes() {
        console.log('Adding Hebrew text prefixes...');
        
        // Add "שיעור בנושא:" prefix to lesson items
        $('.ld-table-list-item:not(.ld-table-list-item-quiz)').each(function() {
            const $item = $(this);
            
            // Skip if already processed
            if ($item.hasClass('ld-prefix-added')) {
                return;
            }
            
            // Find the title element
            const $title = $item.find('.ld-topic-title, .ld-lesson-title, .ld-item-title');
            if ($title.length > 0) {
                const originalText = $title.text().trim();
                if (!originalText.startsWith('שיעור בנושא:')) {
                    $title.text('שיעור בנושא: ' + originalText);
                    console.log('Added lesson prefix to:', originalText);
                }
            }
            
            $item.addClass('ld-prefix-added');
        });
        
        // Add "שאלות בנושא:" prefix to quiz/test items
        $('.ld-table-list-item-quiz').each(function() {
            const $item = $(this);
            
            // Skip if already processed
            if ($item.hasClass('ld-prefix-added')) {
                return;
            }
            
            // Find the title element
            const $title = $item.find('.ld-item-title, .ld-quiz-title');
            if ($title.length > 0) {
                const originalText = $title.text().trim();
                if (!originalText.startsWith('שאלות בנושא:')) {
                    $title.text('שאלות בנושא: ' + originalText);
                    console.log('Added quiz prefix to:', originalText);
                }
            }
            
            $item.addClass('ld-prefix-added');
        });
    }
    
    // Main function to run all enhancements
    function runEnhancements() {
        adjustVideoHeight();
        translateUI();
        fixNavigationColors();
        addHebrewPrefixes();
    }
    
    // Throttled version for frequent calls
    const throttledEnhancements = throttle(runEnhancements, 500);
    
    // Run on page load
    runEnhancements();
    
    // Also run after AJAX loads (for dynamic content) - throttled
    $(document).ajaxComplete(throttledEnhancements);
    
    // Run after a short delay to catch any late-loading content
    setTimeout(runEnhancements, 1000);
    
    // Optimized MutationObserver with throttling and specific targeting
    if (typeof MutationObserver !== 'undefined') {
        const observer = new MutationObserver(throttle(function(mutations) {
            let shouldRun = false;
            
            mutations.forEach(function(mutation) {
                // Only run if relevant nodes were added
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    for (let node of mutation.addedNodes) {
                        if (node.nodeType === 1) { // Element node
                            // Check if it's relevant content
                            if (node.classList && (
                                node.classList.contains('ld-table-list-item') ||
                                node.classList.contains('ld-content-action') ||
                                node.classList.contains('ld-breadcrumbs') ||
                                node.querySelector && (
                                    node.querySelector('.ld-table-list-item') ||
                                    node.querySelector('.ld-content-action') ||
                                    node.querySelector('.ld-breadcrumbs')
                                )
                            )) {
                                shouldRun = true;
                                break;
                            }
                        }
                    }
                }
            });
            
            if (shouldRun) {
                console.log('Relevant DOM mutation detected, running enhancements...');
                runEnhancements();
            }
        }, 1000));
        
        // Start observing with more specific configuration
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: false,
            characterData: false
        });
    }
});
