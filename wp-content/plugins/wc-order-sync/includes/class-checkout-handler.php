<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_Order_Sync_Checkout_Handler {
    private $user_validator;

    public function __construct($user_validator) {
        $this->user_validator = $user_validator;
        
        // Add checkout validation
        add_action('woocommerce_checkout_process', array($this, 'validate_checkout'));
        
        // Modify checkout fields
        add_filter('woocommerce_checkout_fields', array($this, 'customize_checkout_fields'));
        
        // Add custom validation message
        add_action('woocommerce_before_checkout_form', array($this, 'add_checkout_notice'));
    }

    public function customize_checkout_fields($fields) {
        // Remove unnecessary fields for guest checkout
        unset($fields['billing']['billing_company']);
        unset($fields['billing']['billing_address_1']);
        unset($fields['billing']['billing_address_2']);
        unset($fields['billing']['billing_city']);
        unset($fields['billing']['billing_postcode']);
        unset($fields['billing']['billing_country']);
        unset($fields['billing']['billing_state']);
        
        // Make email and phone required
        $fields['billing']['billing_email']['required'] = true;
        $fields['billing']['billing_phone']['required'] = true;
        
        return $fields;
    }

    public function validate_checkout() {
        $email = $_POST['billing_email'];
        
        if (!$this->user_validator->validate_user_email($email)) {
            wc_add_notice(
                'Please use the email address registered on the OTT platform. If you haven\'t registered yet, please register first.',
                'error'
            );
        }
    }

    public function add_checkout_notice() {
        wc_print_notice(
            'Please use your registered email address from the OTT platform to complete the purchase.',
            'notice'
        );
    }
}