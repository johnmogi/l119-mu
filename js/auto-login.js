jQuery(document).ready(function($) {
    'use strict';
    
    console.log('Auto-login script loaded');
    
    // Function to log detailed cookie information
    function logCookies() {
        console.log('Current cookies:');
        document.cookie.split(';').forEach(cookie => {
            console.log('- ' + cookie.trim());
        });
    }
    
    // Check if we're on the order received page
    if ($('body.woocommerce-order-received').length) {
        console.log('On order received page');
        logCookies();
        
        // Get the order ID from the URL
        const urlParams = new URLSearchParams(window.location.search);
        const orderId = urlParams.get('order-received') || urlParams.get('order_id');
        
        console.log('Order ID from URL:', orderId);
        
        if (orderId) {
            // Check if we've already tried to log in (to prevent loops)
            if (sessionStorage.getItem('autoLoginAttempted') === 'true') {
                console.log('Auto-login already attempted, skipping');
                return;
            }
            
            console.log('Attempting auto-login...');
            
            // Mark that we've attempted to log in
            sessionStorage.setItem('autoLoginAttempted', 'true');
            
            // Log current user status
            console.log('Current user status before AJAX:', wpApiSettings ? 'WP API available' : 'WP API not available');
            
            // Try to log the user in
            $.ajax({
                url: autoLoginVars.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'auto_login_after_checkout',
                    order_id: orderId,
                    nonce: autoLoginVars.nonce,
                    _ajax_nonce: autoLoginVars.nonce
                },
                beforeSend: function(xhr) {
                    console.log('Sending AJAX request...');
                    console.log('URL:', this.url);
                    console.log('Data:', this.data);
                },
                success: function(response, status, xhr) {
                    console.log('=== AJAX Success ===');
                    console.log('Status:', status);
                    console.log('Response:', response);
                    console.log('Response Headers:', xhr.getAllResponseHeaders());
                    
                    if (response.success) {
                        console.log('Auto-login successful!');
                        console.log('User ID:', response.data.user_id);
                        console.log('Is logged in:', response.data.is_logged_in);
                        
                        // Log cookies after successful login
                        console.log('Cookies after login:');
                        logCookies();
                        
                        // Add a small delay to ensure the session is properly set
                        console.log('Redirecting to:', response.data.redirect);
                        setTimeout(function() {
                            window.location.href = response.data.redirect || window.location.href;
                        }, 1000);
                    } else {
                        console.error('Auto-login failed:', response.data);
                        // If login failed, clean up the session flag
                        sessionStorage.removeItem('autoLoginAttempted');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('=== AJAX Error ===');
                    console.error('Status:', status);
                    console.error('Error:', error);
                    console.error('Response Text:', xhr.responseText);
                    
                    try {
                        const jsonResponse = JSON.parse(xhr.responseText);
                        console.error('Parsed Error Response:', jsonResponse);
                    } catch (e) {
                        console.error('Could not parse error response as JSON');
                    }
                    
                    // Clean up the session flag on error
                    sessionStorage.removeItem('autoLoginAttempted');
                },
                complete: function(xhr, status) {
                    console.log('AJAX request completed with status:', status);
                }
            });
        } else {
            console.log('No order ID found in URL');
        }
    }
});
