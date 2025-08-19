<?php

namespace Lilac\CheckoutCustomizer;

class CheckoutCustomizer {
    public function __construct() {
        // Remove coupon code section
        add_action('wp_loaded', [$this, 'remove_coupon_section']);
        
        // Customize checkout fields
        add_filter('woocommerce_checkout_fields', [$this, 'customize_checkout_fields'], 9999);
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        
        // Disable coupons
        add_filter('woocommerce_coupons_enabled', '__return_false');
    }
    
    public function remove_coupon_section() {
        remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10);
    }
    
    public function customize_checkout_fields($fields) {
        // Remove order comments
        unset($fields['order']['order_comments']);
        
        // Remove shipping fields
        unset($fields['shipping']);
        
        // Billing fields configuration
        $fields['billing'] = [
            'billing_first_name' => [
                'label'       => '',
                'placeholder' => 'שם פרטי',
                'required'    => true,
                'class'       => ['form-row-first'],
                'priority'    => 10
            ],
            'billing_last_name' => [
                'label'       => '',
                'placeholder' => 'שם משפחה',
                'required'    => true,
                'class'       => ['form-row-last'],
                'priority'    => 20
            ],
            'billing_phone' => [
                'label'       => '',
                'placeholder' => 'טלפון נייד (זיהוי משתמש)',
                'required'    => true,
                'class'       => ['form-row-first'],
                'priority'    => 30,
                'clear'       => true
            ],
            'phone_confirm' => [
                'type'        => 'text',
                'label'       => '',
                'placeholder' => 'וידוא טלפון נייד',
                'required'    => true,
                'class'       => ['form-row-last'],
                'priority'    => 40
            ],
            'id_number' => [
                'type'        => 'text',
                'label'       => '',
                'placeholder' => 'תעודת זהות (סיסמה)',
                'required'    => true,
                'class'       => ['form-row-first'],
                'priority'    => 50
            ],
            'id_confirm' => [
                'type'        => 'text',
                'label'       => '',
                'placeholder' => 'וידוא תעודת זהות',
                'required'    => true,
                'class'       => ['form-row-last'],
                'priority'    => 60
            ],
            'billing_email' => [
                'label'       => '',
                'placeholder' => 'אימייל לאישור',
                'required'    => true,
                'class'       => ['form-row-wide'],
                'priority'    => 70,
                'clear'       => true
            ],
            'school_code' => [
                'type'        => 'text',
                'label'       => '',
                'placeholder' => 'קוד בית ספר (אופציונלי)',
                'required'    => false,
                'class'       => ['form-row-first'],
                'priority'    => 80
            ],
            'class_number' => [
                'type'        => 'text',
                'label'       => '',
                'placeholder' => 'מספר כיתה (אופציונלי)',
                'required'    => false,
                'class'       => ['form-row-last'],
                'priority'    => 90
            ]
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
}
