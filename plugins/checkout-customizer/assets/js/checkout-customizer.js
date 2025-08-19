/**
 * Checkout Customizer - Client-side functionality
 */
(function($) {
    'use strict';

    // Document ready
    $(document).ready(function() {
        const relaxPhoneUI = function() {
            const $phone = $('input[name="billing_phone"]');
            if ($phone.length) {
                // Force text input and remove any validation artifacts
                $phone.attr('type', 'text')
                      .removeClass('validate-phone')
                      .removeAttr('pattern')
                      .removeAttr('inputmode')
                      .removeAttr('aria-invalid')
                      .removeAttr('required')
                      .removeAttr('aria-required')
                      .removeAttr('data-parsley-type')
                      .removeAttr('data-validate')
                      .removeClass('woocommerce-invalid')
                      .removeClass('woocommerce-invalid-required-field');
            }
        };

        // Debug log
        console.log('Checkout Customizer JS loaded');

        // Run initial cleanup
        const cleanupPhoneField = function() {
            const $phone = $('input[name="billing_phone"]');
            if ($phone.length) {
                // Force text input and remove any validation artifacts
                $phone.attr('type', 'text')
                      .removeClass('validate-phone')
                      .removeAttr('pattern')
                      .removeAttr('inputmode')
                      .removeAttr('aria-invalid')
                      .removeAttr('required')
                      .removeAttr('aria-required')
                      .removeAttr('data-parsley-type')
                      .removeAttr('data-validate')
                      .removeClass('woocommerce-invalid')
                      .removeClass('woocommerce-invalid-required-field')
                      .removeClass('validate-required')
                      .closest('.form-row').removeClass('woocommerce-invalid');
                
                // Remove any validation messages
                $phone.siblings('.woocommerce-error').remove();
            }
        };

        // Run on page load
        cleanupPhoneField();

        // Run on AJAX updates
        $(document.body).on('updated_checkout', function() {
            cleanupPhoneField();
            
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
        });

        // Run on form submission
        $('form.checkout').on('checkout_place_order', function() {
            cleanupPhoneField();
            return true;
        });

        // Initial cleanup of UI elements
        $('.woocommerce-form-coupon-toggle, .showcoupon').remove();
        $('#ship-to-different-address, .woocommerce-shipping-fields, .shipping_address').remove();
        $('.woocommerce-billing-fields label:not(.woocommerce-form__label-for-checkbox)').text('');
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

        // Ensure phone is always passable
        relaxPhoneUI();
    });
    
    // Run again after AJAX updates and on checkout update
    $(document).ajaxComplete(function() {
        $('.woocommerce-billing-fields label:not(.woocommerce-form__label-for-checkbox)').text('');
        relaxPhoneUI();
    });
    
    // Handle WooCommerce checkout updates
    $(document.body).on('updated_checkout', function() {
        relaxPhoneUI();
    });
    
})(jQuery);
