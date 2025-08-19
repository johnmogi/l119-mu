<?php

namespace Lilac\CheckoutCustomizer;

class CheckoutCustomizer {
    public function __construct() {
        // Remove coupon code section
        add_action('wp_loaded', [$this, 'remove_coupon_section']);
        
        // Customize checkout fields
        add_filter('woocommerce_checkout_fields', [$this, 'customize_checkout_fields'], 999);
        
        // Add ID validation
        add_action('woocommerce_after_checkout_validation', [$this, 'validate_id_fields']);
        
        // Set default checkout fields
        add_filter('default_checkout_billing_id_number', '__return_empty_string');
        add_filter('default_checkout_billing_id_confirm', '__return_empty_string');
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        
        // Disable coupons
        add_filter('woocommerce_coupons_enabled', '__return_false');
    }
    
    public function remove_coupon_section() {
        remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10);
    }
    
    public function customize_checkout_fields($fields) {
        // Remove shipping fields
        unset($fields['shipping']);
        unset($fields['order']['order_comments']);

        // Customize billing fields
        $fields['billing']['billing_first_name'] = [
            'label'       => 'שם פרטי',
            'placeholder' => 'הזן את שמך הפרטי',
            'priority'    => 10,
            'required'    => true,
            'class'       => ['form-row-first'],
        ];

        $fields['billing']['billing_last_name'] = [
            'label'       => 'שם משפחה',
            'placeholder' => 'הזן את שם משפחתך',
            'priority'    => 20,
            'required'    => true,
            'class'       => ['form-row-last'],
        ];

        $fields['billing']['billing_company'] = [
            'label'       => 'שם החברה (אופציונלי)',
            'placeholder' => 'הזן את שם החברה',
            'priority'    => 30,
            'required'    => false,
            'class'       => ['form-row-wide'],
        ];

        $fields['billing']['billing_country'] = [
            'type'        => 'country',
            'label'       => 'מדינה',
            'priority'    => 40,
            'required'    => true,
            'class'       => ['form-row-first', 'address-field', 'update_totals_on_change'],
            'clear'       => true
        ];

        $fields['billing']['billing_address_1'] = [
            'label'       => 'כתובת',
            'placeholder' => 'הזן את כתובתך',
            'priority'    => 50,
            'required'    => true,
            'class'       => ['form-row-wide', 'address-field'],
        ];

        $fields['billing']['billing_address_2'] = [
            'label'       => 'דירה, דירה, יחידה וכו׳ (אופציונלי)',
            'placeholder' => 'דירה, דירה, יחידה וכו׳ (אופציונלי)',
            'priority'    => 60,
            'required'    => false,
            'class'       => ['form-row-wide', 'address-field'],
        ];

        $fields['billing']['billing_city'] = [
            'label'       => 'עיר',
            'placeholder' => 'הזן את העיר שלך',
            'priority'    => 70,
            'required'    => true,
            'class'       => ['form-row-first', 'address-field'],
        ];

        $fields['billing']['billing_state'] = [
            'type'        => 'state',
            'label'       => 'מדינה / מחוז',
            'priority'    => 80,
            'required'    => true,
            'class'       => ['form-row-last', 'address-field'],
            'clear'       => true,
            'validate'    => ['state']
        ];

        $fields['billing']['billing_postcode'] = [
            'label'       => 'מיקוד',
            'placeholder' => 'הזן את המיקוד שלך',
            'priority'    => 90,
            'required'    => true,
            'class'       => ['form-row-first', 'address-field'],
            'clear'       => true,
            'validate'    => ['postcode']
        ];

        // Phone field with no validation
        $fields['billing']['billing_phone'] = [
            'label'       => 'טלפון',
            'placeholder' => 'הזן את מספר הטלפון שלך',
            'priority'    => 100,
            'required'    => false, // Made not required
            'class'       => ['form-row-last'],
            'clear'       => true,
            'type'        => 'text', // Changed from tel to text
            'validate'    => [], // No validation
        ];

        $fields['billing']['billing_email'] = [
            'label'       => 'כתובת אימייל',
            'placeholder' => 'הזן את כתובת האימייל שלך',
            'priority'    => 110,
            'required'    => true,
            'class'       => ['form-row-first'],
            'validate'    => ['email']
        ];

        // Add ID number field
        $fields['billing']['billing_id_number'] = [
            'label'       => 'תעודת זהות',
            'placeholder' => 'הזן את מספר תעודת הזהות שלך',
            'priority'    => 120,
            'required'    => true,
            'class'       => ['form-row-first'],
            'clear'       => true,
            'type'        => 'text',
            'validate'    => ['id_number']
        ];

        // Add ID confirmation field
        $fields['billing']['billing_id_confirm'] = [
            'label'       => 'אשר תעודת זהות',
            'placeholder' => 'הזן שוב את מספר תעודת הזהות שלך',
            'priority'    => 130,
            'required'    => true,
            'class'       => ['form-row-last'],
            'clear'       => true,
            'type'        => 'text',
            'validate'    => ['id_confirm']
        ];

        return $fields;
    }

    
    public function enqueue_assets() {
        if (!is_checkout()) {
            return;
        }
        
        // Enqueue the JavaScript file
        wp_enqueue_script(
            'checkout-customizer-js',
            plugin_dir_url(__FILE__) . '../assets/js/checkout-customizer.js',
            ['jquery'],
            '1.0.0',
            true
        );
        
        // Add inline styles
        wp_add_inline_style('woocommerce-layout', '
            .woocommerce-form-coupon-toggle,
            .woocommerce-form-coupon,
            #ship-to-different-address,
            .woocommerce-shipping-fields,
            .shipping_address,
            .woocommerce-additional-fields,
            .woocommerce-billing-fields h3,
            .woocommerce-shipping-fields h3,
            .woocommerce-additional-fields h3,
            .woocommerce-billing-fields__field-wrapper label,
            .woocommerce-shipping-fields__field-wrapper label,
            .woocommerce-additional-fields__field-wrapper label {
                display: none !important;
            }
            
            .woocommerce-billing-fields .form-row label:not(.woocommerce-form__label-for-checkbox) {
                display: none !important;
            }
            
            .woocommerce-billing-fields input::placeholder,
            .woocommerce-billing-fields textarea::placeholder {
                opacity: 1 !important;
                color: #777 !important;
            }
            
            .form-row.promo_code_field {
                display: none !important;
            }
        ');
    }
    
    
    /**
     * Validate custom ID fields during checkout submission
     */
    public function validate_id_fields() {
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $id_number     = isset($_POST['billing_id_number']) ? trim(wp_unslash($_POST['billing_id_number'])) : '';
        $id_confirm    = isset($_POST['billing_id_confirm']) ? trim(wp_unslash($_POST['billing_id_confirm'])) : '';
        // phpcs:enable

        if ($id_number === '') {
            wc_add_notice('חובה להזין תעודת זהות', 'error');
        }

        if ($id_confirm === '') {
            wc_add_notice('חובה לאשר תעודת זהות', 'error');
        }

        if ($id_number !== '' && $id_confirm !== '' && $id_number !== $id_confirm) {
            wc_add_notice('תעודת זהות ואימות אינם תואמים', 'error');
        }
    }
}
