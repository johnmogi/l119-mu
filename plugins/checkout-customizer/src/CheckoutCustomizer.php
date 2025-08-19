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
        add_filter('default_checkout_billing_country', [$this, 'set_default_country']);
        
        // Force Israel as country during checkout
        add_action('woocommerce_checkout_process', [$this, 'force_israel_country']);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'save_custom_fields']);
        
        // Remove country/state validation
        add_filter('woocommerce_checkout_fields', [$this, 'remove_country_state_validation'], 9999);
        add_filter('woocommerce_default_address_fields', [$this, 'remove_default_address_fields'], 9999);
        
        // Auto-login after purchase
        add_action('woocommerce_thankyou', [$this, 'auto_login_after_purchase'], 10, 1);
        
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

        // Remove country field entirely and set Israel as default
        unset($fields['billing']['billing_country']);

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

        // Remove state field entirely
        unset($fields['billing']['billing_state']);


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
            
            .form-row.hidden {
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
    
    /**
     * Set default country to Israel
     */
    public function set_default_country() {
        return 'IL';
    }
    
    /**
     * Force Israel as country during checkout
     */
    public function force_israel_country() {
        $_POST['billing_country'] = 'IL';
        $_POST['billing_state'] = '';
    }
    
    /**
     * Save custom fields to order meta
     */
    public function save_custom_fields($order_id) {
        if (!empty($_POST['billing_id_number'])) {
            update_post_meta($order_id, '_billing_id_number', sanitize_text_field($_POST['billing_id_number']));
        }
        if (!empty($_POST['billing_id_confirm'])) {
            update_post_meta($order_id, '_billing_id_confirm', sanitize_text_field($_POST['billing_id_confirm']));
        }
    }
    
    /**
     * Remove country/state validation completely
     */
    public function remove_country_state_validation($fields) {
        // Completely remove country and state fields
        unset($fields['billing']['billing_country']);
        unset($fields['billing']['billing_state']);
        unset($fields['shipping']['shipping_country']);
        unset($fields['shipping']['shipping_state']);
        
        return $fields;
    }
    
    /**
     * Remove default address fields that cause validation errors
     */
    public function remove_default_address_fields($fields) {
        unset($fields['country']);
        unset($fields['state']);
        return $fields;
    }
    
    /**
     * Auto-login user after successful purchase
     */
    public function auto_login_after_purchase($order_id) {
        if (!$order_id) {
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Get billing data
        $phone = $order->get_billing_phone();
        $id_number = $order->get_meta('_billing_id_number');
        $email = $order->get_billing_email();
        $first_name = $order->get_billing_first_name();
        $last_name = $order->get_billing_last_name();
        
        if (empty($phone) || empty($id_number)) {
            return;
        }
        
        // Check if user already exists
        $username = sanitize_user($phone);
        $user = get_user_by('login', $username);
        
        if (!$user) {
            // Create new user
            $user_id = wp_create_user($username, $id_number, $email);
            
            if (is_wp_error($user_id)) {
                return;
            }
            
            // Update user meta
            wp_update_user([
                'ID' => $user_id,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'display_name' => $first_name . ' ' . $last_name
            ]);
            
            // Add custom meta
            update_user_meta($user_id, 'billing_phone', $phone);
            update_user_meta($user_id, 'billing_id_number', $id_number);
            
            $user = get_user_by('ID', $user_id);
        }
        
        // Auto-login the user
        if ($user && !is_user_logged_in()) {
            wp_clear_auth_cookie();
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID, true);
            
            // Redirect to account page or course access
            wp_safe_redirect(wc_get_account_endpoint_url('orders'));
            exit;
        }
    }
}
