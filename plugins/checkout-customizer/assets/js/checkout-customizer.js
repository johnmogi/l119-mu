/**
 * Checkout Customizer - Client-side functionality
 */
(function($) {
    'use strict';

    // Document ready
    $(document).ready(function() {
        // Debug log
        console.log('Checkout Customizer JS loaded');

        // Remove any coupon code links/buttons
        $('.woocommerce-form-coupon-toggle, .showcoupon').remove();
        
        // Remove shipping fields if they somehow appear
        $('#ship-to-different-address, .woocommerce-shipping-fields, .shipping_address').remove();
        
        // Remove any remaining labels
        $('.woocommerce-billing-fields label:not(.woocommerce-form__label-for-checkbox)').text('');
        
        // Ensure placeholders are visible
        $('.woocommerce-billing-fields input, .woocommerce-billing-fields textarea')
            .attr('placeholder', function() {
                return $(this).attr('placeholder') || $(this).parent().find('label').text().trim();
            });
            
        // Handle school code and class number fields
        const $schoolCodeField = $('input[name="school_code"]');
        const $classNumberField = $('input[name="class_number"]');
        
        // Add input masks if needed
        if ($schoolCodeField.length) {
            $schoolCodeField.attr('maxlength', 10);
        }
        
        if ($classNumberField.length) {
            $classNumberField.attr('maxlength', 3);
        }
        
        // Log field changes for debugging
        $('.woocommerce-billing-fields input, .woocommerce-billing-fields select, .woocommerce-billing-fields textarea')
            .on('change input', function() {
                console.log('Field changed:', $(this).attr('name'), 'Value:', $(this).val());
            });
    });
    
    // Run again after AJAX updates
    $(document).ajaxComplete(function() {
        $('.woocommerce-billing-fields label:not(.woocommerce-form__label-for-checkbox)').text('');
    });
    
})(jQuery);
