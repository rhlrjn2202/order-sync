<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_Order_Sync_User_Validator {
    private $remote_site_url;
    private $api_key;
    private $api_secret;
    private $cache_expiry = 300; // 5 minutes cache

    public function __construct($remote_url, $key, $secret) {
        $this->remote_site_url = $remote_url;
        $this->api_key = $key;
        $this->api_secret = $secret;
    }

    public function validate_user_email($email) {
        // Check cache first
        $cache_key = 'wc_order_sync_email_' . md5($email);
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result === 'valid';
        }

        $endpoint = $this->remote_site_url . '/wp-json/wc/v3/customers';
        $args = array(
            'method' => 'GET',
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->api_key . ':' . $this->api_secret)
            ),
            'body' => array(
                'email' => $email,
                'role' => 'all'
            ),
            'timeout' => 15,
            'sslverify' => true
        );

        $retries = 2;
        while ($retries >= 0) {
            $response = wp_remote_get(add_query_arg(array('email' => $email), $endpoint), $args);

            if (!is_wp_error($response)) {
                $response_code = wp_remote_retrieve_response_code($response);
                
                if ($response_code === 200) {
                    $customers = json_decode(wp_remote_retrieve_body($response), true);
                    $is_valid = !empty($customers);
                    
                    // Cache the result
                    set_transient($cache_key, $is_valid ? 'valid' : 'invalid', $this->cache_expiry);
                    
                    return $is_valid;
                }
            }

            if ($retries > 0) {
                sleep(1);
            }
            $retries--;
        }

        // Log the error
        WC_Order_Sync_Logger::log_error('User validation failed after retries', array(
            'email' => $email,
            'error' => is_wp_error($response) ? $response->get_error_message() : 'Invalid response code: ' . $response_code
        ));

        // Cache the failure for a shorter time
        set_transient($cache_key, 'invalid', 60);
        return false;
    }
}